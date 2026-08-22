<div class="adm-head">
  <div>
    <h1>Change password</h1>
    <p class="adm-sub">Signed in as <strong><?= e(Auth::user()['username']) ?></strong>.</p>
  </div>
</div>

<form method="post" action="<?= url('admin/password') ?>" class="adm-form narrow">
  <?= csrf_field() ?>
  <section class="panel">
    <div class="panel-body">
      <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" required minlength="10" autocomplete="new-password">
        <p class="hint">At least 10 characters. A short phrase you can remember beats a scrambled word.</p>
      </div>
      <div class="field">
        <label for="confirm_password">Repeat new password</label>
        <input id="confirm_password" name="confirm_password" type="password" required minlength="10" autocomplete="new-password">
      </div>
    </div>
  </section>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Change password</button>
  </div>
</form>
