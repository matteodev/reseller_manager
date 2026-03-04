<?php
// Pagina impostazioni
if (! current_user_can('manage_options')) {
    wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'mc-reseller-manager'));
}

$mpwp_updated = false;

if (
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mpwp_reseller_settings_nonce'])
) {
    check_admin_referer('mpwp_reseller_settings', 'mpwp_reseller_settings_nonce');

    $mpwp_show_full_price = isset($_POST['reseller_show_full_price']) ? 1 : 0;
    update_option('reseller_show_full_price', $mpwp_show_full_price);

    $mpwp_apply_regular_price = isset($_POST['reseller_apply_regular_price']) ? 1 : 0;
    update_option('reseller_apply_regular_price', $mpwp_apply_regular_price);

    $mpwp_updated = true;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Impostazioni', 'mc-reseller-manager'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($mpwp_updated) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Impostazioni salvate.', 'mc-reseller-manager'); ?></p></div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <?php wp_nonce_field('mpwp_reseller_settings', 'mpwp_reseller_settings_nonce'); ?>
            <div class="form-field">
                <p>
                    <?php esc_html_e('Spuntando questa opzione i prezzi ai rivenditori, verranno mostrati come da listino originale, lo sconto verrà applicato in fase di acquisto. Altrimenti viene mostrato direttamente il prezzo rivenditore', 'mc-reseller-manager'); ?>
                </p>
                <p>
                    <?php esc_html_e('Non sarà possibile assegnare un prezzo speciale ai prodotti del catalogo', 'mc-reseller-manager'); ?>
                </p>
                <label for="reseller_manager_show_full_price">
                    <input type="checkbox" name="reseller_show_full_price" id="reseller_manager_show_full_price" value="1" <?php checked(get_option('reseller_show_full_price'), 1); ?> />
                    <?php esc_html_e('Mostra sconto rivenditore solo in fase di acquisto', 'mc-reseller-manager'); ?>
                </label>
                <br><br>
                <hr>
                <p>
                    <?php esc_html_e('Spuntando questa opzione lo sconto verrà calcolato sul prezzo originale, altrimenti verrà calcolato sul prezzo già scontato', 'mc-reseller-manager'); ?>
                </p>

                <label for="reseller_manager_apply_regular_price">
                    <input type="checkbox" name="reseller_apply_regular_price" id="reseller_manager_apply_regular_price" value="1" <?php checked(get_option('reseller_apply_regular_price'), 1); ?> />
                    <?php esc_html_e('Calcola sconto globale sul prezzo originale', 'mc-reseller-manager'); ?>
                </label>

            </div>
            <br>
            <div class="form-field" style="margin-top:12px;">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Salva', 'mc-reseller-manager'); ?>">
            </div>
        </form>
    </div>
