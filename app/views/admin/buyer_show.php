<?php
/** @var array $buyer @var ?array $issued @var string $suggestion @var array $enquiries */
$label = ['pending' => 'Waiting for review', 'approved' => 'Approved',
          'rejected' => 'Rejected', 'suspended' => 'Suspended'];
// Reuse the admin panel's existing semantic tag colours rather than adding
// four more named ones that would then need keeping in step.
$tagClass = ['pending' => 'tag-new', 'approved' => 'tag-ok',
             'rejected' => 'tag-bad', 'suspended' => 'tag-warn'];
?>
<div class="adm-head">
  <div>
    <p class="adm-sub"><a href="<?= url('admin/buyers') ?>">&larr; Buyer accounts</a></p>
    <h1><?= e($buyer['company'] ?: $buyer['contact_name']) ?></h1>
    <p class="adm-sub">
      <span class="tag <?= e($tagClass[$buyer['status']] ?? 'tag-muted') ?>">
        <?= e($label[$buyer['status']] ?? $buyer['status']) ?></span>
      &middot; <?= $buyer['reviewed_at'] ? 'requested' : 'signed up' ?>
      <?= e(date('j M Y \a\t H:i', strtotime((string) $buyer['created_at']))) ?>
      <?php if (!$buyer['reviewed_at'] && $buyer['status'] === 'approved'): ?>
        &middot; <span class="tag tag-info">created their own account</span>
      <?php endif; ?>
      <?php if ($buyer['last_login_at']): ?>
        &middot; last signed in <?= e(date('j M Y', strtotime((string) $buyer['last_login_at']))) ?>
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if ($issued): ?>
  <?php /* The one and only time this password is readable. It is not stored in
           the database in this form and cannot be shown again - the Reset
           password button issues a new one instead. */ ?>
  <section class="panel panel-credentials">
    <div class="panel-body">
      <h2><?= $issued['reason'] === 'reset' ? 'New password issued' : 'Account approved' ?></h2>
      <p>Send these to <strong><?= e($buyer['email']) ?></strong>. This is the only
         time the password is shown &mdash; it is stored scrambled, so not even
         this screen can show it to you again. If you lose it, press
         &ldquo;Reset password&rdquo; and issue a new one.</p>
      <dl class="kv">
        <dt>Sign in at</dt><dd class="mono"><?= e(absolute_url('account/login')) ?></dd>
        <dt>Username</dt><dd class="mono cred-value"><?= e($issued['username']) ?></dd>
        <dt>Password</dt><dd class="mono cred-value"><?= e($issued['password']) ?></dd>
      </dl>
      <p class="hint">They will be asked to choose their own password the first
         time they sign in.</p>
    </div>
  </section>
<?php endif; ?>

<div class="adm-cols">
  <div class="adm-col-main">

    <section class="panel">
      <div class="panel-body">
        <h2>Their request</h2>
        <dl class="kv">
          <dt>Contact</dt><dd><?= e($buyer['contact_name']) ?></dd>
          <dt>Company</dt><dd><?= e($buyer['company'] ?: '—') ?></dd>
          <dt>Email</dt><dd><?= e($buyer['email']) ?></dd>
          <dt>Phone</dt><dd><?= e($buyer['phone'] ?: '—') ?></dd>
          <dt>Country</dt><dd><?= e($buyer['country'] ?: '—') ?></dd>
          <?php if ($buyer['username']): ?>
            <dt>Username</dt><dd class="mono"><?= e($buyer['username']) ?></dd>
          <?php endif; ?>
        </dl>
        <?php if ($buyer['interest']): ?>
          <h3>What they are looking for</h3>
          <p class="rich"><?= nl2br(e($buyer['interest'])) ?></p>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($enquiries): ?>
      <section class="panel">
        <div class="panel-body">
          <h2>Enquiries from this address</h2>
          <table class="adm-table">
            <thead><tr><th>Reference</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
              <?php foreach ($enquiries as $en): ?>
                <tr>
                  <td class="mono"><a href="<?= url('admin/enquiries/' . $en['id']) ?>"><?= e($en['reference']) ?></a></td>
                  <td><?= e(ucfirst(str_replace('_', ' ', (string) $en['status']))) ?></td>
                  <td class="mono"><?= e(date('j M Y', strtotime((string) $en['created_at']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-body">
        <h2>Your notes</h2>
        <form method="post" action="<?= url('admin/buyers/notes') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
          <div class="field">
            <label for="admin_notes" class="sr-only">Internal notes</label>
            <textarea id="admin_notes" name="admin_notes" rows="4"
                      placeholder="Who they are, who referred them, anything worth remembering&hellip;"><?= e((string) $buyer['admin_notes']) ?></textarea>
            <p class="hint">Only ever visible here. The buyer never sees this.</p>
          </div>
          <button type="submit" class="btn btn-primary">Save notes</button>
        </form>
      </div>
    </section>
  </div>

  <aside class="adm-col-side">

    <?php if ($buyer['status'] === 'pending'): ?>
      <section class="panel">
        <div class="panel-body">
          <h2>Approve this request</h2>
          <p>This creates their login. Nothing is emailed automatically &mdash;
             the username and password appear on this screen and you send them
             yourself.</p>
          <form method="post" action="<?= url('admin/buyers/approve') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <div class="field">
              <label for="username">Username</label>
              <input id="username" name="username" type="text"
                     value="<?= e($suggestion) ?>" maxlength="50">
              <p class="hint">Suggested from their company name. Change it if you like.</p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Approve and issue a login</button>
          </form>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <h2>Turn it down</h2>
          <p>They are not told, and they cannot sign in.</p>
          <form method="post" action="<?= url('admin/buyers/status') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <input type="hidden" name="status" value="rejected">
            <button type="submit" class="btn btn-block">Reject</button>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($buyer['status'] === 'approved'): ?>
      <section class="panel">
        <div class="panel-body">
          <h2>Prices</h2>
          <?php if ((int) $buyer['pricing_access'] === 1): ?>
            <p>This buyer <strong>can</strong> see your price sheet figures on
               product pages.</p>
          <?php else: ?>
            <p>This buyer can browse and shortlist but <strong>cannot</strong>
               see any prices.</p>
          <?php endif; ?>
          <form method="post" action="<?= url('admin/buyers/pricing') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <input type="hidden" name="pricing_access"
                   value="<?= (int) $buyer['pricing_access'] === 1 ? '0' : '1' ?>">
            <button type="submit" class="btn btn-block"><?=
              (int) $buyer['pricing_access'] === 1
                ? 'Stop showing prices to this buyer'
                : 'Show prices to this buyer' ?></button>
          </form>
          <p class="hint">This only has any visible effect while
             <a href="<?= url('admin/settings') ?>">what signing in unlocks</a>
             is set to prices.</p>
        </div>
      </section>

      <section class="panel">
        <div class="panel-body">
          <h2>Manage access</h2>
          <form method="post" action="<?= url('admin/buyers/reset') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <button type="submit" class="btn btn-block">Reset password</button>
            <p class="hint">Issues a new one and shows it once. Their old
               password stops working straight away.</p>
          </form>
          <form method="post" action="<?= url('admin/buyers/status') ?>" style="margin-top:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <input type="hidden" name="status" value="suspended">
            <button type="submit" class="btn btn-block">Suspend access</button>
            <p class="hint">Signs them out on their next click and clears their
               password. You can let them back in later by approving again.</p>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <?php if (in_array($buyer['status'], ['rejected', 'suspended'], true)): ?>
      <section class="panel">
        <div class="panel-body">
          <h2>Let them back in</h2>
          <p>Approving again issues a fresh username and password.</p>
          <form method="post" action="<?= url('admin/buyers/approve') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
            <input type="hidden" name="username" value="<?= e((string) ($buyer['username'] ?: $suggestion)) ?>">
            <button type="submit" class="btn btn-primary btn-block">Approve and issue a login</button>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <section class="panel panel-danger">
      <div class="panel-body">
        <h2>Delete</h2>
        <p>Removes the account and everything they told us. Their enquiries are
           kept &mdash; those belong to your records, not to the login.</p>
        <form method="post" action="<?= url('admin/buyers/delete') ?>"
              onsubmit="return confirm('Delete this buyer account? This cannot be undone.');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $buyer['id'] ?>">
          <button type="submit" class="btn btn-danger btn-block">Delete account</button>
        </form>
      </div>
    </section>
  </aside>
</div>
