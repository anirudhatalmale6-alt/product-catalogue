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
        'min_price'    => $_GET['min_price'] ?? '',
        'max_price'    => $_GET['max_price'] ?? '',
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
        'priceRange'     => ProductRepository::priceRange(),
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
