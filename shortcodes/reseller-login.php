<?php
// Shortcode per il login del reseller
$mpwp_login_notice = Reseller_Manager::get_form_notice('login');
$mpwp_login_email  = isset($_POST['login_email']) ? sanitize_email(wp_unslash($_POST['login_email'])) : '';
?>

<?php if ($mpwp_login_notice) : ?>
    <div class="mpwp-reseller-message mpwp-reseller-message-<?php echo esc_attr($mpwp_login_notice['type']); ?>">
        <?php echo esc_html($mpwp_login_notice['message']); ?>
    </div>
<?php endif; ?>

<form method="post" class="mpwp-reseller-form mpwp-reseller-form-login">
    <p class="mpwp-field">
        <label for="mpwp-reseller-login-email"><?php echo __('Email', 'mc-reseller-manager'); ?></label>
        <input type="email" name="login_email" id="mpwp-reseller-login-email" value="<?php echo esc_attr($mpwp_login_email); ?>" required>
    </p>

    <p class="mpwp-field">
        <label for="mpwp-reseller-login-password"><?php echo __('Password', 'mc-reseller-manager'); ?></label>
        <input type="password" name="login_password" id="mpwp-reseller-login-password" required>
    </p>

    <p class="mpwp-field">
        <label>
            <input type="checkbox" name="rememberme" value="forever">
            <?php echo __('Ricordami', 'mc-reseller-manager'); ?>
        </label>
    </p>

    <?php wp_nonce_field('mpwp_reseller_login', 'mpwp_reseller_nonce'); ?>
    <input type="hidden" name="mpwp_reseller_action" value="login">

    <p class="mpwp-field">
        <button type="submit"><?php echo __('Accedi', 'mc-reseller-manager'); ?></button>
    </p>
</form>
