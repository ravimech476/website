<?php
/**
 * Plugin Name: Captcha Manager
 * Description: Central manager for per-domain reCAPTCHA keys (domain -> key map). Uses WordPress
 *              login + admin UI. Activate on ONE admin site; it edits the shared captcha_manager DB.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

// --- Central captcha DB (override in wp-config.php if different) ---
if (!defined('CAPTCHA_DB_HOST')) { define('CAPTCHA_DB_HOST', 'localhost'); }
if (!defined('CAPTCHA_DB_NAME')) { define('CAPTCHA_DB_NAME', 'captcha_manager'); }
if (!defined('CAPTCHA_DB_USER')) { define('CAPTCHA_DB_USER', 'root'); }
if (!defined('CAPTCHA_DB_PASS')) { define('CAPTCHA_DB_PASS', ''); }

function cm_db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . CAPTCHA_DB_HOST . ';dbname=' . CAPTCHA_DB_NAME . ';charset=utf8mb4',
            CAPTCHA_DB_USER, CAPTCHA_DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }
    return $pdo;
}

// Add the admin menu page (top-level, dashicon)
add_action('admin_menu', function () {
    add_menu_page(
        'Captcha Manager',            // page title
        'Captcha Manager',            // menu label
        'manage_options',             // capability = WordPress admin login
        'captcha-manager',            // slug
        'cm_render_admin_page',       // callback
        'dashicons-shield',           // icon
        66                            // position
    );
});

function cm_render_admin_page() {
    if (!current_user_can('manage_options')) { wp_die('Insufficient permissions.'); }

    $notice = '';
    // Handle form actions (nonce-protected)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cm_action'])) {
        check_admin_referer('cm_manage');
        try {
            if ($_POST['cm_action'] === 'save') {
                $domain = strtolower(trim(sanitize_text_field($_POST['domain'] ?? '')));
                $site   = trim(sanitize_text_field($_POST['site_key'] ?? ''));
                $secret = trim(sanitize_text_field($_POST['secret_key'] ?? ''));
                $label  = trim(sanitize_text_field($_POST['key_label'] ?? ''));
                if ($domain && $site && $secret) {
                    $stmt = cm_db()->prepare(
                        'INSERT INTO captcha_map (domain, site_key, secret_key, key_label)
                         VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE site_key=VALUES(site_key), secret_key=VALUES(secret_key), key_label=VALUES(key_label)'
                    );
                    $stmt->execute(array($domain, $site, $secret, $label));
                    $notice = 'Saved: ' . esc_html($domain);
                } else {
                    $notice = 'Domain, site key and secret key are all required.';
                }
            } elseif ($_POST['cm_action'] === 'delete' && !empty($_POST['id'])) {
                cm_db()->prepare('DELETE FROM captcha_map WHERE id = ?')->execute(array((int)$_POST['id']));
                $notice = 'Deleted.';
            }
        } catch (Exception $e) {
            $notice = 'DB error: ' . esc_html($e->getMessage());
        }
    }

    $filter = isset($_GET['filter_key']) ? sanitize_text_field(wp_unslash($_GET['filter_key'])) : '';
    try {
        if ($filter !== '') {
            $stmt = cm_db()->prepare('SELECT * FROM captcha_map WHERE key_label = ? ORDER BY domain');
            $stmt->execute(array($filter));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = cm_db()->query('SELECT * FROM captcha_map ORDER BY key_label, domain')->fetchAll(PDO::FETCH_ASSOC);
        }
        $counts = cm_db()->query('SELECT key_label, COUNT(*) c FROM captcha_map GROUP BY key_label')->fetchAll(PDO::FETCH_ASSOC);
        $labels = cm_db()->query("SELECT DISTINCT key_label FROM captcha_map WHERE key_label IS NOT NULL AND key_label <> '' ORDER BY key_label")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        echo '<div class="wrap"><h1>Captcha Manager</h1><div class="notice notice-error"><p>Cannot connect to captcha_manager DB: '
            . esc_html($e->getMessage()) . '</p></div></div>';
        return;
    }

    $nonce = wp_nonce_field('cm_manage', '_wpnonce', true, false);
    ?>
    <div class="wrap">
      <h1>Captcha Manager <span class="dashicons dashicons-shield" style="font-size:26px;"></span></h1>
      <p class="description">Map each customer domain to its reCAPTCHA key. The site middleware injects the matching key per domain.</p>

      <?php if ($notice): ?><div class="notice notice-info is-dismissible"><p><?php echo $notice; ?></p></div><?php endif; ?>

      <p>
        <?php foreach ($counts as $c): ?>
          <span class="button button-secondary" style="pointer-events:none;margin-right:6px;">
            <?php echo esc_html($c['key_label'] ?: '(none)'); ?>: <?php echo (int)$c['c']; ?>
          </span>
        <?php endforeach; ?>
        <em>(watch the ~250 domains-per-key limit)</em>
      </p>

      <h2>Add / update a mapping</h2>
      <form method="post">
        <?php echo $nonce; ?>
        <input type="hidden" name="cm_action" value="save">
        <table class="form-table" role="presentation">
          <tr><th><label>Domain</label></th><td><input name="domain" class="regular-text" placeholder="customer1.siliconpractice.in" required></td></tr>
          <tr><th><label>Site key</label></th><td><input name="site_key" class="regular-text" required></td></tr>
          <tr><th><label>Secret key</label></th><td><input name="secret_key" class="regular-text" required></td></tr>
          <tr><th><label>Key label</label></th><td>
            <input name="key_label" class="regular-text" placeholder="Key A" list="cm_labels">
            <datalist id="cm_labels"><?php foreach ($labels as $l): ?><option value="<?php echo esc_attr($l); ?>"></option><?php endforeach; ?></datalist>
          </td></tr>
        </table>
        <?php submit_button('Add / Update'); ?>
      </form>

      <h2>Current mappings</h2>
      <form method="get" style="margin:8px 0;">
        <input type="hidden" name="page" value="captcha-manager">
        <label for="filter_key"><strong>Show domains for key:</strong> </label>
        <select name="filter_key" id="filter_key" onchange="this.form.submit()">
          <option value="">All keys</option>
          <?php foreach ($labels as $l): ?>
            <option value="<?php echo esc_attr($l); ?>" <?php selected($filter, $l); ?>><?php echo esc_html($l); ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($filter !== ''): ?>
          &nbsp;<strong><?php echo count($rows); ?></strong> domain(s) on <strong><?php echo esc_html($filter); ?></strong>
          &nbsp;<a href="<?php echo esc_url(admin_url('admin.php?page=captcha-manager')); ?>">clear filter</a>
        <?php endif; ?>
      </form>
      <table class="wp-list-table widefat fixed striped">
        <thead><tr><th>Domain</th><th>Site key</th><th>Key label</th><th>Active</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5">No mappings yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?php echo esc_html($r['domain']); ?></td>
            <td><code><?php echo esc_html(substr($r['site_key'], 0, 18)); ?>&hellip;</code></td>
            <td><?php echo esc_html($r['key_label']); ?></td>
            <td><?php echo $r['active'] ? 'yes' : 'no'; ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete <?php echo esc_js($r['domain']); ?>?');" style="margin:0;">
                <?php echo $nonce; ?>
                <input type="hidden" name="cm_action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button class="button button-link-delete" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php
}
