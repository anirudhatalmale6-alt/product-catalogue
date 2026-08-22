<?php
$labels = [
    'site_name'       => ['Site name', 'Shown in the header, the footer and page titles.'],
    'site_tagline'    => ['Tagline', 'A short line under the site name.'],
    'currency_code'   => ['Currency code', 'e.g. CAD, USD, EUR. Printed in the footer.'],
    'currency_symbol' => ['Currency symbol', 'Put in front of every price, e.g. $ or £.'],
    'per_page'        => ['Products per page', 'Between 4 and 60.'],
    'contact_email'   => ['Contact email', 'Optional. Shown on product pages so people can enquire.'],
    'contact_phone'   => ['Contact phone', 'Optional.'],
];
?>

<div class="adm-head">
  <div>
    <h1>Settings</h1>
    <p class="adm-sub">Site-wide values. They take effect immediately.</p>
  </div>
</div>

<form method="post" action="<?= url('admin/settings') ?>" class="adm-form narrow">
  <?= csrf_field() ?>
  <section class="panel">
    <div class="panel-body">
      <?php foreach ($keys as $k): ?>
        <div class="field">
          <label for="<?= $k ?>"><?= e($labels[$k][0] ?? $k) ?></label>
          <input id="<?= $k ?>" name="<?= $k ?>" type="<?= $k === 'per_page' ? 'number' : 'text' ?>"
                 <?= $k === 'per_page' ? 'min="4" max="60" step="1"' : '' ?>
                 value="<?= e((string) setting($k, '')) ?>">
          <?php if (!empty($labels[$k][1])): ?><p class="hint"><?= e($labels[$k][1]) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save settings</button>
  </div>
</form>
