<?php
/** @var ShopifyClient $client @var bool $configured @var ?array $connection
 *  @var int $cached @var ?string $lastSync @var array $linked
 *  @var array $suggestions @var array $shopifyRows @var array $unlinked
 *  @var string $q @var ?array $report */
?>
<div class="adm-head">
  <div>
    <h1>Shopify</h1>
    <p class="adm-sub">
      The consumer shop. This screen only ever <strong>reads</strong> from
      Shopify &mdash; nothing here can change your store.
    </p>
  </div>
</div>

<?php if (!$configured): ?>
  <section class="panel panel-danger">
    <div class="panel-body">
      <h2>Not connected yet</h2>
      <p>Shopify needs two values, and they live in the file
         <code>app/config.php</code> on the server rather than in this panel,
         because the token is a key to your shop and this panel's settings end
         up in every database backup.</p>
      <p>Send them over and they take about a minute to put in:</p>
      <ol>
        <li>Your store address, the <code>something.myshopify.com</code> one.</li>
        <li>An Admin API access token. In Shopify: <strong>Settings</strong> &rarr;
            <strong>Apps and sales channels</strong> &rarr;
            <strong>Develop apps</strong> &rarr; <strong>Create an app</strong> &rarr;
            <strong>Configure Admin API scopes</strong> &rarr; tick
            <code>read_products</code> and <code>read_inventory</code> &rarr;
            <strong>Save</strong> &rarr; <strong>Install app</strong> &rarr;
            <strong>Reveal token</strong>.</li>
      </ol>
      <p class="hint">Read permissions only. Nothing in this catalogue writes to
         Shopify, so a write scope would be a risk with no benefit.</p>
    </div>
  </section>
<?php else: ?>

  <section class="panel">
    <div class="panel-body">
      <h2>Connection</h2>
      <?php if ($connection && !empty($connection['ok'])): ?>
        <dl class="kv">
          <dt>Store</dt><dd><?= e($connection['name']) ?></dd>
          <dt>Domain</dt><dd class="mono"><?= e($connection['domain']) ?></dd>
          <?php if (!empty($connection['currency'])): ?>
            <dt>Currency</dt><dd><?= e($connection['currency']) ?></dd>
          <?php endif; ?>
          <dt>Products held</dt><dd><?= (int) $cached ?></dd>
          <dt>Last sync</dt>
          <dd><?= $lastSync ? e(date('j M Y \a\t H:i', strtotime($lastSync))) : 'never' ?></dd>
        </dl>
      <?php else: ?>
        <div class="alert alert-error">
          <p><strong>Shopify would not answer.</strong></p>
          <p><?= e($connection['message'] ?? 'Unknown error') ?></p>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('admin/shopify/sync') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Sync from Shopify now</button>
        <p class="hint">Reads your Shopify products, prices and stock into this
           panel. It runs on its own every night as well.</p>
      </form>

      <?php if ($report): ?>
        <p class="hint mono">
          <?= e($report['message']) ?>
          (<?= (int) $report['calls'] ?> call<?= $report['calls'] === 1 ? '' : 's' ?>)
        </p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($suggestions): ?>
    <section class="panel">
      <div class="panel-body">
        <h2><?= count($suggestions) ?> possible match<?= count($suggestions) === 1 ? '' : 'es' ?></h2>
        <p>These catalogue products have exactly the same name as a Shopify
           product. Nothing has been linked &mdash; matching on a name is a
           guess, so it is offered rather than applied.</p>
        <table class="adm-table">
          <thead><tr><th>Catalogue</th><th>Shopify</th></tr></thead>
          <tbody>
            <?php foreach ($suggestions as $s): ?>
              <tr>
                <td><?= e($s['product_name']) ?></td>
                <td><?= e($s['shopify_title']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <form method="post" action="<?= url('admin/shopify/suggestions') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary">Link all <?= count($suggestions) ?></button>
          <p class="hint">You can unlink any of them again below.</p>
        </form>
      </div>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php /* Shown regardless of whether the token is currently configured. If it
         is removed - moved server, key rotated - the links and the last known
         figures still exist, and a screen that hid them would look like the
         work of connecting everything up had been lost. */ ?>
<?php if ($linked || $shopifyRows): ?>
  <section class="panel">
    <div class="panel-body">
      <h2>Linked products (<?= count($linked) ?>)</h2>
      <?php if (!$linked): ?>
        <p>Nothing is linked yet, so no product on the site shows a buy button.
           Use the list below to connect the items you hold stock for.</p>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr><th>Catalogue product</th><th>Shopify product</th><th>Price</th>
                <th>Stock</th><th>On sale</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($linked as $l): ?>
              <tr>
                <td><strong><?= e($l['name']) ?></strong></td>
                <td>
                  <?php if ($l['cached'] === null): ?>
                    <span class="tag tag-bad">no longer in Shopify</span>
                  <?php else: ?>
                    <?= e($l['shopify_title']) ?>
                  <?php endif; ?>
                </td>
                <td class="mono"><?= $l['price'] !== null ? e($l['currency'] . ' ' . $l['price']) : '—' ?></td>
                <td class="mono"><?= $l['inventory_qty'] === null ? 'not tracked' : (int) $l['inventory_qty'] ?></td>
                <td>
                  <span class="tag <?= !empty($l['is_available']) ? 'tag-ok' : 'tag-muted' ?>">
                    <?= !empty($l['is_available']) ? 'yes' : 'no' ?></span>
                </td>
                <td>
                  <form method="post" action="<?= url('admin/shopify/link') ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $l['id'] ?>">
                    <input type="hidden" name="shopify_product_id" value="">
                    <button type="submit" class="btn btn-ghost btn-sm">Unlink</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <h2>Your Shopify products (<?= count($shopifyRows) ?>)</h2>
      <form class="adm-filters" method="get" action="<?= url('admin/shopify') ?>">
        <div class="field">
          <label for="q">Search</label>
          <input id="q" name="q" type="search" value="<?= e($q) ?>"
                 placeholder="Shopify product name or SKU">
        </div>
        <div class="field field-actions">
          <button type="submit" class="btn btn-primary">Search</button>
          <a class="btn btn-ghost" href="<?= url('admin/shopify') ?>">Reset</a>
        </div>
      </form>

      <?php if (!$shopifyRows): ?>
        <p>Nothing here yet. Press <strong>Sync from Shopify now</strong> above.</p>
      <?php else: ?>
        <table class="adm-table">
          <thead>
            <tr><th>Shopify product</th><th>SKU</th><th>Price</th>
                <th>Stock</th><th>Linked to</th></tr>
          </thead>
          <tbody>
            <?php foreach ($shopifyRows as $r): ?>
              <tr>
                <td><strong><?= e($r['title']) ?></strong></td>
                <td class="mono"><?= e($r['sku'] ?: '—') ?></td>
                <td class="mono"><?= $r['price'] !== null ? e($r['currency'] . ' ' . $r['price']) : '—' ?></td>
                <td class="mono"><?= $r['inventory_qty'] === null ? 'not tracked' : (int) $r['inventory_qty'] ?></td>
                <td>
                  <?php if ($r['linked_product_id']): ?>
                    <?= e($r['linked_product_name']) ?>
                  <?php else: ?>
                    <form method="post" action="<?= url('admin/shopify/link') ?>" class="shopify-link-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="shopify_product_id"
                             value="<?= (int) $r['shopify_product_id'] ?>">
                      <select name="product_id" aria-label="Link to catalogue product">
                        <option value="">Link to&hellip;</option>
                        <?php foreach ($unlinked as $u): ?>
                          <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-sm">Link</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>
