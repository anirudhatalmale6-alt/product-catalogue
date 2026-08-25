<div class="adm-head">
  <div>
    <h1>Origins</h1>
    <p class="adm-sub">Country of origin. Filtered separately from product type, so a
       buyer can ask for one country across every type at once.</p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-primary" href="<?= url('admin/origins/new') ?>">+ New origin</a>
  </div>
</div>

<?php if ($unsetCount): ?>
  <div class="alert alert-info">
    <strong><?= (int) $unsetCount ?></strong> live product<?= $unsetCount === 1 ? ' has' : 's have' ?>
    no origin set.
    <a href="<?= url('catalogue', ['origin' => 'none']) ?>" target="_blank" rel="noopener">List them &nearr;</a>
  </div>
<?php endif; ?>

<div class="panel">
<?php if (!$origins): ?>
  <p class="panel-empty">No origins yet. <a href="<?= url('admin/origins/new') ?>">Create the first one.</a></p>
<?php else: ?>
<table class="table">
  <thead><tr><th>Name</th><th>Slug</th><th>Code</th><th>Products</th><th>Order</th><th>Visible</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($origins as $o): ?>
    <tr>
      <td><a class="strong" href="<?= url('admin/origins/edit/' . $o['id']) ?>"><?= e($o['name']) ?></a></td>
      <td class="muted"><?= e($o['slug']) ?></td>
      <td class="mono"><?= e($o['code'] ?: '—') ?></td>
      <td><?= (int) $o['product_count'] ?></td>
      <td class="muted"><?= (int) $o['sort_order'] ?></td>
      <td>
        <?php if ($o['is_active']): ?>
          <span class="tag tag-ok">Visible</span>
        <?php else: ?>
          <span class="tag tag-muted">Hidden</span>
        <?php endif; ?>
      </td>
      <td class="right nowrap">
        <a class="btn btn-sm" href="<?= url('origin/' . $o['slug']) ?>" target="_blank" rel="noopener">View</a>
        <a class="btn btn-sm" href="<?= url('admin/origins/edit/' . $o['id']) ?>">Edit</a>
        <form method="post" action="<?= url('admin/origins/delete') ?>" class="inline"
              data-confirm="Delete &quot;<?= e($o['name']) ?>&quot;? Its <?= (int) $o['product_count'] ?> product(s) stay in the catalogue but lose their origin.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
