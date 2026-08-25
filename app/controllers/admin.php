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
            admin_product_list();
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
        // Two "still to fill in" counts, so an import that is only part-done
        // is visible on the dashboard instead of having to be gone looking for.
        'no_origin'  => OriginRepository::unsetCount(),
        'no_price'   => (int) Database::scalar(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND price IS NULL'),
    ];

    view('admin/dashboard', [
        'title'  => 'Dashboard',
        'stats'  => $stats,
        'recent' => Database::all(
            'SELECT p.id, p.name, p.slug, p.price, p.stock_status, p.is_active, p.updated_at,
                    c.name AS category_name
               FROM products p LEFT JOIN categories c ON c.id = p.category_id
              ORDER BY p.updated_at DESC LIMIT 8'),
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
        'price'             => trim((string) ($in['price'] ?? '')),
        'sale_price'        => trim((string) ($in['sale_price'] ?? '')),
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

    // An empty price is valid - it means "on request". Only a filled-in one
    // has to be a sensible number.
    if ($data['price'] !== '' && (!is_numeric($data['price']) || (float) $data['price'] < 0)) {
        $errors['price'] = 'Enter a price of 0 or more, or leave it blank to quote on request.';
    }
    if ($data['sale_price'] !== '') {
        if (!is_numeric($data['sale_price']) || (float) $data['sale_price'] < 0) {
            $errors['sale_price'] = 'Sale price must be a number.';
        } elseif ($data['price'] === '') {
            // Otherwise the product page has a sale price struck through
            // against nothing, and the card shows a discount off no figure.
            $errors['sale_price'] = 'A sale price needs a regular price to be reduced from.';
        } elseif (is_numeric($data['price']) && (float) $data['sale_price'] >= (float) $data['price']) {
            $errors['sale_price'] = 'Sale price should be lower than the regular price.';
        }
    }
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
             'per_page', 'price_request_label', 'contact_email', 'contact_phone'];

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
