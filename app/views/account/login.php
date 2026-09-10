<?php /** @var ?string $error */ ?>
<div class="wrap narrow-page">

<div class="page-head">
  <h1>Buyer sign in</h1>
  <p class="page-sub">For approved trade accounts.</p>
</div>

<?php if ($error): ?>
  <div class="alert alert-error" role="alert"><p><?= e($error) ?></p></div>
<?php endif; ?>

<form method="post" action="<?= url('account/login') ?>" class="panel-form">
  <?= csrf_field() ?>

  <div class="field">
    <label for="login">Username or email</label>
    <input id="login" name="login" type="text" required autocomplete="username"
           value="<?= e((string) ($_POST['login'] ?? '')) ?>">
  </div>

  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required
           autocomplete="current-password">
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
    <p class="hint">No account yet?
      <a href="<?= url('request-access') ?>">Request trade access</a>.
      Forgotten your password? Get in touch and we will issue a new one.</p>
  </div>
</form>

</div>
