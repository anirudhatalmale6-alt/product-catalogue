<?php /** @var array $user @var array $enquiries */ ?>
<div class="wrap narrow-page">

<div class="page-head">
  <h1>Your account</h1>
  <p class="page-sub">Signed in as <?= e($user['username']) ?>.</p>
</div>

<?php foreach (take_flashes() as $f): ?>
  <div class="alert <?= $f['type'] === 'error' ? 'alert-error' : 'alert-ok' ?>" role="status">
    <p><?= e($f['message']) ?></p>
  </div>
<?php endforeach; ?>

<?php if (!empty($user['must_change_password'])): ?>
  <div class="alert alert-error" role="alert">
    <p>You are still using the password we issued you.
       <a href="<?= url('account/password') ?>">Choose your own</a>.</p>
  </div>
<?php endif; ?>

<?php if (BuyerAuth::gate() === 'prices'): ?>
  <div class="alert alert-ok">
    <p>Your account has pricing access, so product pages show the current
       indicative price where one has been set.</p>
  </div>
<?php endif; ?>

<section class="acct-section">
  <h2>Your details</h2>
  <dl class="acct-details">
    <dt>Company</dt><dd><?= e($user['company'] ?: '—') ?></dd>
    <dt>Contact</dt><dd><?= e($user['contact_name']) ?></dd>
    <dt>Email</dt><dd><?= e($user['email']) ?></dd>
    <?php if ($user['phone']): ?><dt>Phone</dt><dd><?= e($user['phone']) ?></dd><?php endif; ?>
    <?php if ($user['country']): ?><dt>Country</dt><dd><?= e($user['country']) ?></dd><?php endif; ?>
  </dl>
  <p class="hint">Need any of this changed? Send us a message and we will update it.</p>
</section>

<section class="acct-section">
  <h2>Your enquiries</h2>
  <?php if (!$enquiries): ?>
    <p>You have not sent any enquiries yet. Shortlist what you are interested in
       and send it over &mdash; they will be listed here afterwards.</p>
    <p><a class="btn btn-primary" href="<?= url('catalogue') ?>">Browse the catalogue</a></p>
  <?php else: ?>
    <?php /* Matched on the email address of this account, so an enquiry sent
             before the account existed still shows up here as long as the same
             address was used. */ ?>
    <table class="acct-table">
      <thead>
        <tr><th scope="col">Reference</th><th scope="col">Sent</th>
            <th scope="col">Items</th><th scope="col">Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($enquiries as $en): ?>
          <tr>
            <td><?= e($en['reference']) ?></td>
            <td><?= e(date('j M Y', strtotime((string) $en['created_at']))) ?></td>
            <td><?= (int) $en['item_count'] ?></td>
            <td><?= e(ucfirst(str_replace('_', ' ', (string) $en['status']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="acct-section">
  <h2>Account</h2>
  <p><a href="<?= url('account/password') ?>">Change your password</a></p>
  <form method="post" action="<?= url('account/logout') ?>" class="inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-ghost">Sign out</button>
  </form>
</section>

</div>
