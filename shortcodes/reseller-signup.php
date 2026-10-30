<?php
//Shortcode per il signup del reseller
$mpwp_signup_notice = Reseller_Manager::get_form_notice('signup');
$mpwp_signup_name   = (isset($_POST['mpwp-reseller-signup-name']) && is_string($_POST['mpwp-reseller-signup-name'])) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-name'])) : '';
$mpwp_signup_email  = (isset($_POST['mpwp-reseller-signup-email']) && is_string($_POST['mpwp-reseller-signup-email'])) ? sanitize_email(wp_unslash($_POST['mpwp-reseller-signup-email'])) : '';
$mpwp_signup_cf    = (isset($_POST['mpwp-reseller-signup-cf']) && is_string($_POST['mpwp-reseller-signup-cf'])) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-cf'])) : '';
$mpwp_signup_piva  = (isset($_POST['mpwp-reseller-signup-piva']) && is_string($_POST['mpwp-reseller-signup-piva'])) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-piva'])) : '';
?>

<?php if ($mpwp_signup_notice) : ?>
    <div class="mpwp-reseller-message mpwp-reseller-message-<?php echo esc_attr($mpwp_signup_notice['type']); ?>">
        <?php echo esc_html($mpwp_signup_notice['message']); ?>
    </div>
<?php endif; ?>

<form method="post" class="mpwp-reseller-form mpwp-reseller-form-signup">
    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-name"><?php echo __('Nome / Ragione sociale', 'mc-reseller-manager'); ?></label>
        <input type="text" name="mpwp-reseller-signup-name" id="mpwp-reseller-signup-name" value="<?php echo esc_attr($mpwp_signup_name); ?>" required>
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-cf"><?php echo __('Codice fiscale', 'mc-reseller-manager'); ?> (<?php echo __('opzionale', 'mc-reseller-manager'); ?>)</label>
        <input type="text" name="mpwp-reseller-signup-cf" id="mpwp-reseller-signup-cf" value="<?php echo esc_attr($mpwp_signup_cf); ?>">
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-piva"><?php echo __('Partita IVA', 'mc-reseller-manager'); ?> (<?php echo __('opzionale', 'mc-reseller-manager'); ?>)</label>
        <input type="text" name="mpwp-reseller-signup-piva" id="mpwp-reseller-signup-piva" value="<?php echo esc_attr($mpwp_signup_piva); ?>">
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-email"><?php echo __('Email', 'mc-reseller-manager'); ?></label>
        <input type="email" name="mpwp-reseller-signup-email" id="mpwp-reseller-signup-email" value="<?php echo esc_attr($mpwp_signup_email); ?>" required>
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-password"><?php echo __('Password', 'mc-reseller-manager'); ?></label>
        <input type="password" name="mpwp-reseller-signup-password" id="mpwp-reseller-signup-password" required>
    </p>

    <?php wp_nonce_field('mpwp_reseller_signup', 'mpwp_reseller_nonce'); ?>
    <input type="hidden" name="mpwp_reseller_action" value="signup">

    <p class="mpwp-field">
        <button type="submit"><?php echo __('Iscriviti', 'mc-reseller-manager'); ?></button>
    </p>
</form>
