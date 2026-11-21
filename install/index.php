<?php
/**
 * JUSTI NUMBER ONE ERP — Web Installer (Hostinger / shared hosting)
 *
 * Security model:
 *  - Refuses to run once config/config.php exists OR install/.installed exists.
 *  - Creates the database schema on YOUR Hostinger MySQL database.
 *  - Creates the first OWNER account with a strong password hash.
 *  - Writes config/config.php locally (never inside public version control).
 *  - After installing, DELETE this install/ directory from the server.
 */

const ROOT = __DIR__ . '/..';
const LOCK = __DIR__ . '/.installed';
$errors = [];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$installed = file_exists(LOCK) || file_exists(ROOT . '/config/config.php');

/* ---------- Step handlers ---------- */
if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'install') {
    $dbHost = 'localhost';
    $dbPort = 3306;
    $dbName = trim($_POST['db_name'] ?? '') ?: 'u997441419_software';
    $dbUser = trim($_POST['db_user'] ?? '') ?: 'u997441419_software';
    $dbPass = $_POST['db_pass'] ?? '';
    $company = trim($_POST['company'] ?? 'Chishti Tobacco Company');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';
    $adminPass2 = $_POST['admin_password_confirm'] ?? '';
    $useHttps = isset($_POST['https']);

    if ($dbPass === '') $errors[] = 'Database password is required.';
    if (strlen($adminPass) < 8) $errors[] = 'Owner password must be at least 8 characters.';
    if ($adminPass !== $adminPass2) $errors[] = 'Owner passwords do not match.';
    if (!preg_match('/^[a-zA-Z0-9_-]{3,60}$/', $adminUser)) $errors[] = 'Owner username: 3–60 letters/digits/_/-';

    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            // 1) Clean previous half-installed tables and run fresh schema
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existing as $t) {
                $pdo->exec("DROP TABLE IF EXISTS `{$t}`;");
            }
            $sql = file_get_contents(ROOT . '/database/schema.mysql.sql');
            $statements = preg_split('/;\s*(\r?\n|$)/', $sql);
            foreach ($statements as $st) {
                $st = trim($st);
                if ($st === '') continue;
                $pdo->exec($st);
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            // 2) Base seed (roles, permissions, chart of accounts, FY, branch, warehouses, cash/bank)
            $GLOBALS['CONFIG'] = ['db' => ['driver' => 'mysql']]; // seed.php needs a callable db(); provide via Database class
            require_once ROOT . '/app/core/Database.php';
            require_once ROOT . '/app/core/helpers.php';
            require_once ROOT . '/database/seed.php';
            Database::setPdo($pdo);
            seed_base($pdo);
            // 3) Owner account
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO users (username, full_name, password_hash, is_active, created_at) VALUES (?,?,?,1,?)');
            $ins->execute([$adminUser, $adminName ?: $adminUser, $hash, date('Y-m-d H:i:s')]);
            $uid = (int)$pdo->lastInsertId();
            $ownerRoleId = (int)$pdo->query("SELECT id FROM roles WHERE code = 'owner'")->fetchColumn();
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$uid, $ownerRoleId]);
            // 4) config/config.php
            $sample = file_get_contents(ROOT . '/config/config.sample.php');
            $sample = str_replace("'host'    => 'localhost'", "'host'    => '" . addslashes($dbHost) . "'", $sample);
            $sample = str_replace("'port'    => 3306", "'port'    => " . $dbPort, $sample);
            $sample = preg_replace("/'name'\s*=>\s*'[^']*'/", "'name'    => '" . addslashes($dbName) . "'", $sample);
            $sample = preg_replace("/'user'\s*=>\s*'[^']*'/", "'user'    => '" . addslashes($dbUser) . "'", $sample);
            $sample = preg_replace("/'pass'\s*=>\s*'[^']*'/", "'pass'    => '" . addslashes($dbPass) . "'", $sample);
            $sample = str_replace("'company'   => 'Chishti Tobacco Company'", "'company'   => '" . addslashes($company) . "'", $sample);
            $sample = str_replace("'https'     => true", "'https'     => " . ($useHttps ? 'true' : 'false'), $sample);
            if (file_put_contents(ROOT . '/config/config.php', $sample, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config/config.php — check folder permissions (755).');
            }
            file_put_contents(LOCK, date('c'), LOCK_EX);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

/* ---------- Requirements (always computed for display) ---------- */
$reqs = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'mbstring extension' => extension_loaded('mbstring'),
    'config/ writable' => is_writable(ROOT . '/config') || !file_exists(ROOT . '/config/config.php'),
    'storage/ writable' => is_writable(ROOT . '/storage'),
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — JUSTI NUMBER ONE ERP</title>
<link rel="icon" href="../assets/img/ctc-logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div class="login-wrap" style="align-items:flex-start;padding-top:40px">
  <div class="login-card" style="max-width:560px;text-align:left">
    <img src="../assets/img/ctc-logo.svg" alt="CTC" style="width:150px">
    <h1 style="font-size:17px">Install JUSTI NUMBER ONE ERP</h1>
    <p class="muted">Chishti Tobacco Company — Factory Management, Sales &amp; Distribution, Accounting</p>

<?php if ($installed): ?>
    <div class="alert alert-error"><strong>Installer locked.</strong><br>config/config.php or install/.installed already exists. For security, installation cannot be run again. Delete the <code>install/</code> directory from your server.</div>
    <a class="btn btn-primary" href="../index.php">Go to the application</a>
<?php elseif (!empty($done)): ?>
    <div class="alert alert-success"><strong>✔ Installation complete!</strong><br>Database tables created, default chart of accounts loaded, owner account created.</div>
    <ol>
      <li><strong>Delete the <code>install/</code> folder from your hosting account</strong> (File Manager → public_html/install → Delete).</li>
      <li>Confirm HTTPS is working (padlock) at <code>https://software.chishtitobaccocompany.com</code>.</li>
      <li>Sign in with your owner account, then <strong>change your password</strong> in Users &amp; Roles.</li>
      <li>Review Settings → Company Profile and upload your official logo.</li>
      <li>Enter real opening balances via Settings → Opening Balances and each master form. Do not use demo data in production.</li>
    </ol>
    <a class="btn btn-primary" href="../index.php">Sign in to the ERP</a>
<?php else: ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= h($e) ?></div><?php endforeach; ?>

    <h3 style="font-size:13px;margin:10px 0 6px">1 · Server requirements</h3>
    <ul class="list-clean">
      <?php foreach ($reqs as $label => $ok): ?>
      <li><span><?= h($label) ?></span><span class="badge <?= $ok ? 'green' : 'red' ?>"><?= $ok ? 'OK' : 'MISSING' ?></span></li>
      <?php endforeach; ?>
    </ul>

    <h3 style="font-size:13px;margin:14px 0 6px">2 · Database &amp; owner account</h3>
    <form method="post" action="">
      <input type="hidden" name="action" value="install">
      <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div style="grid-column:1/-1;background:#f8fafc;padding:12px 16px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;line-height:1.6">
          <strong style="color:#0f172a">Database Details (Auto-Configured):</strong><br>
          Host: <code>localhost</code> &bull; Database: <code>u997441419_software</code> &bull; User: <code>u997441419_software</code>
        </div>
        <input type="hidden" name="db_host" value="localhost">
        <input type="hidden" name="db_name" value="u997441419_software">
        <input type="hidden" name="db_user" value="u997441419_software">
        <input type="hidden" name="db_port" value="3306">
        <div style="grid-column:1/-1">
          <label class="f" style="font-weight:700">Hostinger Database Password *</label>
          <input class="f" name="db_pass" type="password" placeholder="Enter Hostinger MySQL Password" required autocomplete="new-password">
        </div>
        <div style="grid-column:1/-1"><label class="f">Company Name</label><input class="f" name="company" value="Chishti Tobacco Company"></div>
        <div><label class="f">Owner Full Name *</label><input class="f" name="admin_name" value="Haseeb Chishti" required></div>
        <div><label class="f">Owner Username *</label><input class="f" name="admin_username" value="admin" required></div>
        <div><label class="f">Owner Password * (min 8)</label><input class="f" name="admin_password" type="password" required autocomplete="new-password"></div>
        <div><label class="f">Confirm Password *</label><input class="f" name="admin_password_confirm" type="password" required autocomplete="new-password"></div>
        <div style="grid-column:1/-1"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="https" checked> Site uses HTTPS (recommended — required for secure cookies)</label></div>
      </div>
      <div class="mt"></div>
      <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Run Installation</button>
    </form>
    <p class="help mt">The installer creates all tables, seeds roles, permissions, the chart of accounts and the current fiscal year, and creates your owner account. Your database password is stored only in <code>config/config.php</code> on the server — never in version control.</p>
<?php endif; ?>
  </div>
</div>
</body>
</html>
