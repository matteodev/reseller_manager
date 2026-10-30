<?php
// Focused contract test. WordPress functions are doubles, not integration coverage.
define('ABSPATH', __DIR__);
function __($text, $domain = '') { return $text; }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function wp_nonce_field($action, $name) { echo '<input name="'.$name.'" value="test">'; }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function sanitize_key($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'test'; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function email_exists($email) { return false; }
function wp_insert_user($data) { $GLOBALS['created'] = $data; return 123; }
function is_wp_error($value) { return false; }
function update_user_meta($id, $key, $value) { $GLOBALS['meta'][$key] = $value; }
require dirname(__DIR__).'/class/class-reseller-manager.php';
function check($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
ob_start();
require dirname(__DIR__).'/shortcodes/reseller-signup.php';
$html = ob_get_clean();
preg_match_all('/<input[^>]*name="([^"]+)"/', $html, $matches);
$names = $matches[1];
$expected = ['mpwp-reseller-signup-name', 'mpwp-reseller-signup-cf', 'mpwp-reseller-signup-piva', 'mpwp-reseller-signup-email', 'mpwp-reseller-signup-password'];
check(count($names) === count(array_unique($names)), 'form field names are unique');
foreach ($expected as $name) { check(in_array($name, $names, true), 'form submits '.$name); }
$_POST = array_combine($expected, ['Demo Company', 'CF-DEMO', 'IVA-DEMO', 'demo@example.test', 'Demo-password-123!']);
$_POST['mpwp_reseller_action'] = 'signup';
$_POST['mpwp_reseller_nonce'] = 'invalid';
Reseller_Manager::handle_shortcode_form();
check(!isset($GLOBALS['created']), 'invalid nonce does not create a user');
$_POST['mpwp_reseller_nonce'] = 'test';
Reseller_Manager::handle_shortcode_form();
check(isset($GLOBALS['created']), 'rendered form contract creates user');
check($GLOBALS['created']['role'] === 'mpwp_reseller', 'signup assigns reseller role');
check($GLOBALS['created']['meta_input']['mpwp_reseller_codice_fiscale'] === 'CF-DEMO', 'fiscal code preserved');
check($GLOBALS['created']['meta_input']['mpwp_reseller_partita_iva'] === 'IVA-DEMO', 'VAT field stays independent');
check($GLOBALS['meta']['mpwp_reseller_status'] === 'disabled', 'signup requires approval');
