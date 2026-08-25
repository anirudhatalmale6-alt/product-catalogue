<?php
/**
 * Admin panel: login, dashboard, products, categories, settings.
 *
 * Every action that changes data is POST-only and checks a CSRF token first.
 */

function admin_dispatch(array $s): void
{
    $action = $s[0] ?? '';

    // --- Public admin routes (no session needed) ---------------------------
    if ($action === 'login') {
        admin_login();
        return;
    }
    if ($action === 'logout') {
        // POST-only so a stray <img src="/admin/logout"> cannot sign you out.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_check();
        }
        Auth::logout();
        redirect('admin/login');
    }

    Auth::requireLogin();

    switch ($action) {
        case '':
            admin_dashboard();
            return;

        case 'products':
            $sub = $s[1] ?? '';
            if ($sub === 'new')    { admin_product_form(null); return; }
            if ($sub === 'edit')   { admin_product_form((int) ($s[2] ?? 0)); return; }
            if ($sub === 'save')   { admin_product_save(); return; }
            if ($sub === 'delete') { admin_product_delete(); return; }
            if ($sub === 'image')  { admin_product_image($s[2] ?? ''); return; }
            if ($sub === 'bulk-images') { admin_bulk_images(); return; }
            admin_product_list();
            return;

        // Internal pricing. Nothing under here is reachable without a session -
        // Auth::requireLogin() has already run above.
        case 'pricing':
            $sub = $s[1] ?? '';
            if ($sub === 'save')   { admin_pricing_save(); return; }
            if ($sub === 'export') { admin_pricing_export(); return; }
            admin_pricing_sheet();
            return;

        case 'enquiries':
            $sub = $s[1] ?? '';
            if ($sub === 'update') { admin_enquiry_update(); return; }
            if ($sub === 'delete') { admin_enquiry_delete(); return; }
            if ($sub === 'export') { admin_enquiry_export((int) ($s[2] ?? 0)); return; }
            if ($sub !== '' && ctype_digit((string) $sub)) {
                admin_enquiry_show((int) $sub);
                return;
            }
            admin_enquiry_list();
            return;

        case 'categories':
            $sub = $s[1] ?? '';
            if ($sub === 'new')    { admin_category_form(null); return; }
            if ($sub === 'edit')   { admin_category_form((int) ($s[2] ?? 0)); return; }
            if ($sub === 'save')   { admin_category_save(); return; }
            if ($sub === 'delete') { admin_category_delete(); return; }
            admin_category_list();
            return;

        case 'origins':
            $sub = $s[1] ?? '';
            if ($sub === 'new')    { admin_origin_form(null); return; }
            if ($sub === 'edit')   { admin_origin_form((int) ($s[2] ?? 0)); return; }
            if ($sub === 'save')   { admin_origin_save(); return; }
            if ($sub === 'delete') { admin_origin_delete(); return; }
            admin_origin_list();
            return;

        case 'settings':
            admin_settings();
            return;

        case 'password':
            admin_password();
            return;

        default:
            not_found();
    }
}

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

function admin_login(): void
{
    if (Auth::check()) {
        redirect('admin');
    }
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $error = Auth::attempt(
            trim((string) ($_POST['username'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );
        if ($error === null) {
            $intended = $_SESSION['intended'] ?? 'admin';
            unset($_SESSION['intended']);
            redirect(str_starts_with($intended, 'admin') ? $intended : 'admin');
        }
    }

    view('admin/login', ['title' => 'Admin sign in', 'error' => $error], 'admin/layout_bare');
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

function admin_dashboard(): void
{
    $stats = [
        'products'   => (int) Database::scalar('SELECT COUNT(*) FROM products'),
        'active'     => (int) Database::scalar('SELECT COUNT(*) FROM products WHERE is_active = 1'),
        'categories' => (int) Database::scalar('SELECT COUNT(*) FROM categories'),
        'images'     => (int) Database::scalar('SELECT COUNT(*) FROM product_images'),
        'out'        => (int) Database::scalar("SELECT COUNT(*) FROM products WHERE stock_status = 'out_of_stock'"),
        'low'        => (int) Database::scalar("SELECT COUNT(*) FROM products WHERE stock_status = 'low_stock'"),
        'no_image'   => (int) Database::scalar(
            'SELECT COUNT(*) FROM products p
              WHERE NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)'),
        // "Still to fill in" counts, so an import that is only part-done is
        // visible on the dashboard instead of having to be gone looking for.
        'no_origin'  => OriginRepository::unsetCount(),
        'no_price'   => (int) Database::scalar(
            'SELECT COUNT(*) FROM products p
              WHERE p.is_active = 1
                AND NOT EXISTS (SELECT 1 FROM product_pricing pr
                                 WHERE pr.product_id = p.id AND pr.price IS NOT NULL)'),
        'new_enquiries' => EnquiryRepository::countNew(),
    ];

    view('admin/dashboard', [
        'title'  => 'Dashboard',
        'stats'  => $stats,
        'recent' => Database::all(
            'SELECT p.id, p.name, p.slug, p.stock_status, p.is_active, p.updated_at,
                    c.name AS category_name
               FROM products p LEFT JOIN categories c ON c.id = p.category_id
              ORDER BY p.updated_at DESC LIMIT 8'),
        'recentEnquiries' => Database::all(
            'SELECT e.id, e.reference, e.company, e.contact_name, e.status, e.created_at,
                    (SELECT COUNT(*) FROM enquiry_items ei WHERE ei.enquiry_id = e.id) AS item_count
               FROM enquiries e ORDER BY e.created_at DESC LIMIT 6'),
        'attention' => Database::all(
            "SELECT id, name, stock_status FROM products
              WHERE stock_status IN ('out_of_stock','low_stock') AND is_active = 1
              ORDER BY FIELD(stock_status,'out_of_stock','low_stock'), name LIMIT 8"),
    ], 'admin/layout');
}

// ---------------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------------

function admin_product_list(): void
{
    $filters = [
        'q'            => trim((string) ($_GET['q'] ?? '')),
        'category_id'  => !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null,
        'availability' => !empty($_GET['availability']) ? [(string) $_GET['availability']] : [],
        'sort'         => (string) ($_GET['sort'] ?? 'newest'),
    ];
    $result = ProductRepository::adminList($filters, max(1, (int) ($_GET['page'] ?? 1)));

    view('admin/products_list', [
        'title'      => 'Products',
        'result'     => $result,
        'filters'    => $filters,
        'categories' => CategoryRepository::all(),
    ], 'admin/layout');
}

function admin_product_form(?int $id): void
{
    $product = null;
    $images  = [];
    $specs   = [];

    if ($id) {
        $product = ProductRepository::find($id);
        if (!$product) {
            not_found('Product not found.');
        }
        $images = ProductRepository::images($id);
        $specs  = Database::all(
            'SELECT * FROM product_specs WHERE product_id = ? ORDER BY sort_order ASC, id ASC', [$id]);
    }

    view('admin/product_form', [
        'title'      => $product ? 'Edit product' : 'New product',
        'product'    => $product,
        'images'     => $images,
        'specs'      => $specs,
        'categories' => CategoryRepository::all(),
        'origins'    => OriginRepository::options(),
    ], 'admin/layout');
}

/** Validate the posted product form. Returns [cleanData, errors]. */
function admin_product_validate(array $in, ?int $id): array
{
    $errors = [];
    $data = [
        'name'              => trim((string) ($in['name'] ?? '')),
        'sku'               => trim((string) ($in['sku'] ?? '')),
        'category_id'       => (string) ($in['category_id'] ?? ''),
        'origin_id'         => (string) ($in['origin_id'] ?? ''),
        'short_description' => trim((string) ($in['short_description'] ?? '')),
        'description'       => trim((string) ($in['description'] ?? '')),
        'stock_status'      => (string) ($in['stock_status'] ?? 'made_to_order'),
        'stock_qty'         => trim((string) ($in['stock_qty'] ?? '')),
        'brand'             => trim((string) ($in['brand'] ?? '')),
        'weight_grams'      => trim((string) ($in['weight_grams'] ?? '')),
        'is_active'         => !empty($in['is_active']),
        'is_featured'       => !empty($in['is_featured']),
        'sort_order'        => (int) ($in['sort_order'] ?? 0),
    ];

    if ($data['name'] === '') {
        $errors['name'] = 'A product name is required.';
    } elseif (mb_strlen($data['name']) > 200) {
        $errors['name'] = 'Name is too long (200 characters max).';
    }

    // There is no price on this form. Internal pricing is entered on the price
    // sheet under Pricing, which writes to product_pricing.
    if ($data['stock_qty'] !== '' && !ctype_digit($data['stock_qty'])) {
        $errors['stock_qty'] = 'Stock quantity must be a whole number.';
    }
    if ($data['weight_grams'] !== '' && !ctype_digit($data['weight_grams'])) {
        $errors['weight_grams'] = 'Weight must be a whole number of grams.';
    }
    if (!in_array($data['stock_status'], stock_statuses(), true)) {
        $errors['stock_status'] = 'Pick a valid availability.';
    }
    if ($data['category_id'] !== '' && !CategoryRepository::find((int) $data['category_id'])) {
        $errors['category_id'] = 'That category no longer exists.';
    }
    if ($data['origin_id'] !== '' && !OriginRepository::find((int) $data['origin_id'])) {
        $errors['origin_id'] = 'That origin no longer exists.';
    }
    if ($data['sku'] !== '') {
        $clash = Database::one('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1',
            [$data['sku'], $id ?? 0]);
        if ($clash) {
            $errors['sku'] = 'Another product already uses that SKU.';
        }
    }
    if (mb_strlen($data['short_description']) > 300) {
        $errors['short_description'] = 'Short description is limited to 300 characters.';
    }

    // Slug: use what was typed, otherwise derive it from the name.
    $slugInput = trim((string) ($in['slug'] ?? ''));
    $data['slug'] = unique_slug('products',
        slugify($slugInput !== '' ? $slugInput : $data['name']), $id);

    return [$data, $errors];
}

function admin_product_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/products');
    }
    csrf_check();

    $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
    if ($id && !ProductRepository::find($id)) {
        not_found('Product not found.');
    }

    [$data, $errors] = admin_product_validate($_POST, $id);

    if ($errors) {
        set_old($_POST);
        $_SESSION['errors'] = $errors;
        flash('error', 'Please fix the highlighted fields.');
        redirect($id ? "admin/products/edit/{$id}" : 'admin/products/new');
    }

    Database::begin();
    try {
        $id = ProductRepository::save($id, $data);

        // Specs arrive as parallel arrays from the repeatable form rows.
        $specs = [];
        $names = $_POST['spec_name'] ?? [];
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                $specs[] = [
                    'spec_group' => $_POST['spec_group'][$i] ?? '',
                    'spec_name'  => $name,
                    'spec_value' => $_POST['spec_value'][$i] ?? '',
                ];
            }
        }
        ProductRepository::replaceSpecs($id, $specs);

        // Alt text edits on existing images.
        if (!empty($_POST['image_alt']) && is_array($_POST['image_alt'])) {
            foreach ($_POST['image_alt'] as $imgId => $alt) {
                Database::run(
                    'UPDATE product_images SET alt_text = ? WHERE id = ? AND product_id = ?',
                    [mb_substr(trim((string) $alt), 0, 200) ?: null, (int) $imgId, $id]);
            }
        }
        Database::commit();
    } catch (Throwable $ex) {
        Database::rollback();
        throw $ex;
    }

    // Images are handled after the commit so a failed upload never rolls back
    // a perfectly good product edit - the admin just sees which file failed.
    $uploadNotes = admin_handle_uploads($id);
    ProductRepository::ensurePrimaryImage($id);

    clear_old();
    unset($_SESSION['errors']);
    flash('success', 'Product saved.');
    foreach ($uploadNotes as $note) {
        flash('error', $note);
    }
    redirect("admin/products/edit/{$id}");
}

/** Un-bundles the multi-file input and stores each image. */
function admin_handle_uploads(int $productId): array
{
    $problems = [];
    if (empty($_FILES['images']) || !is_array($_FILES['images']['name'])) {
        return $problems;
    }

    $count = count($_FILES['images']['name']);
    $next  = (int) Database::scalar(
        'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ?',
        [$productId]);

    for ($i = 0; $i < $count; $i++) {
        if ((int) $_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $file = [
            'name'     => $_FILES['images']['name'][$i],
            'type'     => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'error'    => $_FILES['images']['error'][$i],
            'size'     => $_FILES['images']['size'][$i],
        ];
        try {
            $path = ImageUploader::store($file);
            Database::run(
                'INSERT INTO product_images (product_id, file_path, alt_text, is_primary, sort_order)
                 VALUES (?, ?, NULL, 0, ?)',
                [$productId, $path, $next++]);
        } catch (RuntimeException $ex) {
            // Flash messages are escaped where they are printed, so the raw
            // filename is safe to put in here.
            $problems[] = '"' . (string) $file['name'] . '": ' . $ex->getMessage();
        }
    }
    return $problems;
}

/** /admin/products/image/{primary|delete} - POST only. */
function admin_product_image(string $op): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/products');
    }
    csrf_check();

    $imageId   = (int) ($_POST['image_id'] ?? 0);
    $productId = (int) ($_POST['product_id'] ?? 0);
    $image = Database::one('SELECT * FROM product_images WHERE id = ? AND product_id = ?',
        [$imageId, $productId]);

    if (!$image) {
        flash('error', 'That image no longer exists.');
        redirect("admin/products/edit/{$productId}");
    }

    if ($op === 'primary') {
        ProductRepository::setPrimaryImage($productId, $imageId);
        flash('success', 'Main image updated.');
    } elseif ($op === 'delete') {
        Database::run('DELETE FROM product_images WHERE id = ?', [$imageId]);
        ImageUploader::delete($image['file_path']);
        ProductRepository::ensurePrimaryImage($productId);
        flash('success', 'Image removed.');
    }
    redirect("admin/products/edit/{$productId}");
}

function admin_product_delete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/products');
    }
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $product = ProductRepository::find($id);
    if ($product) {
        ProductRepository::delete($id);
        flash('success', 'Deleted "' . $product['name'] . '".');
    }
    redirect('admin/products');
}

// ---------------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------------

function admin_category_list(): void
{
    view('admin/categories_list', [
        'title'      => 'Categories',
        'categories' => CategoryRepository::all(),
    ], 'admin/layout');
}

function admin_category_form(?int $id): void
{
    $category = null;
    if ($id) {
        $category = CategoryRepository::find($id);
        if (!$category) {
            not_found('Category not found.');
        }
    }
    view('admin/category_form', [
        'title'      => $category ? 'Edit category' : 'New category',
        'category'   => $category,
        'categories' => CategoryRepository::all(),
    ], 'admin/layout');
}

function admin_category_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/categories');
    }
    csrf_check();

    $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
    $data = [
        'name'          => trim((string) ($_POST['name'] ?? '')),
        'description'   => trim((string) ($_POST['description'] ?? '')),
        'spec_template' => trim((string) ($_POST['spec_template'] ?? '')),
        'parent_id'     => $_POST['parent_id'] ?? '',
        'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
        'is_active'     => !empty($_POST['is_active']),
    ];

    $errors = [];
    if ($data['name'] === '') {
        $errors['name'] = 'A category name is required.';
    }
    if ($data['parent_id'] !== '' && !CategoryRepository::find((int) $data['parent_id'])) {
        $errors['parent_id'] = 'That parent category does not exist.';
    }

    if ($errors) {
        set_old($_POST);
        $_SESSION['errors'] = $errors;
        flash('error', 'Please fix the highlighted fields.');
        redirect($id ? "admin/categories/edit/{$id}" : 'admin/categories/new');
    }

    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $data['slug'] = unique_slug('categories',
        slugify($slugInput !== '' ? $slugInput : $data['name']), $id);

    CategoryRepository::save($id, $data);
    clear_old();
    unset($_SESSION['errors']);
    flash('success', 'Category saved.');
    redirect('admin/categories');
}

function admin_category_delete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/categories');
    }
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $cat = CategoryRepository::find($id);
    if ($cat) {
        CategoryRepository::delete($id);
        flash('success', 'Deleted "' . $cat['name'] . '". Its products are now uncategorised.');
    }
    redirect('admin/categories');
}

// ---------------------------------------------------------------------------
// Origins
// ---------------------------------------------------------------------------

function admin_origin_list(): void
{
    view('admin/origins_list', [
        'title'       => 'Origins',
        'origins'     => OriginRepository::all(),
        'unsetCount'  => OriginRepository::unsetCount(),
    ], 'admin/layout');
}

function admin_origin_form(?int $id): void
{
    $origin = null;
    if ($id) {
        $origin = OriginRepository::find($id);
        if (!$origin) {
            not_found('Origin not found.');
        }
    }
    view('admin/origin_form', [
        'title'  => $origin ? 'Edit origin' : 'New origin',
        'origin' => $origin,
    ], 'admin/layout');
}

function admin_origin_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/origins');
    }
    csrf_check();

    $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
    $data = [
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'code'       => trim((string) ($_POST['code'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active'  => !empty($_POST['is_active']),
    ];

    $errors = [];
    if ($data['name'] === '') {
        $errors['name'] = 'An origin name is required.';
    } elseif (mb_strlen($data['name']) > 120) {
        $errors['name'] = 'Name is too long (120 characters max).';
    }
    if ($data['code'] !== '' && !preg_match('/^[A-Za-z]{2,8}$/', $data['code'])) {
        $errors['code'] = 'Use letters only, e.g. CA for Canada. Leave blank if unsure.';
    }

    if ($errors) {
        set_old($_POST);
        $_SESSION['errors'] = $errors;
        flash('error', 'Please fix the highlighted fields.');
        redirect($id ? "admin/origins/edit/{$id}" : 'admin/origins/new');
    }

    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $data['slug'] = unique_slug('origins',
        slugify($slugInput !== '' ? $slugInput : $data['name']), $id);

    OriginRepository::save($id, $data);
    clear_old();
    unset($_SESSION['errors']);
    flash('success', 'Origin saved.');
    redirect('admin/origins');
}

function admin_origin_delete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/origins');
    }
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $origin = OriginRepository::find($id);
    if ($origin) {
        OriginRepository::delete($id);
        flash('success', 'Deleted "' . $origin['name'] . '". Its products now have no origin set.');
    }
    redirect('admin/origins');
}

// ---------------------------------------------------------------------------
// Settings and password
// ---------------------------------------------------------------------------

function admin_settings(): void
{
    $keys = ['site_name', 'site_tagline', 'currency_code', 'currency_symbol',
             'per_page', 'price_request_label', 'contact_email', 'contact_phone',
             'enquiry_notify_email', 'enquiry_intro'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        foreach ($keys as $k) {
            $val = trim((string) ($_POST[$k] ?? ''));
            if ($k === 'per_page') {
                $val = (string) max(4, min(60, (int) $val ?: 12));
            }
            // Blanking this would leave unpriced products showing nothing
            // at all where a price belongs.
            if ($k === 'price_request_label' && $val === '') {
                $val = 'Price on request';
            }
            // A typo here silently stops the notifications rather than
            // erroring, so it is rejected at the point it is entered.
            if ($k === 'enquiry_notify_email' && $val !== ''
                && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'That notification email address is not valid, so it was not saved. '
                    . 'Enquiries still appear in the admin panel.');
                continue;
            }
            Database::run(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$k, $val]);
        }
        $GLOBALS['settings'] = load_settings();
        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }

    view('admin/settings', ['title' => 'Settings', 'keys' => $keys], 'admin/layout');
}

function admin_password(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $row = Database::one('SELECT password_hash FROM admin_users WHERE id = ?',
            [$_SESSION['admin_id']]);

        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('error', 'Your current password is not correct.');
        } elseif (strlen($new) < 10) {
            flash('error', 'Choose a new password of at least 10 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'The two new passwords do not match.');
        } else {
            Database::run('UPDATE admin_users SET password_hash = ? WHERE id = ?',
                [password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            flash('success', 'Password changed.');
        }
        redirect('admin/password');
    }

    view('admin/password', ['title' => 'Change password'], 'admin/layout');
}

// ---------------------------------------------------------------------------
// Internal price sheet
//
// This is the only part of the application that reads or writes
// product_pricing. It sits behind Auth::requireLogin() like every other admin
// route, and nothing on the public side links to it or knows it exists.
// ---------------------------------------------------------------------------

function admin_pricing_filters(): array
{
    $ids = [];
    // "Open in the price sheet" from an enquiry passes the product ids along,
    // so a quote can be built from exactly the lines the buyer asked about.
    if (!empty($_GET['ids'])) {
        $ids = array_slice(array_filter(array_map('intval',
            explode(',', (string) $_GET['ids']))), 0, 200);
    }

    return [
        'category_id' => (int) ($_GET['category_id'] ?? 0),
        'origin_id'   => ($_GET['origin_id'] ?? '') === 'none'
                            ? 'none' : (int) ($_GET['origin_id'] ?? 0),
        'q'           => trim((string) ($_GET['q'] ?? '')),
        'priced'      => (string) ($_GET['priced'] ?? ''),
        'expiring'    => !empty($_GET['expiring']),
        'ids'         => $ids,
    ];
}

function admin_pricing_sheet(): void
{
    $filters = admin_pricing_filters();

    view('admin/pricing_sheet', [
        'title'      => 'Internal price sheet',
        'rows'       => PricingRepository::sheet($filters),
        'filters'    => $filters,
        'stats'      => PricingRepository::stats(),
        'categories' => CategoryRepository::all(),
        'origins'    => OriginRepository::options(),
        'incoterms'  => PricingRepository::INCOTERMS,
    ], 'admin/layout');
}

/**
 * Save the whole visible sheet in one POST.
 *
 * Only the rows actually rendered are submitted, and each carries its product
 * id, so saving a filtered view touches those products and leaves the rest
 * alone. A row cleared of every value deletes its pricing record rather than
 * storing a set of NULLs - see PricingRepository::save().
 */
function admin_pricing_save(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/pricing');
    }
    csrf_check();

    $rows = $_POST['row'] ?? [];
    if (!is_array($rows)) {
        $rows = [];
    }

    $saved  = 0;
    $errors = [];

    foreach ($rows as $productId => $data) {
        $productId = (int) $productId;
        if ($productId <= 0 || !is_array($data)) {
            continue;
        }

        $rowErrors = PricingRepository::validate($data);
        if ($rowErrors) {
            // Name the product rather than the row number - by the time this is
            // read the sheet has been re-sorted and "row 14" means nothing.
            $name = Database::scalar('SELECT name FROM products WHERE id = ?', [$productId])
                    ?: ('product #' . $productId);
            foreach ($rowErrors as $msg) {
                $errors[] = $name . ': ' . $msg;
            }
            continue;
        }

        PricingRepository::save($productId, $data);
        $saved++;
    }

    if ($errors) {
        // The valid rows are already written, so say exactly that instead of
        // implying the whole save was rolled back.
        flash('error', 'Saved ' . $saved . ' row' . ($saved === 1 ? '' : 's') . '. '
            . count($errors) . ' had a problem: ' . implode(' | ', array_slice($errors, 0, 5))
            . (count($errors) > 5 ? ' (and ' . (count($errors) - 5) . ' more)' : ''));
    } else {
        flash('success', 'Price sheet saved - ' . $saved . ' row'
            . ($saved === 1 ? '' : 's') . ' updated.');
    }

    // Keep whatever filter the sheet was showing.
    $keep = array_intersect_key($_POST, array_flip(
        ['category_id', 'origin_id', 'q', 'priced', 'expiring', 'ids']));
    redirect('admin/pricing' . ($keep ? '?' . http_build_query(array_filter($keep, 'strlen')) : ''));
}

/**
 * Download the sheet as CSV.
 *
 * CSV rather than a real .xlsx because Excel, Numbers and Sheets all open it
 * natively and it needs no library on the host - this project has no Composer
 * dependencies and adding one for a download would be a poor trade.
 */
function admin_pricing_export(): void
{
    $rows = PricingRepository::sheet(admin_pricing_filters());

    $filename = 'ds-price-sheet-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');

    // Excel reads a bare UTF-8 CSV as the system codepage and turns accented
    // names into mojibake. The BOM is what makes it pick UTF-8.
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['SKU', 'Product', 'Category', 'Origin', 'Availability',
                   'Price', 'Currency', 'Per', 'MOQ', 'Incoterm',
                   'Valid until', 'Supplier', 'Notes', 'Updated', 'Listed']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['sku'],
            $r['name'],
            $r['category_name'],
            $r['origin_name'],
            stock_label($r['stock_status']),
            $r['price'],
            $r['price'] === null ? '' : $r['currency'],
            $r['price_unit'],
            $r['moq'],
            $r['incoterm'],
            $r['valid_until'],
            $r['supplier'],
            $r['notes'],
            $r['updated_at'],
            (int) $r['is_active'] === 1 ? 'yes' : 'no',
        ]);
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------------
// Enquiries
// ---------------------------------------------------------------------------

function admin_enquiry_list(): void
{
    $filters = [
        'status' => (string) ($_GET['status'] ?? ''),
        'q'      => trim((string) ($_GET['q'] ?? '')),
    ];
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $result = EnquiryRepository::listing($filters, $page);

    view('admin/enquiries_list', [
        'title'   => 'Enquiries',
        'result'  => $result,
        'filters' => $filters,
    ], 'admin/layout');
}

function admin_enquiry_show(int $id): void
{
    $enquiry = EnquiryRepository::find($id);
    if (!$enquiry) {
        not_found('That enquiry does not exist.');
    }

    $items = EnquiryRepository::items($id);

    view('admin/enquiry_show', [
        'title'    => 'Enquiry ' . $enquiry['reference'],
        'enquiry'  => $enquiry,
        'items'    => $items,
        // The internal figures for exactly the products asked about, so the
        // quote can be put together without leaving the page.
        'pricing'  => PricingRepository::forProducts(
                          array_column($items, 'product_id')),
        'statuses' => EnquiryRepository::STATUSES,
    ], 'admin/layout');
}

function admin_enquiry_update(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/enquiries');
    }
    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    if (!$id || !EnquiryRepository::find($id)) {
        not_found('That enquiry does not exist.');
    }

    EnquiryRepository::updateStatus($id,
        (string) ($_POST['status'] ?? 'new'),
        (string) ($_POST['admin_notes'] ?? ''));

    flash('success', 'Enquiry updated.');
    redirect('admin/enquiries/' . $id);
}

function admin_enquiry_delete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin/enquiries');
    }
    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        EnquiryRepository::delete($id);
        flash('success', 'Enquiry deleted.');
    }
    redirect('admin/enquiries');
}

/**
 * One enquiry as a CSV quote sheet, with the internal figures alongside each
 * line. This is an internal working document, not something to forward to the
 * buyer as-is.
 */
function admin_enquiry_export(int $id): void
{
    $enquiry = EnquiryRepository::find($id);
    if (!$enquiry) {
        not_found('That enquiry does not exist.');
    }

    $items   = EnquiryRepository::items($id);
    $pricing = PricingRepository::forProducts(array_column($items, 'product_id'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $enquiry['reference'] . '.csv"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Enquiry', $enquiry['reference']]);
    fputcsv($out, ['Received', $enquiry['created_at']]);
    fputcsv($out, ['Company', $enquiry['company']]);
    fputcsv($out, ['Contact', $enquiry['contact_name']]);
    fputcsv($out, ['Email', $enquiry['email']]);
    fputcsv($out, ['Phone', $enquiry['phone']]);
    fputcsv($out, ['Country', $enquiry['country']]);
    fputcsv($out, ['Destination', $enquiry['destination']]);
    fputcsv($out, ['Incoterm', $enquiry['incoterm']]);
    fputcsv($out, ['Message', $enquiry['message']]);
    fputcsv($out, []);
    fputcsv($out, ['SKU', 'Product', 'Quantity wanted', 'Buyer notes',
                   'Internal price', 'Currency', 'Per', 'MOQ', 'Incoterm',
                   'Valid until', 'Supplier', 'Internal notes']);

    foreach ($items as $it) {
        $p = $pricing[(int) $it['product_id']] ?? null;
        fputcsv($out, [
            $it['product_sku'],
            $it['product_name'],
            $it['quantity'],
            $it['notes'],
            $p['price']       ?? '',
            ($p && $p['price'] !== null) ? $p['currency'] : '',
            $p['price_unit']  ?? '',
            $p['moq']         ?? '',
            $p['incoterm']    ?? '',
            $p['valid_until'] ?? '',
            $p['supplier']    ?? '',
            $p['notes']       ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------------
// Bulk image upload
//
// 197 products is far too many to attach photos to one form at a time. This
// takes a folder of files and matches each one to a product by its filename.
// ---------------------------------------------------------------------------

/**
 * Reduce a filename to something matchable: drop the extension, drop a
 * trailing "-1"/"(2)" copy marker, and flatten punctuation to single hyphens.
 * "Fresh Young Coconut (2).jpg" and "fresh-young-coconut-2.JPG" both land on
 * "fresh-young-coconut".
 */
function bulk_image_key(string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = preg_replace('/[\s_]*\(\d+\)\s*$/', '', $base);     // "name (2)"
    $base = preg_replace('/[-_\s]+\d+$/', '', $base);           // "name-2", "name 2"
    return slugify($base);
}

function admin_bulk_images(): void
{
    $report = $_SESSION['bulk_image_report'] ?? null;
    unset($_SESSION['bulk_image_report']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $report = admin_bulk_images_process();
        $_SESSION['bulk_image_report'] = $report;
        redirect('admin/products/bulk-images');
    }

    view('admin/bulk_images', [
        'title'  => 'Bulk image upload',
        'report' => $report,
        // Shown so it is obvious what the filenames need to look like.
        'sample' => Database::all(
            'SELECT p.name, p.slug, p.sku FROM products p
              WHERE NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)
              ORDER BY p.name LIMIT 12'),
        'missing' => (int) Database::scalar(
            'SELECT COUNT(*) FROM products p
              WHERE NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)'),
    ], 'admin/layout');
}

/** Returns ['matched' => [...], 'skipped' => [...], 'failed' => [...]]. */
function admin_bulk_images_process(): array
{
    $report = ['matched' => [], 'skipped' => [], 'failed' => []];

    $files = $_FILES['images'] ?? null;
    if (!$files || !is_array($files['name'])) {
        $report['failed'][] = ['file' => '-', 'why' => 'No files were received.'];
        return $report;
    }

    // Build the lookup once. Three ways in, most specific first: exact SKU,
    // then the product slug, then the slugified name. A filename that matches
    // more than one product is reported rather than guessed at.
    $products = Database::all('SELECT id, name, slug, sku FROM products');
    $bySku = $byKey = [];
    foreach ($products as $p) {
        if ($p['sku']) {
            $bySku[strtolower($p['sku'])] = $p;
        }
        foreach ([$p['slug'], slugify($p['name'])] as $k) {
            if ($k === '') {
                continue;
            }
            // A key that two products share is ambiguous, so it is marked
            // rather than silently resolving to whichever was read first.
            $byKey[$k] = isset($byKey[$k]) && $byKey[$k]['id'] !== $p['id'] ? false : $p;
        }
    }

    $replace = !empty($_POST['replace_existing']);
    $count   = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $name = (string) $files['name'][$i];
        if ($name === '' || $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $key     = bulk_image_key($name);
        $skuKey  = strtolower(pathinfo($name, PATHINFO_FILENAME));
        $product = $bySku[$skuKey] ?? ($byKey[$key] ?? null);

        if ($product === false) {
            $report['skipped'][] = ['file' => $name,
                'why' => 'More than one product matches "' . $key . '" - rename it to the product slug.'];
            continue;
        }
        if (!$product) {
            $report['skipped'][] = ['file' => $name,
                'why' => 'No product matches "' . $key . '".'];
            continue;
        }

        $existing = (int) Database::scalar(
            'SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$product['id']]);
        if ($existing && !$replace) {
            $report['skipped'][] = ['file' => $name,
                'why' => $product['name'] . ' already has ' . $existing . ' image'
                       . ($existing === 1 ? '' : 's') . '.'];
            continue;
        }

        try {
            // Same hardened path as the single-product upload: MIME sniffed
            // from the bytes, re-encoded through GD, filename generated here.
            $stored = ImageUploader::store([
                'name'     => $name,
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ]);
        } catch (Throwable $e) {
            $report['failed'][] = ['file' => $name, 'why' => $e->getMessage()];
            continue;
        }

        if ($existing && $replace) {
            foreach (ProductRepository::images((int) $product['id']) as $old) {
                ImageUploader::delete($old['file_path']);
            }
            Database::run('DELETE FROM product_images WHERE product_id = ?', [$product['id']]);
            $existing = 0;
        }

        Database::run(
            'INSERT INTO product_images (product_id, file_path, alt_text, is_primary, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$product['id'], $stored, $product['name'], $existing === 0 ? 1 : 0, $existing]);

        $report['matched'][] = ['file' => $name, 'product' => $product['name'],
                                'id' => (int) $product['id']];
    }

    return $report;
}
