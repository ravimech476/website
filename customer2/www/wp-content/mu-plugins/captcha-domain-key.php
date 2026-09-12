<?php
/**
 * Plugin Name: Captcha Domain Key (middleware)
 * Description: Overrides Formidable's reCAPTCHA keys per customer domain, read from the
 *              central `captcha_manager` DB. Drop this in each site's wp-content/mu-plugins/
 *              (or symlink it) so every customer automatically uses the key mapped to its domain.
 *
 * How it works: Formidable reads its captcha keys from get_option('frm_options')
 * (fields pubkey / privkey). This filter replaces those, at runtime, with the key
 * mapped to the current $_SERVER['HTTP_HOST'] in the central captcha_map table.
 */

if (!defined('ABSPATH')) { exit; }

// --- Central captcha DB connection (override these in wp-config.php if different) ---
if (!defined('CAPTCHA_DB_HOST')) { define('CAPTCHA_DB_HOST', 'localhost'); }
if (!defined('CAPTCHA_DB_NAME')) { define('CAPTCHA_DB_NAME', 'captcha_manager'); }
if (!defined('CAPTCHA_DB_USER')) { define('CAPTCHA_DB_USER', 'root'); }
if (!defined('CAPTCHA_DB_PASS')) { define('CAPTCHA_DB_PASS', ''); }

/**
 * Look up the reCAPTCHA key pair for a domain. Cached per request.
 * @return array{site_key:string,secret_key:string}|null
 */
function cdk_lookup_keys_for_domain($domain) {
    static $cache = array();
    if (array_key_exists($domain, $cache)) { return $cache[$domain]; }

    $result = null;
    try {
        $pdo = new PDO(
            'mysql:host=' . CAPTCHA_DB_HOST . ';dbname=' . CAPTCHA_DB_NAME . ';charset=utf8mb4',
            CAPTCHA_DB_USER, CAPTCHA_DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2)
        );
        $stmt = $pdo->prepare(
            'SELECT site_key, secret_key FROM captcha_map WHERE domain = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute(array($domain));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['site_key']) && !empty($row['secret_key'])) {
            $result = $row;
        }
    } catch (Exception $e) {
        // Fail open: on any error, leave Formidable's own keys untouched.
        $result = null;
    }

    $cache[$domain] = $result;
    return $result;
}

/**
 * Inject the per-domain key into Formidable's settings.
 * frm_options may be an array or an object depending on Formidable version — handle both.
 */
add_filter('option_frm_options', function ($opts) {
    // Inject the centrally-mapped key everywhere Formidable reads it — including the
    // Formidable > Captcha settings screen, so that page REFLECTS the assigned key.
    // The settings fields are made read-only by cdk_lock_formidable_fields() below, and the
    // actual key used at runtime is always the central one regardless of what is stored.
    $domain = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    if ($domain === '') { return $opts; }

    $keys = cdk_lookup_keys_for_domain($domain);
    if ($keys === null) { return $opts; }   // no mapping -> keep the site's own keys

    if (is_object($opts)) {
        $opts->pubkey  = $keys['site_key'];
        $opts->privkey = $keys['secret_key'];
    } elseif (is_array($opts)) {
        $opts['pubkey']  = $keys['site_key'];
        $opts['privkey'] = $keys['secret_key'];
    }
    return $opts;
}, 99);

/**
 * On the Formidable > Captcha settings screen, if this domain is centrally managed,
 * make the reCAPTCHA Site Key / Secret Key fields READ-ONLY so the customer can see the
 * assigned key but cannot edit it. (Managed only from the Captcha Manager.)
 * Sites with no mapping are left fully editable.
 */
function cdk_lock_formidable_fields() {
    if (!is_admin()) { return; }
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    if ($page !== 'formidable-settings') { return; }

    $domain = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    if ($domain === '' || cdk_lookup_keys_for_domain($domain) === null) { return; } // not managed
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        ['frm_pubkey', 'frm_privkey'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) { return; }
            el.readOnly = true;
            el.style.background = '#f0f0f1';
            el.style.cursor = 'not-allowed';
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'cdk_lock_formidable_fields');
