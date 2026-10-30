<?php
// Run only in the disposable integration stack: this script creates test fixtures.
if (getenv('WORDPRESS_DB_NAME') !== 'reseller_tests') {
    throw new RuntimeException('Refusing to run outside reseller_tests database.');
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
global $failures, $results;
$failures = 0;
$results = [];
function verify_case($name, $test) {
    global $failures, $results;
    try { $test(); $results[] = 'PASS: '.$name; }
    catch (Throwable $e) { $failures++; $results[] = 'FAIL: '.$name.' — '.$e->getMessage(); }
}
function expect_true($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function expect_price($actual, $expected) { expect_true(is_numeric($actual) && abs((float)$actual - $expected) < 0.001, 'expected '.$expected.', got '.var_export($actual, true)); }
class Reseller_Test_Denied extends RuntimeException {}
add_filter('wp_die_handler', function () { return function ($message) { throw new Reseller_Test_Denied(is_string($message) ? strip_tags($message) : 'Request denied'); }; });
add_filter('pre_wp_mail', '__return_true'); // No external email during tests.
$tag = uniqid('rmtest');
$admin = wp_create_user($tag.'admin', 'Admin-local-123!', $tag.'admin@example.test');
(new WP_User($admin))->set_role('administrator');
$reseller = wp_create_user($tag.'reseller', 'Test-local-123!', $tag.'reseller@example.test');
(new WP_User($reseller))->set_role('mpwp_reseller');
$customer = wp_create_user($tag.'customer', 'Test-local-123!', $tag.'customer@example.test');
(new WP_User($customer))->set_role('subscriber');
update_user_meta($reseller, 'mpwp_reseller_status', 'enabled');
update_user_meta($reseller, 'mpwp_reseller_discount', 10);
$p1 = new WC_Product_Simple(); $p1->set_name($tag.'one'); $p1->set_regular_price(100); $p1->set_price(100); $p1->save();
$p2 = new WC_Product_Simple(); $p2->set_name($tag.'two'); $p2->set_regular_price(200); $p2->set_sale_price(160); $p2->set_price(160); $p2->save();
update_user_meta($reseller, 'mpwp_reseller_product_prices', [$p1->get_id() => 50]);
update_option('reseller_show_full_price', 0);
update_option('reseller_apply_regular_price', 0);
if (!WC()->session) WC()->initialize_session();
WC()->cart = new WC_Cart();
function test_cart($products) {
    $cart = new WC_Cart();
    foreach ($products as $product) $cart->add_to_cart($product->get_id());
    return $cart;
}
function profile_request($id, $post = []) {
    $_GET = ['id' => $id]; $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $level = ob_get_level(); ob_start();
    try { include dirname(__DIR__).'/pages/reseller-profile.php'; return ob_get_contents(); }
    finally { while (ob_get_level() > $level) ob_end_clean(); $_POST = []; $_GET = []; }
}
verify_case('subscriber cannot read reseller profile', function () use ($customer, $reseller) {
    wp_set_current_user($customer);
    try { profile_request($reseller); } catch (Reseller_Test_Denied $e) { return; }
    throw new RuntimeException('profile was rendered for subscriber');
});
verify_case('subscriber cannot change discount with their own valid nonce', function () use ($customer, $reseller) {
    wp_set_current_user($customer);
    try { profile_request($reseller, ['mpwp_reseller_discount_nonce'=>wp_create_nonce('mpwp_reseller_discount_'.$reseller), 'mpwp_reseller_discount'=>77]); } catch (Reseller_Test_Denied $e) {}
    expect_price(get_user_meta($reseller, 'mpwp_reseller_discount', true), 10);
    update_user_meta($reseller, 'mpwp_reseller_discount', 10);
});
// Reset even after the vulnerable baseline case fails.
update_user_meta($reseller, 'mpwp_reseller_discount', 10);
verify_case('administrator cannot target a non-reseller profile', function () use ($admin, $customer) {
    wp_set_current_user($admin);
    try { profile_request($customer); } catch (Reseller_Test_Denied $e) { return; }
    throw new RuntimeException('non-reseller profile accepted');
});
verify_case('invalid profile nonce cannot write', function () use ($admin, $reseller) {
    wp_set_current_user($admin);
    try { profile_request($reseller, ['mpwp_reseller_discount_nonce'=>'bad', 'mpwp_reseller_discount'=>88]); } catch (Reseller_Test_Denied $e) {}
    expect_price(get_user_meta($reseller, 'mpwp_reseller_discount', true), 10);
});
verify_case('paginated custom price save preserves other products', function () use ($admin, $reseller, $p1, $p2) {
    wp_set_current_user($admin);
    profile_request($reseller, ['mpwp_reseller_prices_nonce'=>wp_create_nonce('mpwp_reseller_prices_'.$reseller), 'mpwp_reseller_product_price'=>[$p2->get_id()=>60]]);
    $prices = get_user_meta($reseller, 'mpwp_reseller_product_prices', true);
    expect_price($prices[$p1->get_id()] ?? null, 50); expect_price($prices[$p2->get_id()] ?? null, 60);
});
update_user_meta($reseller, 'mpwp_reseller_product_prices', [$p1->get_id() => 50]);
verify_case('custom cart price is numeric and every row gets processed', function () use ($reseller, $p1, $p2) {
    wp_set_current_user($reseller); $cart = test_cart([$p1, $p2]);
    Reseller_Manager::filter_price($cart); $items = array_values($cart->get_cart());
    expect_price($items[0]['data']->get_price(), 50); expect_price($items[1]['data']->get_price(), 144);
});
verify_case('cart recalculation is idempotent and preserves regular price', function () use ($reseller, $p2) {
    wp_set_current_user($reseller); $cart = test_cart([$p2]);
    Reseller_Manager::filter_price($cart); Reseller_Manager::filter_price($cart);
    $item = array_values($cart->get_cart())[0]; expect_price($item['data']->get_price(), 144); expect_price($item['data']->get_regular_price(), 200);
});
verify_case('zero global discount still applies custom price', function () use ($reseller, $p1) {
    wp_set_current_user($reseller); update_user_meta($reseller, 'mpwp_reseller_discount', 0);
    $cart = test_cart([$p1]); Reseller_Manager::filter_price($cart);
    expect_price(array_values($cart->get_cart())[0]['data']->get_price(), 50);
});
update_user_meta($reseller, 'mpwp_reseller_discount', 10);
verify_case('disabled reseller receives no custom cart price', function () use ($reseller, $p1) {
    wp_set_current_user($reseller); update_user_meta($reseller, 'mpwp_reseller_status', 'disabled');
    $cart = test_cart([$p1]); Reseller_Manager::filter_price($cart);
    expect_price(array_values($cart->get_cart())[0]['data']->get_price(), 100);
});
update_user_meta($reseller, 'mpwp_reseller_status', 'enabled');
verify_case('non-reseller metadata does not grant discounts', function () use ($customer, $p2) {
    wp_set_current_user($customer); update_user_meta($customer, 'mpwp_reseller_status', 'enabled'); update_user_meta($customer, 'mpwp_reseller_discount', 90);
    $cart = test_cart([$p2]); Reseller_Manager::filter_price($cart);
    expect_price(array_values($cart->get_cart())[0]['data']->get_price(), 160);
});
verify_case('catalog formatting never mutates regular price', function () use ($reseller, $p2) {
    wp_set_current_user($reseller); $product = wc_get_product($p2->get_id());
    Reseller_Manager::filter_price_html('original', $product);
    expect_price($product->get_regular_price(), 200);
});
verify_case('sale price filter returns numeric custom price', function () use ($reseller, $p1) {
    wp_set_current_user($reseller); expect_price(Reseller_Manager::filter_sale_price('100', $p1), 50);
});
verify_case('regular-price option calculates discount from list price', function () use ($reseller, $p2) {
    wp_set_current_user($reseller); update_option('reseller_apply_regular_price', 1);
    $cart = test_cart([$p2]); Reseller_Manager::filter_price($cart);
    expect_price(array_values($cart->get_cart())[0]['data']->get_price(), 180);
});
update_option('reseller_apply_regular_price', 0);
verify_case('real cart totals use both reseller prices and remain stable', function () use ($reseller, $p1, $p2) {
    wp_set_current_user($reseller); WC()->customer = new WC_Customer($reseller);
    $cart = test_cart([$p1, $p2]); $cart->calculate_totals(); expect_price($cart->get_subtotal(), 194);
    $cart->calculate_totals(); expect_price($cart->get_subtotal(), 194);
});
verify_case('full-price mode charges one global discount', function () use ($reseller, $p1, $p2) {
    wp_set_current_user($reseller); update_option('reseller_show_full_price', 1);
    $cart = test_cart([$p1, $p2]); $cart->calculate_totals();
    expect_price($cart->get_subtotal(), 260); expect_price($cart->get_fee_total(), -26);
});
verify_case('disabled reseller receives no full-price discount', function () use ($reseller, $p1) {
    wp_set_current_user($reseller); update_user_meta($reseller, 'mpwp_reseller_status', 'disabled');
    $cart = test_cart([$p1]); $cart->calculate_totals(); expect_price($cart->get_fee_total(), 0);
});
update_option('reseller_show_full_price', 0); update_user_meta($reseller, 'mpwp_reseller_status', 'enabled');
verify_case('shortcode returns form without leaking output', function () {
    ob_start(); $value = do_shortcode('[mcwp_reseller_signup]'); $leaked = ob_get_clean();
    expect_true($leaked === '' && strpos($value, '<form') !== false, 'shortcode emitted output instead of returning it');
});
verify_case('real signup preserves password and creates pending reseller', function () use ($tag) {
    $_POST = wp_slash(['mpwp_reseller_action'=>'signup', 'mpwp_reseller_nonce'=>wp_create_nonce('mpwp_reseller_signup'), 'mpwp-reseller-signup-name'=>'Demo', 'mpwp-reseller-signup-email'=>$tag.'signup@example.test', 'mpwp-reseller-signup-password'=>'pass<word>\\"123!', 'mpwp-reseller-signup-cf'=>'CF', 'mpwp-reseller-signup-piva'=>'VAT']);
    Reseller_Manager::handle_shortcode_form(); $user = get_user_by('email', $tag.'signup@example.test');
    expect_true($user && wp_check_password('pass<word>\\"123!', $user->user_pass, $user->ID), 'password changed or signup failed');
    expect_true(get_user_meta($user->ID, 'mpwp_reseller_status', true) === 'disabled', 'signup not pending');
    wp_delete_user($user->ID); $_POST = [];
});
verify_case('profile renders independent, balanced forms including discount nonce', function () use ($admin, $reseller) {
    wp_set_current_user($admin); $html = profile_request($reseller); $depth = 0;
    preg_match_all('/<\/?form\b[^>]*>/', $html, $tags);
    foreach ($tags[0] as $tag) { $depth += strpos($tag, '</') === 0 ? -1 : 1; expect_true($depth >= 0 && $depth <= 1, 'nested/unbalanced form'); }
    expect_true($depth === 0 && strpos($html, 'name="mpwp_reseller_discount_nonce"') !== false, 'missing discount form/nonce');
});
require_once dirname(__DIR__).'/class/class-reseller-table.php';
class Reseller_Test_Redirect extends RuntimeException {}
add_filter('wp_redirect', function ($url) { throw new Reseller_Test_Redirect($url); }, 1);
verify_case('subscriber cannot approve reseller using valid nonce', function () use ($customer, $reseller) {
    wp_set_current_user($customer); update_user_meta($reseller, 'mpwp_reseller_status', 'disabled');
    $_GET = ['action'=>'enable', 'id'=>$reseller, 'confirm'=>'1', '_wpnonce'=>wp_create_nonce('enable_reseller_'.$reseller)];
    try { Reseller_Table::handle_action(); } catch (Reseller_Test_Denied $e) {} catch (Reseller_Test_Redirect $e) {}
    expect_true(get_user_meta($reseller, 'mpwp_reseller_status', true) === 'disabled', 'subscriber approved reseller');
});
verify_case('administrator cannot disable a non-reseller user', function () use ($admin, $customer) {
    wp_set_current_user($admin); update_user_meta($customer, 'mpwp_reseller_status', 'enabled');
    $_GET = ['action'=>'disable', 'id'=>$customer, 'confirm'=>'1', '_wpnonce'=>wp_create_nonce('disable_reseller_'.$customer)];
    try { Reseller_Table::handle_action(); } catch (Reseller_Test_Denied $e) {} catch (Reseller_Test_Redirect $e) {}
    expect_true(get_user_meta($customer, 'mpwp_reseller_status', true) === 'enabled', 'non-reseller target was changed');
});
verify_case('administrator approves reseller and login accepts original password', function () use ($admin, $reseller, $tag) {
    wp_set_current_user($admin);
    $_GET = ['action'=>'enable', 'id'=>$reseller, 'confirm'=>'1', '_wpnonce'=>wp_create_nonce('enable_reseller_'.$reseller)];
    try { Reseller_Table::handle_action(); } catch (Reseller_Test_Redirect $e) {}
    expect_true(get_user_meta($reseller, 'mpwp_reseller_status', true) === 'enabled', 'approval did not persist');
    wp_set_current_user(0);
    $_POST = wp_slash(['mpwp_reseller_action'=>'login', 'mpwp_reseller_nonce'=>wp_create_nonce('mpwp_reseller_login'), 'login_email'=>$tag.'reseller@example.test', 'login_password'=>'Test-local-123!']);
    try { Reseller_Manager::handle_shortcode_form(); } catch (Reseller_Test_Redirect $e) {}
    expect_true(get_current_user_id() === $reseller, 'enabled reseller login failed'); $_POST = [];
});
verify_case('disabled reseller login is denied', function () use ($reseller, $tag) {
    update_user_meta($reseller, 'mpwp_reseller_status', 'disabled'); wp_set_current_user(0);
    $_POST = wp_slash(['mpwp_reseller_action'=>'login', 'mpwp_reseller_nonce'=>wp_create_nonce('mpwp_reseller_login'), 'login_email'=>$tag.'reseller@example.test', 'login_password'=>'Test-local-123!']);
    Reseller_Manager::handle_shortcode_form(); expect_true(get_current_user_id() === 0, 'disabled account logged in'); $_POST = [];
});
verify_case('non-reseller login is denied even with enabled metadata', function () use ($customer, $tag) {
    update_user_meta($customer, 'mpwp_reseller_status', 'enabled'); wp_set_current_user(0);
    $_POST = wp_slash(['mpwp_reseller_action'=>'login', 'mpwp_reseller_nonce'=>wp_create_nonce('mpwp_reseller_login'), 'login_email'=>$tag.'customer@example.test', 'login_password'=>'Test-local-123!']);
    try { Reseller_Manager::handle_shortcode_form(); } catch (Reseller_Test_Redirect $e) {}
    expect_true(get_current_user_id() === 0, 'non-reseller logged in'); $_POST = [];
});
verify_case('malformed signup scalar input is rejected without exception', function () {
    $_POST = ['mpwp_reseller_action'=>['signup'], 'mpwp_reseller_nonce'=>'test'];
    Reseller_Manager::handle_shortcode_form(); $_POST = [];
});
verify_case('uninstall preserves accounts and unrelated metadata without SQL errors', function () use ($reseller, $customer) {
    global $wpdb;
    update_user_meta($customer, 'mpwp_reseller_external', 'keep');
    update_user_meta($reseller, 'mpwp_reseller_discount', 10);
    $previous = $wpdb->suppress_errors(true);
    try { Reseller_Manager::uninstall(); $error = $wpdb->last_error; }
    finally { $wpdb->suppress_errors($previous); Reseller_Manager::register_reseller_role(); }
    expect_true($error === '', 'uninstall SQL error: '.$error);
    expect_true((bool)get_userdata($reseller), 'uninstall deleted WordPress account');
    expect_true(!in_array('mpwp_reseller', (array)get_userdata($reseller)->roles, true), 'removed role still assigned');
    expect_true(get_user_meta($reseller, 'mpwp_reseller_discount', true) === '', 'owned metadata remains');
    expect_true(get_user_meta($customer, 'mpwp_reseller_external', true) === 'keep', 'unrelated metadata removed');
});

// Remove all fixtures created in this run, including a signup from a failed assertion.
foreach (get_users(['search'=>$tag.'*', 'search_columns'=>['user_login']]) as $user) wp_delete_user($user->ID);
$p1->delete(true); $p2->delete(true);
$_POST = []; $_GET = [];
foreach ($results as $result) WP_CLI::log($result);
WP_CLI::log('Environment: WordPress '.get_bloginfo('version').', WooCommerce '.WC_VERSION.', PHP '.PHP_VERSION);
if ($failures) WP_CLI::error($failures.' integration cases failed.');
WP_CLI::success(count($results).' integration cases passed.');
