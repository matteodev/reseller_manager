<?php
$mpwp_reseller_id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;

if (! $mpwp_reseller_id) {
    wp_die('ID reseller mancante.');
}

$reseller = get_userdata($mpwp_reseller_id);

if (! $reseller) {
    wp_die('Reseller non trovato.');
}

$mpwp_status        = get_user_meta($reseller->ID, 'mpwp_reseller_status', true);
$mpwp_status        = $mpwp_status ? $mpwp_status : 'disabled';
$mpwp_status_label  = $mpwp_status === 'enabled' ? __('Abilitato', 'mc-reseller-manager') : __('Disabilitato', 'mc-reseller-manager');
$mpwp_status_notice = $mpwp_status === 'enabled' ? 'notice-success' : 'notice-warning';

//Gestione sconto globale e prezzi speciali
$mpwp_notice         = '';
$mpwp_prices_notice  = '';
$mpwp_product_prices = get_user_meta($reseller->ID, 'mpwp_reseller_product_prices', true);
$mpwp_product_prices = is_array($mpwp_product_prices) ? $mpwp_product_prices : [];
if (
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mpwp_reseller_discount_nonce'])
) {
    if (! wp_verify_nonce(wp_unslash($_POST['mpwp_reseller_discount_nonce']), 'mpwp_reseller_discount_' . $mpwp_reseller_id)) {
        wp_nonce_ays('mpwp_reseller_discount_' . $mpwp_reseller_id);
        exit;
    }

    $mpwp_discount = isset($_POST['mpwp_reseller_discount']) ? floatval(wp_unslash($_POST['mpwp_reseller_discount'])) : 0;
    $mpwp_discount = max(0, min(100, $mpwp_discount));

    update_user_meta($reseller->ID, 'mpwp_reseller_discount', $mpwp_discount);
    $mpwp_notice = __('Sconto aggiornato con successo.', 'mc-reseller-manager');
}
$mpwp_current_discount = get_user_meta($reseller->ID, 'mpwp_reseller_discount', true);

if (
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mpwp_reseller_prices_nonce'])
) {
    if (! wp_verify_nonce(wp_unslash($_POST['mpwp_reseller_prices_nonce']), 'mpwp_reseller_prices_' . $mpwp_reseller_id)) {
        wp_nonce_ays('mpwp_reseller_prices_' . $mpwp_reseller_id);
        exit;
    }

    $mpwp_clean_prices = [];
    if (isset($_POST['mpwp_reseller_product_price']) && is_array($_POST['mpwp_reseller_product_price'])) {
        foreach ($_POST['mpwp_reseller_product_price'] as $product_id => $value) {
            $product_id = absint($product_id);
            if (! $product_id) {
                continue;
            }
            $price = trim((string) wp_unslash($value));
            if ($price === '') {
                continue;
            }
            $price = max(0, floatval($price));
            $mpwp_clean_prices[$product_id] = $price;
        }
    }

    update_user_meta($reseller->ID, 'mpwp_reseller_product_prices', $mpwp_clean_prices);
    $mpwp_product_prices = $mpwp_clean_prices;
    $mpwp_prices_notice  = __('Prezzi speciali aggiornati con successo.', 'mc-reseller-manager');
}


$mpwp_list_url     = admin_url('admin.php?page=reseller-manager');
$mpwp_actions_base = admin_url('admin.php?page=reseller-manager');
$mpwp_enable_url   = wp_nonce_url(add_query_arg(['action' => 'enable', 'id' => $mpwp_reseller_id], $mpwp_actions_base), 'enable_reseller_' . $mpwp_reseller_id);
$mpwp_disable_url  = wp_nonce_url(add_query_arg(['action' => 'disable', 'id' => $mpwp_reseller_id], $mpwp_actions_base), 'disable_reseller_' . $mpwp_reseller_id);
$mpwp_delete_url   = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $mpwp_reseller_id], $mpwp_actions_base), 'delete_reseller_' . $mpwp_reseller_id);
$mpwp_apply_discount_url = wp_nonce_url(add_query_arg(['action' => 'apply_discount', 'id' => $mpwp_reseller_id], $mpwp_actions_base), 'apply_discount_reseller_' . $mpwp_reseller_id);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Profilo Rivenditore', 'mc-reseller-manager'); ?></h1>
    <a href="<?php echo esc_url($mpwp_list_url); ?>" class="page-title-action"><?php esc_html_e('Torna alla lista', 'mc-reseller-manager'); ?></a>

    <hr class="wp-header-end">

    <?php if ($mpwp_notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($mpwp_notice); ?></p></div>
    <?php endif; ?>
    <?php if ($mpwp_prices_notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($mpwp_prices_notice); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('mpwp_reseller_discount_' . $mpwp_reseller_id, 'mpwp_reseller_discount_nonce'); ?>
        <div class="mpwp-reseller-columns" style="display:grid;grid-template-columns:repeat(2,1fr);gap:300px;">
            <div class="card">
                <h2 style="padding-left:0" class="title"><i class="dashicons dashicons-businessman"></i> <?php echo esc_html($reseller->display_name); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Nome/Ragione sociale', 'mc-reseller-manager'); ?></th>
                            <td><?php echo esc_html($reseller->display_name); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Email', 'mc-reseller-manager'); ?></th>
                            <td><a href="mailto:<?php echo esc_attr($reseller->user_email); ?>"><?php echo esc_html($reseller->user_email); ?></a></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Codice Fiscale', 'mc-reseller-manager'); ?></th>
                            <td><?php echo esc_html(get_user_meta($reseller->ID, 'mpwp_reseller_codice_fiscale', true)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Partita IVA', 'mc-reseller-manager'); ?></th>
                            <td><?php echo esc_html(get_user_meta($reseller->ID, 'mpwp_reseller_partita_iva', true)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Registrato il', 'mc-reseller-manager'); ?></th>
                            <td>
                                <?php
                                    $ts = strtotime($reseller->user_registered . ' UTC'); // registro è in GMT
                                    echo esc_html( wp_date( 'd M Y H:i', $ts ) );
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stato', 'mc-reseller-manager'); ?></th>
                            <td>
                                <div class="notice <?php echo esc_attr($mpwp_status_notice); ?> inline">
                                    <p style="margin:0;"><?php echo esc_html($mpwp_status_label); ?></p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2 class="title"><?php esc_html_e('Gestisci sconto', 'mc-reseller-manager'); ?></h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                            <th scope="row"><?php esc_html_e('Sconto globale', 'mc-reseller-manager'); ?></th>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="mpwp_reseller_discount" value="<?php echo esc_attr($mpwp_current_discount); ?>" class="regular-text" style="width:120px;"> %
                            </td>
                            
                            <?php if(get_option('reseller_apply_regular_price') == 1) : ?>
                                <p style="font-size:12px;"><?php esc_html_e('Lo sconto verrà applicato al prezzo originale, eventuali sconti sui prodotti verranno ignorati.
                                Puoi decidere di applicare lo sconto rivenditore considerando il prezzo scontato, disabilitando l\'opzione in Impostazioni', 'mc-reseller-manager'); ?></p>
                            <?php else : ?>
                                <p style="font-size:12px;"><?php esc_html_e('Lo sconto verrà applicato anche al prezzo di prodotti che hanno già uno sconto applicato. 
                                Puoi decidere di applicare lo sconto rivenditore al prezzo di listino, abilitando l\'opzione in Impostazioni', 'mc-reseller-manager'); ?></p>
                            <?php endif; ?>
                        </tr>
                        <tr>
                            <td style="padding-left:0">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Aggiorna sconto', 'mc-reseller-manager'); ?></button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr>
                <h2 style="padding-left:0" class="title"><?php esc_html_e('Azioni account', 'mc-reseller-manager'); ?></h2>
                <p><?php esc_html_e('Gestisci lo stato del rivenditore oppure rimuovilo definitivamente.', 'mc-reseller-manager'); ?></p>
                <p class="mpwp-reseller-actions">
                    <?php if ($mpwp_status === 'disabled') : ?>
                        <a class="button button-primary" href="<?php echo esc_url($mpwp_enable_url); ?>">
                            <?php esc_html_e('Abilita', 'mc-reseller-manager'); ?>
                        </a>
                    <?php else : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($mpwp_disable_url); ?>">
                            <?php esc_html_e('Disabilita', 'mc-reseller-manager'); ?>
                        </a>
                    <?php endif; ?>
                    <a class="button button-link-delete" href="<?php echo esc_url($mpwp_delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo reseller?', 'mc-reseller-manager')); ?>');">
                        <?php esc_html_e('Elimina', 'mc-reseller-manager'); ?>
                    </a>
                </p>                
            </div>
        </div>
        
        <div class="card" style="width:100%;max-width:100%;padding:16px;box-sizing:border-box;">
            <h2 class="title" style="padding-left:0"><?php esc_html_e('Prezzo speciale sul prodotto', 'mc-reseller-manager'); ?></h2>
            <p><?php esc_html_e('Puoi applicare un prezzo dedicato per questo rivenditore. Lo sconto globale verrà ignorato se viene assegnato un prezzo speciale al prodotto', 'mc-reseller-manager'); ?></p>
            <?php
            //Controllo che sia attivo reseller_show_full_price
            if(get_option('reseller_show_full_price') == 1){
                echo '<p style="color:red;">'.esc_html__('Attenzione: è attiva l\'opzione per mostrare il prezzo orginale e lo sconto solo in fase di acquisto, quindi non è possibile assegnare un prezzo speciale ai prodotti.', 'mc-reseller-manager').'</p>';
                echo '<p style="color:red;">'.esc_html__('Disabilita l\'opzione in impostazioni', 'mc-reseller-manager').'</p>';
            }else {
            $mpwp_reset     = isset($_GET['mpwp_reset']) || isset($_POST['mpwp_reset']);
            $mpwp_search    = isset($_GET['mpwp_product_search'])
                ? sanitize_text_field(wp_unslash($_GET['mpwp_product_search']))
                : (isset($_POST['mpwp_product_search']) ? sanitize_text_field(wp_unslash($_POST['mpwp_product_search'])) : '');
            $mpwp_paged     = isset($_GET['mpwp_product_page'])
                ? max(1, absint(wp_unslash($_GET['mpwp_product_page'])))
                : (isset($_POST['mpwp_product_page']) ? max(1, absint(wp_unslash($_POST['mpwp_product_page']))) : 1);
            if ($mpwp_reset) {
                $mpwp_search = '';
                $mpwp_paged  = 1;
            }
            $mpwp_per_page  = 5;
            $mpwp_query_args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $mpwp_per_page,
                'paged'          => $mpwp_paged,
                's'              => $mpwp_search,
            ];
            $mpwp_products_query = new WP_Query($mpwp_query_args);
            ?>
            <form method="get" class="mpwp-product-search" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="mpwp-reseller-view">
                <input type="hidden" name="id" value="<?php echo esc_attr($mpwp_reseller_id); ?>">
                <label for="mpwp_product_search" class="screen-reader-text"><?php esc_html_e('Cerca prodotti', 'mc-reseller-manager'); ?></label>
                <input
                    type="search"
                    id="mpwp_product_search"
                    name="mpwp_product_search"
                    value="<?php echo esc_attr($mpwp_search); ?>"
                    placeholder="<?php esc_attr_e('Cerca prodotti...', 'mc-reseller-manager'); ?>"
                    class="regular-text"
                >
                <button class="button" type="submit"><?php esc_html_e('Cerca', 'mc-reseller-manager'); ?></button>
                <button class="button" type="submit" name="mpwp_reset" value="1"><?php esc_html_e('Reset', 'mc-reseller-manager'); ?></button>
            </form>

            <?php if ($mpwp_products_query->have_posts()) : ?>
                <form method="post">
                    <?php wp_nonce_field('mpwp_reseller_prices_' . $mpwp_reseller_id, 'mpwp_reseller_prices_nonce'); ?>
                    <input type="hidden" name="page" value="mpwp-reseller-view">
                    <input type="hidden" name="id" value="<?php echo esc_attr($mpwp_reseller_id); ?>">
                    <input type="hidden" name="mpwp_product_search" value="<?php echo esc_attr($mpwp_search); ?>">
                    <input type="hidden" name="mpwp_product_page" value="<?php echo esc_attr($mpwp_paged); ?>">

                    <table class="wp-list-table widefat fixed striped" style="margin-top:12px">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Prodotto', 'mc-reseller-manager'); ?></th>
                                <th scope="col"><?php esc_html_e('Prezzo pubblico', 'mc-reseller-manager'); ?></th>
                                <th scope="col"><?php esc_html_e('Prezzo speciale rivenditore', 'mc-reseller-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($mpwp_products_query->have_posts()) :
                                $mpwp_products_query->the_post();
                                $mpwp_product_id = get_the_ID();
                                $mpwp_price      = get_post_meta($mpwp_product_id, '_price', true);
                                $mpwp_special    = isset($mpwp_product_prices[$mpwp_product_id]) ? $mpwp_product_prices[$mpwp_product_id] : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html(get_the_title()); ?></td>
                                    <td><?php echo wp_kses_post(wc_price($mpwp_price)); ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="mpwp_reseller_product_price[<?php echo esc_attr($mpwp_product_id); ?>]"
                                            value="<?php echo $mpwp_special !== '' ? esc_attr($mpwp_special) : ''; ?>"
                                            class="regular-text"
                                            style="width:120px;"
                                        >
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php
                    $mpwp_total_pages = $mpwp_products_query->max_num_pages;
                    if ($mpwp_total_pages > 1) :
                        ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <?php
                                echo wp_kses_post(
                                    paginate_links([
                                        'total'   => $mpwp_total_pages,
                                        'current' => $mpwp_paged,
                                        'base'    => add_query_arg(
                                            [
                                                'page'                => 'mpwp-reseller-view',
                                                'id'                  => $mpwp_reseller_id,
                                                'mpwp_product_search' => $mpwp_search,
                                                'mpwp_product_page'   => '%#%',
                                            ],
                                            admin_url('admin.php')
                                        ),
                                        'format'  => '',
                                    ])
                                );
                                ?>
                            </div>
                        </div>
                    <?php
                endif;
                wp_reset_postdata();
                ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Salva prezzi speciali', 'mc-reseller-manager'); ?></button>
                    </p>
                </form>

            <?php else : ?>
                <p><?php esc_html_e('Nessun prodotto trovato.', 'mc-reseller-manager'); ?></p>
            <?php endif; ?>
        <?php }?>
        </div>
    </form>
</div>
