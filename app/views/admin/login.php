<div class="login-wrap">
  <form class="login-card" method="post" action="<?= url('admin/login') ?>">
    <?= csrf_field() ?>
    <div class="login-head">
      <img class="adm-mark" src="<?= asset('img/favicon.png') ?>" alt="" width="64" height="64">
      <h1><?= e(setting('site_name', 'Catalogue')) ?></h1>
      <p>Sign in to manage the catalogue</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <label for="username">Username</label>
    <input id="username" name="username" type="text" required autofocus autocomplete="username"
           value="<?= e($_POST['username'] ?? '') ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">

    <button type="submit" class="btn btn-primary btn-block">Sign in</button>

    <p class="login-foot"><a href="<?= url('catalogue') ?>">&larr; Back to the catalogue</a></p>
  </form>
</div>
