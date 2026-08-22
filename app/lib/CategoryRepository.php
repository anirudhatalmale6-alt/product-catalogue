<?php
class CategoryRepository
{
    /** Active categories with a live product count, in display order. */
    public static function navigation(): array
    {
        return Database::all(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM products p
                      WHERE p.category_id = c.id AND p.is_active = 1) AS product_count
               FROM categories c
              WHERE c.is_active = 1
              ORDER BY c.sort_order ASC, c.name ASC');
    }

    public static function all(): array
    {
        return Database::all(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
               FROM categories c
              ORDER BY c.sort_order ASC, c.name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one('SELECT * FROM categories WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function save(?int $id, array $data): int
    {
        $fields = [
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id) {
            // A category cannot be its own parent.
            if ($fields['parent_id'] === $id) {
                $fields['parent_id'] = null;
            }
            $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $id;
            Database::run("UPDATE categories SET {$sets} WHERE id = :id", $fields);
            return $id;
        }

        $cols = implode(', ', array_keys($fields));
        $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($fields)));
        return Database::insert("INSERT INTO categories ({$cols}) VALUES ({$ph})", $fields);
    }

    /**
     * Deleting a category leaves its products in place - they simply become
     * uncategorised (the foreign key is ON DELETE SET NULL). Losing products
     * because a category was tidied up would be a nasty surprise.
     */
    public static function delete(int $id): void
    {
        Database::run('DELETE FROM categories WHERE id = ?', [$id]);
    }
}
