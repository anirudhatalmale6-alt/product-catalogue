<?php
/**
 * Buyer enquiries - a submitted shortlist plus who sent it.
 */
class EnquiryRepository
{
    public const STATUSES = ['new', 'in_progress', 'quoted', 'closed'];

    /** How many lines one enquiry may carry. */
    public const MAX_ITEMS = 100;

    public static function statusLabel(string $s): string
    {
        return [
            'new'         => 'New',
            'in_progress' => 'In progress',
            'quoted'      => 'Quoted',
            'closed'      => 'Closed',
        ][$s] ?? ucfirst($s);
    }

    /**
     * Validate a submitted enquiry. Returns [errors, cleanData].
     *
     * $items arrives from the browser as [['id' => n, 'qty' => '', 'note' => '']].
     * The product NAME is never taken from the request - it is read back from
     * the database by id, so a tampered form cannot write an arbitrary line
     * into the enquiry that appears to be a real catalogue product.
     */
    public static function validate(array $in, array $items): array
    {
        $errors = [];

        $data = [
            'company'      => trim((string) ($in['company'] ?? '')),
            'contact_name' => trim((string) ($in['contact_name'] ?? '')),
            'email'        => trim((string) ($in['email'] ?? '')),
            'phone'        => trim((string) ($in['phone'] ?? '')),
            'country'      => trim((string) ($in['country'] ?? '')),
            'destination'  => trim((string) ($in['destination'] ?? '')),
            'incoterm'     => strtoupper(trim((string) ($in['incoterm'] ?? ''))),
            'message'      => trim((string) ($in['message'] ?? '')),
        ];

        if ($data['contact_name'] === '') {
            $errors['contact_name'] = 'Please tell us who to reply to.';
        }
        if ($data['email'] === '') {
            $errors['email'] = 'We need an email address to send the quote to.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'That does not look like an email address.';
        }
        if (!$items) {
            $errors['items'] = 'Your shortlist is empty - add some products first.';
        } elseif (count($items) > self::MAX_ITEMS) {
            $errors['items'] = 'A shortlist can hold up to ' . self::MAX_ITEMS . ' products.';
        }

        foreach (['company' => 160, 'contact_name' => 120, 'email' => 190,
                  'phone' => 60, 'country' => 120, 'destination' => 160,
                  'incoterm' => 20] as $k => $max) {
            $data[$k] = mb_substr($data[$k], 0, $max);
        }
        $data['message'] = mb_substr($data['message'], 0, 4000);

        return [$errors, $data];
    }

    /**
     * Write the enquiry and its lines in one transaction. Returns the row.
     *
     * @param array $items [['id' => productId, 'qty' => string, 'note' => string]]
     */
    public static function create(array $data, array $items): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn($i) => (int) ($i['id'] ?? 0), $items))));
        if (!$ids) {
            throw new RuntimeException('An enquiry needs at least one product.');
        }

        // Read the real names back rather than trusting the posted ones, and
        // drop any id that is not a live product.
        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $rows  = Database::all(
            "SELECT id, name, sku FROM products WHERE id IN ({$ph}) AND is_active = 1", $ids);
        $known = [];
        foreach ($rows as $r) {
            $known[(int) $r['id']] = $r;
        }
        if (!$known) {
            throw new RuntimeException('None of those products are still available.');
        }

        // An optional field left blank is stored as NULL, not '', so "no phone
        // number" is one value everywhere rather than two that both have to be
        // tested for on every screen that prints it.
        foreach (['company', 'phone', 'country', 'destination', 'incoterm', 'message'] as $k) {
            if (($data[$k] ?? '') === '') {
                $data[$k] = null;
            }
        }

        Database::begin();
        try {
            // The reference embeds the id, so it can only be built after the
            // insert. A placeholder goes in first because the column is unique
            // and NOT NULL.
            //
            // 20 characters, against a VARCHAR(24) column - uniqid('', true)
            // is 23 and with the prefix overflowed it, which failed every
            // single submission. random_bytes also keeps two inserts in the
            // same microsecond apart, which uniqid() does not.
            $tmp = 'tmp-' . bin2hex(random_bytes(8));
            $id  = Database::insert(
                'INSERT INTO enquiries
                    (reference, company, contact_name, email, phone, country,
                     destination, incoterm, message, ip_address)
                 VALUES (:reference, :company, :contact_name, :email, :phone,
                         :country, :destination, :incoterm, :message, :ip)',
                array_merge($data, [
                    'reference' => $tmp,
                    'ip'        => self::packedIp(),
                ]));

            $reference = self::buildReference((int) $id);
            Database::run('UPDATE enquiries SET reference = ? WHERE id = ?', [$reference, $id]);

            $sort = 0;
            foreach ($items as $line) {
                $pid = (int) ($line['id'] ?? 0);
                if (!isset($known[$pid])) {
                    continue;
                }
                Database::run(
                    'INSERT INTO enquiry_items
                        (enquiry_id, product_id, product_name, product_sku,
                         quantity, notes, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $pid, $known[$pid]['name'], $known[$pid]['sku'],
                     mb_substr(trim((string) ($line['qty'] ?? '')), 0, 60) ?: null,
                     mb_substr(trim((string) ($line['note'] ?? '')), 0, 300) ?: null,
                     $sort++]);
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return self::find((int) $id);
    }

    /** DS-YYMM-NNNN, e.g. DS-2608-0042. */
    private static function buildReference(int $id): string
    {
        return sprintf('DS-%s-%04d', date('ym'), $id);
    }

    /**
     * The client IP as packed bytes for VARBINARY(16), or null.
     * inet_pton handles v4 and v6; a proxy header is deliberately ignored
     * because anyone can set one.
     */
    private static function packedIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $p  = $ip ? @inet_pton($ip) : false;
        return $p === false ? null : $p;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM enquiries WHERE id = ?', [$id]);
    }

    public static function findByReference(string $ref): ?array
    {
        return Database::one('SELECT * FROM enquiries WHERE reference = ?', [$ref]);
    }

    public static function items(int $enquiryId): array
    {
        return Database::all(
            'SELECT ei.*, p.slug AS product_slug
               FROM enquiry_items ei
               LEFT JOIN products p ON p.id = ei.product_id
              WHERE ei.enquiry_id = ?
              ORDER BY ei.sort_order ASC, ei.id ASC', [$enquiryId]);
    }

    /** Admin listing with status filter and pagination. */
    public static function listing(array $filters, int $page, int $perPage = 25): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[]  = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($filters['q'])) . '%';
            $where[] = '(e.reference LIKE ? OR e.company LIKE ?
                         OR e.contact_name LIKE ? OR e.email LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total  = (int) Database::scalar("SELECT COUNT(*) FROM enquiries e {$w}", $params);
        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $items = Database::all(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM enquiry_items ei WHERE ei.enquiry_id = e.id) AS item_count
               FROM enquiries e
               {$w}
              ORDER BY e.created_at DESC, e.id DESC
              LIMIT {$perPage} OFFSET {$offset}", $params);

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function updateStatus(int $id, string $status, string $notes): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        Database::run('UPDATE enquiries SET status = ?, admin_notes = ? WHERE id = ?',
            [$status, mb_substr(trim($notes), 0, 4000) ?: null, $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM enquiries WHERE id = ?', [$id]);   // items cascade
    }

    public static function countNew(): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM enquiries WHERE status = 'new'");
    }

    /** The product ids on an enquiry, for opening it in the price sheet. */
    public static function productIds(int $enquiryId): array
    {
        return array_map('intval', array_column(Database::all(
            'SELECT product_id FROM enquiry_items
              WHERE enquiry_id = ? AND product_id IS NOT NULL
              ORDER BY sort_order ASC', [$enquiryId]), 'product_id'));
    }
}
