<?php
/**
 * Captcha Manager — admin UI (username/password login)
 * CRUD for the central captcha_map (domain -> reCAPTCHA key).
 * Credentials live in config.php (kept out of git).
 */
session_start();
require __DIR__ . '/config.php';

// ---- logout ----
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: index.php');
    exit;
}

// ---- login ----
$login_error = '';
if (($_POST['action'] ?? '') === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if ($u === CAPTCHA_ADMIN_USER && password_verify($p, CAPTCHA_ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['captcha_admin'] = $u;
    } else {
        $login_error = 'Invalid username or password.';
    }
}

$is_logged_in = !empty($_SESSION['captcha_admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// ---- login screen ----
if (!$is_logged_in) {
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Captcha Manager — Login</title>
    <style>
     body{font:14px system-ui,Arial;background:#f3f4f6;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;color:#1f2937}
     .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08);width:320px}
     h1{font-size:18px;margin:0 0 16px} label{display:block;margin:12px 0 4px;font-size:13px}
     input{width:100%;padding:9px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px}
     button{margin-top:18px;width:100%;padding:10px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;font-size:14px}
     .err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:8px;border-radius:6px;font-size:13px;margin-bottom:8px}
    </style></head><body>
    <form class="card" method="post">
      <h1>Captcha Manager</h1>
      <?php if($login_error): ?><div class="err"><?=h($login_error)?></div><?php endif; ?>
      <input type="hidden" name="action" value="login">
      <label>Username</label><input name="username" autofocus>
      <label>Password</label><input name="password" type="password">
      <button type="submit">Log in</button>
    </form></body></html>
    <?php
    exit;
}

// ---- logged in: DB + CRUD ----
$pdo = new PDO("mysql:host=".CAPTCHA_DB_HOST.";dbname=".CAPTCHA_DB_NAME.";charset=utf8mb4",
    CAPTCHA_DB_USER, CAPTCHA_DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $site   = trim($_POST['site_key'] ?? '');
        $secret = trim($_POST['secret_key'] ?? '');
        $label  = trim($_POST['key_label'] ?? '');
        if ($domain && $site && $secret) {
            $stmt = $pdo->prepare(
                'INSERT INTO captcha_map (domain, site_key, secret_key, key_label)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE site_key=VALUES(site_key), secret_key=VALUES(secret_key), key_label=VALUES(key_label)'
            );
            $stmt->execute(array($domain, $site, $secret, $label));
            $msg = "Saved: $domain";
        } else {
            $msg = 'Domain, site key and secret key are all required.';
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $pdo->prepare('DELETE FROM captcha_map WHERE id = ?')->execute(array((int)$_POST['id']));
        $msg = 'Deleted.';
    }
}

$rows   = $pdo->query('SELECT * FROM captcha_map ORDER BY key_label, domain')->fetchAll(PDO::FETCH_ASSOC);
$counts = $pdo->query('SELECT key_label, COUNT(*) c FROM captcha_map GROUP BY key_label')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Captcha Manager</title>
<style>
 body{font:14px system-ui,Arial;margin:24px;color:#1f2937}
 h1{font-size:20px;display:inline-block} .top{display:flex;justify-content:space-between;align-items:center}
 .logout{font-size:13px;color:#dc2626;text-decoration:none}
 table{border-collapse:collapse;width:100%;margin-top:16px}
 th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:13px} th{background:#f9fafb}
 input{padding:6px;width:100%;box-sizing:border-box}
 .row{display:grid;grid-template-columns:2fr 2fr 2fr 1fr auto;gap:8px;align-items:end;margin:16px 0}
 .msg{background:#ecfdf5;border:1px solid #a7f3d0;padding:8px 12px;border-radius:6px}
 button{padding:7px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}
 button.del{background:#dc2626}
 .counts span{display:inline-block;background:#eef2ff;color:#3730a3;padding:3px 10px;border-radius:999px;margin-right:8px}
</style></head><body>
<div class="top"><h1>Captcha Manager &mdash; domain &rarr; reCAPTCHA key</h1>
  <span>Signed in as <b><?=h($_SESSION['captcha_admin'])?></b> &middot; <a class="logout" href="?logout=1">Log out</a></span></div>
<?php if($msg): ?><p class="msg"><?=h($msg)?></p><?php endif; ?>
<p class="counts">Per-key domain counts (watch the ~250 limit):
<?php foreach($counts as $c): ?><span><?=h($c['key_label']?:'(none)')?>: <?=h($c['c'])?></span><?php endforeach; ?></p>

<form method="post">
 <input type="hidden" name="action" value="save">
 <div class="row">
   <div><label>Domain</label><input name="domain" placeholder="customer1.siliconpractice.in" required></div>
   <div><label>Site key</label><input name="site_key" required></div>
   <div><label>Secret key</label><input name="secret_key" required></div>
   <div><label>Key label</label><input name="key_label" placeholder="Key A"></div>
   <div><button type="submit">Add / Update</button></div>
 </div>
</form>

<table>
 <tr><th>Domain</th><th>Site key</th><th>Key label</th><th>Active</th><th></th></tr>
 <?php foreach($rows as $r): ?>
 <tr>
   <td><?=h($r['domain'])?></td>
   <td><code><?=h(substr($r['site_key'],0,16))?>&hellip;</code></td>
   <td><?=h($r['key_label'])?></td>
   <td><?=$r['active']?'yes':'no'?></td>
   <td><form method="post" onsubmit="return confirm('Delete <?=h($r['domain'])?>?')">
       <input type="hidden" name="action" value="delete">
       <input type="hidden" name="id" value="<?=h($r['id'])?>">
       <button class="del" type="submit">Delete</button></form></td>
 </tr>
 <?php endforeach; ?>
</table>
</body></html>
