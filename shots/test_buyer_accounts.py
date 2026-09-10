"""
End-to-end test of the buyer account feature.

Run the site locally first:
    php -S 127.0.0.1:8417 -t public tools/router_dev.php
    python3 shots/test_buyer_accounts.py

Every assertion that matters has a CONTROL beside it - a case that must FAIL.
A test that only ever checks the happy path passes just as well when the thing
it is guarding has been switched off, which is the failure mode this file is
written to avoid. The price-gate section in particular checks both that an
approved buyer SEES the figure and that a logged-out visitor does NOT, plus
that supplier and internal notes never reach the page for anybody.
"""
import re
import subprocess
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8417"
ADMIN_USER, ADMIN_PASS = "admin", "CatalogueDemo2026!"
APPLICANT_EMAIL = "buyer.test@example.com"

checks = []


def check(name, ok, detail=""):
    checks.append((name, bool(ok), detail))
    print(("  PASS  " if ok else "  FAIL  ") + name + ((" | " + str(detail)) if detail else ""))


def php(code):
    """Run a snippet against the same database the site is using."""
    out = subprocess.run(
        ["php", "-r", 'require "app/bootstrap.php";' + code],
        capture_output=True, text=True, cwd=".")
    if out.returncode != 0:
        print("PHP ERROR:", out.stderr[:400])
    return out.stdout.strip()


def setting(key, value):
    php(f'Database::run("UPDATE settings SET setting_value=? WHERE setting_key=?", '
        f'["{value}", "{key}"]);')


def rendered(page, selector="body"):
    """Text as the browser paints it, lowercased.

    inner_text() applies CSS text-transform, and this site uppercases every
    h1 and every definition-list term. Comparing against the source wording
    therefore fails on a page that is rendering perfectly - so every text
    assertion here is case-insensitive on purpose.
    """
    return page.inner_text(selector).lower()


def go(page, path):
    page.goto(BASE + path, wait_until="load", timeout=30000)
    page.wait_for_load_state("load")


def admin_login(page):
    go(page, "/admin/login")
    page.fill("#username", ADMIN_USER)
    page.fill("#password", ADMIN_PASS)
    # Scoped to the login form: a bare button[type=submit] would match the
    # layout's Sign out button on any other admin page.
    page.click("form button[type=submit]")
    page.wait_for_load_state("load")


def main():
    # Clean slate for this applicant, and a known starting configuration.
    php(f'Database::run("DELETE FROM buyer_accounts WHERE email LIKE \'%example.com\'");')
    # Failed attempts are counted per IP across BOTH login forms and survive
    # between runs, so without this the fourth or fifth run of this file starts
    # throttled and every sign-in below fails for a reason that has nothing to
    # do with the code under test. Section 9 proves the throttle still works.
    php('Database::run("DELETE FROM login_attempts");')
    setting("buyer_accounts_enabled", "1")
    setting("buyer_gate", "none")

    with sync_playwright() as p:
        b = p.chromium.launch()
        ctx = b.new_context(viewport={"width": 1280, "height": 900})
        page = ctx.new_page()
        errors = []
        page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)

        # ------------------------------------------------------------------
        print("\n1. The public request form")
        # ------------------------------------------------------------------
        go(page, "/request-access")
        check("request form loads", "request trade access" in rendered(page, "h1"))

        # CONTROL: the honeypot must swallow a bot submission silently.
        page.fill("#contact_name", "Bot")
        page.fill("#company", "Bot Co")
        page.fill("#email", "bot@example.com")
        page.fill("#website", "http://spam.example")
        page.click("form.panel-form button[type=submit]")
        page.wait_for_load_state("load")
        n = php('echo Database::scalar("SELECT COUNT(*) FROM buyer_accounts WHERE email=\'bot@example.com\'");')
        check("CONTROL honeypot submission writes nothing", n == "0", f"rows={n}")
        check("honeypot still shows the confirmation page", "/access-requested" in page.url)

        # CONTROL: a missing company must be refused rather than saved.
        go(page, "/request-access")
        page.fill("#contact_name", "No Company")
        page.fill("#email", "nocompany@example.com")
        page.evaluate("() => document.querySelector('#company').removeAttribute('required')")
        page.click("form.panel-form button[type=submit]")
        page.wait_for_load_state("load")
        n = php('echo Database::scalar("SELECT COUNT(*) FROM buyer_accounts WHERE email=\'nocompany@example.com\'");')
        check("CONTROL request with no company is rejected", n == "0", f"rows={n}")

        # The real application.
        go(page, "/request-access")
        page.fill("#contact_name", "Priya Raman")
        page.fill("#company", "Northwind Produce")
        page.fill("#email", APPLICANT_EMAIL)
        page.fill("#phone", "+1 604 555 0134")
        page.fill("#country", "Canada")
        page.fill("#interest", "Fresh fruit, one container a month, into Vancouver.")
        page.click("form.panel-form button[type=submit]")
        page.wait_for_load_state("load")
        check("application confirmed", "/access-requested" in page.url)

        row = php(f'$r=BuyerRepository::findByEmail("{APPLICANT_EMAIL}");'
                  'echo $r["status"]."|".($r["username"]===null?"NULLuser":"HASuser")'
                  '."|".($r["password_hash"]===null?"NULLpass":"HASpass");')
        check("saved as pending with no login", row == "pending|NULLuser|NULLpass", row)

        # CONTROL: applying twice must not create a second row.
        go(page, "/request-access")
        page.fill("#contact_name", "Priya Raman")
        page.fill("#company", "Northwind Produce")
        page.fill("#email", APPLICANT_EMAIL)
        page.click("form.panel-form button[type=submit]")
        page.wait_for_load_state("load")
        n = php(f'echo Database::scalar("SELECT COUNT(*) FROM buyer_accounts WHERE email=?", ["{APPLICANT_EMAIL}"]);')
        check("CONTROL duplicate application makes no second row", n == "1", f"rows={n}")
        check("duplicate is not told the address is known",
              "/access-requested" in page.url and "already" not in rendered(page))

        # ------------------------------------------------------------------
        print("\n2. A pending account cannot sign in")
        # ------------------------------------------------------------------
        go(page, "/account/login")
        page.fill("#login", APPLICANT_EMAIL)
        page.fill("#password", "anything-at-all")
        page.click("form.panel-form button[type=submit]")
        page.wait_for_load_state("load")
        body = rendered(page)
        check("CONTROL pending account is refused", "/account" != page.url.replace(BASE, ""))
        check("pending gets an explanation, not 'wrong password'",
              "being reviewed" in body)

        # ------------------------------------------------------------------
        print("\n3. Vetting in the admin panel")
        # ------------------------------------------------------------------
        admin_login(page)
        check("admin signed in", "dashboard" in page.title().lower())

        go(page, "/admin/buyers")
        check("application listed", "northwind produce" in rendered(page))
        check("badge counts the pending one",
              page.query_selector(".adm-side .adm-badge") is not None)

        link = page.query_selector("a[href*='/admin/buyers/']")
        go(page, link.get_attribute("href").replace(BASE, ""))
        check("detail page opens", "northwind produce" in rendered(page, "h1"))
        check("their message is shown", "one container a month" in rendered(page))

        page.click("form[action$='buyers/approve'] button[type=submit]")
        page.wait_for_load_state("load")
        # Read the two values from their own elements rather than scraping the
        # page text - the labels beside them are uppercased by CSS.
        creds = page.eval_on_selector_all(
            ".cred-value", "els => els.map(e => e.textContent.trim())")
        check("credentials shown once after approval", len(creds) == 2, creds)
        if len(creds) != 2:
            print("BODY:", page.inner_text("body")[:400].replace("\n", " | "))
            b.close()
            return
        username, password = creds[0], creds[1]

        # A relative "/account/login" is a fine link inside a page and useless
        # pasted into an email, which is exactly what this value is for.
        signin = page.eval_on_selector(
            ".panel-credentials .kv dd", "el => el.textContent.trim()")
        check("sign-in address is a full URL an admin can paste into an email",
              signin.startswith("http://") or signin.startswith("https://"), signin)

        print(f"        issued: {username} / {password}")

        detail_url = page.url.replace(BASE, "")
        go(page, detail_url)
        check("CONTROL password is NOT shown again on reload",
              password not in page.evaluate("document.body.textContent"))

        stored = php(f'$r=BuyerRepository::findByEmail("{APPLICANT_EMAIL}");'
                     f'echo password_verify("{password}", $r["password_hash"]) ? "hashed-ok" : "MISMATCH";')
        check("password stored only as a hash that verifies", stored == "hashed-ok", stored)
        plain = php(f'echo Database::scalar("SELECT COUNT(*) FROM buyer_accounts WHERE password_hash = ?", ["{password}"]);')
        check("CONTROL the plain password is nowhere in the table", plain == "0", f"rows={plain}")

        # ------------------------------------------------------------------
        print("\n4. The buyer signs in")
        # ------------------------------------------------------------------
        # A context of its own. Reusing the admin's context would leave the
        # admin cookie in the jar, and the "a buyer cannot reach /admin" check
        # below would then pass for the wrong reason - it would be testing the
        # administrator, not the buyer. A control that shares state with the
        # thing it is controlling for is not a control.
        buyer_ctx = b.new_context(viewport={"width": 1280, "height": 900})
        buyer = buyer_ctx.new_page()

        go(buyer, "/account/login")
        buyer.fill("#login", username)
        buyer.fill("#password", password + "x")
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("CONTROL wrong password refused", "incorrect" in rendered(buyer))

        go(buyer, "/account/login")
        buyer.fill("#login", username)
        buyer.fill("#password", password)
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("correct password signs in", "/account" in buyer.url)
        check("sent straight to change the issued password",
              buyer.url.endswith("/account/password"), buyer.url)

        # CONTROL: a buyer session must not open the admin panel.
        admin_probe = buyer_ctx.new_page()
        go(admin_probe, "/admin/products")
        check("CONTROL buyer session cannot reach the admin panel",
              "/admin/login" in admin_probe.url, admin_probe.url)
        admin_probe.close()

        new_password = "MyOwnPassword2026!"
        buyer.fill("#new_password", new_password)
        buyer.fill("#confirm_password", new_password)
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("password change lands on the account page",
              buyer.url.rstrip("/").endswith("/account"), buyer.url)

        buyer.click("form[action$='account/logout'] button[type=submit]")
        buyer.wait_for_load_state("load")

        go(buyer, "/account/login")
        buyer.fill("#login", username)
        buyer.fill("#password", password)
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("CONTROL the old issued password no longer works", "incorrect" in rendered(buyer))

        go(buyer, "/account/login")
        buyer.fill("#login", APPLICANT_EMAIL)
        buyer.fill("#password", new_password)
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("signing in by email address works too",
              "/account" in buyer.url and "login" not in buyer.url, buyer.url)

        # ------------------------------------------------------------------
        print("\n5. The price gate")
        # ------------------------------------------------------------------
        setting("buyer_gate", "prices")

        go(buyer, "/product/avocados")
        btext = buyer.evaluate("document.body.textContent")
        check("approved buyer sees the internal figure", "3.25" in btext)
        check("and the indicative caveat", "Indicative only" in btext)
        check("CONTROL supplier name never reaches the page",
              "Secret Supplier" not in btext)
        check("CONTROL internal note never reaches the page",
              "internal note do not show" not in btext)

        anon = b.new_context(viewport={"width": 1280, "height": 900})
        anon_page = anon.new_page()
        anon_page.goto(BASE + "/product/avocados", wait_until="load", timeout=30000)
        atext = anon_page.evaluate("document.body.textContent")
        check("CONTROL logged-out visitor sees no figure", "3.25" not in atext)
        check("logged-out visitor still sees the request label",
              "Price on request" in atext)

        setting("buyer_gate", "none")
        go(buyer, "/product/avocados")
        check("CONTROL gate off hides the figure from the buyer too",
              "3.25" not in buyer.evaluate("document.body.textContent"))

        # ------------------------------------------------------------------
        print("\n6. The whole-catalogue gate")
        # ------------------------------------------------------------------
        setting("buyer_gate", "catalogue")
        anon_page.goto(BASE + "/product/avocados", wait_until="load", timeout=30000)
        check("logged-out visitor gets the access page",
              "trade access required" in rendered(anon_page))
        anon_page.goto(BASE + "/", wait_until="load", timeout=30000)
        check("home page is gated too", "trade access required" in rendered(anon_page))

        # CONTROL: the JSON endpoint must not be a way around the gate.
        r = anon_page.request.get(BASE + "/shortlist-items?ids=1,2,3")
        check("CONTROL the shortlist JSON endpoint is gated as well",
              "Avocados" not in r.text(), r.text()[:80])

        go(buyer, "/product/avocados")
        check("signed-in buyer still sees the product", "avocados" in rendered(buyer, "h1"))

        setting("buyer_gate", "none")
        anon_page.goto(BASE + "/", wait_until="load", timeout=30000)
        check("CONTROL turning the gate off restores the public catalogue",
              "trade access required" not in rendered(anon_page))

        # ------------------------------------------------------------------
        print("\n7. Suspending access")
        # ------------------------------------------------------------------
        go(page, detail_url)
        page.click("form[action$='buyers/status'] button[type=submit]")
        page.wait_for_load_state("load")
        check("account suspended", "suspended" in rendered(page))

        go(buyer, "/account")
        check("CONTROL suspended buyer is signed out on their next click",
              "/account/login" in buyer.url, buyer.url)

        go(buyer, "/account/login")
        buyer.fill("#login", username)
        buyer.fill("#password", new_password)
        buyer.click("form.panel-form button[type=submit]")
        buyer.wait_for_load_state("load")
        check("CONTROL suspended buyer cannot sign back in",
              "/account/login" in buyer.url or "not currently active" in rendered(buyer))

        # ------------------------------------------------------------------
        print("\n8. Switching the feature off entirely")
        # ------------------------------------------------------------------
        setting("buyer_accounts_enabled", "0")
        anon_page.goto(BASE + "/request-access", wait_until="load", timeout=30000)
        check("request form is gone when the feature is off",
              "not found" in rendered(anon_page))
        anon_page.goto(BASE + "/", wait_until="load", timeout=30000)
        check("CONTROL the public catalogue is unaffected",
              "all products" in rendered(anon_page))
        check("no sign-in link left in the header", "trade sign in" not in rendered(anon_page))
        setting("buyer_accounts_enabled", "1")

        # ------------------------------------------------------------------
        print("\n9. Guessing is throttled")
        # ------------------------------------------------------------------
        # Proved rather than assumed: the earlier sections deliberately clear
        # this counter, so without this section nothing here would show that
        # the limit exists at all.
        php('Database::run("DELETE FROM login_attempts");')
        setting("buyer_accounts_enabled", "1")
        throttled_at = None
        for attempt in range(1, 13):
            go(anon_page, "/account/login")
            anon_page.fill("#login", username)
            anon_page.fill("#password", f"guess-{attempt}")
            anon_page.click("form.panel-form button[type=submit]")
            anon_page.wait_for_load_state("load")
            if "too many failed attempts" in rendered(anon_page):
                throttled_at = attempt
                break
        check("repeated wrong passwords get throttled", throttled_at is not None,
              f"after {throttled_at} attempts")
        php('Database::run("DELETE FROM login_attempts");')

        check("no JavaScript console errors anywhere", not errors, errors[:3])
        b.close()

    passed = sum(1 for _, ok, _ in checks if ok)
    print(f"\n{passed}/{len(checks)} checks passed")
    failed = [n for n, ok, _ in checks if not ok]
    if failed:
        print("FAILED: " + "; ".join(failed))
    sys.exit(0 if not failed else 1)


if __name__ == "__main__":
    main()
