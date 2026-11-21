<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= __('Sign In —') ?><?= e(cfg('app.company')) ?></title>
<link rel="icon" href="<?= asset('assets/img/ctc-logo.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img src="<?= asset('assets/img/ctc-logo.svg') ?>" alt="CTC — Chishti Tobacco Company">
    <h1><?= __('JUSTI NUMBER ONE ERP') ?></h1>
    <p><?= __('Factory Management · Sales & Distribution · Accounting') ?><br><strong><?= __('Chishti Tobacco Company') ?></strong></p>
    <?php if (!empty($_GET['timeout'])): ?><div class="alert alert-warn"><?= __('Your session expired due to inactivity. Please sign in again.') ?></div><?php endif; ?>
    <?php if (!empty($_GET['blocked'])): ?><div class="alert alert-error"><?= __('This account is not allowed to sign in. This ERP is for the Owner/Admin, Manager and Accountant only.') ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('auth/login')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <label class="f" for="username"><?= __('Username') ?></label>
      <input class="f" id="username" name="username" required autofocus autocapitalize="none" autocomplete="username">
      <div class="mt"></div>
      <label class="f" for="password"><?= __('Password') ?></label>
      <input class="f" id="password" name="password" type="password" required autocomplete="current-password">
      <div class="mt"></div>
      <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;padding:11px;font-size:15px"><?= __('Sign In') ?></button>
    </form>
    <p class="help mt"><?= __('Authorized sign-in: Owner/Admin, Manager and Accountant only — All access is logged — Software support: CTC IT Department') ?></p>
  </div>
</div>
</body>
</html>
