<?php
/**
 * Pulls Shopify's product list into the local cache.
 *
 * One direction only, Shopify -> catalogue. There is no code path in this
 * application that changes anything in Shopify, and there should never be one
 * without a deliberate decision: Shopify is where real orders and real money
 * live, and this is a catalogue.
 */
class ShopifySync
{
    /**
     * @return array{ok:bool, fetched:int, updated:int, removed:int,
     *                calls:int, message:string}
     */
    public static function run(?ShopifyClient $client = null): array
    {
        $client = $client ?? new ShopifyClient();

        $report = ['ok' => false, 'fetched' => 0, 'updated' => 0, 'removed' => 0,
                   'calls' => 0, 'message' => ''];

        if (!$client->isConfigured()) {
            $report['message'] = 'Shopify is not set up yet. Add the shop domain '
                . 'and token to app/config.php.';
            return $report;
        }

        // Shopify carries the currency on the SHOP, not on the variant, so it
        // has to be asked for separately. Without this the catalogue printed a
        // bare "12.50" with no idea whether that was dollars or baht - which is
        // worse than showing no price at all on a page aimed at importers.
        $shopInfo = $client->checkConnection();
        $currency = $shopInfo['ok'] ? ($shopInfo['currency'] ?? null) : null;

        $products = $client->allProducts();
        $report['calls'] = $client->callCount;

        // An error mid-way through is NOT partial success. Shopify may have
        // handed back two pages of five before failing, and writing those while
        // pruning everything else would delete most of the cache and take the
        // buy buttons off the site. So: any error, change nothing.
        if ($client->lastError !== null) {
            $report['message'] = $client->lastError;
            return $report;
        }

        $report['fetched'] = count($products);

        // Equally: a successful call that returns zero products is far more
        // likely to be the wrong shop, or a token scoped to nothing, than a
        // shop that genuinely has none. Refuse to act on it.
        if (!$products) {
            $report['message'] = 'Shopify returned no products at all. Nothing was '
                . 'changed - check the shop domain and that the app has read_products.';
            return $report;
        }

        $keep = [];
        foreach ($products as $p) {
            $row = self::mapProduct($p, $client->shopDomain(), $currency);
            if ($row === null) {
                continue;
            }
            ShopifyRepository::upsert($row);
            $keep[] = $row['shopify_product_id'];
            $report['updated']++;
        }

        $report['removed'] = ShopifyRepository::pruneMissing($keep);
        $report['ok']      = true;
        $report['message'] = sprintf(
            '%d product(s) from Shopify, %d cached, %d no longer in Shopify removed.',
            $report['fetched'], $report['updated'], $report['removed']);

        return $report;
    }

    /**
     * Flattens one Shopify product into the single row the catalogue needs.
     *
     * Shopify products have many variants; the catalogue shows one line per
     * product, so the FIRST variant is used for price and stock. That is a
     * simplification worth stating out loud rather than hiding: if a product
     * is sold in three sizes at three prices, the catalogue shows the first
     * one. It is a "from" price with a link, not a quote.
     */
    public static function mapProduct(array $p, string $shopDomain,
                                      ?string $shopCurrency = null): ?array
    {
        if (empty($p['id'])) {
            return null;
        }
        $variant = $p['variants'][0] ?? [];

        // "Available" is Shopify's own answer, not ours. A shop can be set to
        // keep selling at zero stock, so quantity alone is the wrong test.
        $qty       = isset($variant['inventory_quantity']) ? (int) $variant['inventory_quantity'] : null;
        $policy    = (string) ($variant['inventory_policy'] ?? 'deny');
        $tracked   = ($variant['inventory_management'] ?? null) !== null;
        $published = !empty($p['published_at']) && ($p['status'] ?? 'active') === 'active';

        $available = $published && (
            !$tracked                       // stock not tracked - always sellable
            || $policy === 'continue'       // oversell allowed
            || ($qty !== null && $qty > 0)
        );

        return [
            'shopify_product_id' => (int) $p['id'],
            'shopify_variant_id' => isset($variant['id']) ? (int) $variant['id'] : null,
            'title'              => (string) ($p['title'] ?? 'Untitled'),
            'handle'             => $p['handle'] ?? null,
            'sku'                => ($variant['sku'] ?? '') !== '' ? $variant['sku'] : null,
            'price'              => isset($variant['price']) && $variant['price'] !== ''
                                      ? (float) $variant['price'] : null,
            // Variants have no currency of their own; this comes from the shop.
            'currency'           => $variant['currency'] ?? $shopCurrency,
            'inventory_qty'      => $qty,
            'is_available'       => $available,
            'online_url'         => !empty($p['handle'])
                                      ? 'https://' . $shopDomain . '/products/' . $p['handle']
                                      : null,
            'image_url'          => $p['image']['src'] ?? ($p['images'][0]['src'] ?? null),
            'status'             => $p['status'] ?? null,
        ];
    }
}
