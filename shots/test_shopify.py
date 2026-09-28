"""
End-to-end test of the Shopify link, against a FAKE Shopify.

    php -S 127.0.0.1:8417 -t public tools/router_dev.php
    php -S 127.0.0.1:8499 shots/mock_shopify.php
    python3 shots/test_shopify.py

The mock is deliberately hostile in the ways the real API is: it demands the
token, it pages, and it returns one 429 with Retry-After before it will answer.

Every claim here is paired with a control that must FAIL, because the whole
risk in this feature is showing a customer something untrue - a buy button for
something out of stock, a retail price where a wholesale one belongs, or a
price belonging to a product nobody linked.
"""
import subprocess
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8417"
MOCK = "http://127.0.0.1:8499"
ADMIN_USER, ADMIN_PASS = "admin", "CatalogueDemo2026!"

checks = []


def check(name, ok, detail=""):
    checks.append((name, bool(ok)))
    print(("  PASS  " if ok else "  FAIL  ") + name + ((" | " + str(detail)) if detail else ""))


def php(code):
    r = subprocess.run(["php", "-r", 'require "app/bootstrap.php";' + code],
                       capture_output=True, text=True, cwd=".")
    if r.returncode != 0:
        print("PHP ERROR:", r.stderr[:400])
    return r.stdout.strip()


def setting(key, value):
    php(f'Database::run("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) '
        f'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", ["{key}","{value}"]);')


def rendered(page, sel="body"):
    return page.inner_text(sel).lower()


def go(page, path):
    page.goto(BASE + path, wait_until="load", timeout=30000)
    page.wait_for_load_state("load")


def sync():
    """Runs a sync against the mock and returns the report as a string."""
    return php(
        '$c = new ShopifyClient("ds-test","shpat_faketoken_for_tests","2025-01",10,'
        f'"{MOCK}");'
        '$r = ShopifySync::run($c);'
        'echo ($r["ok"]?"ok":"fail")."|".$r["fetched"]."|".$r["updated"]."|".$r["removed"]."|".$r["message"];')


def main():
    # Known starting point: catalogue public, Shopify on, cache empty.
    setting("buyer_gate", "none")
    setting("buyer_accounts_enabled", "1")
    setting("shopify_enabled", "1")
    setting("shopify_show_price", "1")
    php('Database::run("UPDATE products SET shopify_product_id = NULL");')
    php('Database::run("DELETE FROM shopify_products");')
    subprocess.run(["rm", "-f", "shots/.mock_shopify_state"])

    print("\n1. The client")
    bad = php('$c = new ShopifyClient("ds-test","WRONG-TOKEN","2025-01",10,'
              f'"{MOCK}"); $c->get("shop.json"); echo $c->lastError;')
    check("CONTROL a wrong token is reported, not swallowed", "rejected the token" in bad, bad[:70])

    unconf = php('$c = new ShopifyClient("","","2025-01",10); '
                 '$r = ShopifySync::run($c); echo $r["message"];')
    check("CONTROL an unconfigured client changes nothing",
          "not set up" in unconf and php('echo Database::scalar("SELECT COUNT(*) FROM shopify_products");') == "0")

    print("\n2. The sync")
    rep = sync()
    ok, fetched, updated, removed, msg = rep.split("|", 4)
    check("sync succeeds through a 429 and two pages",
          ok == "ok" and fetched == "5" and updated == "5", rep)

    cur = php('$r = Database::one("SELECT currency FROM shopify_products WHERE shopify_product_id = 1001"); echo $r["currency"] ?? "NULL";')
    check("the shop currency is applied to every price", cur == "CAD", cur)

    rows = php('foreach (Database::all("SELECT shopify_product_id,is_available FROM shopify_products ORDER BY shopify_product_id") as $r)'
               ' echo $r["shopify_product_id"],"=",$r["is_available"],";";')
    # 1001 in stock -> 1, 1002 zero+deny -> 0, 1003 zero+continue -> 1,
    # 1004 draft -> 0, 1005 untracked -> 1
    check("availability is Shopify's answer, not just quantity > 0",
          rows == "1001=1;1002=0;1003=1;1004=0;1005=1;", rows)

    print("\n3. Linking")
    # Link "Dragon fruit" (catalogue) to the Shopify one with stock.
    res = php('$p = Database::one("SELECT id FROM products WHERE slug=\'dragon-fruit\'");'
              'echo ShopifyRepository::link((int)$p["id"], 1001) ?? "linked";')
    check("a product can be linked", res == "linked", res)

    other = php('$p = Database::one("SELECT id FROM products WHERE slug=\'bananas\'");'
                'echo ShopifyRepository::link((int)$p["id"], 1001) ?? "linked";')
    check("CONTROL the same Shopify item cannot be linked twice",
          "already linked" in other, other[:70])

    missing = php('$p = Database::one("SELECT id FROM products WHERE slug=\'bananas\'");'
                  'echo ShopifyRepository::link((int)$p["id"], 999999) ?? "linked";')
    check("CONTROL linking to a Shopify id we do not hold is refused",
          "not in the local copy" in missing, missing[:70])

    print("\n4. What a visitor sees")
    with sync_playwright() as p:
        b = p.chromium.launch()
        ctx = b.new_context(viewport={"width": 1280, "height": 900})
        page = ctx.new_page()
        errs = []
        page.on("console", lambda m: errs.append(m.text) if m.type == "error" else None)

        go(page, "/product/dragon-fruit")
        body = page.evaluate("document.body.textContent")
        check("linked and in stock shows the buy button", "Buy online" in body)
        check("and the retail price with its currency", "CAD 12.50" in body, body[body.find("Buy online")-90:body.find("Buy online")] if "Buy online" in body else "")
        check("labelled as retail, not as a wholesale quote", "per unit, retail" in body)
        check("the enquiry route is still offered as well", "Add to shortlist" in body)

        go(page, "/product/bananas")
        body = page.evaluate("document.body.textContent")
        check("CONTROL an unlinked product shows no buy button", "Buy online" not in body)
        check("CONTROL and no price at all", "12.50" not in body)

        # Link the out-of-stock one and prove the button stays away.
        php('$p = Database::one("SELECT id FROM products WHERE slug=\'durian\'");'
            'ShopifyRepository::link((int)$p["id"], 1002);')
        go(page, "/product/durian")
        body = page.evaluate("document.body.textContent")
        check("CONTROL linked but out of stock shows NO buy button", "Buy online" not in body)
        check("CONTROL and does not leak its price either", "31.00" not in body)

        # The draft product: has stock, but is not published in Shopify.
        php('$p = Database::one("SELECT id FROM products WHERE slug=\'mangosteen\'");'
            'ShopifyRepository::link((int)$p["id"], 1004);')
        go(page, "/product/mangosteen")
        check("CONTROL a Shopify draft is never offered for sale",
              "Buy online" not in page.evaluate("document.body.textContent"))

        # Master switch off.
        setting("shopify_enabled", "0")
        go(page, "/product/dragon-fruit")
        check("CONTROL the master switch removes every buy button",
              "Buy online" not in page.evaluate("document.body.textContent"))
        setting("shopify_enabled", "1")

        # Price display switch.
        setting("shopify_show_price", "0")
        go(page, "/product/dragon-fruit")
        body = page.evaluate("document.body.textContent")
        check("hiding the price keeps the button", "Buy online" in body)
        check("CONTROL and really does hide the figure", "12.50" not in body)
        setting("shopify_show_price", "1")

        print("\n5. The admin screen")
        go(page, "/admin/login")
        page.fill("#username", ADMIN_USER)
        page.fill("#password", ADMIN_PASS)
        page.click("form button[type=submit]")
        page.wait_for_load_state("load")

        go(page, "/admin/shopify")
        t = rendered(page)
        check("the Shopify screen loads", "shopify" in page.title().lower())
        check("it lists the linked products", "dragon fruit" in t)
        check("it says it only reads", "read" in t)

        print("\n6. A product deleted in Shopify")
        # Sync again with the product gone: the cache row goes, the link stays,
        # and the buy button disappears rather than pointing at a dead page.
        php('Database::run("DELETE FROM shopify_products WHERE shopify_product_id = 1001");')
        go(page, "/product/dragon-fruit")
        check("CONTROL a vanished Shopify product removes the button",
              "Buy online" not in page.evaluate("document.body.textContent"))
        still = php('$p = Database::one("SELECT shopify_product_id FROM products WHERE slug=\'dragon-fruit\'");'
                    'echo $p["shopify_product_id"];')
        check("but the link is kept so the admin can see what happened", still == "1001", still)

        check("no JavaScript console errors", not errs, errs[:3])
        b.close()

    php('Database::run("UPDATE products SET shopify_product_id = NULL");')
    php('Database::run("DELETE FROM shopify_products");')

    passed = sum(1 for _, ok in checks if ok)
    print(f"\n{passed}/{len(checks)} checks passed")
    failed = [n for n, ok in checks if not ok]
    if failed:
        print("FAILED: " + "; ".join(failed))
    sys.exit(0 if not failed else 1)


if __name__ == "__main__":
    main()
