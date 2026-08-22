<?php /** @var array $p */ ?>
<article class="card">
  <a class="card-media" href="<?= url('product/' . $p['slug']) ?>">
    <img src="<?= e(upload_url($p['primary_image'] ?? null)) ?>"
         alt="<?= e($p['name']) ?>" loading="lazy" width="500" height="500">
    <?php if (!empty($p['sale_price'])): ?>
      <span class="badge badge-sale">Sale</span>
    <?php elseif (!empty($p['is_featured'])): ?>
      <span class="badge badge-featured">Featured</span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <?php if (!empty($p['category_name'])): ?>
      <p class="card-cat"><?= e($p['category_name']) ?></p>
    <?php endif; ?>
    <h2 class="card-title">
      <a href="<?= url('product/' . $p['slug']) ?>"><?= e($p['name']) ?></a>
    </h2>
    <?php // The clamp lives on an inner element: -webkit-line-clamp is ignored
          // on a direct child of a flex container (the text would be cut
          // mid-line instead of at a line boundary). ?>
    <div class="card-desc-wrap">
      <p class="card-desc"><?= e($p['short_description'] ?? '') ?></p>
    </div>
    <div class="card-foot">
      <p class="price">
        <?php if (!empty($p['sale_price'])): ?>
          <span class="price-now"><?= money($p['sale_price']) ?></span>
          <span class="price-was"><?= money($p['price']) ?></span>
        <?php else: ?>
          <span class="price-now"><?= money($p['price']) ?></span>
        <?php endif; ?>
      </p>
      <p class="stock stock-<?= stock_class($p['stock_status']) ?>">
        <span class="dot" aria-hidden="true"></span><?= e(stock_label($p['stock_status'])) ?>
      </p>
    </div>
  </div>
</article>
