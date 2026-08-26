<?php
/**
 * Country of origin. Deliberately a separate dimension from categories:
 * a buyer filters "Thai origin" across every product type at once, so
 * modelling it as a second category tree would duplicate every type under
 * every country.
 */
class OriginRepository
{
    /**
     * Active origins with a live product count, in display order.
     *
     * An origin nobody has used yet is left out of the bar: a country sitting
     * there with a 0 beside it reads to a buyer as "you stock nothing from
     * here", which is worse than not offering the filter at all. It stays in
     * the admin menus either way, so adding the first product from a country
     * brings it back on its own.
     */
    public static function navigation(): array
    {
        return Database::all(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.origin_id = o.id AND p.is_active = 1) AS product_count
               FROM origins o
              WHERE o.is_active = 1
             HAVING product_count > 0
              ORDER BY o.sort_order ASC, o.name ASC');
    }

    public static function all(): array
    {
        return Database::all(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM products p WHERE p.origin_id = o.id) AS product_count
               FROM origins o
              ORDER BY o.sort_order ASC, o.name ASC');
    }

    /** For <select> menus on the admin product form. */
    public static function options(): array
    {
        return Database::all('SELECT id, name FROM origins WHERE is_active = 1
                              ORDER BY sort_order ASC, name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM origins WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one('SELECT * FROM origins WHERE slug = ? LIMIT 1', [$slug]);
    }

    /** How many live products have no origin recorded yet. */
    public static function unsetCount(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND origin_id IS NULL');
    }

    public static function save(?int $id, array $data): int
    {
        $fields = [
            'name'       => $data['name'],
            'slug'       => $data['slug'],
            // Stored upper-case so "ca" and "CA" cannot both end up in the list.
            'code'       => trim((string) ($data['code'] ?? '')) !== ''
                             ? strtoupper(substr(trim((string) $data['code']), 0, 8)) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $id;
            Database::run("UPDATE origins SET {$sets} WHERE id = :id", $fields);
            return $id;
        }

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($fields)));
        return Database::insert("INSERT INTO origins ({$cols}) VALUES ({$ph})", $fields);
    }

    /**
     * Products survive the deletion of their origin - the foreign key is
     * ON DELETE SET NULL, so they fall back to "not specified" rather than
     * vanishing from the catalogue.
     */
    public static function delete(int $id): void
    {
        Database::run('DELETE FROM origins WHERE id = ?', [$id]);
    }
}
