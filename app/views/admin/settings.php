<?php
$labels = [
    'site_name'       => ['Site name', 'Shown in the header, the footer and page titles.'],
    'site_tagline'    => ['Tagline', 'A short line under the site name.'],
    'currency_code'   => ['Default currency code',
        'e.g. CAD, USD, EUR. Used as the default on new internal price sheet rows. It is never shown on the public site.'],
    'currency_symbol' => ['Currency symbol', 'Used on internal figures, e.g. $ or £.'],
    'per_page'        => ['Products per page', 'Between 4 and 60.'],
    'price_request_label' => ['Label shown instead of a price',
        'The catalogue shows no prices at all, so this appears on every product, e.g. "Price on request" or "Contact for a quote".'],
    'contact_email'   => ['Contact email', 'Optional. Shown on product pages so people can enquire.'],
    'contact_phone'   => ['Contact phone', 'Optional. Leave blank to keep it off the public site.'],
    'enquiry_notify_email' => ['Enquiry notification email',
        'Optional. A copy of every enquiry is emailed here. Enquiries are always saved to the admin panel whether this is set or not.'],
    'buyer_accounts_enabled' => ['Buyer accounts',
        'Switch the whole thing off and the request form, the sign-in page and this section\'s effects all disappear from the site. Nothing is deleted.'],
    'buyer_gate'      => ['What signing in unlocks',
        'Start with "nothing extra" until you are sure. Choosing prices publishes your internal price sheet figures to every approved buyer. Choosing the whole catalogue hides all your products from Google, so nobody new can find you through search.'],
    'buyer_intro'     => ['Request access page introduction',
        'The line at the top of the "Request trade access" form.'],
    'enquiry_intro'   => ['Shortlist page introduction',
        'The line at the top of the shortlist page, above the enquiry form.'],
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
        <?php if ($k === 'buyer_accounts_enabled'): ?>
          <?php /* Everything from here down belongs to the buyer accounts
                   feature, so it gets its own heading rather than sitting in
                   one long undifferentiated column. */ ?>
          </div></section>
          <section class="panel"><div class="panel-body">
          <h2>Buyer accounts</h2>
          <p class="hint">People can ask for a login from the site, you approve
             or turn down each request, and you choose what signing in unlocks.</p>
        <?php endif; ?>
        <div class="field">
          <label for="<?= $k ?>"><?= e($labels[$k][0] ?? $k) ?></label>
          <?php if (isset($choices[$k])): ?>
            <select id="<?= $k ?>" name="<?= $k ?>">
              <?php foreach ($choices[$k] as $value => $text): ?>
                <option value="<?= e((string) $value) ?>"
                  <?= (string) setting($k, '') === (string) $value ? 'selected' : '' ?>>
                  <?= e($text) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input id="<?= $k ?>" name="<?= $k ?>" type="<?= $k === 'per_page' ? 'number' : 'text' ?>"
                   <?= $k === 'per_page' ? 'min="4" max="60" step="1"' : '' ?>
                   value="<?= e((string) setting($k, '')) ?>">
          <?php endif; ?>
          <?php if (!empty($labels[$k][1])): ?><p class="hint"><?= e($labels[$k][1]) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Save settings</button>
  </div>
</form>
