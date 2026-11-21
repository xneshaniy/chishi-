<!DOCTYPE html><html><head><meta charset="utf-8"><title><?= __('Access denied (403)') ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>"></head>
<body><div class="content"><div class="panel"><h2><?= __('403 — Access denied') ?></h2>
<p><?= __('You don\'t have permission to do that (') ?><code><?= e($perm ?? '') ?></code><?= __('). Contact your administrator if you believe this is wrong.') ?></p>
<a class="btn btn-primary" href="<?= e(url('dashboard')) ?>"><?= __('Back to Dashboard') ?></a></div></div></body></html>
