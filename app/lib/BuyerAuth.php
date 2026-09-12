<?php
/**
 * Buyer authentication - the customer-facing door.
 *
 * Deliberately a separate class from Auth even though the two look similar:
 *
 * - it reads a different session key, so a signed-in buyer is never one
 *   mistyped condition away from being treated as an administrator
 * - a row is only usable while its status is 'approved', and that is checked on
 *   every page rather than only at sign-in, so revoking access in the admin
 *   panel takes effect on the buyer's very next click
 * - seeing PRICES is a separate grant again (`pricing_access`), because when
 *   sign-up is instant, "is signed in" stops meaning anything about who they are
 * - it shares the login_attempts table with the admin login, so somebody
 *   guessing passwords cannot get a fresh allowance simply by moving to the
 *   other form
 */
class BuyerAuth
{
    /** Session key. Not 'admin_id' - that separation is the whole point. */
    private const SESSION_KEY = 'buyer_id';

    public static function user(): ?array
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = Database::one(
            'SELECT id, username, email, contact_name, company, country, phone,
                    status, pricing_access, must_change_password, last_login_at,
                    created_at
               FROM buyer_accounts WHERE id = ?',
            [$_SESSION[self::SESSION_KEY]]
        );
        // Suspended, rejected or deleted while they were signed in. Checking on
        // every page rather than only at login means revoking access in the
        // admin panel takes effect on the buyer's very next click.
        if (!$cached || $cached['status'] !== 'approved') {
            self::logout();
            return $cached = null;
        }
        return $cached;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Call at the top of any page that is for signed-in buyers only. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['buyer_intended'] = $_GET['r'] ?? 'account';
            redirect('account/login');
        }
    }

    /**
     * Returns an error string on failure, null on success.
     *
     * The wording of each failure is chosen on purpose. A pending or rejected
     * applicant is told the truth - they applied, they are waiting - because
     * they know they applied and a flat "incorrect password" would just
     * generate an email asking what went wrong. A username that does not exist
     * gets the generic message, so the form cannot be used to find out who
     * holds an account.
     */
    public static function attempt(string $login, string $password): ?string
    {
        if (Auth::isThrottled()) {
            return 'Too many failed attempts. Please wait a few minutes and try again.';
        }

        // Accept either the assigned username or the email address - people
        // remember the address they applied with far more reliably.
        $row = Database::one(
            'SELECT id, username, password_hash, status
               FROM buyer_accounts
              WHERE (username IS NOT NULL AND username = ?) OR email = ?
              LIMIT 1',
            [$login, $login]
        );

        // Always spend the same time on a hash comparison whether or not the
        // account exists, so timing cannot be used to enumerate buyers.
        $hash = $row['password_hash']
            ?? '$2y$10$usesomesillystringforsalttoavoidtimingleaksxxxxxxxxxxxxxx';
        $passwordOk = password_verify($password, $hash) && !empty($row['password_hash']);

        if (!$row || !$passwordOk) {
            self::record($login, false);
            if ($row && $row['status'] === 'pending') {
                return 'Your access request is still being reviewed. '
                     . 'You will receive your login by email once it is approved.';
            }
            return 'Incorrect login or password.';
        }

        // The password was right - but being right is not the same as being
        // allowed in.
        if ($row['status'] !== 'approved') {
            self::record($login, false);
            if ($row['status'] === 'pending') {
                return 'Your access request is still being reviewed.';
            }
            return 'This account is not currently active. Please get in touch if you think that is a mistake.';
        }

        self::record($login, true);
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $row['id'];
        Database::run('UPDATE buyer_accounts SET last_login_at = NOW() WHERE id = ?', [$row['id']]);

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE buyer_accounts SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        return null;
    }

    private static function record(string $login, bool $success): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip_address, username, was_success) VALUES (?, ?, ?)',
            [self::ipBinary(), substr('buyer:' . $login, 0, 64), $success ? 1 : 0]
        );
    }

    private static function ipBinary(): string
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $bin = @inet_pton($ip);
        return $bin === false ? inet_pton('0.0.0.0') : $bin;
    }

    /**
     * Signs the buyer out WITHOUT destroying the whole session.
     *
     * Auth::logout() throws the session away entirely, which is right for the
     * admin panel. Here it would also wipe an administrator who happened to be
     * signed in in the same browser - a normal thing to do while testing the
     * buyer side - so only the buyer's own keys are removed.
     */
    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY], $_SESSION['buyer_intended']);
    }

    /**
     * What a signed-in buyer is allowed to see beyond a public visitor.
     * 'none' | 'prices' | 'catalogue' - set in Admin -> Settings.
     */
    public static function gate(): string
    {
        $g = (string) setting('buyer_gate', 'none');
        return in_array($g, ['none', 'prices', 'catalogue'], true) ? $g : 'none';
    }

    public static function accountsEnabled(): bool
    {
        return (string) setting('buyer_accounts_enabled', '0') === '1';
    }

    /**
     * 'vetted'  - a visitor sends a request and waits for the owner to approve
     *             it, and the owner issues the password.
     * 'instant' - a visitor creates their own account and is signed in at once.
     */
    public static function signupMode(): string
    {
        $m = (string) setting('buyer_signup_mode', 'vetted');
        return in_array($m, ['vetted', 'instant'], true) ? $m : 'vetted';
    }

    /**
     * True when this visitor may see internal price sheet figures.
     *
     * Note the last condition. Being signed in is NOT enough, because in
     * instant mode anybody can be signed in within thirty seconds - a
     * competitor included. Prices need `pricing_access`, which only the site
     * owner can set, from the admin panel, one account at a time.
     */
    public static function canSeePrices(): bool
    {
        if (!self::accountsEnabled() || self::gate() !== 'prices') {
            return false;
        }
        $user = self::user();
        return $user !== null && (int) $user['pricing_access'] === 1;
    }

    /**
     * True when the catalogue itself should be hidden from this visitor.
     * Only ever true when the whole-catalogue gate is switched on AND they are
     * not signed in.
     */
    public static function catalogueHidden(): bool
    {
        return self::accountsEnabled() && self::gate() === 'catalogue' && !self::check();
    }
}
