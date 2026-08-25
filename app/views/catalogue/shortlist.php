<?php
/** @var array $errors @var array $old */
$ov = fn(string $k, string $default = '') => (string) ($old[$k] ?? $default);
?>
<div class="wrap shortlist-page">
<div class="page-head">
  <h1>Your shortlist</h1>
  <p class="page-sub"><?= e(setting('enquiry_intro', '')) ?></p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" role="alert">
    <p><strong>Your enquiry was not sent.</strong></p>
    <ul>
      <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php /* Everything below is rendered by site.js from the ids in localStorage.
         The empty state is the default markup rather than something scripted
         in afterwards, so a browser that never runs the script shows an honest
         "nothing here" page with a route back to the catalogue instead of a
         blank panel that looks broken. */ ?>
<div id="shortlist-empty" class="empty-state">
  <h2>Nothing shortlisted yet</h2>
  <p>Browse the catalogue and press &ldquo;Add to shortlist&rdquo; on anything
     you want a price for. Your shortlist is kept in this browser &mdash; there
     is no account to create.</p>
  <p><a class="btn btn-primary" href="<?= url('catalogue') ?>">Browse the catalogue</a></p>
</div>

<form id="shortlist-form" method="post" action="<?= url('enquiry') ?>" hidden>
  <?= csrf_field() ?>
  <?php /* The lines are serialised into this field on submit. They are posted
           as ids and free text only; the product names on the saved enquiry
           are read back from the database, never taken from here. */ ?>
  <input type="hidden" name="items" id="shortlist-payload" value="[]">

  <section class="sl-section">
    <div class="sl-head">
      <h2><span id="sl-count">0</span> item<span id="sl-plural">s</span> shortlisted</h2>
      <button type="button" class="btn btn-ghost btn-sm" id="sl-clear">Clear shortlist</button>
    </div>

    <ul class="sl-list" id="shortlist-rows"></ul>
  </section>

  <section class="sl-section">
    <h2>Where should we send the quote?</h2>
    <div class="form-grid">
      <div class="field <?= isset($errors['contact_name']) ? 'has-error' : '' ?>">
        <label for="contact_name">Your name <span class="req" aria-hidden="true">*</span></label>
        <input id="contact_name" name="contact_name" type="text" required
               autocomplete="name" value="<?= e($ov('contact_name')) ?>">
        <?php if (isset($errors['contact_name'])): ?><p class="err"><?= e($errors['contact_name']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="company">Company</label>
        <input id="company" name="company" type="text"
               autocomplete="organization" value="<?= e($ov('company')) ?>">
      </div>
      <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <label for="email">Email <span class="req" aria-hidden="true">*</span></label>
        <input id="email" name="email" type="email" required
               autocomplete="email" value="<?= e($ov('email')) ?>">
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
      <div class="field">
        <label for="destination">Destination port or city</label>
        <input id="destination" name="destination" type="text"
               placeholder="e.g. Vancouver" value="<?= e($ov('destination')) ?>">
      </div>
      <div class="field">
        <label for="incoterm">Preferred incoterm</label>
        <input id="incoterm" name="incoterm" type="text" list="incoterms"
               placeholder="e.g. FOB" value="<?= e($ov('incoterm')) ?>">
        <datalist id="incoterms">
          <?php foreach (['EXW','FCA','FOB','CFR','CIF','CPT','CIP','DAP','DPU','DDP'] as $ic): ?>
            <option value="<?= $ic ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
    </div>

    <div class="field">
      <label for="message">Anything else we should know?</label>
      <textarea id="message" name="message" rows="4"
                placeholder="Timelines, packing preferences, certification you need&hellip;"><?= e($ov('message')) ?></textarea>
    </div>

    <?php /* Honeypot. Hidden from people by CSS and from screen readers by
             aria-hidden, and tabindex="-1" keeps it out of the keyboard order
             so nobody using the form can land in it by accident. */ ?>
    <div class="hp" aria-hidden="true">
      <label for="website">Website</label>
      <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <div class="sl-submit">
      <button type="submit" class="btn btn-primary btn-lg">Send enquiry</button>
      <p class="filter-note">
        We use your details to answer this enquiry and nothing else.
        No prices are shown in the catalogue because they depend on volume,
        packing and incoterm &mdash; tell us what you need and we will quote it.
      </p>
    </div>
  </section>
</form>
</div>
