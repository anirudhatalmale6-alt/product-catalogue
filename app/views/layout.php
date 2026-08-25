<?php /** Public site layout. $content and $title come from view(). */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> &middot; <?= e(setting('site_name', 'Catalogue')) ?></title>
<meta name="description" content="<?= e($metaDescription ?? setting('site_tagline', '')) ?>">
<?php if (!empty($noindex)): ?>
<?php // A shortlist and a sent-confirmation are personal working pages, not
      // catalogue content. Keeping them out of the index also keeps them out
      // of the "why is this thin page ranking" pile later on. ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#0E0E0E">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/css/site.css') ?>">
<link rel="icon" href="<?= asset('img/favicon.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('img/apple-touch-icon.png') ?>">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= url('/') ?>">
      <?php // Swap this file for your own artwork to rebrand the header; the
            // alt text falls back to the site name set under Settings.
            // ds-logo.png is the light-on-dark lockup; ds-logo-dark.png is the
            // same wordmark with the black ink, for any light surface. ?>
      <img class="brand-logo" src="<?= asset('img/ds-logo.png') ?>"
           alt="<?= e(setting('site_name', 'Catalogue')) ?>" width="1100" height="63">
    </a>

    <form class="header-search" method="get" action="<?= url('catalogue') ?>" role="search">
      <label class="sr-only" for="hdr-q">Search products</label>
      <input id="hdr-q" type="search" name="q" placeholder="Search products, SKUs, specs&hellip;"
             value="<?= e($filters['q'] ?? '') ?>" autocomplete="off">
      <button type="submit" aria-label="Search">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm5 12 4 4"/></svg>
      </button>
    </form>

    <a class="shortlist-link" href="<?= url('shortlist') ?>">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 6h13l-1.2 7H6.4zM6.4 13 4 3H2m5 16.5a1 1 0 1 0 2 0 1 1 0 0 0-2 0m6 0a1 1 0 1 0 2 0 1 1 0 0 0-2 0"/></svg>
      <span class="shortlist-text">Shortlist</span>
      <?php // Starts hidden and is only revealed once site.js has read the
            // stored list. Rendering a "0" server-side would flash a wrong
            // number on every page for anyone with items already saved. ?>
      <span class="shortlist-count" data-shortlist-count hidden>0</span>
    </a>

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

<?php // Origins get their own strip rather than a slot in the category bar:
      // they are a different axis, and mixing them into one row makes a
      // country read as another product type. ?>
<?php $navOrigins = OriginRepository::navigation(); ?>
<?php if ($navOrigins): ?>
<div class="origin-bar">
  <div class="wrap origin-inner">
    <span class="origin-label">Origin</span>
    <?php foreach ($navOrigins as $o): ?>
      <a href="<?= url('origin/' . $o['slug']) ?>"
         class="<?= (($origin['id'] ?? 0) == $o['id']) ? 'is-current' : '' ?>">
        <?= e($o['name']) ?>
        <span class="pill"><?= (int) $o['product_count'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-inner">
    <div>
      <img class="footer-logo" src="<?= asset('img/ds-logo.png') ?>"
           alt="<?= e(setting('site_name', 'Catalogue')) ?>" width="1100" height="63">
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
    <?php // No currency is named because no figure is ever shown here. Pricing
          // depends on volume, packing and incoterm and is quoted per enquiry. ?>
    <small>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Catalogue')) ?>.
      All items quoted on request &mdash; pricing depends on volume, packing
      and incoterm.
    </small>
  </div>
</footer>

<?php // The JSON endpoint the shortlist page reads. Passed in from PHP rather
      // than hard-coded in the script so it stays correct whether the site is
      // running with mod_rewrite on or on the index.php?r= fallback. ?>
<script>window.DS_SHORTLIST_URL = <?= json_encode(url('shortlist-items')) ?>;</script>
<script src="<?= asset('js/site.js') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/js/site.js') ?>" defer></script>
</body>
</html>
