<?php
if (! current_user_can('manage_options')) {
    wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'mc-reseller-manager'));
}

$mpwp_success = '';
$mpwp_error   = '';
$mpwp_notice  = '';
$mpwp_name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
$mpwp_cf      = isset($_POST['cf']) ? sanitize_text_field(wp_unslash($_POST['cf'])) : '';
$mpwp_iva     = isset($_POST['piva']) ? sanitize_text_field(wp_unslash($_POST['piva'])) : '';
$mpwp_email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
$mpwp_status  = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'enabled';

if (
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mpwp_add_reseller_nonce'])
) {
    check_admin_referer('mpwp_add_reseller', 'mpwp_add_reseller_nonce');

    if ($mpwp_name === '' || $mpwp_email === '') {
        $mpwp_error = __('Compila tutti i campi richiesti.', 'mc-reseller-manager');
    } elseif (! is_email($mpwp_email)) {
        $mpwp_error = __('Inserisci un indirizzo email valido.', 'mc-reseller-manager');
    } elseif (email_exists($mpwp_email) || username_exists($mpwp_email)) {
        $mpwp_error = __('Questa email e gia registrata.', 'mc-reseller-manager');
    }

    if (! $mpwp_error) {
        $mpwp_generated_password = wp_generate_password(12, true, true);
        $mpwp_user_id = wp_insert_user([
            'user_login'      => $mpwp_email,
            'user_email'      => $mpwp_email,
            'user_pass'       => $mpwp_generated_password,
            'role'            => 'mpwp_reseller',
            'display_name'    => $mpwp_name,
            'user_nicename'   => sanitize_title($mpwp_name),
            'user_registered' => gmdate('Y-m-d H:i:s'),
            'meta_input' => [
                'mpwp_reseller_codice_fiscale' => $mpwp_cf,
                'mpwp_reseller_partita_iva' => $mpwp_iva,
                'mpwp_reseller_creation_from'=>"admin"
            ],
        ]);

        if (is_wp_error($mpwp_user_id)) {
            $mpwp_error = $mpwp_user_id->get_error_message();
        } else {
            $mpwp_status_value = $mpwp_status === 'disabled' ? 'disabled' : 'enabled';
            update_user_meta($mpwp_user_id, 'mpwp_reseller_status', $mpwp_status_value);

            $mpwp_profile_url = admin_url('admin.php?page=mpwp-reseller-view&id=' . $mpwp_user_id);
            $mpwp_success     = sprintf(
                /* translators: 1: profile url */
                __('Rivenditore creato con successo. %1$sApri profilo%2$s.', 'mc-reseller-manager'),
                '<a href="' . esc_url($mpwp_profile_url) . '">',
                '</a>'
            );

            // Invio email per creare la password se l'utente è abilitato
            if ($mpwp_status_value === 'enabled') {
                $mpwp_user  = get_user_by('id', $mpwp_user_id);
                $mpwp_key   = $mpwp_user ? get_password_reset_key($mpwp_user) : false;
                $mpwp_login = $mpwp_user ? $mpwp_user->user_login : '';

                if (! is_wp_error($mpwp_key) && $mpwp_key && $mpwp_login) {
                    $mpwp_reset_link = network_site_url(
                        'wp-login.php?action=rp&key=' . rawurlencode($mpwp_key) . '&login=' . rawurlencode($mpwp_login),
                        'login'
                    );

                    $mpwp_site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
                    $mpwp_subject   = sprintf('[%s] Crea la tua password rivenditore', $mpwp_site_name);
                    $mpwp_message   = sprintf(
                        "Ciao %s,\n\nsei stato abilitato come rivenditore su %s.\nPer impostare la tua password clicca il link seguente:\n%s\n\nSe non hai richiesto questo accesso ignora questa email.\n\nGrazie!",
                        $mpwp_name,
                        $mpwp_site_name,
                        $mpwp_reset_link
                    );

                    $mpwp_mail_sent = wp_mail($mpwp_email, $mpwp_subject, $mpwp_message);
                    if (! $mpwp_mail_sent) {
                        $mpwp_notice = __('Utente creato ma impossibile inviare l\'email di impostazione password. Invia manualmente il link di reset.', 'mc-reseller-manager');
                    }
                } else {
                    $mpwp_notice = __('Utente creato ma impossibile generare il link per impostare la password.', 'mc-reseller-manager');
                }

                $mpwp_name   = '';
                $mpwp_email  = '';
                $mpwp_status = 'enabled';
            }
        }
    }
}

$mpwp_ajax_nonce = wp_create_nonce('mpwp_check_reseller_field');
$mpwp_ajax_url   = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Aggiungi nuovo rivenditore', 'mc-reseller-manager'); ?></h1>
    <a href="?page=reseller-manager" class="page-title-action"><?php echo esc_html__('Torna alla lista', 'mc-reseller-manager'); ?></a>
    <hr class="wp-header-end">

    <div class="card" style="max-width:820px;margin-top:12px;">
        <?php if ($mpwp_success) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post($mpwp_success); ?></p></div>
        <?php endif; ?>
        <?php if ($mpwp_error) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($mpwp_error); ?></p></div>
        <?php endif; ?>
        <?php if ($mpwp_notice) : ?>
            <div class="notice notice-warning is-dismissible"><p><?php echo esc_html($mpwp_notice); ?></p></div>
        <?php endif; ?>

        <form method="post" id="mpwp-add-reseller-form">
            <?php wp_nonce_field('mpwp_add_reseller', 'mpwp_add_reseller_nonce'); ?>
            <div class="mpwp-form-grid">
                <div class="mpwp-field">
                    <label for="name"><?php echo esc_html__('Nome/Ragione sociale', 'mc-reseller-manager'); ?></label>
                    <input type="text" id="name" name="name" value="<?php echo esc_attr($mpwp_name); ?>" required>
                    <span class="mpwp-inline-status" aria-live="polite"></span>
                </div>

                <div class="mpwp-field">
                    <label for="email"><?php echo esc_html__('Email rivenditore', 'mc-reseller-manager'); ?></label>
                    <input type="email" id="email" name="email" value="<?php echo esc_attr($mpwp_email); ?>" autocomplete="email" required>
                    <span class="mpwp-inline-status" aria-live="polite"></span>
                </div>

                <div class="mpwp-field">
                    <label for="cf"><?php echo esc_html__('Codice fiscale', 'mc-reseller-manager'); ?></label>
                    <input type="text" id="cf" name="cf" value="<?php echo esc_attr($mpwp_cf); ?>">
                    <span class="mpwp-inline-status" aria-live="polite"></span>
                </div>

                <div class="mpwp-field">
                    <label for="piva"><?php echo esc_html__('Partita IVA', 'mc-reseller-manager'); ?></label>
                    <input type="text" id="piva" name="piva" value="<?php echo esc_attr($mpwp_iva); ?>">
                    <span class="mpwp-inline-status" aria-live="polite"></span>
                </div>

                <div class="mpwp-field">
                    <label for="status"><?php echo esc_html__('Stato', 'mc-reseller-manager'); ?></label>
                    <select id="status" name="status">
                        <option value="enabled" <?php selected($mpwp_status, 'enabled'); ?>><?php echo esc_html__('Abilitato', 'mc-reseller-manager'); ?></option>
                        <option value="disabled" <?php selected($mpwp_status, 'disabled'); ?>><?php echo esc_html__('Disabilitato', 'mc-reseller-manager'); ?></option>
                    </select>
                </div>
                <div class="mpwp-field">
                    <span class="description">
                        <?php echo esc_html__('Imposta se il rivenditore puo accedere subito.', 'mc-reseller-manager'); ?><br>
                        <?php echo esc_html__('Se abilitato verrà inviata una mail automatica per la creazione della password.', 'mc-reseller-manager'); ?><br>
                        <?php echo esc_html__('Altrimenti la riceverà quando verrà abilitato.', 'mc-reseller-manager'); ?>
                    </span>
                </div>
            </div>

            <hr>

            <p class="submit" style="margin-top:12px;">
                <button type="submit" class="button button-primary"><?php echo esc_html__('Aggiungi Rivenditore', 'mc-reseller-manager'); ?></button>
            </p>
        </form>
    </div>
</div>

<style>
#mpwp-add-reseller-form .mpwp-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(100%,1fr));gap:10px}
#mpwp-add-reseller-form .mpwp-field{display:flex;flex-direction:column;gap:2px}
#mpwp-add-reseller-form .mpwp-inline-status{min-height:16px;font-size:12px;color:#646970}
#mpwp-add-reseller-form .mpwp-inline-status.is-error{color:#d63638}
#mpwp-add-reseller-form .mpwp-inline-status.is-ok{color:#1f8a3d}
#mpwp-add-reseller-form .mpwp-inline-status.is-loading{color:#646970}
</style>

<script>
(function() {
    var form = document.getElementById('mpwp-add-reseller-form');
    if (!form) return;

    var ajaxUrl = '<?php echo esc_js($mpwp_ajax_url); ?>';
    var nonce = '<?php echo esc_js($mpwp_ajax_nonce); ?>';
    var submitBtn = form.querySelector('button[type="submit"]');

    var fields = [
        { input: form.querySelector('#name'), field: 'name' },
        { input: form.querySelector('#email'), field: 'email' }
    ];

    function setStatus(input, state, text) {
        if (!input) return;
        var status = input.closest('.mpwp-field').querySelector('.mpwp-inline-status');
        if (!status) return;
        status.classList.remove('is-error', 'is-ok', 'is-loading');
        status.textContent = '';
        if (!state) return;
        status.classList.add('is-' + state);
        status.textContent = text || (state === 'loading' ? 'Controllo...' : '');
    }

    function toggleSubmit() {
        if (!submitBtn) return;
        var hasError = form.querySelector('.mpwp-inline-status.is-error');
        submitBtn.disabled = !!hasError;
    }

    function checkField(input, field) {
        if (!input || !field) return;
        var value = input.value.trim();
        if (!value) {
            setStatus(input);
            toggleSubmit();
            return;
        }

        setStatus(input, 'loading');

        var body = new URLSearchParams();
        body.append('action', 'mpwp_check_reseller_field');
        body.append('field', field);
        body.append('value', value);
        body.append('_ajax_nonce', nonce);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data || typeof data.success === 'undefined') {
                    throw new Error('bad_response');
                }
                var exists = data.data && data.data.exists;
                setStatus(
                    input,
                    exists ? 'error' : 'ok',
                    data.data && data.data.message ? data.data.message : ''
                );
                toggleSubmit();
            })
            .catch(function() {
                setStatus(input, 'error', 'Impossibile verificare ora.');
                toggleSubmit();
            });
    }

    fields.forEach(function(item) {
        if (!item.input) return;
        item.input.addEventListener('blur', function() {
            checkField(item.input, item.field);
        });
    });
})();
</script>
