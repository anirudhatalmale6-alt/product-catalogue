<?php
/** @var array $rows @var array $filters @var array $stats */
$total  = (int) ($stats['total']  ?? 0);
$priced = (int) ($stats['priced'] ?? 0);
$expired = (int) ($stats['expired'] ?? 0);

// The filter state has to ride along with the save so the redirect lands back
// on the same view instead of dumping you at the top of 197 rows.
$carry = array_filter([
    'category_id' => $filters['category_id'] ?: '',
    'origin_id'   => $filters['origin_id'] === 'none' ? 'none' : ($filters['origin_id'] ?: ''),
    'q'           => $filters['q'],
    'priced'      => $filters['priced'],
    'expiring'    => $filters['expiring'] ? '1' : '',
    'ids'         => $filters['ids'] ? implode(',', $filters['ids']) : '',
], 'strlen');
?>
<div class="adm-head">
  <div>
    <h1>Internal price sheet</h1>
    <p class="adm-sub">
      <strong><?= $priced ?></strong> of <?= $total ?> products priced.
      <?php if ($expired): ?>
        <span class="warn-text"><?= $expired ?> past their valid-until date.</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-ghost" href="<?= url('admin/pricing/export', $carry) ?>">Export CSV</a>
  </div>
</div>

<div class="notice notice-internal">
  <strong>These figures are internal.</strong>
  Nothing on this screen appears anywhere on the public catalogue &mdash; the
  buyer-facing pages do not read this table at all. Use Export CSV to send a
  sheet to whoever needs one.
</div>

<form class="adm-filters" method="get" action="<?= url('admin/pricing') ?>">
  <div class="field">
    <label for="f-q">Search</label>
    <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Name, SKU or supplier">
  </div>
  <div class="field">
    <label for="f-cat">Category</label>
    <select id="f-cat" name="category_id">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-org">Origin</label>
    <select id="f-org" name="origin_id">
      <option value="">All origins</option>
      <option value="none" <?= $filters['origin_id'] === 'none' ? 'selected' : '' ?>>Not specified</option>
      <?php foreach ($origins as $o): ?>
        <option value="<?= (int) $o['id'] ?>" <?= $filters['origin_id'] === (int) $o['id'] ? 'selected' : '' ?>>
          <?= e($o['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-priced">Priced</label>
    <select id="f-priced" name="priced">
      <option value="">Any</option>
      <option value="no"  <?= $filters['priced'] === 'no'  ? 'selected' : '' ?>>Not priced yet</option>
      <option value="yes" <?= $filters['priced'] === 'yes' ? 'selected' : '' ?>>Priced</option>
    </select>
  </div>
  <div class="field field-check">
    <label><input type="checkbox" name="expiring" value="1" <?= $filters['expiring'] ? 'checked' : '' ?>>
      Expiring or expired</label>
  </div>
  <div class="field field-actions">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="<?= url('admin/pricing') ?>">Reset</a>
  </div>
</form>

<?php if ($filters['ids']): ?>
  <p class="adm-sub">
    Showing the <?= count($filters['ids']) ?> product<?= count($filters['ids']) === 1 ? '' : 's' ?>
    from one enquiry. <a href="<?= url('admin/pricing') ?>">Show the whole sheet</a>
  </p>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="empty-state"><p>No products match that filter.</p></div>
<?php else: ?>
<form id="price-sheet-form" method="post" action="<?= url('admin/pricing/save') ?>" class="sheet-form">
  <?= csrf_field() ?>
  <?php foreach ($carry as $k => $v): ?>
    <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
  <?php endforeach; ?>

  <div class="sheet-wrap">
    <table class="sheet">
      <thead>
        <tr>
          <th class="col-name">Product</th>
          <th class="col-num">Price</th>
          <th class="col-cur">Cur</th>
          <th class="col-unit">Per</th>
          <th class="col-moq">MOQ</th>
          <th class="col-cur">Incoterm</th>
          <th class="col-date">Valid until</th>
          <th class="col-supp">Supplier</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php $lastCat = null; foreach ($rows as $r): ?>
          <?php if ($r['category_name'] !== $lastCat): $lastCat = $r['category_name']; ?>
            <tr class="sheet-group">
              <th colspan="9"><?= e($lastCat ?? 'Uncategorised') ?></th>
            </tr>
          <?php endif; ?>
          <?php
            $id = (int) $r['id'];
            $n  = fn(string $f) => 'row[' . $id . '][' . $f . ']';
            $expiredRow = $r['price'] !== null && $r['valid_until'] !== null
                          && $r['valid_until'] < date('Y-m-d');
          ?>
          <tr class="<?= $expiredRow ? 'is-expired' : '' ?> <?= (int) $r['is_active'] === 0 ? 'is-hidden-product' : '' ?>">
            <th scope="row" class="col-name">
              <a href="<?= url('admin/products/edit/' . $id) ?>"><?= e($r['name']) ?></a>
              <span class="sheet-meta">
                <?= $r['sku'] ? e($r['sku']) . ' &middot; ' : '' ?>
                <?= e($r['origin_name'] ?? 'Origin not set') ?>
                <?php if ((int) $r['is_active'] === 0): ?> &middot; <em>not listed</em><?php endif; ?>
              </span>
            </th>
            <td><input class="in-num" type="number" step="0.01" min="0" name="<?= $n('price') ?>"
                       value="<?= e((string) ($r['price'] ?? '')) ?>" placeholder="&mdash;"></td>
            <td><input class="in-cur" type="text" maxlength="3" name="<?= $n('currency') ?>"
                       value="<?= e((string) ($r['currency'] ?? setting('currency_code', 'CAD'))) ?>"></td>
            <td><input type="text" maxlength="60" name="<?= $n('price_unit') ?>" list="price-units"
                       value="<?= e((string) ($r['price_unit'] ?? '')) ?>" placeholder="per kg"></td>
            <td><input type="text" maxlength="60" name="<?= $n('moq') ?>"
                       value="<?= e((string) ($r['moq'] ?? '')) ?>" placeholder="1 x 20ft"></td>
            <td><input class="in-cur" type="text" maxlength="20" name="<?= $n('incoterm') ?>" list="incoterms"
                       value="<?= e((string) ($r['incoterm'] ?? '')) ?>" placeholder="FOB"></td>
            <td><input class="in-date" type="date" name="<?= $n('valid_until') ?>"
                       value="<?= e((string) ($r['valid_until'] ?? '')) ?>"></td>
            <td><input type="text" maxlength="160" name="<?= $n('supplier') ?>"
                       value="<?= e((string) ($r['supplier'] ?? '')) ?>"></td>
            <td><input type="text" maxlength="400" name="<?= $n('notes') ?>"
                       value="<?= e((string) ($r['notes'] ?? '')) ?>"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <datalist id="price-units">
    <?php foreach (['per kg', 'per tonne', 'per carton', 'per case', 'per piece',
                    'per 10kg carton', 'per 20ft FCL', 'per 40ft FCL',
                    'per 40ft HQ reefer'] as $u): ?>
      <option value="<?= e($u) ?>"></option>
    <?php endforeach; ?>
  </datalist>
  <datalist id="incoterms">
    <?php foreach ($incoterms as $ic): ?><option value="<?= e($ic) ?>"></option><?php endforeach; ?>
  </datalist>

  <?php // The sheet is long, so the save control follows you down it rather
        // than sitting at the bottom of 197 rows. ?>
  <div class="sheet-save">
    <button type="submit" class="btn btn-primary">Save price sheet</button>
    <span class="muted"><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?> shown</span>
  </div>
</form>
<?php endif; ?>
