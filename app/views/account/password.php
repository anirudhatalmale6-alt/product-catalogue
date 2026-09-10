<?php /** @var array $user @var array $errors */ ?>
<div class="wrap narrow-page">

<div class="page-head">
  <h1>Change your password</h1>
  <?php if (!empty($user['must_change_password'])): ?>
    <p class="page-sub">You are signed in with the password we issued.
       Please choose one of your own.</p>
  <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <ul>
      <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= url('account/password') ?>" class="panel-form">
  <?= csrf_field() ?>

  <?php /* Skipped on the first visit: they would be retyping the generated
           password they used moments ago to get to this page. */ ?>
  <?php if (empty($user['must_change_password'])): ?>
    <div class="field <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password"
             required autocomplete="current-password">
      <?php if (isset($errors['current_password'])): ?><p class="err"><?= e($errors['current_password']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="field <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
    <label for="new_password">New password</label>
    <input id="new_password" name="new_password" type="password" required
           minlength="10" autocomplete="new-password">
    <p class="hint">At least 10 characters.</p>
    <?php if (isset($errors['new_password'])): ?><p class="err"><?= e($errors['new_password']) ?></p><?php endif; ?>
  </div>

  <div class="field <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
    <label for="confirm_password">Repeat the new password</label>
    <input id="confirm_password" name="confirm_password" type="password" required
           autocomplete="new-password">
    <?php if (isset($errors['confirm_password'])): ?><p class="err"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg">Save new password</button>
    <p class="hint"><a href="<?= url('account') ?>">Back to your account</a></p>
  </div>
</form>

</div>
