<?php /** Public site layout. $content and $title come from view(). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> &middot; <?= e(setting('site_name', 'Catalogue')) ?></title>
<meta name="description" content="<?= e($metaDescription ?? setting('site_tagline', '')) ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/css/site.css') ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= url('/') ?>">
      <span class="brand-mark" aria-hidden="true"></span>
      <span class="brand-text">
        <strong><?= e(setting('site_name', 'Catalogue')) ?></strong>
        <small><?= e(setting('site_tagline', '')) ?></small>
      </span>
    </a>

    <form class="header-search" method="get" action="<?= url('catalogue') ?>" role="search">
      <label class="sr-only" for="hdr-q">Search products</label>
      <input id="hdr-q" type="search" name="q" placeholder="Search products, SKUs, specs&hellip;"
             value="<?= e($filters['q'] ?? '') ?>" autocomplete="off">
      <button type="submit" aria-label="Search">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm5 12 4 4"/></svg>
      </button>
    </form>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span></span><span></span><span></span>
      <em class="sr-only">Menu</em>
    </button>
  </div>

  <nav id="site-nav" class="site-nav" aria-label="Product categories">
    <div class="wrap nav-inner">
      <a href="<?= url('catalogue') ?>" class="<?= ($_GET['r'] ?? '') === 'catalogue' || ($_GET['r'] ?? '') === '' ? 'is-current' : '' ?>">All products</a>
      <?php foreach (($categories ?? []) as $c): ?>
        <a href="<?= url('category/' . $c['slug']) ?>"
           class="<?= (($category['id'] ?? 0) == $c['id']) ? 'is-current' : '' ?>">
          <?= e($c['name']) ?>
          <span class="pill"><?= (int) $c['product_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-inner">
    <div>
      <strong><?= e(setting('site_name', 'Catalogue')) ?></strong>
      <p><?= e(setting('site_tagline', '')) ?></p>
    </div>
    <div class="footer-contact">
      <?php if (setting('contact_email')): ?>
        <p><?= e(setting('contact_email')) ?></p>
      <?php endif; ?>
      <?php if (setting('contact_phone')): ?>
        <p><?= e(setting('contact_phone')) ?></p>
      <?php endif; ?>
    </div>
    <div class="footer-links">
      <a href="<?= url('catalogue') ?>">Catalogue</a>
      <a href="<?= url('admin') ?>">Admin</a>
    </div>
  </div>
  <div class="wrap footer-legal">
    <small>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Catalogue')) ?>. Prices in <?= e(setting('currency_code', '')) ?>.</small>
  </div>
</footer>

<script src="<?= asset('js/site.js') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/js/site.js') ?>" defer></script>
</body>
</html>
