<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= __('Admin Console — Chishti Tobacco Company') ?></title><link rel="icon" href="<?= asset('assets/img/ctc-logo.svg') ?>" type="image/svg+xml"><link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>"></head>
<body>
<div class="login-wrap" style="background:radial-gradient(1200px 600px at 70% -10%,#3a1414 0%,#221010 50%,#0d0d0d 100%)">
  <div class="login-card" style="border-top:6px solid #c0392b">
    <img src="<?= asset('assets/img/ctc-logo.svg') ?>" alt="CTC">
    <h1 style="color:#8a2a1d"><?= __('ADMIN CONSOLE') ?></h1>
    <p><?= __('Chishti Tobacco Company · Owner & Admin only') ?><br><?= __('Every outgoing payment requires your approval.') ?></p>
    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('admin/login')) ?>" autocomplete="off"><?= csrf_field() ?>
      <label class="f" for="username"><?= __('Username') ?></label>
      <input class="f" id="username" name="username" required autofocus autocapitalize="none">
      <div class="mt"></div>
      <label class="f" for="password"><?= __('Password') ?></label>
      <input class="f" id="password" name="password" type="password" required>
      <div class="mt"></div>
      <button class="btn" type="submit" style="width:100%;justify-content:center;padding:11px;font-size:15px;background:#8a2a1d;color:#fff"><?= __('Sign in to Admin Console') ?></button>
    </form>
    <p class="help mt"><?= __('Authorized Owner/Admin personnel only · All access is logged ·') ?><a href="<?= e(url('auth/login')) ?>"><?= __('Staff sign-in →') ?></a></p>
  </div>
</div>
</body></html>
