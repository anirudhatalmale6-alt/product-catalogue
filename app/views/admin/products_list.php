<div class="adm-head">
  <div>
    <h1>Products</h1>
    <p class="adm-sub"><?= (int) $result['total'] ?> product<?= $result['total'] === 1 ? '' : 's' ?> in the catalogue.</p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-primary" href="<?= url('admin/products/new') ?>">+ New product</a>
  </div>
</div>

<form class="adm-filters" method="get" action="<?= url('admin/products') ?>">
  <input type="search" name="q" placeholder="Search name, SKU, brand&hellip;" value="<?= e($filters['q']) ?>">
  <select name="category_id">
    <option value="">All categories</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] == $c['id'] ? 'selected' : '' ?>>
        <?= e($c['name']) ?> (<?= (int) $c['product_count'] ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <select name="availability">
    <option value="">Any availability</option>
    <?php foreach (stock_statuses() as $st): ?>
      <option value="<?= $st ?>" <?= ($filters['availability'][0] ?? '') === $st ? 'selected' : '' ?>><?= e(stock_label($st)) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort">
    <?php foreach (['newest'=>'Newest','name_asc'=>'Name A-Z','price_asc'=>'Price low-high','price_desc'=>'Price high-low'] as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-ghost" href="<?= url('admin/products') ?>">Reset</a>
</form>

<?php if (!$result['items']): ?>
  <div class="panel"><p class="panel-empty">Nothing matched. <a href="<?= url('admin/products') ?>">Clear the filters</a> or <a href="<?= url('admin/products/new') ?>">add a product</a>.</p></div>
<?php else: ?>
<div class="panel">
<table class="table table-products">
  <thead>
    <tr>
      <th class="col-img"></th>
      <th>Name</th>
      <th>SKU</th>
      <th>Category</th>
      <th>Origin</th>
      <th>Price</th>
      <th>Availability</th>
      <th>Images</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($result['items'] as $p): ?>
    <tr>
      <td class="col-img">
        <img src="<?= e(upload_url($p['primary_image'])) ?>" alt="" width="46" height="46">
      </td>
      <td>
        <a class="strong" href="<?= url('admin/products/edit/' . $p['id']) ?>"><?= e($p['name']) ?></a>
        <?php if (!$p['is_active']): ?><span class="tag tag-muted">Hidden</span><?php endif; ?>
        <?php if ($p['is_featured']): ?><span class="tag tag-info">Featured</span><?php endif; ?>
      </td>
      <td class="muted col-sku"><?= e($p['sku'] ?: '—') ?></td>
      <td class="muted"><?= e($p['category_name'] ?: '—') ?></td>
      <td class="<?= $p['origin_name'] ? 'muted' : 'warn-text' ?>"><?= e($p['origin_name'] ?: 'Not set') ?></td>
      <td class="mono">
        <?php if ($p['sale_price'] !== null): ?>
          <strong><?= money($p['sale_price']) ?></strong> <s class="muted"><?= money($p['price']) ?></s>
        <?php elseif ($p['price'] === null): ?>
          <span class="muted">On request</span>
        <?php else: ?>
          <?= money($p['price']) ?>
        <?php endif; ?>
      </td>
      <td><span class="tag tag-<?= stock_class($p['stock_status']) ?>"><?= e(stock_label($p['stock_status'])) ?></span></td>
      <td class="<?= (int) $p['image_count'] === 0 ? 'warn-text' : 'muted' ?>"><?= (int) $p['image_count'] ?></td>
      <td class="right nowrap">
        <a class="btn btn-sm" href="<?= url('product/' . $p['slug']) ?>" target="_blank" rel="noopener">View</a>
        <a class="btn btn-sm" href="<?= url('admin/products/edit/' . $p['id']) ?>">Edit</a>
        <form method="post" action="<?= url('admin/products/delete') ?>" class="inline"
              data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;? This also removes its images and specs.">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="pagination adm-pagination">
    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
      <?php if ($i === $result['page']): ?>
        <span class="page-link is-current"><?= $i ?></span>
      <?php else: ?>
        <a class="page-link" href="<?= with_query(['page' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
