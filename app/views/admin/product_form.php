<?php
/** @var ?array $product @var array $images @var array $specs @var array $categories */
$errors = $_SESSION['errors'] ?? [];
$isEdit = $product !== null;

/** Field value: what was typed last (after a validation error), else the row. */
$fv = function (string $key, $default = '') use ($product) {
    if (isset($_SESSION['old'][$key])) {
        return $_SESSION['old'][$key];
    }
    if ($product !== null && array_key_exists($key, $product) && $product[$key] !== null) {
        return $product[$key];
    }
    return $default;
};
$checked = function (string $key, bool $defaultOn) use ($product) {
    if (isset($_SESSION['old'])) {
        return !empty($_SESSION['old'][$key]);
    }
    if ($product !== null) {
        return (int) $product[$key] === 1;
    }
    return $defaultOn;
};
// Spec rows: repopulate from the failed POST if there was one.
$specRows = $specs;
if (isset($_SESSION['old']['spec_name']) && is_array($_SESSION['old']['spec_name'])) {
    $specRows = [];
    foreach ($_SESSION['old']['spec_name'] as $i => $n) {
        $specRows[] = [
            'spec_group' => $_SESSION['old']['spec_group'][$i] ?? '',
            'spec_name'  => $n,
            'spec_value' => $_SESSION['old']['spec_value'][$i] ?? '',
        ];
    }
}
?>

<div class="adm-head">
  <div>
    <nav class="adm-crumbs"><a href="<?= url('admin/products') ?>">&larr; Products</a></nav>
    <h1><?= $isEdit ? 'Edit product' : 'New product' ?></h1>
    <?php if ($isEdit): ?>
      <p class="adm-sub">
        Public link:
        <a href="<?= url('product/' . $product['slug']) ?>" target="_blank" rel="noopener">/product/<?= e($product['slug']) ?></a>
      </p>
    <?php endif; ?>
  </div>
  <div class="adm-head-actions">
    <button type="submit" form="product-form" class="btn btn-primary">Save product</button>
  </div>
</div>

<form id="product-form" method="post" action="<?= url('admin/products/save') ?>" enctype="multipart/form-data" class="adm-form">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><?php endif; ?>

  <div class="form-cols">
    <div class="form-main">

      <section class="panel">
        <div class="panel-head"><h2>Basics</h2></div>
        <div class="panel-body">

          <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Product name <span class="req">*</span></label>
            <input id="name" name="name" type="text" required maxlength="200" value="<?= e($fv('name')) ?>">
            <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?>
          </div>

          <div class="field-row">
            <div class="field <?= isset($errors['sku']) ? 'has-error' : '' ?>">
              <label for="sku">SKU / part number</label>
              <input id="sku" name="sku" type="text" maxlength="64" value="<?= e($fv('sku')) ?>">
              <?php if (isset($errors['sku'])): ?><p class="err"><?= e($errors['sku']) ?></p><?php endif; ?>
            </div>
            <div class="field">
              <label for="brand">Brand / manufacturer</label>
              <input id="brand" name="brand" type="text" maxlength="120" value="<?= e($fv('brand')) ?>">
            </div>
          </div>

          <div class="field <?= isset($errors['short_description']) ? 'has-error' : '' ?>">
            <label for="short_description">Short description</label>
            <input id="short_description" name="short_description" type="text" maxlength="300"
                   value="<?= e($fv('short_description')) ?>"
                   placeholder="One line shown on the catalogue cards">
            <p class="hint">Up to 300 characters. This is the line under the name on the grid.</p>
            <?php if (isset($errors['short_description'])): ?><p class="err"><?= e($errors['short_description']) ?></p><?php endif; ?>
          </div>

          <div class="field">
            <label for="description">Full description</label>
            <textarea id="description" name="description" rows="8"><?= e($fv('description')) ?></textarea>
            <p class="hint">Plain text. Line breaks are kept.</p>
          </div>

          <div class="field">
            <label for="slug">URL slug</label>
            <input id="slug" name="slug" type="text" maxlength="220" value="<?= e($fv('slug')) ?>"
                   placeholder="Leave blank to build it from the name">
            <p class="hint">The address will be /product/<span id="slug-preview"><?= e($fv('slug', 'your-product')) ?></span></p>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2>Technical specifications</h2>
          <button type="button" class="btn btn-sm" id="add-spec">+ Add row</button>
        </div>
        <div class="panel-body">
          <p class="hint">
            Free-form name/value pairs, so you can record anything: pack sizes, shelf life,
            minimum order, certifications, HS codes. Pick a category and the standard
            headings for it appear here, ready to fill in. The optional group heading
            splits long lists into sections on the product page.
          </p>
          <div class="spec-rows" id="spec-rows">
            <?php foreach ($specRows as $s): ?>
              <div class="spec-row">
                <input type="text" name="spec_group[]"  placeholder="Group (optional)" value="<?= e($s['spec_group'] ?? '') ?>" maxlength="80">
                <input type="text" name="spec_name[]"   placeholder="Name, e.g. Shelf life" value="<?= e($s['spec_name'] ?? '') ?>" maxlength="120">
                <input type="text" name="spec_value[]"  placeholder="Value, e.g. 18 months" value="<?= e($s['spec_value'] ?? '') ?>" maxlength="400">
                <button type="button" class="btn btn-sm btn-danger remove-spec" aria-label="Remove row">&times;</button>
              </div>
            <?php endforeach; ?>
          </div>
          <template id="spec-template">
            <div class="spec-row">
              <input type="text" name="spec_group[]"  placeholder="Group (optional)" maxlength="80">
              <input type="text" name="spec_name[]"   placeholder="Name, e.g. Shelf life" maxlength="120">
              <input type="text" name="spec_value[]"  placeholder="Value, e.g. 18 months" maxlength="400">
              <button type="button" class="btn btn-sm btn-danger remove-spec" aria-label="Remove row">&times;</button>
            </div>
          </template>
          <?php // Per-category heading lists, read by admin.js when the category
                // changes. Only offered while the spec table is still empty, so
                // choosing a category never disturbs rows already filled in. ?>
          <script type="application/json" id="spec-templates"><?=
            json_encode(array_column($categories, 'spec_template', 'id'),
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
          ?></script>
          <?php if (!$specRows): ?>
            <p class="panel-empty" id="spec-empty">No specifications yet &mdash; use &ldquo;Add row&rdquo;,
              or pick a category and the standard headings for it appear here.</p>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Add images</h2></div>
        <div class="panel-body">
          <div class="dropzone" id="dropzone">
            <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
            <label for="images">
              <strong>Choose images</strong> or drag them here<br>
              <span class="hint">JPG, PNG, GIF or WebP &middot; up to
                <?= round((int) config('uploads.max_bytes', 6291456) / 1048576, 1) ?> MB each &middot; several at once</span>
            </label>
          </div>
          <div class="preview-grid" id="preview-grid"></div>
          <?php if (!$isEdit): ?>
            <p class="hint">Images upload when you save the product.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <aside class="form-side">
      <section class="panel">
        <div class="panel-head"><h2>Visibility</h2></div>
        <div class="panel-body">
          <label class="switch">
            <input type="checkbox" name="is_active" value="1" <?= $checked('is_active', true) ? 'checked' : '' ?>>
            <span>Show on the public catalogue</span>
          </label>
          <label class="switch">
            <input type="checkbox" name="is_featured" value="1" <?= $checked('is_featured', false) ? 'checked' : '' ?>>
            <span>Feature this product</span>
          </label>
          <div class="field">
            <label for="sort_order">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" step="1" value="<?= e((string) $fv('sort_order', '0')) ?>">
            <p class="hint">Lower numbers appear first in &ldquo;Featured&rdquo; sorting.</p>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Category</h2></div>
        <div class="panel-body">
          <div class="field <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
            <label for="category_id" class="sr-only">Category</label>
            <select id="category_id" name="category_id">
              <option value="">— Uncategorised —</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (string) $fv('category_id') === (string) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><p class="err"><?= e($errors['category_id']) ?></p><?php endif; ?>
            <p class="hint"><a href="<?= url('admin/categories/new') ?>" target="_blank">Add a new category &nearr;</a></p>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Origin</h2></div>
        <div class="panel-body">
          <div class="field <?= isset($errors['origin_id']) ? 'has-error' : '' ?>">
            <label for="origin_id" class="sr-only">Origin</label>
            <select id="origin_id" name="origin_id">
              <option value="">— Not specified —</option>
              <?php foreach ($origins as $o): ?>
                <option value="<?= (int) $o['id'] ?>" <?= (string) $fv('origin_id') === (string) $o['id'] ? 'selected' : '' ?>>
                  <?= e($o['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['origin_id'])): ?><p class="err"><?= e($errors['origin_id']) ?></p><?php endif; ?>
            <p class="hint">Country of origin. Buyers can filter the whole catalogue by it.
               <a href="<?= url('admin/origins/new') ?>" target="_blank">Add one &nearr;</a></p>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Price</h2></div>
        <div class="panel-body">
          <div class="field <?= isset($errors['price']) ? 'has-error' : '' ?>">
            <label for="price">Regular price (<?= e(setting('currency_code', '')) ?>)</label>
            <?php // No `required` and no 0.00 default: blank is a real answer here
                  // and pre-filling a zero would publish the item as free. ?>
            <input id="price" name="price" type="number" min="0" step="0.01"
                   value="<?= e((string) $fv('price', '')) ?>" placeholder="Leave blank">
            <p class="hint">Leave blank and the item shows
               &ldquo;<?= e(price_request_label()) ?>&rdquo; instead of a figure.</p>
            <?php if (isset($errors['price'])): ?><p class="err"><?= e($errors['price']) ?></p><?php endif; ?>
          </div>
          <div class="field <?= isset($errors['sale_price']) ? 'has-error' : '' ?>">
            <label for="sale_price">Sale price</label>
            <input id="sale_price" name="sale_price" type="number" min="0" step="0.01" value="<?= e((string) $fv('sale_price', '')) ?>">
            <p class="hint">Leave blank if the item is not on sale.</p>
            <?php if (isset($errors['sale_price'])): ?><p class="err"><?= e($errors['sale_price']) ?></p><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Availability</h2></div>
        <div class="panel-body">
          <div class="field <?= isset($errors['stock_status']) ? 'has-error' : '' ?>">
            <label for="stock_status">Status</label>
            <select id="stock_status" name="stock_status">
              <?php foreach (stock_statuses() as $st): ?>
                <option value="<?= $st ?>" <?= (string) $fv('stock_status', 'made_to_order') === $st ? 'selected' : '' ?>>
                  <?= e(stock_label($st)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field <?= isset($errors['stock_qty']) ? 'has-error' : '' ?>">
            <label for="stock_qty">Quantity on hand</label>
            <input id="stock_qty" name="stock_qty" type="number" min="0" step="1" value="<?= e((string) $fv('stock_qty', '')) ?>">
            <p class="hint">Optional. Leave blank if you do not count units.</p>
            <?php if (isset($errors['stock_qty'])): ?><p class="err"><?= e($errors['stock_qty']) ?></p><?php endif; ?>
          </div>
          <div class="field <?= isset($errors['weight_grams']) ? 'has-error' : '' ?>">
            <label for="weight_grams">Weight (grams)</label>
            <input id="weight_grams" name="weight_grams" type="number" min="0" step="1" value="<?= e((string) $fv('weight_grams', '')) ?>">
            <?php if (isset($errors['weight_grams'])): ?><p class="err"><?= e($errors['weight_grams']) ?></p><?php endif; ?>
          </div>
        </div>
      </section>

      <div class="side-actions">
        <button type="submit" class="btn btn-primary btn-block">Save product</button>
        <a class="btn btn-ghost btn-block" href="<?= url('admin/products') ?>">Cancel</a>
      </div>
    </aside>
  </div>
</form>

<?php if ($isEdit && $images): ?>
<?php // Kept outside the main form: each image action is its own POST.
      // The alt-text inputs use form="product-form" so they still save with the product. ?>
<section class="panel">
  <div class="panel-head">
    <h2>Current images (<?= count($images) ?>)</h2>
    <span class="hint">The main image is the one shown on the catalogue grid.</span>
  </div>
  <div class="panel-body">
    <div class="image-grid">
      <?php foreach ($images as $img): ?>
        <div class="image-card <?= $img['is_primary'] ? 'is-primary' : '' ?>">
          <div class="image-thumb">
            <img src="<?= e(upload_url($img['file_path'])) ?>" alt="<?= e($img['alt_text'] ?: '') ?>" loading="lazy">
            <?php if ($img['is_primary']): ?><span class="badge badge-featured">Main</span><?php endif; ?>
          </div>
          <input class="alt-input" type="text" form="product-form"
                 name="image_alt[<?= (int) $img['id'] ?>]" maxlength="200"
                 value="<?= e($img['alt_text'] ?? '') ?>" placeholder="Alt text (for accessibility)">
          <div class="image-actions">
            <?php if (!$img['is_primary']): ?>
              <form method="post" action="<?= url('admin/products/image/primary') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button type="submit" class="btn btn-sm">Make main</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('admin/products/image/delete') ?>" class="inline"
                  data-confirm="Remove this image? The file is deleted from the server.">
              <?= csrf_field() ?>
              <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($isEdit): ?>
<section class="panel panel-danger">
  <div class="panel-head"><h2>Delete this product</h2></div>
  <div class="panel-body danger-body">
    <p>Removes the product, its specifications and its image files. This cannot be undone.</p>
    <form method="post" action="<?= url('admin/products/delete') ?>"
          data-confirm="Delete &quot;<?= e($product['name']) ?>&quot; permanently?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
      <button type="submit" class="btn btn-danger">Delete product</button>
    </form>
  </div>
</section>
<?php endif; ?>

<?php clear_old(); unset($_SESSION['errors']); ?>
