#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
stack=(docker compose -p reseller-case-study -f compose.integration.yaml)
"${stack[@]}" up -d --wait wordpress
if ! "${stack[@]}" run --rm cli core is-installed; then
  "${stack[@]}" run --rm cli core install --url=http://wordpress --title='Reseller Manager Demo' --admin_user=demo_admin --admin_password='Local-test-only-123!' --admin_email=admin@example.test --skip-email
fi
"${stack[@]}" run --rm cli plugin install woocommerce --version=11.2.1 --activate
"${stack[@]}" run --rm cli plugin activate reseller_manager
"${stack[@]}" run --rm cli language core install it_IT --activate
"${stack[@]}" run --rm cli eval '
require_once ABSPATH."wp-admin/includes/user.php";
if (getenv("WORDPRESS_DB_NAME") !== "reseller_tests") throw new RuntimeException("Test database required");
$previous = get_user_by("email", "studio.demo@example.test");
if ($previous) wp_delete_user($previous->ID);
$id = username_exists("atelier_demo");
if (!$id) $id = wp_create_user("atelier_demo", "Demo-local-123!", "atelier@example.test");
(new WP_User($id))->set_role("mpwp_reseller");
wp_update_user(["ID"=>$id, "display_name"=>"Atelier Demo"]);
update_user_meta($id, "mpwp_reseller_status", "enabled");
update_user_meta($id, "mpwp_reseller_discount", 10);
$prices = [];
foreach (["demo-notebook"=>["Quaderno Demo",100,50], "demo-bag"=>["Borsa Demo",200,150]] as $sku=>$data) {
  $pid = wc_get_product_id_by_sku($sku);
  $p = $pid ? wc_get_product($pid) : new WC_Product_Simple();
  $p->set_name($data[0]); $p->set_sku($sku); $p->set_regular_price($data[1]); $p->set_price($data[1]); $p->save();
  $prices[$p->get_id()] = $data[2];
}
update_user_meta($id, "mpwp_reseller_product_prices", $prices);
$page = get_page_by_path("case-study-signup");
$pid = wp_insert_post(["ID"=>$page ? $page->ID : 0, "post_name"=>"case-study-signup", "post_title"=>"Richiedi un account rivenditore", "post_type"=>"page", "post_status"=>"publish", "post_content"=>"<p>Ambiente dimostrativo locale. Tutti i dati mostrati sono fittizi.</p>[mcwp_reseller_signup]"]);
update_option("reseller_show_full_price", 0);
update_option("woocommerce_onboarding_profile", ["completed"=>true]);
update_option("woocommerce_task_list_hidden", "yes");
delete_transient("_wc_activation_redirect");
WP_CLI::log("DEMO_PAGE=".$pid." DEMO_RESELLER=".$id);
'
