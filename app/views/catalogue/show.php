<?php
/** @var array $product @var array $images @var array $specGroups @var array $related */
$primary = $images[0]['file_path'] ?? null;
?>

<div class="page-head slim">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="<?= url('/') ?>">Home</a>
      <span aria-hidden="true">/</span>
      <a href="<?= url('catalogue') ?>">Catalogue</a>
      <?php if ($product['category_slug']): ?>
        <span aria-hidden="true">/</span>
        <a href="<?= url('category/' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
      <?php endif; ?>
      <span aria-hidden="true">/</span>
      <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>
  </div>
</div>

<div class="wrap product-detail">

  <div class="gallery">
    <div class="gallery-main">
      <img id="gallery-main-img"
           src="<?= e(upload_url($primary)) ?>"
           alt="<?= e($images[0]['alt_text'] ?? $product['name']) ?>"
           width="800" height="800">
    </div>
    <?php if (count($images) > 1): ?>
      <ul class="gallery-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <li>
            <button type="button" class="thumb <?= $i === 0 ? 'is-active' : '' ?>"
                    data-full="<?= e(upload_url($img['file_path'])) ?>"
                    data-alt="<?= e($img['alt_text'] ?: $product['name']) ?>">
              <img src="<?= e(upload_url($img['file_path'])) ?>"
                   alt="<?= e($img['alt_text'] ?: $product['name'] . ' - view ' . ($i + 1)) ?>"
                   loading="lazy" width="120" height="120">
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <?php if ($product['category_name']): ?>
      <p class="eyebrow"><a href="<?= url('category/' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a></p>
    <?php endif; ?>

    <h1><?= e($product['name']) ?></h1>

    <div class="meta-row">
      <?php if ($product['brand']): ?><span class="meta-item">Brand: <strong><?= e($product['brand']) ?></strong></span><?php endif; ?>
      <?php if ($product['sku']): ?><span class="meta-item">SKU: <strong><?= e($product['sku']) ?></strong></span><?php endif; ?>
    </div>

    <?php if ($product['short_description']): ?>
      <p class="lede"><?= e($product['short_description']) ?></p>
    <?php endif; ?>

    <div class="buy-box">
      <p class="price price-lg">
        <?php if ($product['sale_price'] !== null): ?>
          <span class="price-now"><?= money($product['sale_price']) ?></span>
          <span class="price-was"><?= money($product['price']) ?></span>
          <span class="save">Save <?= money((float) $product['price'] - (float) $product['sale_price']) ?></span>
        <?php else: ?>
          <span class="price-now"><?= money($product['price']) ?></span>
        <?php endif; ?>
      </p>
      <p class="stock stock-<?= stock_class($product['stock_status']) ?> stock-lg">
        <span class="dot" aria-hidden="true"></span>
        <?= e(stock_label($product['stock_status'])) ?>
        <?php if ($product['stock_qty'] !== null && $product['stock_status'] !== 'out_of_stock'): ?>
          <span class="muted">(<?= (int) $product['stock_qty'] ?> available)</span>
        <?php endif; ?>
      </p>
      <?php if (setting('contact_email') || setting('contact_phone')): ?>
        <p class="enquire">To order or ask about this item, contact
          <?php if (setting('contact_email')): ?><strong><?= e(setting('contact_email')) ?></strong><?php endif; ?>
          <?php if (setting('contact_phone')): ?> &middot; <strong><?= e(setting('contact_phone')) ?></strong><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($product['description']): ?>
      <section class="section">
        <h2>Description</h2>
        <div class="rich"><?= nl2br(e($product['description'])) ?></div>
      </section>
    <?php endif; ?>

    <?php if ($specGroups): ?>
      <section class="section" id="specs">
        <h2>Technical specifications</h2>
        <?php foreach ($specGroups as $groupName => $rows): ?>
          <?php if (count($specGroups) > 1): ?>
            <h3 class="spec-group"><?= e($groupName) ?></h3>
          <?php endif; ?>
          <table class="spec-table">
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <th scope="row"><?= e($r['spec_name']) ?></th>
                <td><?= e($r['spec_value']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($product['weight_grams'] !== null && $groupName === array_key_last($specGroups)): ?>
              <tr><th scope="row">Weight</th><td><?= number_format((int) $product['weight_grams'] / 1000, 2) ?> kg</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        <?php endforeach; ?>
      </section>
    <?php elseif ($product['weight_grams'] !== null): ?>
      <section class="section">
        <h2>Technical specifications</h2>
        <table class="spec-table"><tbody>
          <tr><th scope="row">Weight</th><td><?= number_format((int) $product['weight_grams'] / 1000, 2) ?> kg</td></tr>
        </tbody></table>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php if ($related): ?>
<section class="related">
  <div class="wrap">
    <h2>More in <?= e($product['category_name']) ?></h2>
    <div class="product-grid">
      <?php foreach ($related as $p): ?>
        <?php $p['category_name'] = $product['category_name']; ?>
        <?php partial('product_card', ['p' => $p]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
