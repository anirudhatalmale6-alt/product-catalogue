<?php $me = Auth::user(); $r = $_GET['r'] ?? ''; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?> &middot; Admin</title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/css/admin.css') ?>">
<link rel="icon" href="<?= asset('img/favicon.png') ?>" type="image/png">
</head>
<body class="admin">

<header class="adm-top">
  <a class="adm-brand" href="<?= url('admin') ?>">
    <img class="adm-mark" src="<?= asset('img/ds-logo.png') ?>"
         alt="<?= e(setting('site_name', 'Catalogue')) ?>" width="1100" height="63">
    <span>admin</span>
  </a>
  <button class="adm-nav-toggle" type="button" aria-expanded="false" aria-controls="adm-side">Menu</button>
  <div class="adm-top-right">
    <a class="adm-view-site" href="<?= url('catalogue') ?>" target="_blank" rel="noopener">View site &nearr;</a>
    <span class="adm-user"><?= e($me['full_name'] ?: $me['username']) ?></span>
    <form method="post" action="<?= url('admin/logout') ?>" class="inline">
      <?= csrf_field() ?>
      <button type="submit" class="adm-signout">Sign out</button>
    </form>
  </div>
</header>

<div class="adm-shell">
  <nav id="adm-side" class="adm-side" aria-label="Admin sections">
    <a href="<?= url('admin') ?>" class="<?= $r === 'admin' ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9632;</span> Dashboard</a>
    <a href="<?= url('admin/products') ?>" class="<?= str_starts_with($r, 'admin/products') ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9635;</span> Products</a>
    <?php // Flagged as internal in the navigation itself, so nobody has to
          // remember which screens are safe to show on a shared screen. ?>
    <a href="<?= url('admin/pricing') ?>" class="<?= str_starts_with($r, 'admin/pricing') ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9878;</span> Price sheet
      <span class="adm-tag">internal</span></a>
    <?php $newEnquiries = EnquiryRepository::countNew(); ?>
    <a href="<?= url('admin/enquiries') ?>" class="<?= str_starts_with($r, 'admin/enquiries') ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9993;</span> Enquiries
      <?php if ($newEnquiries): ?><span class="adm-badge"><?= $newEnquiries ?></span><?php endif; ?></a>
    <a href="<?= url('admin/categories') ?>" class="<?= str_starts_with($r, 'admin/categories') ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9636;</span> Categories</a>
    <a href="<?= url('admin/origins') ?>" class="<?= str_starts_with($r, 'admin/origins') ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9673;</span> Origins</a>
    <a href="<?= url('admin/settings') ?>" class="<?= $r === 'admin/settings' ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9881;</span> Settings</a>
    <a href="<?= url('admin/password') ?>" class="<?= $r === 'admin/password' ? 'is-current' : '' ?>">
      <span class="ico" aria-hidden="true">&#9919;</span> Password</a>
  </nav>

  <main class="adm-main">
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>

<script src="<?= asset('js/admin.js') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/js/admin.js') ?>" defer></script>
</body>
</html>
