<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>?v=<?= @filemtime(PUBLIC_DIR . '/assets/css/admin.css') ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="admin admin-bare">
<?= $content ?>
</body>
</html>
