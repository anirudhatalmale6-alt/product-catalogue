<?php
/**
 * All product reads and writes live here so the controllers stay thin and
 * there is exactly one place to look when a query needs changing.
 */
class ProductRepository
{
    /** Columns the front-end is allowed to sort by, mapped to real SQL. */
    private const SORTS = [
        'newest'     => 'p.created_at DESC, p.id DESC',
        'name_asc'   => 'p.name ASC',
        'name_desc'  => 'p.name DESC',
        'price_asc'  => 'COALESCE(p.sale_price, p.price) ASC, p.name ASC',
        'price_desc' => 'COALESCE(p.sale_price, p.price) DESC, p.name ASC',
        'featured'   => 'p.is_featured DESC, p.sort_order ASC, p.name ASC',
    ];

    /**
     * Build the shared WHERE clause for the public listing.
     * Returns [sqlFragment, params].
     */
    private static function buildFilter(array $f, bool $publicOnly = true): array
    {
        $where  = [];
        $params = [];

        if ($publicOnly) {
            $where[] = 'p.is_active = 1';
        }

        if (!empty($f['category_id'])) {
            // Include products filed under child categories too.
            $where[] = '(p.category_id = ? OR c.parent_id = ?)';
            $params[] = (int) $f['category_id'];
            $params[] = (int) $f['category_id'];
        }

        if (!empty($f['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($f['q'])) . '%';
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?
                         OR p.short_description LIKE ? OR p.description LIKE ?
                         OR EXISTS (SELECT 1 FROM product_specs s
                                     WHERE s.product_id = p.id
                                       AND (s.spec_name LIKE ? OR s.spec_value LIKE ?)))';
            array_push($params, $term, $term, $term, $term, $term, $term, $term);
        }

        if (!empty($f['availability'])) {
            $allowed = ['in_stock', 'low_stock', 'out_of_stock', 'preorder', 'discontinued'];
            $picked  = array_values(array_intersect((array) $f['availability'], $allowed));
            if ($picked) {
                $where[] = 'p.stock_status IN (' . implode(',', array_fill(0, count($picked), '?')) . ')';
                $params  = array_merge($params, $picked);
            }
        }

        if (!empty($f['brand'])) {
            $where[] = 'p.brand = ?';
            $params[] = $f['brand'];
        }

        if (isset($f['min_price']) && $f['min_price'] !== '' && is_numeric($f['min_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.price) >= ?';
            $params[] = (float) $f['min_price'];
        }
        if (isset($f['max_price']) && $f['max_price'] !== '' && is_numeric($f['max_price'])) {
            $where[] = 'COALESCE(p.sale_price, p.price) <= ?';
            $params[] = (float) $f['max_price'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * Paginated public listing. Returns
     * ['items' => [...], 'total' => n, 'pages' => n, 'page' => n]
     */
    public static function search(array $filters, int $page = 1, int $perPage = 12): array
    {
        [$where, $params] = self::buildFilter($filters);

        $sortKey = $filters['sort'] ?? 'featured';
        $orderBy = self::SORTS[$sortKey] ?? self::SORTS['featured'];

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             {$where}", $params);

        $perPage = max(1, min(60, $perPage));
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        $offset  = ($page - 1) * $perPage;

        // LIMIT/OFFSET are cast to int, never bound as strings, because MySQL
        // will not accept a placeholder there with emulation switched off.
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                       (SELECT file_path FROM product_images i
                         WHERE i.product_id = p.id
                         ORDER BY i.is_primary DESC, i.sort_order ASC, i.id ASC
                         LIMIT 1) AS primary_image
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                  {$where}
                 ORDER BY {$orderBy}
                 LIMIT {$perPage} OFFSET {$offset}";

        return [
            'items'    => Database::all($sql, $params),
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /** Counts per category for the sidebar, respecting the other filters. */
    public static function countsByCategory(array $filters): array
    {
        $f = $filters;
        unset($f['category_id']);          // a category count ignores itself
        [$where, $params] = self::buildFilter($f);

        $rows = Database::all(
            "SELECT p.category_id, COUNT(*) AS n
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               {$where}
              GROUP BY p.category_id", $params);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['category_id']] = (int) $r['n'];
        }
        return $out;
    }

    /** Counts per availability value, for the sidebar. */
    public static function countsByAvailability(array $filters): array
    {
        $f = $filters;
        unset($f['availability']);
        [$where, $params] = self::buildFilter($f);

        $rows = Database::all(
            "SELECT p.stock_status, COUNT(*) AS n
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               {$where}
              GROUP BY p.stock_status", $params);

        $out = [];
        foreach ($rows as $r) {
            $out[$r['stock_status']] = (int) $r['n'];
        }
        return $out;
    }

    public static function brands(): array
    {
        return array_column(Database::all(
            "SELECT DISTINCT brand FROM products
              WHERE is_active = 1 AND brand IS NOT NULL AND brand <> ''
              ORDER BY brand"), 'brand');
    }

    public static function priceRange(): array
    {
        $row = Database::one(
            'SELECT MIN(COALESCE(sale_price, price)) AS lo,
                    MAX(COALESCE(sale_price, price)) AS hi
               FROM products WHERE is_active = 1');
        return [(float) ($row['lo'] ?? 0), (float) ($row['hi'] ?? 0)];
    }

    public static function findBySlug(string $slug, bool $publicOnly = true): ?array
    {
        $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.slug = ?';
        if ($publicOnly) {
            $sql .= ' AND p.is_active = 1';
        }
        return Database::one($sql . ' LIMIT 1', [$slug]);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, c.name AS category_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.id = ?', [$id]);
    }

    public static function images(int $productId): array
    {
        return Database::all(
            'SELECT * FROM product_images WHERE product_id = ?
              ORDER BY is_primary DESC, sort_order ASC, id ASC', [$productId]);
    }

    /** Specs grouped by spec_group, preserving admin sort order. */
    public static function specs(int $productId): array
    {
        $rows = Database::all(
            'SELECT * FROM product_specs WHERE product_id = ?
              ORDER BY sort_order ASC, id ASC', [$productId]);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['spec_group'] ?: 'General'][] = $r;
        }
        return $grouped;
    }

    /** Related products: same category, excluding this one. */
    public static function related(int $productId, ?int $categoryId, int $limit = 4): array
    {
        if (!$categoryId) {
            return [];
        }
        $limit = max(1, min(12, $limit));
        return Database::all(
            "SELECT p.*, (SELECT file_path FROM product_images i
                           WHERE i.product_id = p.id
                           ORDER BY i.is_primary DESC, i.sort_order ASC, i.id ASC
                           LIMIT 1) AS primary_image
               FROM products p
              WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1
              ORDER BY p.is_featured DESC, RAND()
              LIMIT {$limit}", [$categoryId, $productId]);
    }

    /** Admin listing with its own search + pagination. */
    public static function adminList(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::buildFilter($filters, false);
        $total  = (int) Database::scalar(
            "SELECT COUNT(*) FROM products p
             LEFT JOIN categories c ON c.id = p.category_id {$where}", $params);
        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $orderBy = self::SORTS[$filters['sort'] ?? 'newest'] ?? self::SORTS['newest'];

        $items = Database::all(
            "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) AS image_count,
                    (SELECT file_path FROM product_images i
                      WHERE i.product_id = p.id
                      ORDER BY i.is_primary DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS primary_image
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               {$where}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}", $params);

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Insert or update. Returns the product id. */
    public static function save(?int $id, array $data): int
    {
        $fields = [
            'category_id'       => $data['category_id'] !== '' ? (int) $data['category_id'] : null,
            'sku'               => $data['sku'] !== '' ? $data['sku'] : null,
            'name'              => $data['name'],
            'slug'              => $data['slug'],
            'short_description' => $data['short_description'],
            'description'       => $data['description'],
            'price'             => (float) $data['price'],
            'sale_price'        => ($data['sale_price'] === '' || $data['sale_price'] === null)
                                    ? null : (float) $data['sale_price'],
            'stock_status'      => $data['stock_status'],
            'stock_qty'         => ($data['stock_qty'] === '' || $data['stock_qty'] === null)
                                    ? null : (int) $data['stock_qty'],
            'brand'             => $data['brand'] !== '' ? $data['brand'] : null,
            'weight_grams'      => ($data['weight_grams'] === '' || $data['weight_grams'] === null)
                                    ? null : (int) $data['weight_grams'],
            'is_active'         => !empty($data['is_active']) ? 1 : 0,
            'is_featured'       => !empty($data['is_featured']) ? 1 : 0,
            'sort_order'        => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $id;
            Database::run("UPDATE products SET {$sets} WHERE id = :id", $fields);
            return $id;
        }

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($fields)));
        return Database::insert("INSERT INTO products ({$cols}) VALUES ({$ph})", $fields);
    }

    /** Replace the whole spec set for a product in one go. */
    public static function replaceSpecs(int $productId, array $specs): void
    {
        Database::run('DELETE FROM product_specs WHERE product_id = ?', [$productId]);
        $i = 0;
        foreach ($specs as $s) {
            $name  = trim((string) ($s['spec_name'] ?? ''));
            $value = trim((string) ($s['spec_value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;                       // skip blank rows from the form
            }
            Database::run(
                'INSERT INTO product_specs (product_id, spec_group, spec_name, spec_value, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$productId, trim((string) ($s['spec_group'] ?? '')) ?: null,
                 mb_substr($name, 0, 120), mb_substr($value, 0, 400), $i++]);
        }
    }

    /** Deletes the row; images cascade in SQL, files are removed here. */
    public static function delete(int $id): void
    {
        foreach (self::images($id) as $img) {
            ImageUploader::delete($img['file_path']);
        }
        Database::run('DELETE FROM products WHERE id = ?', [$id]);
    }

    /** Exactly one image per product carries is_primary = 1. */
    public static function setPrimaryImage(int $productId, int $imageId): void
    {
        Database::run('UPDATE product_images SET is_primary = 0 WHERE product_id = ?', [$productId]);
        Database::run('UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?',
            [$imageId, $productId]);
    }

    /** Promote the first remaining image when the primary one is deleted. */
    public static function ensurePrimaryImage(int $productId): void
    {
        $has = Database::scalar(
            'SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1',
            [$productId]);
        if ($has) {
            return;
        }
        $first = Database::scalar(
            'SELECT id FROM product_images WHERE product_id = ?
              ORDER BY sort_order ASC, id ASC LIMIT 1', [$productId]);
        if ($first) {
            Database::run('UPDATE product_images SET is_primary = 1 WHERE id = ?', [$first]);
        }
    }
}
