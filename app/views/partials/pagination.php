<?php
/** @var array $result */
if ($result['pages'] <= 1) { return; }
$page  = $result['page'];
$pages = $result['pages'];
$window = 2;
$from = max(1, $page - $window);
$to   = min($pages, $page + $window);
?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($page > 1): ?>
    <a class="page-link" href="<?= with_query(['page' => $page - 1]) ?>" rel="prev">&larr; Prev</a>
  <?php else: ?>
    <span class="page-link is-disabled">&larr; Prev</span>
  <?php endif; ?>

  <?php if ($from > 1): ?>
    <a class="page-link" href="<?= with_query(['page' => 1]) ?>">1</a>
    <?php if ($from > 2): ?><span class="page-gap">&hellip;</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $from; $i <= $to; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="page-link is-current" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a class="page-link" href="<?= with_query(['page' => $i]) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($to < $pages): ?>
    <?php if ($to < $pages - 1): ?><span class="page-gap">&hellip;</span><?php endif; ?>
    <a class="page-link" href="<?= with_query(['page' => $pages]) ?>"><?= $pages ?></a>
  <?php endif; ?>

  <?php if ($page < $pages): ?>
    <a class="page-link" href="<?= with_query(['page' => $page + 1]) ?>" rel="next">Next &rarr;</a>
  <?php else: ?>
    <span class="page-link is-disabled">Next &rarr;</span>
  <?php endif; ?>
</nav>
