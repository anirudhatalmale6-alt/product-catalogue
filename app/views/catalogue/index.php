<?php
/** @var array $result @var array $filters @var array $categories */
$items       = $result['items'];
$activeCount = 0;
foreach (['q', 'brand', 'min_price', 'max_price'] as $k) {
    if (($filters[$k] ?? '') !== '') { $activeCount++; }
}
$activeCount += count($filters['availability']);
if ($filters['category_id'] && !$category) { $activeCount++; }
?>

<div class="page-head">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="<?= url('/') ?>">Home</a>
      <span aria-hidden="true">/</span>
      <?php if ($category): ?>
        <a href="<?= url('catalogue') ?>">Catalogue</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($category['name']) ?></span>
      <?php else: ?>
        <span aria-current="page">Catalogue</span>
      <?php endif; ?>
    </nav>
    <h1><?= e($category['name'] ?? ($filters['q'] !== '' ? 'Search results' : 'All products')) ?></h1>
    <?php if ($category && $category['description']): ?>
      <p class="page-lede"><?= e($category['description']) ?></p>
    <?php elseif ($filters['q'] !== ''): ?>
      <p class="page-lede">Showing matches for &ldquo;<?= e($filters['q']) ?>&rdquo;.</p>
    <?php endif; ?>
  </div>
</div>

<div class="wrap layout-with-sidebar">

  <button class="filter-toggle" type="button" aria-expanded="false" aria-controls="filters">
    Filters<?= $activeCount ? ' (' . $activeCount . ')' : '' ?>
  </button>

  <aside id="filters" class="filters" aria-label="Product filters">
    <form method="get" action="<?= url($category ? 'category/' . $category['slug'] : 'catalogue') ?>" id="filter-form">

      <div class="filter-block">
        <label class="filter-title" for="f-q">Search</label>
        <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>"
               placeholder="Name, SKU, spec&hellip;">
      </div>

      <?php if (!$category): ?>
      <div class="filter-block">
        <span class="filter-title">Product type</span>
        <ul class="filter-list">
          <li>
            <a class="<?= $filters['category_id'] ? '' : 'is-active' ?>" href="<?= with_query(['category' => null]) ?>">
              All types
            </a>
          </li>
          <?php foreach ($categories as $c): ?>
            <?php $n = $catCounts[(int) $c['id']] ?? 0; ?>
            <li>
              <a class="<?= $filters['category_id'] == $c['id'] ? 'is-active' : '' ?>"
                 href="<?= with_query(['category' => $c['slug']]) ?>">
                <?= e($c['name']) ?><span class="count"><?= $n ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="filter-block">
        <span class="filter-title">Availability</span>
        <?php foreach (['in_stock', 'low_stock', 'preorder', 'out_of_stock'] as $st): ?>
          <label class="check">
            <input type="checkbox" name="availability[]" value="<?= $st ?>"
              <?= in_array($st, $filters['availability'], true) ? 'checked' : '' ?>>
            <span><?= e(stock_label($st)) ?></span>
            <span class="count"><?= (int) ($availCounts[$st] ?? 0) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <?php if ($brands): ?>
      <div class="filter-block">
        <label class="filter-title" for="f-brand">Brand</label>
        <select id="f-brand" name="brand">
          <option value="">Any brand</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= e($b) ?>" <?= $filters['brand'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="filter-block">
        <span class="filter-title">Price (<?= e(setting('currency_code', '')) ?>)</span>
        <div class="price-row">
          <label class="sr-only" for="f-min">Minimum price</label>
          <input id="f-min" type="number" name="min_price" min="0" step="0.01"
                 placeholder="<?= (int) floor($priceRange[0]) ?>" value="<?= e((string) $filters['min_price']) ?>">
          <span aria-hidden="true">&ndash;</span>
          <label class="sr-only" for="f-max">Maximum price</label>
          <input id="f-max" type="number" name="max_price" min="0" step="0.01"
                 placeholder="<?= (int) ceil($priceRange[1]) ?>" value="<?= e((string) $filters['max_price']) ?>">
        </div>
      </div>

      <?php if ($filters['sort'] !== ''): ?>
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
      <?php endif; ?>
      <?php if (!$category && !empty($_GET['category'])): ?>
        <input type="hidden" name="category" value="<?= e((string) $_GET['category']) ?>">
      <?php endif; ?>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-block">Apply filters</button>
        <?php if ($activeCount): ?>
          <a class="btn btn-ghost btn-block" href="<?= url($category ? 'category/' . $category['slug'] : 'catalogue') ?>">Clear all</a>
        <?php endif; ?>
      </div>
    </form>
  </aside>

  <section class="results" aria-label="Products">
    <div class="results-bar">
      <p class="results-count">
        <strong><?= (int) $result['total'] ?></strong>
        product<?= $result['total'] === 1 ? '' : 's' ?>
        <?php if ($result['pages'] > 1): ?>
          <span class="muted">&middot; page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
        <?php endif; ?>
      </p>
      <div class="sort-box">
        <label for="sort">Sort</label>
        <select id="sort" name="sort" data-autonav>
          <?php foreach ([
              'featured'   => 'Featured first',
              'newest'     => 'Newest',
              'name_asc'   => 'Name A&ndash;Z',
              'name_desc'  => 'Name Z&ndash;A',
              'price_asc'  => 'Price low to high',
              'price_desc' => 'Price high to low',
          ] as $val => $label): ?>
            <option value="<?= with_query(['sort' => $val]) ?>" <?= $filters['sort'] === $val ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($activeCount): ?>
      <div class="active-filters">
        <?php if ($filters['q'] !== ''): ?>
          <a class="chip" href="<?= with_query(['q' => null]) ?>">&ldquo;<?= e($filters['q']) ?>&rdquo; <span aria-hidden="true">&times;</span></a>
        <?php endif; ?>
        <?php foreach ($filters['availability'] as $st): ?>
          <?php $rest = array_values(array_diff($filters['availability'], [$st])); ?>
          <a class="chip" href="<?= with_query(['availability' => $rest ?: null]) ?>"><?= e(stock_label($st)) ?> <span aria-hidden="true">&times;</span></a>
        <?php endforeach; ?>
        <?php if ($filters['brand'] !== ''): ?>
          <a class="chip" href="<?= with_query(['brand' => null]) ?>"><?= e($filters['brand']) ?> <span aria-hidden="true">&times;</span></a>
        <?php endif; ?>
        <?php if ($filters['min_price'] !== '' || $filters['max_price'] !== ''): ?>
          <a class="chip" href="<?= with_query(['min_price' => null, 'max_price' => null]) ?>">
            <?= $filters['min_price'] !== '' ? money($filters['min_price']) : 'Any' ?>
            &ndash;
            <?= $filters['max_price'] !== '' ? money($filters['max_price']) : 'Any' ?>
            <span aria-hidden="true">&times;</span>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty">
        <h2>No products match those filters</h2>
        <p>Try removing a filter or searching for something broader.</p>
        <a class="btn btn-primary" href="<?= url($category ? 'category/' . $category['slug'] : 'catalogue') ?>">Clear filters</a>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($items as $p): ?>
          <?php partial('product_card', ['p' => $p]); ?>
        <?php endforeach; ?>
      </div>
      <?php partial('pagination', ['result' => $result]); ?>
    <?php endif; ?>
  </section>
</div>
