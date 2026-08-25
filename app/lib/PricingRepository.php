<?php
/**
 * Internal pricing. Admin-only, by construction.
 *
 * Every method here is called from app/controllers/admin.php and nowhere else.
 * The catalogue controller does not include this class and ProductRepository
 * never joins product_pricing, so a public page has no route to a figure even
 * if a template asked for one.
 */
class PricingRepository
{
    /** Fields the price sheet edits, in the order they appear on screen. */
    public const FIELDS = ['price', 'currency', 'price_unit', 'moq',
                           'incoterm', 'valid_until', 'supplier', 'notes'];

    /** Common incoterms, offered as a datalist rather than enforced. */
    public const INCOTERMS = ['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'CPT',
                              'CIP', 'DAP', 'DPU', 'DDP'];

    public static function find(int $productId): ?array
    {
        return Database::one(
            'SELECT * FROM product_pricing WHERE product_id = ?', [$productId]);
    }

    /**
     * Keyed by product id, for rendering a whole sheet without N queries.
     * @param int[] $productIds
     */
    public static function forProducts(array $productIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $productIds)));
        if (!$ids) {
            return [];
        }
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all(
            "SELECT * FROM product_pricing WHERE product_id IN ({$ph})", $ids);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['product_id']] = $r;
        }
        return $out;
    }

    /**
     * The price sheet itself: every product, priced or not, with the filters
     * the sheet offers. Unpriced rows are included on purpose - the sheet is
     * the tool for finding what still needs a price, so hiding them would hide
     * the work.
     *
     * $filters: category_id, origin_id ('none' allowed), q, priced
     *           ('yes'|'no'|''), expiring ('1' = valid_until in the past or
     *           within 30 days).
     */
    public static function sheet(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]  = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (isset($filters['origin_id']) && $filters['origin_id'] === 'none') {
            $where[] = 'p.origin_id IS NULL';
        } elseif (!empty($filters['origin_id'])) {
            $where[]  = 'p.origin_id = ?';
            $params[] = (int) $filters['origin_id'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($filters['q'])) . '%';
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR pr.supplier LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        if (($filters['priced'] ?? '') === 'yes') {
            $where[] = 'pr.price IS NOT NULL';
        } elseif (($filters['priced'] ?? '') === 'no') {
            $where[] = 'pr.price IS NULL';
        }
        // "Expiring" means a date that has passed or is close, which is only
        // meaningful on a row that actually carries a price.
        if (!empty($filters['expiring'])) {
            $where[] = 'pr.price IS NOT NULL AND pr.valid_until IS NOT NULL
                        AND pr.valid_until <= (CURRENT_DATE + INTERVAL 30 DAY)';
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $filters['ids'])));
            if ($ids) {
                $where[] = 'p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params  = array_merge($params, $ids);
            }
        }

        $sql = 'SELECT p.id, p.name, p.sku, p.stock_status, p.is_active,
                       c.name AS category_name, o.name AS origin_name,
                       pr.price, pr.currency, pr.price_unit, pr.moq, pr.incoterm,
                       pr.valid_until, pr.supplier, pr.notes, pr.updated_at
                  FROM products p
                  LEFT JOIN categories c       ON c.id = p.category_id
                  LEFT JOIN origins o          ON o.id = p.origin_id
                  LEFT JOIN product_pricing pr ON pr.product_id = p.id'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY c.sort_order ASC, c.name ASC, p.name ASC';

        return Database::all($sql, $params);
    }

    /** Category / origin / priced counts for the sheet header. */
    public static function stats(): array
    {
        return Database::one(
            'SELECT COUNT(*) AS total,
                    SUM(pr.price IS NOT NULL) AS priced,
                    SUM(pr.price IS NOT NULL
                        AND pr.valid_until IS NOT NULL
                        AND pr.valid_until < CURRENT_DATE) AS expired
               FROM products p
               LEFT JOIN product_pricing pr ON pr.product_id = p.id') ?: [];
    }

    /**
     * Write one product's terms.
     *
     * A row with nothing in any field is deleted rather than stored as a set of
     * NULLs, so "no internal price" is one state in the data instead of two
     * that have to be checked separately everywhere else.
     */
    public static function save(int $productId, array $data): void
    {
        $row = [
            'price'       => ($data['price'] ?? '') === '' ? null : (float) $data['price'],
            'currency'    => strtoupper(substr(trim((string) ($data['currency'] ?? '')), 0, 3))
                             ?: setting('currency_code', 'CAD'),
            'price_unit'  => self::blankToNull($data['price_unit'] ?? '', 60),
            'moq'         => self::blankToNull($data['moq'] ?? '', 60),
            'incoterm'    => self::blankToNull(strtoupper(trim((string) ($data['incoterm'] ?? ''))), 20),
            'valid_until' => self::validDate($data['valid_until'] ?? ''),
            'supplier'    => self::blankToNull($data['supplier'] ?? '', 160),
            'notes'       => self::blankToNull($data['notes'] ?? '', 400),
        ];

        $hasContent = $row['price'] !== null;
        foreach (['price_unit', 'moq', 'incoterm', 'valid_until', 'supplier', 'notes'] as $k) {
            $hasContent = $hasContent || $row[$k] !== null;
        }
        if (!$hasContent) {
            Database::run('DELETE FROM product_pricing WHERE product_id = ?', [$productId]);
            return;
        }

        $row['product_id'] = $productId;
        $cols = implode(', ', array_keys($row));
        $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($row)));
        $upd  = implode(', ', array_map(
            fn($k) => "{$k} = VALUES({$k})",
            array_filter(array_keys($row), fn($k) => $k !== 'product_id')));

        Database::run(
            "INSERT INTO product_pricing ({$cols}) VALUES ({$ph})
             ON DUPLICATE KEY UPDATE {$upd}", $row);
    }

    /** Validate one submitted row. Returns a list of error strings. */
    public static function validate(array $data): array
    {
        $errors = [];
        if (($data['price'] ?? '') !== ''
            && (!is_numeric($data['price']) || (float) $data['price'] < 0)) {
            $errors[] = 'Price must be a number of 0 or more.';
        }
        $cur = trim((string) ($data['currency'] ?? ''));
        if ($cur !== '' && !preg_match('/^[A-Za-z]{3}$/', $cur)) {
            $errors[] = 'Currency must be a three-letter code such as CAD or USD.';
        }
        $vu = trim((string) ($data['valid_until'] ?? ''));
        if ($vu !== '' && self::validDate($vu) === null) {
            $errors[] = 'Valid-until must be a real date in YYYY-MM-DD form.';
        }
        // Terms with no figure are a legitimate half-filled row (you often know
        // the MOQ before the price), but a figure with no unit is ambiguous
        // enough on an export line to be worth flagging.
        if (($data['price'] ?? '') !== '' && trim((string) ($data['price_unit'] ?? '')) === '') {
            $errors[] = 'A price needs a unit - per kg, per carton, per container.';
        }
        return $errors;
    }

    public static function delete(int $productId): void
    {
        Database::run('DELETE FROM product_pricing WHERE product_id = ?', [$productId]);
    }

    private static function blankToNull(string $v, int $max): ?string
    {
        $v = trim($v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    /**
     * Y-m-d or null. checkdate() rejects 2026-02-30, which strtotime() would
     * happily roll forward into March.
     */
    private static function validDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            return null;
        }
        return $v;
    }
}
