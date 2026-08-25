<?php /** @var array $p */ ?>
<article class="card">
  <a class="card-media" href="<?= url('product/' . $p['slug']) ?>">
    <img src="<?= e(upload_url($p['primary_image'] ?? null)) ?>"
         alt="<?= e($p['name']) ?>" loading="lazy" width="500" height="500">
    <?php if (!empty($p['is_featured'])): ?>
      <span class="badge badge-featured">Featured</span>
    <?php endif; ?>
    <?php if (!empty($p['origin_name'])): ?>
      <span class="badge badge-origin"><?= e($p['origin_name']) ?></span>
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
      <p class="stock stock-<?= stock_class($p['stock_status']) ?>">
        <span class="dot" aria-hidden="true"></span><?= e(stock_label($p['stock_status'])) ?>
      </p>
      <?php // The label is rendered server-side rather than being added by
            // JavaScript, so it is present for a buyer with scripting off and
            // for anything reading the page without running scripts. ?>
      <p class="poa"><?= e(price_request_label()) ?></p>
    </div>
    <?php /* aria-pressed carries the on/off state; site.js flips it and the
             label text together so a screen reader hears the change rather
             than only seeing the colour move. */ ?>
    <button type="button" class="btn-shortlist" data-shortlist="<?= (int) $p['id'] ?>"
            aria-pressed="false">
      <span class="sl-add">Add to shortlist</span>
      <span class="sl-on">On your shortlist</span>
    </button>
  </div>
</article>
