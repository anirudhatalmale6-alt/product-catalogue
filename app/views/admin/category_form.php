<?php
$errors = $_SESSION['errors'] ?? [];
$isEdit = $category !== null;
$fv = function (string $k, $d = '') use ($category) {
    if (isset($_SESSION['old'][$k])) { return $_SESSION['old'][$k]; }
    if ($category !== null && isset($category[$k])) { return $category[$k]; }
    return $d;
};
$activeOn = isset($_SESSION['old'])
    ? !empty($_SESSION['old']['is_active'])
    : ($isEdit ? (int) $category['is_active'] === 1 : true);
?>

<div class="adm-head">
  <div>
    <nav class="adm-crumbs"><a href="<?= url('admin/categories') ?>">&larr; Categories</a></nav>
    <h1><?= $isEdit ? 'Edit category' : 'New category' ?></h1>
  </div>
</div>

<form method="post" action="<?= url('admin/categories/save') ?>" class="adm-form narrow">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><?php endif; ?>

  <section class="panel">
    <div class="panel-body">
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">Category name <span class="req">*</span></label>
        <input id="name" name="name" type="text" required maxlength="120" value="<?= e($fv('name')) ?>" autofocus>
        <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e($fv('description')) ?></textarea>
        <p class="hint">Shown under the heading on the category page.</p>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="slug">URL slug</label>
          <input id="slug" name="slug" type="text" maxlength="140" value="<?= e($fv('slug')) ?>"
                 placeholder="Built from the name if blank">
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input id="sort_order" name="sort_order" type="number" step="1" value="<?= e((string) $fv('sort_order', '0')) ?>">
        </div>
      </div>

      <div class="field <?= isset($errors['parent_id']) ? 'has-error' : '' ?>">
        <label for="parent_id">Parent category</label>
        <select id="parent_id" name="parent_id">
          <option value="">— None (top level) —</option>
          <?php foreach ($categories as $c): ?>
            <?php if ($isEdit && (int) $c['id'] === (int) $category['id']) { continue; } ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) $fv('parent_id') === (string) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Optional. Filtering a parent also shows products in its children.</p>
      </div>

      <div class="field">
        <label for="spec_template">Standard specification headings</label>
        <textarea id="spec_template" name="spec_template" rows="7"
                  placeholder="Pack format&#10;Pack sizes&#10;Minimum order&#10;Shelf life&#10;Storage&#10;Certifications"><?= e($fv('spec_template')) ?></textarea>
        <p class="hint">
          One heading per line. When you create a product in this category the
          specification table starts with these rows, empty and ready to fill in &mdash;
          so the same headings get used across the whole category instead of being
          retyped slightly differently each time. Nothing is saved to a product
          until you type a value, and you can always add, rename or delete rows on
          the product itself.
          <br>To put a heading in a section, write it as <code>Group|Heading</code>,
          e.g. <code>Logistics|Minimum order</code>.
        </p>
      </div>

      <label class="switch">
        <input type="checkbox" name="is_active" value="1" <?= $activeOn ? 'checked' : '' ?>>
        <span>Show this category on the public site</span>
      </label>
    </div>
  </section>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save category</button>
    <a class="btn btn-ghost" href="<?= url('admin/categories') ?>">Cancel</a>
  </div>
</form>

<?php clear_old(); unset($_SESSION['errors']); ?>
