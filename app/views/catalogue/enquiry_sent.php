<?php /** @var ?string $reference */ ?>
<div class="wrap">
<div class="empty-state sent-state">
  <h1>Enquiry sent</h1>
  <?php if ($reference): ?>
    <p class="sent-ref">Your reference is <strong><?= e($reference) ?></strong></p>
  <?php endif; ?>
  <p>Thank you &mdash; we have your shortlist and will come back to you with
     pricing, packing options and lead times.</p>
  <?php if (setting('contact_email') || setting('contact_phone')): ?>
    <p class="muted">If it is urgent, reach us on
      <?php if (setting('contact_email')): ?><strong><?= e(setting('contact_email')) ?></strong><?php endif; ?>
      <?php if (setting('contact_email') && setting('contact_phone')): ?> &middot; <?php endif; ?>
      <?php if (setting('contact_phone')): ?><strong><?= e(setting('contact_phone')) ?></strong><?php endif; ?>
      <?php if ($reference): ?>and quote <?= e($reference) ?><?php endif; ?>.
    </p>
  <?php endif; ?>
  <p><a class="btn btn-primary" href="<?= url('catalogue') ?>">Back to the catalogue</a></p>
</div>

<?php /* The shortlist is cleared here rather than on submit, so a buyer whose
         enquiry bounced back with a validation error still has their list. The
         flag is read by site.js on load. */ ?>
<div id="clear-shortlist" hidden></div>
</div>
