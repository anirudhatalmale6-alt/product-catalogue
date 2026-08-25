<?php
/** @var array $result @var array $filters @var array $categories @var array $origins */
$items       = $result['items'];
$origin      = $origin ?? null;
$activeCount = 0;
foreach (['q', 'brand'] as $k) {
    if (($filters[$k] ?? '') !== '') { $activeCount++; }
}
$activeCount += count($filters['availability']);
if ($filters['category_id'] && !$category) { $activeCount++; }
if ($filters['origin_id'] !== null && !$origin) { $activeCount++; }

// Where "Clear all" and the filter form post back to. On a /category or
// /origin page that is the page itself, so clearing the sidebar filters does
// not also drop you out of the section you were browsing.
$baseUrl = url($category ? 'category/' . $category['slug']
                         : ($origin ? 'origin/' . $origin['slug'] : 'catalogue'));

$originLabel = null;
if ($origin) {
    $originLabel = $origin['name'];
} elseif ($filters['origin_id'] === 'none') {
    $originLabel = 'Origin not specified';
} elseif ($filters['origin_id']) {
    foreach ($origins as $o) {
        if ((int) $o['id'] === (int) $filters['origin_id']) { $originLabel = $o['name']; }
    }
}
?>

<div class="page-head">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="<?= url('/') ?>">Home</a>
      <span aria-hidden="true">/</span>
      <?php if ($category || $origin): ?>
        <a href="<?= url('catalogue') ?>">Catalogue</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($category['name'] ?? $origin['name']) ?></span>
      <?php else: ?>
        <span aria-current="page">Catalogue</span>
      <?php endif; ?>
    </nav>
    <h1>
      <?php if ($category): ?><?= e($category['name']) ?>
      <?php elseif ($origin): ?><?= e($origin['name']) ?> origin
      <?php else: ?><?= $filters['q'] !== '' ? 'Search results' : 'All products' ?><?php endif; ?>
    </h1>
    <?php if ($category && $category['description']): ?>
      <p class="page-lede"><?= e($category['description']) ?></p>
    <?php elseif ($origin): ?>
      <p class="page-lede">Every item we source from <?= e($origin['name']) ?>, across all product types.</p>
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
    <form method="get" action="<?= $baseUrl ?>" id="filter-form">

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

      <?php if (!$origin && $origins): ?>
      <div class="filter-block">
        <span class="filter-title">Origin</span>
        <ul class="filter-list">
          <li>
            <a class="<?= $filters['origin_id'] === null ? 'is-active' : '' ?>" href="<?= with_query(['origin' => null]) ?>">
              Any origin
            </a>
          </li>
          <?php foreach ($origins as $o): ?>
            <li>
              <a class="<?= $filters['origin_id'] === (int) $o['id'] ? 'is-active' : '' ?>"
                 href="<?= with_query(['origin' => $o['slug']]) ?>">
                <?= e($o['name']) ?><span class="count"><?= (int) ($originCounts[(int) $o['id']] ?? 0) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
          <?php // Surfacing the unfilled rows is how you work through an import
                // without hunting for them one product at a time. ?>
          <?php if (!empty($originCounts['none'])): ?>
            <li>
              <a class="subtle <?= $filters['origin_id'] === 'none' ? 'is-active' : '' ?>"
                 href="<?= with_query(['origin' => 'none']) ?>">
                Not specified<span class="count"><?= (int) $originCounts['none'] ?></span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="filter-block">
        <span class="filter-title">Availability</span>
        <?php foreach (stock_statuses() as $st): ?>
          <?php // Hide a status nothing currently uses, unless it is ticked -
                // otherwise unticking it would make the checkbox disappear. ?>
          <?php $n = (int) ($availCounts[$st] ?? 0); ?>
          <?php $on = in_array($st, $filters['availability'], true); ?>
          <?php if ($n === 0 && !$on) { continue; } ?>
          <label class="check">
            <input type="checkbox" name="availability[]" value="<?= $st ?>" <?= $on ? 'checked' : '' ?>>
            <span><?= e(stock_label($st)) ?></span>
            <span class="count"><?= $n ?></span>
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

      <?php // There is no price filter. The catalogue carries no pricing at
            // all - see product_pricing in the schema. ?>

      <?php if ($filters['sort'] !== ''): ?>
        <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
      <?php endif; ?>
      <?php // The category and origin filters are links, not form controls, so
            // their current value has to ride along or submitting the form
            // would silently widen the search back out to everything. ?>
      <?php if (!$category && !empty($_GET['category'])): ?>
        <input type="hidden" name="category" value="<?= e((string) $_GET['category']) ?>">
      <?php endif; ?>
      <?php if (!$origin && !empty($_GET['origin'])): ?>
        <input type="hidden" name="origin" value="<?= e((string) $_GET['origin']) ?>">
      <?php endif; ?>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-block">Apply filters</button>
        <?php if ($activeCount): ?>
          <a class="btn btn-ghost btn-block" href="<?= $baseUrl ?>">Clear all</a>
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
              'origin'     => 'Origin',
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
        <?php if ($originLabel !== null && !$origin): ?>
          <a class="chip" href="<?= with_query(['origin' => null]) ?>"><?= e($originLabel) ?> <span aria-hidden="true">&times;</span></a>
        <?php endif; ?>
        <?php if ($filters['brand'] !== ''): ?>
          <a class="chip" href="<?= with_query(['brand' => null]) ?>"><?= e($filters['brand']) ?> <span aria-hidden="true">&times;</span></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty">
        <h2>No products match those filters</h2>
        <p>Try removing a filter or searching for something broader.</p>
        <a class="btn btn-primary" href="<?= $baseUrl ?>">Clear filters</a>
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
