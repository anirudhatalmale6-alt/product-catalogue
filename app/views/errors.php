<div class="wrap">
  <div class="empty tall">
    <h1><?= e($title ?? 'Error') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <a class="btn btn-primary" href="<?= url('catalogue') ?>">Back to the catalogue</a>
  </div>
</div>
