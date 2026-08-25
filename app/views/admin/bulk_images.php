<?php /** @var ?array $report @var array $sample @var int $missing */ ?>
<div class="adm-head">
  <div>
    <h1>Bulk image upload</h1>
    <p class="adm-sub"><?= $missing ?> product<?= $missing === 1 ? '' : 's' ?> still have no photograph.</p>
  </div>
  <div class="adm-head-actions">
    <a class="btn btn-ghost" href="<?= url('admin/products') ?>">Back to products</a>
  </div>
</div>

<?php if ($report): ?>
  <?php
    $m = count($report['matched']);
    $s = count($report['skipped']);
    $f = count($report['failed']);
  ?>
  <div class="alert alert-<?= $m && !$f ? 'success' : ($m ? 'warn' : 'error') ?>">
    <strong><?= $m ?> image<?= $m === 1 ? '' : 's' ?> attached.</strong>
    <?php if ($s): ?><?= $s ?> skipped.<?php endif; ?>
    <?php if ($f): ?><?= $f ?> failed.<?php endif; ?>
  </div>

  <?php // Every file is accounted for by name. A run that silently dropped
        // half the folder would look identical to one that worked. ?>
  <?php foreach ([['matched', 'Attached'], ['skipped', 'Skipped'], ['failed', 'Failed']] as [$key, $label]): ?>
    <?php if ($report[$key]): ?>
      <section class="panel">
        <div class="panel-head"><h2><?= $label ?> (<?= count($report[$key]) ?>)</h2></div>
        <div class="table-wrap">
          <table class="adm-table">
            <thead><tr><th>File</th><th><?= $key === 'matched' ? 'Product' : 'Reason' ?></th></tr></thead>
            <tbody>
              <?php foreach ($report[$key] as $line): ?>
                <tr>
                  <td class="mono"><?= e($line['file']) ?></td>
                  <td>
                    <?php if ($key === 'matched'): ?>
                      <a href="<?= url('admin/products/edit/' . (int) $line['id']) ?>"><?= e($line['product']) ?></a>
                    <?php else: ?>
                      <?= e($line['why']) ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>Upload a folder of photographs</h2></div>
  <div class="panel-body">
    <p>Each file is matched to a product by its filename. Three things are tried,
       in order: the exact SKU, the product's web address slug, then the product
       name with spaces and punctuation ignored. Case does not matter, and a
       trailing copy number is stripped &mdash; <span class="mono">Fresh Young Coconut (2).jpg</span>
       and <span class="mono">fresh-young-coconut.JPG</span> both find the same product.</p>

    <p class="muted">Every image is re-encoded on the server before it is saved,
       the same as a single upload, so nothing hidden inside a file survives.
       Accepted: JPEG, PNG, GIF and WebP.</p>

    <form id="bulk-image-form" method="post" action="<?= url('admin/products/bulk-images') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label for="images">Images</label>
        <input id="images" name="images[]" type="file" multiple
               accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="hint">Select as many as you like. Large batches can take a
           while &mdash; if the page times out, send them in smaller groups.</p>
      </div>
      <div class="field field-check">
        <label>
          <input type="checkbox" name="replace_existing" value="1">
          Replace images on products that already have one
        </label>
        <p class="hint">Off by default, so a second run only fills the gaps
           rather than overwriting photographs you have already sorted out.</p>
      </div>
      <button type="submit" class="btn btn-primary">Upload and match</button>
    </form>
  </div>
</section>

<?php if ($sample): ?>
<section class="panel">
  <div class="panel-head"><h2>Filenames that would match</h2></div>
  <div class="panel-body">
    <p class="muted">A sample of products still waiting for a photograph, with
       the filename to use. Any image extension works.</p>
    <div class="table-wrap">
      <table class="adm-table">
        <thead><tr><th>Product</th><th>Name the file</th></tr></thead>
        <tbody>
          <?php foreach ($sample as $p): ?>
            <tr>
              <td><?= e($p['name']) ?></td>
              <td class="mono"><?= e($p['sku'] ?: $p['slug']) ?>.jpg</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>
