<?php /* Shown instead of the catalogue when Settings has "what signing in
         unlocks" set to the whole catalogue. A real page rather than a
         redirect, so a product link a buyer shares still lands somewhere that
         explains itself. */ ?>
<div class="wrap narrow-page">
  <div class="empty-state">
    <h1>Trade access required</h1>
    <p><?= e(setting('buyer_intro', '')) ?></p>
    <p class="form-actions">
      <a class="btn btn-primary btn-lg" href="<?= url('account/login') ?>">Sign in</a>
      <a class="btn btn-ghost btn-lg" href="<?= url('request-access') ?>">Request access</a>
    </p>
    <?php if (setting('contact_email') || setting('contact_phone')): ?>
      <p class="hint">Or contact us directly:
        <?php if (setting('contact_email')): ?><strong><?= e(setting('contact_email')) ?></strong><?php endif; ?>
        <?php if (setting('contact_email') && setting('contact_phone')): ?> &middot; <?php endif; ?>
        <?php if (setting('contact_phone')): ?><strong><?= e(setting('contact_phone')) ?></strong><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</div>
