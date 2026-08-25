<div class="adm-head">
  <div>
    <h1>Dashboard</h1>
    <p class="adm-sub">Everything on the catalogue at a glance.</p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-primary" href="<?= url('admin/products/new') ?>">+ New product</a>
  </div>
</div>

<div class="stat-grid">
  <a class="stat" href="<?= url('admin/products') ?>">
    <span class="stat-num"><?= $stats['products'] ?></span>
    <span class="stat-label">Products</span>
    <span class="stat-note"><?= $stats['active'] ?> visible on the site</span>
  </a>
  <a class="stat" href="<?= url('admin/categories') ?>">
    <span class="stat-num"><?= $stats['categories'] ?></span>
    <span class="stat-label">Categories</span>
    <span class="stat-note">Used for filtering</span>
  </a>
  <div class="stat">
    <span class="stat-num"><?= $stats['images'] ?></span>
    <span class="stat-label">Images</span>
    <span class="stat-note"><?= $stats['no_image'] ?> product<?= $stats['no_image'] === 1 ? '' : 's' ?> with none</span>
  </div>
  <a class="stat <?= $stats['out'] ? 'stat-warn' : '' ?>" href="<?= url('admin/products', ['availability' => 'out_of_stock']) ?>">
    <span class="stat-num"><?= $stats['out'] ?></span>
    <span class="stat-label">Out of stock</span>
    <span class="stat-note"><?= $stats['low'] ?> more running low</span>
  </a>
  <?php // Links out to the public listing, which is the one place that can
        // actually list the products with no origin against their categories. ?>
  <a class="stat" href="<?= url('catalogue', ['origin' => 'none']) ?>" target="_blank" rel="noopener">
    <span class="stat-num"><?= $stats['no_origin'] ?></span>
    <span class="stat-label">No origin set</span>
    <span class="stat-note">Live products missing a country</span>
  </a>
  <div class="stat">
    <span class="stat-num"><?= $stats['no_price'] ?></span>
    <span class="stat-label">Quoted on request</span>
    <span class="stat-note">Live products with no price</span>
  </div>
</div>

<div class="adm-cols">
  <section class="panel">
    <div class="panel-head">
      <h2>Recently updated</h2>
      <a href="<?= url('admin/products') ?>">All products &rarr;</a>
    </div>
    <?php if (!$recent): ?>
      <p class="panel-empty">No products yet. <a href="<?= url('admin/products/new') ?>">Add the first one.</a></p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $p): ?>
          <tr>
            <td>
              <a href="<?= url('admin/products/edit/' . $p['id']) ?>"><?= e($p['name']) ?></a>
              <?php if (!$p['is_active']): ?><span class="tag tag-muted">Hidden</span><?php endif; ?>
            </td>
            <td class="muted"><?= e($p['category_name'] ?? '&mdash;') ?></td>
            <td class="mono"><?= $p['price'] === null ? '<span class="muted">On request</span>' : money($p['price']) ?></td>
            <td><span class="tag tag-<?= stock_class($p['stock_status']) ?>"><?= e(stock_label($p['stock_status'])) ?></span></td>
            <td class="right"><a class="btn btn-sm" href="<?= url('product/' . $p['slug']) ?>" target="_blank" rel="noopener">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Needs attention</h2></div>
    <?php if (!$attention): ?>
      <p class="panel-empty">Everything is in stock.</p>
    <?php else: ?>
      <ul class="simple-list">
        <?php foreach ($attention as $p): ?>
          <li>
            <a href="<?= url('admin/products/edit/' . $p['id']) ?>"><?= e($p['name']) ?></a>
            <span class="tag tag-<?= stock_class($p['stock_status']) ?>"><?= e(stock_label($p['stock_status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="panel-head" style="margin-top:1.5rem"><h2>Quick actions</h2></div>
    <ul class="simple-list">
      <li><a href="<?= url('admin/products/new') ?>">Add a product</a></li>
      <li><a href="<?= url('admin/categories/new') ?>">Add a category</a></li>
      <li><a href="<?= url('admin/origins') ?>">Manage origins</a></li>
      <li><a href="<?= url('admin/settings') ?>">Change site name or currency</a></li>
    </ul>
  </section>
</div>
