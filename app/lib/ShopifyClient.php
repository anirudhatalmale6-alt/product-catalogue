<?php
/**
 * A deliberately small, READ-ONLY client for Shopify's Admin API.
 *
 * Two rules this class exists to enforce:
 *
 * 1. It can only issue GET requests. There is no post(), no put(), no delete(),
 *    and request() hard-codes the method. Shopify owns the consumer shop; if
 *    this application could write to it, a bug here could empty a real store's
 *    inventory. The token it is given should be read-only too - this is the
 *    second lock on the same door, not a replacement for the first.
 *
 * 2. It never throws its way onto a customer's screen. A sync is a background
 *    convenience; a Shopify outage must leave the catalogue working exactly as
 *    it did before, showing the last figures it cached.
 */
class ShopifyClient
{
    private string $shop;
    private string $token;
    private string $version;
    private int    $timeout;

    /**
     * Test seam. Lets the test suite point this client at a local fake Shopify.
     * Deliberately NOT read from config: the scheme in the real code path is
     * hard-coded https, so no configuration mistake can downgrade a live site
     * to sending its API token over plain http.
     */
    private ?string $baseUrlForTests;

    /** Populated by the last request, for the sync report. */
    public ?string $lastError = null;
    public int     $callCount = 0;

    public function __construct(?string $shop = null, ?string $token = null,
                                ?string $version = null, ?int $timeout = null,
                                ?string $baseUrlForTests = null)
    {
        $this->baseUrlForTests = $baseUrlForTests;
        $this->shop    = self::normaliseShop((string) ($shop ?? config('shopify.shop', '')));
        $this->token   = (string) ($token ?? config('shopify.token', ''));
        $this->version = (string) ($version ?? config('shopify.version', '2025-01'));
        $this->timeout = (int) ($timeout ?? config('shopify.timeout', 15));
    }

    /**
     * Accepts "store", "store.myshopify.com" or a full URL and returns the
     * bare host. People paste whichever of those is on screen at the time.
     */
    public static function normaliseShop(string $shop): string
    {
        $shop = trim($shop);
        if ($shop === '') {
            return '';
        }
        if (str_contains($shop, '://')) {
            $shop = (string) parse_url($shop, PHP_URL_HOST);
        }
        $shop = rtrim($shop, '/');
        if ($shop !== '' && !str_contains($shop, '.')) {
            $shop .= '.myshopify.com';
        }
        return $shop;
    }

    public function isConfigured(): bool
    {
        return $this->shop !== '' && $this->token !== '';
    }

    /** Somewhere to point the admin screen when it is not set up yet. */
    public function shopDomain(): string
    {
        return $this->shop;
    }

    /**
     * One GET. Returns the decoded body, or null on any failure - and sets
     * $lastError to something a human can act on rather than a stack trace.
     *
     * @param array{0:?string} $linkHeader filled with the Link header, for paging
     */
    public function get(string $path, array $query = [], ?array &$linkHeader = null): ?array
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Shopify is not configured - shop domain or token missing from app/config.php.';
            return null;
        }

        $base = $this->baseUrlForTests !== null
              ? rtrim($this->baseUrlForTests, '/')
              : 'https://' . $this->shop;
        $url = $base . '/admin/api/' . $this->version . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        // Up to three attempts, but only for the failures that are actually
        // worth retrying: rate limiting and Shopify's own 5xx. A 401 is a wrong
        // token and will be just as wrong in two seconds.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_HTTPHEADER     => [
                    'X-Shopify-Access-Token: ' . $this->token,
                    'Accept: application/json',
                ],
            ]);
            $raw  = curl_exec($ch);
            $this->callCount++;

            if ($raw === false) {
                $this->lastError = 'Could not reach Shopify: ' . curl_error($ch);
                curl_close($ch);
                return null;
            }

            $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers    = substr($raw, 0, $headerSize);
            $body       = substr($raw, $headerSize);
            curl_close($ch);

            if ($status === 429 || $status >= 500) {
                // Shopify sends Retry-After on 429. Respect it rather than
                // guessing, and cap it so a sync cannot hang for minutes.
                $wait = 1;
                if (preg_match('/^retry-after:\s*([\d.]+)/mi', $headers, $m)) {
                    $wait = min(5, max(1, (int) ceil((float) $m[1])));
                }
                if ($attempt < 3) {
                    sleep($wait);
                    continue;
                }
                $this->lastError = "Shopify returned {$status} three times - giving up for now.";
                return null;
            }

            if ($status === 401 || $status === 403) {
                $this->lastError = 'Shopify rejected the token (' . $status . '). '
                    . 'Check it is the Admin API access token and that the app has '
                    . 'read_products and read_inventory.';
                return null;
            }
            if ($status === 404) {
                $this->lastError = 'Shopify returned 404 for ' . $path
                    . ' - check the shop domain and the API version.';
                return null;
            }
            if ($status !== 200) {
                $this->lastError = 'Shopify returned HTTP ' . $status . '.';
                return null;
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                $this->lastError = 'Shopify returned something that is not JSON.';
                return null;
            }

            if ($linkHeader !== null || func_num_args() >= 3) {
                $linkHeader = [];
                if (preg_match('/^link:\s*(.+)$/mi', $headers, $m)) {
                    $linkHeader['raw'] = trim($m[1]);
                    if (preg_match('/<([^>]+)>;\s*rel="next"/', $m[1], $n)) {
                        $linkHeader['next'] = $n[1];
                    }
                }
            }
            return $data;
        }
        return null;
    }

    /**
     * Every product, following Shopify's cursor paging.
     *
     * Capped at 20 pages (5000 products). A cap that is never mentioned is how
     * "we synced everything" turns into "we synced the first few hundred", so
     * hitting it sets lastError rather than returning quietly.
     *
     * @return array[] raw Shopify product rows
     */
    public function allProducts(int $perPage = 250): array
    {
        $out  = [];
        $path = 'products.json';
        $query = ['limit' => max(1, min(250, $perPage))];

        for ($page = 1; $page <= 20; $page++) {
            $link = [];
            $data = $this->get($path, $query, $link);
            if ($data === null) {
                return $out;   // lastError already set
            }
            foreach (($data['products'] ?? []) as $p) {
                $out[] = $p;
            }
            if (empty($link['next'])) {
                return $out;
            }
            // The next URL is absolute and already carries page_info; strip it
            // back to path + query so get() can rebuild it.
            $next  = $link['next'];
            $path  = ltrim((string) parse_url($next, PHP_URL_PATH), '/');
            $path  = preg_replace('#^admin/api/[^/]+/#', '', $path);
            parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        }

        $this->lastError = 'Stopped after 5000 products. If your shop is larger '
                         . 'than that, the sync needs raising.';
        return $out;
    }

    /** A cheap call used to prove the credentials work before anything else. */
    public function checkConnection(): array
    {
        $data = $this->get('shop.json');
        if ($data === null) {
            return ['ok' => false, 'message' => $this->lastError ?? 'Unknown error'];
        }
        return [
            'ok'      => true,
            'name'    => $data['shop']['name'] ?? '(unnamed)',
            'domain'  => $data['shop']['myshopify_domain'] ?? $this->shop,
            'currency' => $data['shop']['currency'] ?? null,
        ];
    }
}
