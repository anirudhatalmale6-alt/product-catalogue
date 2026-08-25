<?php
$errors = $_SESSION['errors'] ?? [];
$isEdit = $origin !== null;
$fv = function (string $k, $d = '') use ($origin) {
    if (isset($_SESSION['old'][$k])) { return $_SESSION['old'][$k]; }
    if ($origin !== null && isset($origin[$k])) { return $origin[$k]; }
    return $d;
};
$activeOn = isset($_SESSION['old'])
    ? !empty($_SESSION['old']['is_active'])
    : ($isEdit ? (int) $origin['is_active'] === 1 : true);
?>

<div class="adm-head">
  <div>
    <nav class="adm-crumbs"><a href="<?= url('admin/origins') ?>">&larr; Origins</a></nav>
    <h1><?= $isEdit ? 'Edit origin' : 'New origin' ?></h1>
  </div>
</div>

<form method="post" action="<?= url('admin/origins/save') ?>" class="adm-form narrow">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $origin['id'] ?>"><?php endif; ?>

  <section class="panel">
    <div class="panel-body">
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">Origin name <span class="req">*</span></label>
        <input id="name" name="name" type="text" required maxlength="120" value="<?= e($fv('name')) ?>" autofocus>
        <p class="hint">Usually a country, e.g. Thailand. Whatever you type here is what buyers see.</p>
        <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field <?= isset($errors['code']) ? 'has-error' : '' ?>">
          <label for="code">Country code</label>
          <input id="code" name="code" type="text" maxlength="8" value="<?= e($fv('code')) ?>"
                 placeholder="TH">
          <p class="hint">Optional two-letter code. Not shown on the site &mdash; it is there
             for exports and paperwork later.</p>
          <?php if (isset($errors['code'])): ?><p class="err"><?= e($errors['code']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="sort_order">Sort order</label>
          <input id="sort_order" name="sort_order" type="number" step="1" value="<?= e((string) $fv('sort_order', '0')) ?>">
          <p class="hint">Lower comes first. Leave gaps (10, 20, 30) so a new one can be slotted in.</p>
        </div>
      </div>

      <div class="field">
        <label for="slug">URL slug</label>
        <input id="slug" name="slug" type="text" maxlength="140" value="<?= e($fv('slug')) ?>"
               placeholder="Built from the name if blank">
        <p class="hint">The address of this origin's page, e.g. /origin/thailand.</p>
      </div>

      <label class="switch">
        <input type="checkbox" name="is_active" value="1" <?= $activeOn ? 'checked' : '' ?>>
        <span>Show this origin on the public site</span>
      </label>
    </div>
  </section>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save origin</button>
    <a class="btn btn-ghost" href="<?= url('admin/origins') ?>">Cancel</a>
  </div>
</form>

<?php clear_old(); unset($_SESSION['errors']); ?>
