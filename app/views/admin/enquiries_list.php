<?php /** @var array $result @var array $filters */ ?>
<div class="adm-head">
  <div>
    <h1>Enquiries</h1>
    <p class="adm-sub"><?= (int) $result['total'] ?> enquir<?= $result['total'] === 1 ? 'y' : 'ies' ?></p>
  </div>
</div>

<form class="adm-filters" method="get" action="<?= url('admin/enquiries') ?>">
  <div class="field">
    <label for="f-q">Search</label>
    <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>"
           placeholder="Reference, company, name or email">
  </div>
  <div class="field">
    <label for="f-status">Status</label>
    <select id="f-status" name="status">
      <option value="">All statuses</option>
      <?php foreach (EnquiryRepository::STATUSES as $st): ?>
        <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>>
          <?= e(EnquiryRepository::statusLabel($st)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field field-actions">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="<?= url('admin/enquiries') ?>">Reset</a>
  </div>
</form>

<?php if (!$result['items']): ?>
  <div class="empty-state">
    <h2>No enquiries yet</h2>
    <p>When a buyer sends a shortlist from the catalogue it will appear here.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Reference</th><th>From</th><th>Items</th>
          <th>Destination</th><th>Status</th><th>Received</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['items'] as $e): ?>
          <tr class="<?= $e['status'] === 'new' ? 'is-new' : '' ?>">
            <td class="mono"><a href="<?= url('admin/enquiries/' . $e['id']) ?>"><?= e($e['reference']) ?></a></td>
            <td>
              <strong><?= e($e['company'] ?: $e['contact_name']) ?></strong>
              <?php if ($e['company']): ?><span class="sheet-meta"><?= e($e['contact_name']) ?></span><?php endif; ?>
            </td>
            <td class="mono"><?= (int) $e['item_count'] ?></td>
            <td><?= e($e['destination'] ?: $e['country'] ?: '—') ?></td>
            <td><span class="tag tag-<?= e($e['status']) ?>"><?= e(EnquiryRepository::statusLabel($e['status'])) ?></span></td>
            <td class="mono"><?= e(date('j M Y', strtotime($e['created_at']))) ?></td>
            <td class="row-actions"><a href="<?= url('admin/enquiries/' . $e['id']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <a class="<?= $i === $result['page'] ? 'is-current' : '' ?>"
           href="<?= url('admin/enquiries', array_filter(['page' => $i] + $filters, 'strlen')) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
