<?php
/**
 * Public-facing pages: the catalogue listing, a category view and the
 * product detail page.
 */

function catalogue_dispatch(array $segments): void
{
    $first = $segments[0] ?? '';

    switch ($first) {
        case '':
        case 'catalogue':
        case 'products':
            catalogue_index();
            return;

        case 'category':
            $slug = $segments[1] ?? '';
            $cat  = $slug !== '' ? CategoryRepository::findBySlug($slug) : null;
            if (!$cat || (int) $cat['is_active'] !== 1) {
                not_found('That category does not exist.');
            }
            catalogue_index($cat);
            return;

        case 'origin':
            // A shareable URL per country, the same page the sidebar filter
            // produces. /origin/thailand rather than /catalogue?origin=thailand.
            $slug = $segments[1] ?? '';
            $org  = $slug !== '' ? OriginRepository::findBySlug($slug) : null;
            if (!$org || (int) $org['is_active'] !== 1) {
                not_found('That origin does not exist.');
            }
            catalogue_index(null, $org);
            return;

        case 'product':
            catalogue_show($segments[1] ?? '');
            return;

        case 'search':
            // /search?q=... - same page as the listing, kept as a friendly URL
            catalogue_index();
            return;

        case 'shortlist':
            catalogue_shortlist();
            return;

        case 'enquiry':
            // POST from the shortlist page; GET lands people back on the list.
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                catalogue_enquiry_submit();
            } else {
                redirect('shortlist');
            }
            return;

        case 'enquiry-sent':
            catalogue_enquiry_sent();
            return;

        // The shortlist lives in the browser, so it holds ids rather than
        // names. This returns the matching products as JSON so the page can
        // render real titles, images and categories from the database instead
        // of trusting whatever was cached in localStorage weeks ago.
        case 'shortlist-items':
            catalogue_shortlist_items();
            return;

        default:
            not_found();
    }
}

/** Read filters out of the query string, normalising as we go. */
function catalogue_filters(?array $category = null, ?array $origin = null): array
{
    $availability = $_GET['availability'] ?? [];
    if (is_string($availability)) {
        $availability = [$availability];
    }
    $availability = is_array($availability) ? array_map('strval', $availability) : [];

    $categoryId = null;
    if ($category) {
        $categoryId = (int) $category['id'];
    } elseif (!empty($_GET['category'])) {
        $c = CategoryRepository::findBySlug((string) $_GET['category']);
        $categoryId = $c ? (int) $c['id'] : null;
    }

    // ?origin=none is the "not specified yet" bucket, so it is resolved to the
    // literal string rather than looked up and thrown away as an unknown slug.
    $originId = null;
    if ($origin) {
        $originId = (int) $origin['id'];
    } else {
        $originSlug = trim((string) ($_GET['origin'] ?? ''));
        if ($originSlug === 'none') {
            $originId = 'none';
        } elseif ($originSlug !== '') {
            $o = OriginRepository::findBySlug($originSlug);
            $originId = $o ? (int) $o['id'] : null;
        }
    }

    return [
        'q'            => trim((string) ($_GET['q'] ?? '')),
        'category_id'  => $categoryId,
        'origin_id'    => $originId,
        'availability' => $availability,
        'brand'        => trim((string) ($_GET['brand'] ?? '')),
        'sort'         => (string) ($_GET['sort'] ?? 'featured'),
    ];
}

function catalogue_index(?array $category = null, ?array $origin = null): void
{
    $filters = catalogue_filters($category, $origin);
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) setting('per_page', 12);

    $result = ProductRepository::search($filters, $page, $perPage);

    if ($category) {
        $title = $category['name'];
    } elseif ($origin) {
        $title = $origin['name'];
    } else {
        $title = $filters['q'] !== '' ? 'Search: ' . $filters['q'] : 'All products';
    }

    view('catalogue/index', [
        'title'          => $title,
        'category'       => $category,
        'origin'         => $origin,
        'filters'        => $filters,
        'result'         => $result,
        'categories'     => CategoryRepository::navigation(),
        'origins'        => OriginRepository::navigation(),
        'catCounts'      => ProductRepository::countsByCategory($filters),
        'originCounts'   => ProductRepository::countsByOrigin($filters),
        'availCounts'    => ProductRepository::countsByAvailability($filters),
        'brands'         => ProductRepository::brands(),
    ]);
}

function catalogue_show(string $slug): void
{
    if ($slug === '') {
        not_found();
    }
    $product = ProductRepository::findBySlug($slug);
    if (!$product) {
        not_found('That product is no longer listed.');
    }

    view('catalogue/show', [
        'title'      => $product['name'],
        'product'    => $product,
        'images'     => ProductRepository::images((int) $product['id']),
        'specGroups' => ProductRepository::specs((int) $product['id']),
        'related'    => ProductRepository::related(
                            (int) $product['id'],
                            $product['category_id'] ? (int) $product['category_id'] : null),
        'categories' => CategoryRepository::navigation(),
        'metaDescription' => $product['short_description'] ?: setting('site_tagline', ''),
    ]);
}

/**
 * The shortlist page. The list itself is client-side, so this renders an empty
 * shell and the enquiry form; site.js fills in the rows from /shortlist-items.
 *
 * Errors from a rejected submission are carried back in the session so a buyer
 * who mistypes an email does not lose the shortlist they just spent ten minutes
 * building.
 */
function catalogue_shortlist(): void
{
    $errors = $_SESSION['enquiry_errors'] ?? [];
    $old    = $_SESSION['enquiry_old']    ?? [];
    unset($_SESSION['enquiry_errors'], $_SESSION['enquiry_old']);

    view('catalogue/shortlist', [
        'title'      => 'Your shortlist',
        'errors'     => $errors,
        'old'        => $old,
        'categories' => CategoryRepository::navigation(),
        'noindex'    => true,
    ]);
}

/**
 * JSON for the shortlist page: the live products matching the posted ids.
 *
 * Ids that no longer resolve are simply absent from the response, which is how
 * the page learns to drop them - a product withdrawn from the catalogue should
 * not sit in someone's shortlist forever.
 */
function catalogue_shortlist_items(): void
{
    $raw = $_GET['ids'] ?? '';
    $ids = array_slice(array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) $raw))))), 0, EnquiryRepository::MAX_ITEMS);

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // A shortlist is personal to the browser that asked; never let a shared
    // cache hand one response to the next visitor.
    header('Cache-Control: no-store, private');

    if (!$ids) {
        echo json_encode(['items' => []]);
        return;
    }

    $items = ProductRepository::byIds($ids);
    $out   = [];
    foreach ($items as $p) {
        $out[] = [
            'id'         => (int) $p['id'],
            'name'       => $p['name'],
            'sku'        => $p['sku'],
            'slug'       => $p['slug'],
            'url'        => url('product/' . $p['slug']),
            'category'   => $p['category_name'],
            'origin'     => $p['origin_name'],
            'stock'      => stock_label($p['stock_status']),
            'stockClass' => stock_class($p['stock_status']),
            'image'      => upload_url($p['primary_image'] ?? null),
        ];
    }
    echo json_encode(['items' => $out]);
}

/** Handle the posted shortlist. */
function catalogue_enquiry_submit(): void
{
    if (!csrf_valid()) {
        $_SESSION['enquiry_errors'] = ['items' => 'Your session expired. Please send it again.'];
        redirect('shortlist');
    }

    // A hidden field no human fills in. A bot that posts every input it finds
    // gets a success page and writes nothing, so it has no signal to retry
    // against - cheaper and quieter than a captcha on a low-volume form.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        redirect('enquiry-sent');
    }

    $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
    $items = is_array($items) ? $items : [];

    [$errors, $data] = EnquiryRepository::validate($_POST, $items);

    if ($errors) {
        $_SESSION['enquiry_errors'] = $errors;
        $_SESSION['enquiry_old']    = $data;
        redirect('shortlist');
    }

    try {
        $enquiry = EnquiryRepository::create($data, $items);
    } catch (Throwable $e) {
        error_log('Enquiry failed: ' . $e->getMessage());
        $_SESSION['enquiry_errors'] = ['items' =>
            'Something went wrong saving your enquiry. Please try again.'];
        $_SESSION['enquiry_old'] = $data;
        redirect('shortlist');
    }

    // The enquiry is already saved by this point, so a mail failure is logged
    // and swallowed rather than shown as a failure to the buyer - telling them
    // it did not send when it did would get it sent twice.
    enquiry_notify($enquiry);

    $_SESSION['enquiry_reference'] = $enquiry['reference'];
    redirect('enquiry-sent');
}

function catalogue_enquiry_sent(): void
{
    $reference = $_SESSION['enquiry_reference'] ?? null;
    unset($_SESSION['enquiry_reference']);

    view('catalogue/enquiry_sent', [
        'title'      => 'Enquiry sent',
        'reference'  => $reference,
        'categories' => CategoryRepository::navigation(),
        'noindex'    => true,
    ]);
}

/**
 * Email the enquiry to whoever is set under Settings, if anyone is.
 *
 * Left unconfigured this does nothing at all and the enquiry still lands in the
 * admin panel, which is the record that matters. PHP's mail() depends entirely
 * on the host having a working sendmail, so it is treated as a convenience,
 * never as the thing that makes the enquiry real.
 */
function enquiry_notify(array $enquiry): void
{
    $to = trim((string) setting('enquiry_notify_email', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $items = EnquiryRepository::items((int) $enquiry['id']);
    $lines = [];
    foreach ($items as $i => $it) {
        $lines[] = sprintf('%2d. %s%s%s',
            $i + 1,
            $it['product_name'],
            $it['product_sku'] ? ' [' . $it['product_sku'] . ']' : '',
            $it['quantity'] ? ' - ' . $it['quantity'] : '');
        if ($it['notes']) {
            $lines[] = '    ' . $it['notes'];
        }
    }

    $body = "New enquiry {$enquiry['reference']}\n\n"
          . "From:        {$enquiry['contact_name']}\n"
          . "Company:     " . ($enquiry['company'] ?: '-') . "\n"
          . "Email:       {$enquiry['email']}\n"
          . "Phone:       " . ($enquiry['phone'] ?: '-') . "\n"
          . "Country:     " . ($enquiry['country'] ?: '-') . "\n"
          . "Destination: " . ($enquiry['destination'] ?: '-') . "\n"
          . "Incoterm:    " . ($enquiry['incoterm'] ?: '-') . "\n\n"
          . "Products (" . count($items) . "):\n" . implode("\n", $lines) . "\n\n"
          . ($enquiry['message'] ? "Message:\n{$enquiry['message']}\n\n" : '')
          . 'Open it in the admin panel: ' . url('admin/enquiries/' . $enquiry['id']) . "\n";

    // The From address stays on this site's own domain so SPF still lines up;
    // the buyer's address goes in Reply-To, where hitting reply will use it.
    $host    = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from    = 'no-reply@' . ($host ?: 'localhost');
    $headers = "From: {$from}\r\n"
             . 'Reply-To: ' . str_replace(["\r", "\n"], '', $enquiry['email']) . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    $subject = 'Catalogue enquiry ' . $enquiry['reference']
             . ($enquiry['company'] ? ' - ' . $enquiry['company'] : '');

    if (!@mail($to, $subject, $body, $headers)) {
        error_log('Enquiry ' . $enquiry['reference'] . ' saved but the notification email failed.');
    }
}
