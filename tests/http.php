<?php
if (getenv('WORDPRESS_DB_NAME') !== 'reseller_tests') throw new RuntimeException('Test database required');
require_once ABSPATH.'wp-admin/includes/user.php';
$tag = uniqid('rmhttp');
$page = wp_insert_post(['post_title'=>$tag, 'post_status'=>'publish', 'post_type'=>'page', 'post_content'=>'[mcwp_reseller_signup][mcwp_reseller_auth]']);
$url = get_permalink($page);
$user_id = 0;
function http_expect($condition, $message) { if (!$condition) throw new RuntimeException($message); WP_CLI::log('PASS: '.$message); }
function form_nonce($html, $action) {
    preg_match_all('/<form\b[^>]*>(.*?)<\/form>/s', $html, $forms);
    foreach ($forms[1] as $form) {
        if (strpos($form, 'value="'.$action.'"') !== false && preg_match('/name="mpwp_reseller_nonce" value="([^"]+)"/', $form, $match)) return $match[1];
    }
    throw new RuntimeException('Missing '.$action.' nonce');
}
function login_cookie($response) {
    foreach (wp_remote_retrieve_cookies($response) as $cookie) if (strpos($cookie->name, 'wordpress_logged_in_') === 0 && $cookie->value) return true;
    return false;
}
try {
    $response = wp_remote_get($url, ['timeout'=>30]);
    http_expect(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200, 'HTTP page renders both reseller forms');
    $html = wp_remote_retrieve_body($response);
    $signup = ['mpwp_reseller_action'=>'signup', 'mpwp_reseller_nonce'=>form_nonce($html, 'signup'), 'mpwp-reseller-signup-name'=>'HTTP Demo', 'mpwp-reseller-signup-email'=>$tag.'@example.test', 'mpwp-reseller-signup-password'=>'HTTP<word>123!', 'mpwp-reseller-signup-cf'=>'CFHTTP', 'mpwp-reseller-signup-piva'=>'VATHTTP'];
    $bad = $signup; $bad['mpwp_reseller_nonce'] = 'invalid';
    wp_remote_post($url, ['timeout'=>30, 'body'=>$bad]);
    http_expect(!get_user_by('email', $signup['mpwp-reseller-signup-email']), 'HTTP signup rejects invalid nonce');
    $response = wp_remote_post($url, ['timeout'=>30, 'body'=>$signup]);
    $user = get_user_by('email', $signup['mpwp-reseller-signup-email']); $user_id = $user ? $user->ID : 0;
    http_expect($user_id && get_user_meta($user_id, 'mpwp_reseller_status', true) === 'disabled', 'HTTP signup creates pending account');
    http_expect(get_user_meta($user_id, 'mpwp_reseller_codice_fiscale', true) === 'CFHTTP' && get_user_meta($user_id, 'mpwp_reseller_partita_iva', true) === 'VATHTTP', 'HTTP signup preserves independent fiscal fields');
    $login = ['mpwp_reseller_action'=>'login', 'mpwp_reseller_nonce'=>form_nonce(wp_remote_retrieve_body($response), 'login'), 'login_email'=>$signup['mpwp-reseller-signup-email'], 'login_password'=>$signup['mpwp-reseller-signup-password']];
    $response = wp_remote_post($url, ['timeout'=>30, 'redirection'=>0, 'body'=>$login]);
    http_expect(wp_remote_retrieve_response_code($response) === 200 && !login_cookie($response), 'HTTP pending reseller cannot log in');
    update_user_meta($user_id, 'mpwp_reseller_status', 'enabled');
    $bad = $login; $bad['login_password'] = 'incorrect';
    $response = wp_remote_post($url, ['timeout'=>30, 'redirection'=>0, 'body'=>$bad]);
    http_expect(wp_remote_retrieve_response_code($response) === 200 && !login_cookie($response), 'HTTP wrong password receives no login cookie');
    $response = wp_remote_post($url, ['timeout'=>30, 'redirection'=>0, 'body'=>$login]);
    http_expect(wp_remote_retrieve_response_code($response) === 302 && login_cookie($response), 'HTTP approved reseller receives redirect and login cookie');
    WP_CLI::success('7 HTTP cases passed; web PHP runtime '.wp_remote_retrieve_header(wp_remote_get($url), 'x-powered-by'));
} finally {
    if ($user_id) wp_delete_user($user_id);
    wp_delete_post($page, true);
}
