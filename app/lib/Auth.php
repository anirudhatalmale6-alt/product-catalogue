<?php
/**
 * Admin authentication.
 *
 * - passwords are bcrypt hashes (password_hash / password_verify)
 * - failed logins are counted per IP and throttled
 * - the session id is regenerated on login to stop session fixation
 * - login_attempts stores the IP in binary form (inet_pton) so IPv6 fits
 */
class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = Database::one(
            'SELECT id, username, full_name, is_active FROM admin_users WHERE id = ?',
            [$_SESSION['admin_id']]
        );
        // Account deleted or disabled while logged in.
        if (!$cached || (int) $cached['is_active'] !== 1) {
            self::logout();
            return $cached = null;
        }
        return $cached;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Call at the top of every admin page. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['intended'] = $_GET['r'] ?? 'admin';
            redirect('admin/login');
        }
    }

    private static function ipBinary(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $bin = @inet_pton($ip);
        return $bin === false ? inet_pton('0.0.0.0') : $bin;
    }

    /** True when this IP has burned through its attempts. */
    public static function isThrottled(): bool
    {
        $max     = (int) config('security.max_login_attempts', 8);
        $minutes = (int) config('security.login_window_minutes', 15);
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip_address = ? AND was_success = 0
                AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
            [self::ipBinary(), $minutes]
        );
        return $count >= $max;
    }

    private static function record(string $username, bool $success): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip_address, username, was_success) VALUES (?, ?, ?)',
            [self::ipBinary(), substr($username, 0, 64), $success ? 1 : 0]
        );
        // Opportunistic cleanup so the table never grows unbounded.
        if (random_int(1, 20) === 1) {
            Database::run('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        }
    }

    /** Returns an error string on failure, null on success. */
    public static function attempt(string $username, string $password): ?string
    {
        if (self::isThrottled()) {
            return 'Too many failed attempts. Please wait a few minutes and try again.';
        }

        $user = Database::one(
            'SELECT id, username, password_hash, is_active FROM admin_users WHERE username = ?',
            [$username]
        );

        // Always run a hash comparison so a missing user and a wrong password
        // take the same amount of time.
        $hash = $user['password_hash']
            ?? '$2y$10$usesomesillystringforsalttoavoidtimingleaksxxxxxxxxxxxxxx';

        if (!password_verify($password, $hash) || !$user || (int) $user['is_active'] !== 1) {
            self::record($username, false);
            return 'Incorrect username or password.';
        }

        self::record($username, true);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        Database::run('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

        // Rehash if PHP's default cost has moved on since the hash was made.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE admin_users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
