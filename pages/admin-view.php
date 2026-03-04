<?php
$mpwp_table = new Reseller_Table();
$mpwp_table->prepare_items();

//Gestione messaggi
if (isset($_GET['message'])) {
    $mpwp_message = sanitize_key(wp_unslash($_GET['message']));
    if($mpwp_message === 'enabled'){
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Rivenditore abilitato con successo', 'mc-reseller-manager') . '</p></div>';
    }
    if($mpwp_message === 'disabled'){
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Rivenditore disabilitato con successo', 'mc-reseller-manager') . '</p></div>';
    }
    if($mpwp_message === 'deleted'){
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Rivenditore eliminato con successo', 'mc-reseller-manager') . '</p></div>';
    }
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo __('Rivenditori', 'mc-reseller-manager'); ?></h1>
    <a href="?page=mpwp-reseller-add" class="page-title-action"><?php echo __('Aggiungi nuovo', 'mc-reseller-manager'); ?></a>
    <hr class="wp-header-end">

    <p><?php echo __('Per mostrare la login e l\'accesso del rivenditore, inserisci questi shortcode:', 'mc-reseller-manager'); ?></p>
    <p><?php echo __('<code>[mcwp_reseller_signup]</code> per il form di registrazione', 'mc-reseller-manager'); ?></p>
    <p><?php echo __('<code>[mcwp_reseller_auth]</code> per il form di accesso', 'mc-reseller-manager'); ?></p>
    <p><?php echo __('Oppure puoi usare il sistema a blocchi del editor di WordPress Gutenberg.', 'mc-reseller-manager'); ?></p>

    <form method="post">
        <?php wp_nonce_field('mpwp_reseller_table_actions', 'mpwp_reseller_table_nonce'); ?>
        <?php $mpwp_table->display(); ?>
    </form>
</div>
