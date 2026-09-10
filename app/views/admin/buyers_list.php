<?php
/** @var array $rows @var int $total @var ?string $status @var array $counts */
$pages = max(1, (int) ceil($total / $perPage));
$label = ['pending' => 'Waiting for review', 'approved' => 'Approved',
          'rejected' => 'Rejected', 'suspended' => 'Suspended'];
$tagClass = ['pending' => 'tag-new', 'approved' => 'tag-ok',
             'rejected' => 'tag-bad', 'suspended' => 'tag-warn'];
?>
<div class="adm-head">
  <div>
    <h1>Buyer accounts</h1>
    <p class="adm-sub"><?= (int) $total ?> account<?= $total === 1 ? '' : 's' ?><?php
      if ($counts['pending']): ?> &middot; <strong><?= (int) $counts['pending'] ?>
      waiting for your decision</strong><?php endif; ?></p>
  </div>
</div>

<nav class="adm-tabs" aria-label="Filter by status">
  <a class="<?= $status === null ? 'is-current' : '' ?>"
     href="<?= url('admin/buyers') ?>">All <span class="pill"><?= (int) $total ?></span></a>
  <?php foreach (BuyerRepository::STATUSES as $st): ?>
    <a class="<?= $status === $st ? 'is-current' : '' ?>"
       href="<?= url('admin/buyers', ['status' => $st]) ?>">
      <?= e($label[$st]) ?> <span class="pill"><?= (int) $counts[$st] ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$rows): ?>
  <div class="empty-state">
    <h2>Nothing here yet</h2>
    <p>When somebody fills in the &ldquo;Request trade access&rdquo; form on the
       site they will appear here, waiting for you to approve or turn down.
       Nobody can sign in until you do.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Company</th><th>Contact</th><th>Email</th>
          <th>Country</th><th>Status</th><th>Requested</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $b): ?>
          <tr class="<?= $b['status'] === 'pending' ? 'is-new' : '' ?>">
            <td>
              <strong><a href="<?= url('admin/buyers/' . $b['id']) ?>"><?= e($b['company'] ?: '—') ?></a></strong>
              <?php if ($b['username']): ?><span class="sheet-meta mono"><?= e($b['username']) ?></span><?php endif; ?>
            </td>
            <td><?= e($b['contact_name']) ?></td>
            <td><?= e($b['email']) ?></td>
            <td><?= e($b['country'] ?: '—') ?></td>
            <td><span class="tag <?= e($tagClass[$b['status']] ?? 'tag-muted') ?>">
              <?= e($label[$b['status']] ?? $b['status']) ?></span></td>
            <td class="mono"><?= e(date('j M Y', strtotime((string) $b['created_at']))) ?></td>
            <td class="row-actions"><a href="<?= url('admin/buyers/' . $b['id']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'is-current' : '' ?>"
           href="<?= url('admin/buyers', array_filter(['page' => $i, 'status' => $status])) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
