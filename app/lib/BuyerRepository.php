<?php
/**
 * Buyer account applications and the accounts they turn into.
 *
 * The writes are deliberately narrow. The public form can do exactly two
 * things - createApplication() in vetted mode, selfRegister() in instant mode -
 * and after that only the admin panel can change an account's status or its
 * password.
 *
 * Neither public method can grant `pricing_access`. That is the one thing a
 * visitor must never be able to give themselves, and keeping it out of both of
 * them is what makes that true by construction rather than by remembering.
 */
class BuyerRepository
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    /** Characters used for generated passwords: no 0/O, 1/l/I, 5/S or 8/B. */
    private const ALPHABET = 'abcdefghijkmnpqrtuvwxyzACDEFGHJKLMNPQRTUVWXY234679';

    // -----------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM buyer_accounts WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM buyer_accounts WHERE email = ?', [$email]);
    }

    /** @return array{rows: array, total: int} */
    public static function paginate(?string $status, int $page, int $perPage = 25): array
    {
        $where  = '';
        $params = [];
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $where  = ' WHERE status = ?';
            $params = [$status];
        }

        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM buyer_accounts' . $where, $params);

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Pending first regardless of date - the whole screen exists so that
        // things waiting on a decision are impossible to miss.
        $rows = Database::all(
            'SELECT * FROM buyer_accounts' . $where . "
              ORDER BY (status = 'pending') DESC, created_at DESC
              LIMIT {$perPage} OFFSET {$offset}", $params);

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,int> status => count, every status present. */
    public static function countsByStatus(): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        foreach (Database::all(
            'SELECT status, COUNT(*) AS n FROM buyer_accounts GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public static function pendingCount(): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM buyer_accounts WHERE status = 'pending'");
    }

    // -----------------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------------

    /**
     * Records a request from the public form. Always lands as 'pending' with
     * no username and no password - there is no argument to this method that
     * can change that.
     *
     * @return int the new id
     */
    public static function createApplication(array $d): int
    {
        return Database::insert(
            'INSERT INTO buyer_accounts
                (email, contact_name, company, phone, country, interest, status)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\')',
            [
                mb_substr(trim($d['email']), 0, 190),
                mb_substr(trim($d['contact_name']), 0, 120),
                self::nullable($d['company'] ?? '', 160),
                self::nullable($d['phone'] ?? '', 60),
                self::nullable($d['country'] ?? '', 120),
                self::nullable($d['interest'] ?? '', 4000),
            ]);
    }

    /**
     * Creates an account for a visitor who signed themselves up.
     *
     * Only reachable while Settings has sign-up set to 'instant'. The account
     * is usable immediately - that is the point of the mode - but note what it
     * deliberately does NOT do:
     *
     * - `pricing_access` stays 0. Anybody can sign up, so being signed in
     *   cannot be what unlocks your commercial figures.
     * - `must_change_password` is 0, because they chose the password.
     * - `reviewed_at` stays NULL, which is how the admin list tells a
     *   self-registered account from one you approved.
     *
     * @return int the new id
     */
    public static function selfRegister(array $d, string $password): int
    {
        $id = Database::insert(
            'INSERT INTO buyer_accounts
                (email, contact_name, company, phone, country, interest,
                 password_hash, status, pricing_access, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'approved\', 0, 0)',
            [
                mb_substr(trim($d['email']), 0, 190),
                mb_substr(trim($d['contact_name']), 0, 120),
                self::nullable($d['company'] ?? '', 160),
                self::nullable($d['phone'] ?? '', 60),
                self::nullable($d['country'] ?? '', 120),
                self::nullable($d['interest'] ?? '', 4000),
                password_hash($password, PASSWORD_DEFAULT),
            ]);

        // A username is still assigned so the admin screens and the sign-in
        // form behave identically for both kinds of account. They will
        // normally sign in with their email address.
        Database::run('UPDATE buyer_accounts SET username = ? WHERE id = ?',
            [self::uniqueUsername(self::suggestUsername($d), $id), $id]);

        return $id;
    }

    /** Turn price visibility on or off for one account. Owner-only. */
    public static function setPricingAccess(int $id, bool $on): void
    {
        Database::run('UPDATE buyer_accounts SET pricing_access = ? WHERE id = ?',
            [$on ? 1 : 0, $id]);
    }

    /**
     * Approves an application and issues credentials.
     *
     * The plain password is RETURNED, never stored - the caller shows it to the
     * administrator once so they can pass it on. Only the hash goes to the
     * database, so if this screen is closed the password genuinely cannot be
     * recovered by anyone, and the honest answer is to reset it.
     *
     * @return array{username: string, password: string}
     */
    public static function approve(int $id, ?string $username = null): array
    {
        $row = self::find($id);
        if (!$row) {
            throw new RuntimeException('No such buyer account.');
        }

        $username = $username !== null && trim($username) !== ''
            ? self::uniqueUsername(trim($username), $id)
            : ($row['username'] ?: self::uniqueUsername(self::suggestUsername($row), $id));

        $password = self::generatePassword();

        // Approving from the admin panel is the site owner saying "this is a
        // trade customer", so it grants price visibility as well. Nothing a
        // visitor can do reaches this method.
        Database::run(
            "UPDATE buyer_accounts
                SET username = ?, password_hash = ?, status = 'approved',
                    pricing_access = 1, must_change_password = 1,
                    reviewed_at = NOW()
              WHERE id = ?",
            [$username, password_hash($password, PASSWORD_DEFAULT), $id]);

        return ['username' => $username, 'password' => $password];
    }

    /** Issues a fresh password for an already-approved account. */
    public static function resetPassword(int $id): string
    {
        $password = self::generatePassword();
        Database::run(
            'UPDATE buyer_accounts
                SET password_hash = ?, must_change_password = 1 WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]);
        return $password;
    }

    /** The buyer choosing their own password from the account area. */
    public static function setPassword(int $id, string $password): void
    {
        Database::run(
            'UPDATE buyer_accounts
                SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /**
     * Moves an account to rejected or suspended.
     *
     * The password hash is cleared as well. Leaving it in place would mean a
     * suspended account still holds a working password that starts working
     * again the moment somebody reactivates it, which is not what "revoked"
     * should mean.
     */
    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        if ($status === 'approved') {
            throw new InvalidArgumentException('Use approve() so credentials are issued.');
        }
        // pricing_access goes with it. "Revoked" that leaves a flag set which
        // silently comes back to life on reinstatement is not revoked.
        Database::run(
            'UPDATE buyer_accounts
                SET status = ?, password_hash = NULL, pricing_access = 0,
                    reviewed_at = NOW()
              WHERE id = ?',
            [$status, $id]);
    }

    public static function saveNotes(int $id, string $notes): void
    {
        Database::run('UPDATE buyer_accounts SET admin_notes = ? WHERE id = ?',
            [mb_substr($notes, 0, 8000), $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM buyer_accounts WHERE id = ?', [$id]);
    }

    // -----------------------------------------------------------------------
    // Credentials
    // -----------------------------------------------------------------------

    /**
     * Three groups of four from an alphabet with no lookalike characters, so
     * it survives being read down a phone line or retyped from an email.
     * 49^12 combinations, which is plenty for a password that the buyer is
     * asked to change on first sign-in anyway.
     */
    public static function generatePassword(): string
    {
        $out = '';
        $len = strlen(self::ALPHABET);
        for ($i = 0; $i < 12; $i++) {
            if ($i > 0 && $i % 4 === 0) {
                $out .= '-';
            }
            $out .= self::ALPHABET[random_int(0, $len - 1)];
        }
        return $out;
    }

    /** A readable starting point: company name, else the email local part. */
    public static function suggestUsername(array $row): string
    {
        $base = trim((string) ($row['company'] ?? ''));
        if ($base === '') {
            $base = (string) strstr((string) $row['email'], '@', true);
        }
        $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $base) ?? '');
        return $base !== '' ? substr($base, 0, 40) : 'buyer';
    }

    /** Appends 2, 3, 4... until the username is free. */
    public static function uniqueUsername(string $base, int $ownId): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '', $base) ?? '');
        $base = substr($base !== '' ? $base : 'buyer', 0, 50);

        $candidate = $base;
        $n = 1;
        while (Database::scalar(
            'SELECT COUNT(*) FROM buyer_accounts WHERE username = ? AND id <> ?',
            [$candidate, $ownId])) {
            $n++;
            $candidate = $base . $n;
        }
        return $candidate;
    }

    private static function nullable(string $v, int $max): ?string
    {
        $v = trim($v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
