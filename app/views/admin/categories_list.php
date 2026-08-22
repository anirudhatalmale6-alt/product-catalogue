<div class="adm-head">
  <div>
    <h1>Categories</h1>
    <p class="adm-sub">These are the product types the public filters use.</p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-primary" href="<?= url('admin/categories/new') ?>">+ New category</a>
  </div>
</div>

<div class="panel">
<?php if (!$categories): ?>
  <p class="panel-empty">No categories yet. <a href="<?= url('admin/categories/new') ?>">Create the first one.</a></p>
<?php else: ?>
<table class="table">
  <thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th>Products</th><th>Order</th><th>Visible</th><th></th></tr></thead>
  <tbody>
  <?php $byId = []; foreach ($categories as $c) { $byId[(int) $c['id']] = $c['name']; } ?>
  <?php foreach ($categories as $c): ?>
    <tr>
      <td><a class="strong" href="<?= url('admin/categories/edit/' . $c['id']) ?>"><?= e($c['name']) ?></a></td>
      <td class="muted"><?= e($c['slug']) ?></td>
      <td class="muted"><?= e($c['parent_id'] ? ($byId[(int) $c['parent_id']] ?? '—') : '—') ?></td>
      <td><?= (int) $c['product_count'] ?></td>
      <td class="muted"><?= (int) $c['sort_order'] ?></td>
      <td>
        <?php if ($c['is_active']): ?>
          <span class="tag tag-ok">Visible</span>
        <?php else: ?>
          <span class="tag tag-muted">Hidden</span>
        <?php endif; ?>
      </td>
      <td class="right nowrap">
        <a class="btn btn-sm" href="<?= url('category/' . $c['slug']) ?>" target="_blank" rel="noopener">View</a>
        <a class="btn btn-sm" href="<?= url('admin/categories/edit/' . $c['id']) ?>">Edit</a>
        <form method="post" action="<?= url('admin/categories/delete') ?>" class="inline"
              data-confirm="Delete &quot;<?= e($c['name']) ?>&quot;? Its <?= (int) $c['product_count'] ?> product(s) stay in the catalogue but become uncategorised.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
