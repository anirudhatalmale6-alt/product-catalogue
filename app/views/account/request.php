<?php
/** @var array $errors @var array $old */
$ov = fn(string $k, string $default = '') => (string) ($old[$k] ?? $default);
?>
<div class="wrap narrow-page">

<div class="page-head">
  <h1>Request trade access</h1>
  <p class="page-sub"><?= e(setting('buyer_intro', '')) ?></p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <p><strong>Your request was not sent.</strong></p>
    <ul>
      <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= url('request-access') ?>" class="panel-form">
  <?= csrf_field() ?>

  <div class="form-grid">
    <div class="field <?= isset($errors['contact_name']) ? 'has-error' : '' ?>">
      <label for="contact_name">Your name <span class="req" aria-hidden="true">*</span></label>
      <input id="contact_name" name="contact_name" type="text" required
             autocomplete="name" value="<?= e($ov('contact_name')) ?>">
      <?php if (isset($errors['contact_name'])): ?><p class="err"><?= e($errors['contact_name']) ?></p><?php endif; ?>
    </div>

    <div class="field <?= isset($errors['company']) ? 'has-error' : '' ?>">
      <label for="company">Company <span class="req" aria-hidden="true">*</span></label>
      <input id="company" name="company" type="text" required
             autocomplete="organization" value="<?= e($ov('company')) ?>">
      <?php if (isset($errors['company'])): ?><p class="err"><?= e($errors['company']) ?></p><?php endif; ?>
    </div>

    <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
      <label for="email">Work email <span class="req" aria-hidden="true">*</span></label>
      <input id="email" name="email" type="email" required
             autocomplete="email" value="<?= e($ov('email')) ?>">
      <p class="hint">Your login will be sent to this address once your request is approved.</p>
      <?php if (isset($errors['email'])): ?><p class="err"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="phone">Phone</label>
      <input id="phone" name="phone" type="tel"
             autocomplete="tel" value="<?= e($ov('phone')) ?>">
    </div>

    <div class="field">
      <label for="country">Country</label>
      <input id="country" name="country" type="text"
             autocomplete="country-name" value="<?= e($ov('country')) ?>">
    </div>
  </div>

  <div class="field">
    <label for="interest">What are you looking to source?</label>
    <textarea id="interest" name="interest" rows="4"
              placeholder="Product lines, rough volumes, destination market&hellip;"><?= e($ov('interest')) ?></textarea>
    <p class="hint">Not required, but it helps us review your request faster.</p>
  </div>

  <?php /* Honeypot. Hidden from people, tempting to a bot that fills in every
           input it can find. Anything typed here means the submission is
           discarded quietly. */ ?>
  <div class="hp" aria-hidden="true">
    <label for="website">Website</label>
    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary btn-lg">Send request</button>
    <p class="hint">Already have a login?
      <a href="<?= url('account/login') ?>">Sign in here</a>.</p>
  </div>
</form>

</div>
