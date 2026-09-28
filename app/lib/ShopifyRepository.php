<?php
/**
 * The local cache of what Shopify told us, and the link between a Shopify
 * product and a catalogue product.
 *
 * Nothing in here is authoritative. `shopify_products` can be truncated and
 * refilled at any time without losing anything, because the only durable fact
 * is `products.shopify_product_id` - the link a person made on purpose in the
 * admin panel. That separation is deliberate: a bad sync should never be able
 * to unpick the links.
 */
class ShopifyRepository
{
    // -----------------------------------------------------------------------
    // The cache
    // -----------------------------------------------------------------------

    /** One row as Shopify last described it, or null. */
    public static function find(int $shopifyProductId): ?array
    {
        return Database::one(
            'SELECT * FROM shopify_products WHERE shopify_product_id = ?',
            [$shopifyProductId]);
    }

    /**
     * Everything we know, for the picker. Already-linked items are marked so
     * the screen can say "linked to Bananas" instead of offering it twice.
     */
    public static function all(string $search = ''): array
    {
        $sql = 'SELECT s.*, p.id AS linked_product_id, p.name AS linked_product_name
                  FROM shopify_products s
             LEFT JOIN products p ON p.shopify_product_id = s.shopify_product_id';
        $params = [];
        if (trim($search) !== '') {
            $sql .= ' WHERE s.title LIKE ? OR s.sku LIKE ?';
            $params = ['%' . trim($search) . '%', '%' . trim($search) . '%'];
        }
        $sql .= ' ORDER BY s.title';
        return Database::all($sql, $params);
    }

    public static function count(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM shopify_products');
    }

    public static function lastSyncedAt(): ?string
    {
        $v = Database::scalar('SELECT MAX(synced_at) FROM shopify_products');
        return $v ?: null;
    }

    /** Insert or update one cached product. */
    public static function upsert(array $row): void
    {
        Database::run(
            'INSERT INTO shopify_products
                (shopify_product_id, shopify_variant_id, title, handle, sku,
                 price, currency, inventory_qty, is_available, online_url,
                 image_url, status, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                shopify_variant_id = VALUES(shopify_variant_id),
                title              = VALUES(title),
                handle             = VALUES(handle),
                sku                = VALUES(sku),
                price              = VALUES(price),
                currency           = VALUES(currency),
                inventory_qty      = VALUES(inventory_qty),
                is_available       = VALUES(is_available),
                online_url         = VALUES(online_url),
                image_url          = VALUES(image_url),
                status             = VALUES(status),
                synced_at          = NOW()',
            [
                $row['shopify_product_id'],
                $row['shopify_variant_id'] ?? null,
                mb_substr((string) $row['title'], 0, 255),
                $row['handle'] ?? null,
                $row['sku'] ?? null,
                $row['price'] ?? null,
                $row['currency'] ?? null,
                $row['inventory_qty'] ?? null,
                !empty($row['is_available']) ? 1 : 0,
                $row['online_url'] ?? null,
                $row['image_url'] ?? null,
                $row['status'] ?? null,
            ]);
    }

    /**
     * Removes cached rows Shopify no longer returns.
     *
     * A product deleted in Shopify must stop being offered here, but the LINK
     * is left alone on purpose - the catalogue product keeps its
     * shopify_product_id, the buy button disappears because the cache row has
     * gone, and the admin screen can say "linked to something Shopify no
     * longer has" instead of silently forgetting the connection ever existed.
     *
     * @param int[] $keepIds
     * @return int rows removed
     */
    public static function pruneMissing(array $keepIds): int
    {
        if (!$keepIds) {
            // Refuse to empty the cache on an empty list. That is what a failed
            // or half-finished fetch looks like, and treating it as "Shopify
            // has no products" would take every buy button off the site.
            return 0;
        }
        $ph = implode(',', array_fill(0, count($keepIds), '?'));
        $st = Database::run(
            "DELETE FROM shopify_products WHERE shopify_product_id NOT IN ({$ph})",
            array_map('intval', $keepIds));
        return $st->rowCount();
    }

    // -----------------------------------------------------------------------
    // The link
    // -----------------------------------------------------------------------

    /**
     * Point a catalogue product at a Shopify one, or pass null to unlink.
     *
     * The unique key on products.shopify_product_id means the database refuses
     * a Shopify item that is already linked elsewhere, so this checks first and
     * returns a sentence rather than letting a duplicate-key error reach the
     * screen.
     *
     * @return ?string an error message, or null on success
     */
    public static function link(int $productId, ?int $shopifyProductId): ?string
    {
        if ($shopifyProductId !== null) {
            if (!self::find($shopifyProductId)) {
                return 'That Shopify product is not in the local copy. Run a sync first.';
            }
            $taken = Database::one(
                'SELECT id, name FROM products WHERE shopify_product_id = ? AND id <> ?',
                [$shopifyProductId, $productId]);
            if ($taken) {
                return 'That Shopify product is already linked to "' . $taken['name']
                     . '". Unlink it there first.';
            }
        }
        Database::run('UPDATE products SET shopify_product_id = ? WHERE id = ?',
            [$shopifyProductId, $productId]);
        return null;
    }

    /** What a catalogue product is linked to, with the cached detail. */
    public static function forProduct(int $productId): ?array
    {
        return Database::one(
            'SELECT s.* FROM products p
               JOIN shopify_products s ON s.shopify_product_id = p.shopify_product_id
              WHERE p.id = ?',
            [$productId]);
    }

    /** Linked catalogue products, for the admin overview. */
    public static function linkedProducts(): array
    {
        return Database::all(
            'SELECT p.id, p.name, p.slug, p.shopify_product_id,
                    s.title AS shopify_title, s.price, s.currency,
                    s.inventory_qty, s.is_available, s.synced_at,
                    s.shopify_product_id AS cached
               FROM products p
          LEFT JOIN shopify_products s ON s.shopify_product_id = p.shopify_product_id
              WHERE p.shopify_product_id IS NOT NULL
           ORDER BY p.name');
    }

    public static function linkedCount(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM products WHERE shopify_product_id IS NOT NULL');
    }

    /**
     * Catalogue products with no link yet, for the picker's other column.
     * Only active ones - an item taken off the site is not a candidate.
     */
    public static function unlinkedProducts(): array
    {
        return Database::all(
            'SELECT id, name, slug FROM products
              WHERE shopify_product_id IS NULL AND is_active = 1
           ORDER BY name');
    }

    /**
     * Suggests links by exact, case-insensitive name match.
     *
     * Offered as a SUGGESTION the owner confirms, never applied automatically.
     * Name matching is exactly the fragile thing this design avoids relying on;
     * it is fine as a shortcut for a human who can see both names side by side,
     * and would not be fine as the thing that silently keeps stock in step.
     *
     * @return array[] {product_id, product_name, shopify_product_id, shopify_title}
     */
    public static function suggestLinks(): array
    {
        return Database::all(
            'SELECT p.id AS product_id, p.name AS product_name,
                    s.shopify_product_id, s.title AS shopify_title
               FROM products p
               JOIN shopify_products s ON LOWER(TRIM(s.title)) = LOWER(TRIM(p.name))
              WHERE p.shopify_product_id IS NULL
                AND p.is_active = 1
                AND NOT EXISTS (SELECT 1 FROM products p2
                                 WHERE p2.shopify_product_id = s.shopify_product_id)
           ORDER BY p.name');
    }
}
