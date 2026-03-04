<?php
//Shortcode per il signup del reseller
$mpwp_signup_notice = Reseller_Manager::get_form_notice('signup');
$mpwp_signup_name   = isset($_POST['signup_name']) ? sanitize_text_field(wp_unslash($_POST['signup_name'])) : '';
$mpwp_signup_email  = isset($_POST['signup_email']) ? sanitize_email(wp_unslash($_POST['signup_email'])) : '';
$mpwp_signup_cf    = isset($_POST['signup_company']) ? sanitize_text_field(wp_unslash($_POST['signup_company'])) : '';
$mpwp_signup_piva  = isset($_POST['signup_company']) ? sanitize_text_field(wp_unslash($_POST['signup_company'])) : '';
$mpwp_signup_password = isset($_POST['signup_password']) ? sanitize_text_field(wp_unslash($_POST['signup_password'])) : '';
?>

<?php if ($mpwp_signup_notice) : ?>
    <div class="mpwp-reseller-message mpwp-reseller-message-<?php echo esc_attr($mpwp_signup_notice['type']); ?>">
        <?php echo esc_html($mpwp_signup_notice['message']); ?>
    </div>
<?php endif; ?>

<form method="post" class="mpwp-reseller-form mpwp-reseller-form-signup">
    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-name"><?php echo __('Nome / Ragione sociale', 'mc-reseller-manager'); ?></label>
        <input type="text" name="signup_name" id="mpwp-reseller-signup-name" value="<?php echo esc_attr($mpwp_signup_name); ?>" required>
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-company"><?php echo __('Codice fiscale', 'mc-reseller-manager'); ?> (<?php echo __('opzionale', 'mc-reseller-manager'); ?>)</label>
        <input type="text" name="signup_company" id="mpwp-reseller-signup-cf" value="<?php echo esc_attr($mpwp_signup_cf); ?>">
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-company"><?php echo __('Partita IVA', 'mc-reseller-manager'); ?> (<?php echo __('opzionale', 'mc-reseller-manager'); ?>)</label>
        <input type="text" name="signup_company" id="mpwp-reseller-signup-piva" value="<?php echo esc_attr($mpwp_signup_piva); ?>">
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-email"><?php echo __('Email', 'mc-reseller-manager'); ?></label>
        <input type="email" name="signup_email" id="mpwp-reseller-signup-email" value="<?php echo esc_attr($mpwp_signup_email); ?>" required>
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-signup-password"><?php echo __('Password', 'mc-reseller-manager'); ?></label>
        <input type="password" name="signup_password" id="mpwp-reseller-signup-password" required>
    </p>

    <?php wp_nonce_field('mpwp_reseller_signup', 'mpwp_reseller_nonce'); ?>
    <input type="hidden" name="mpwp_reseller_action" value="signup">

    <p class="mpwp-field">
        <button type="submit"><?php echo __('Iscriviti', 'mc-reseller-manager'); ?></button>
    </p>
</form>
