<?php
/** @var array $enquiry @var array $items @var array $pricing */
$productIds = array_filter(array_column($items, 'product_id'));
?>
<div class="adm-head">
  <div>
    <h1>Enquiry <span class="mono"><?= e($enquiry['reference']) ?></span></h1>
    <p class="adm-sub">
      Received <?= e(date('j M Y \a\t H:i', strtotime($enquiry['created_at']))) ?>
      &middot; <span class="tag tag-<?= e($enquiry['status']) ?>"><?= e(EnquiryRepository::statusLabel($enquiry['status'])) ?></span>
    </p>
  </div>
  <div class="adm-head-actions">
    <?php if ($productIds): ?>
      <a class="btn btn-ghost" href="<?= url('admin/pricing', ['ids' => implode(',', $productIds)]) ?>">
        Open in price sheet</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= url('admin/enquiries/export/' . (int) $enquiry['id']) ?>">
      Export quote sheet</a>
  </div>
</div>

<div class="adm-cols">
  <div class="adm-col-main">
    <section class="panel">
      <div class="panel-head"><h2><?= count($items) ?> product<?= count($items) === 1 ? '' : 's' ?> requested</h2></div>
      <div class="table-wrap">
        <table class="adm-table">
          <thead>
            <tr><th>Product</th><th>Quantity wanted</th><th>Buyer notes</th><th>Internal price</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <?php $p = $pricing[(int) $it['product_id']] ?? null; ?>
              <tr>
                <td>
                  <?php if ($it['product_slug']): ?>
                    <a href="<?= url('product/' . $it['product_slug']) ?>" target="_blank" rel="noopener">
                      <?= e($it['product_name']) ?></a>
                  <?php else: ?>
                    <?php // The product row is gone but the enquiry still records
                          // what was asked for - the name is stored on the line. ?>
                    <?= e($it['product_name']) ?>
                    <span class="sheet-meta"><em>no longer in the catalogue</em></span>
                  <?php endif; ?>
                  <?php if ($it['product_sku']): ?>
                    <span class="sheet-meta mono"><?= e($it['product_sku']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e($it['quantity'] ?: '—') ?></td>
                <td><?= e($it['notes'] ?: '—') ?></td>
                <td class="mono">
                  <?php if ($p && $p['price'] !== null): ?>
                    <?= e($p['currency']) ?> <?= e(number_format((float) $p['price'], 2)) ?>
                    <?php if ($p['price_unit']): ?><span class="sheet-meta"><?= e($p['price_unit']) ?></span><?php endif; ?>
                    <?php if ($p['incoterm']): ?><span class="sheet-meta"><?= e($p['incoterm']) ?></span><?php endif; ?>
                  <?php else: ?>
                    <span class="muted">not priced</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if ($enquiry['message']): ?>
      <section class="panel">
        <div class="panel-head"><h2>Their message</h2></div>
        <div class="panel-body"><p class="rich"><?= nl2br(e($enquiry['message'])) ?></p></div>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2>Status and internal notes</h2></div>
      <div class="panel-body">
        <form id="enquiry-status-form" method="post" action="<?= url('admin/enquiries/update') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($statuses as $st): ?>
                <option value="<?= e($st) ?>" <?= $enquiry['status'] === $st ? 'selected' : '' ?>>
                  <?= e(EnquiryRepository::statusLabel($st)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="admin_notes">Internal notes</label>
            <textarea id="admin_notes" name="admin_notes" rows="4"
                      placeholder="What was quoted, who is following up&hellip;"><?= e((string) $enquiry['admin_notes']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </section>
  </div>

  <aside class="adm-col-side">
    <section class="panel">
      <div class="panel-head"><h2>Contact</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <dt>Name</dt><dd><?= e($enquiry['contact_name']) ?></dd>
          <?php if ($enquiry['company']): ?><dt>Company</dt><dd><?= e($enquiry['company']) ?></dd><?php endif; ?>
          <dt>Email</dt>
          <dd><a href="mailto:<?= e(rawurlencode($enquiry['email'])) ?>?subject=<?= e(rawurlencode('Your enquiry ' . $enquiry['reference'])) ?>">
            <?= e($enquiry['email']) ?></a></dd>
          <?php if ($enquiry['phone']): ?><dt>Phone</dt><dd><?= e($enquiry['phone']) ?></dd><?php endif; ?>
          <?php if ($enquiry['country']): ?><dt>Country</dt><dd><?= e($enquiry['country']) ?></dd><?php endif; ?>
          <?php if ($enquiry['destination']): ?><dt>Destination</dt><dd><?= e($enquiry['destination']) ?></dd><?php endif; ?>
          <?php if ($enquiry['incoterm']): ?><dt>Incoterm</dt><dd><?= e($enquiry['incoterm']) ?></dd><?php endif; ?>
        </dl>
      </div>
    </section>

    <section class="panel panel-danger">
      <div class="panel-head"><h2>Delete</h2></div>
      <div class="panel-body">
        <p class="muted">Removes the enquiry and its lines. This cannot be undone.</p>
        <form method="post" action="<?= url('admin/enquiries/delete') ?>"
              onsubmit="return confirm('Delete enquiry <?= e($enquiry['reference']) ?>?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $enquiry['id'] ?>">
          <button type="submit" class="btn btn-danger">Delete enquiry</button>
        </form>
      </div>
    </section>
  </aside>
</div>
