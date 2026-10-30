<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Reseller_Manager{
    private static $form_notices = [];

    public static function init() {
        //Aggiunge i menu admin
        add_action('admin_menu', array(__CLASS__, 'AdminMenu'));

        //Applico CSS
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        //Creo shortcode login reseller
        add_shortcode('mcwp_reseller_auth', [__CLASS__, 'reseller_login_form']);
        //Creo shortcode sign up reseller
        add_shortcode('mcwp_reseller_signup', [__CLASS__, 'reseller_signup_form']);

        //Registro i blocchi che replicano gli shortcode
        add_action('init', [__CLASS__, 'register_blocks']);

        //Handle form
        add_action('init', [__CLASS__, 'handle_shortcode_form']);

        //Ajax check duplicati
        add_action('wp_ajax_mpwp_check_reseller_field', [__CLASS__, 'ajax_check_reseller_field']);

        //Applico sconto globale se rivenditore è abilitato al carrello
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_discount'], 20);

        //Se non è abilitata l'opzione
        if(get_option('reseller_show_full_price') == 0){
            //Mostro i prezzi rivenditore
            add_filter('woocommerce_get_price_html', [__CLASS__, 'filter_price_html'], 10, 2);
            // Raw product getters retain WooCommerce's numeric catalog values.
            //Mando al carrello i prezzi corretti
            add_action('woocommerce_before_calculate_totals', [__CLASS__, 'filter_price'], 10, 1);
        }

        //Modifica campi edit-account solo per rivenditori
        add_filter('woocommerce_save_account_details_required_fields', [__CLASS__, 'edit_account_rivenditori_set_fields'], 10, 1);
        add_filter('woocommerce_edit_account_form_fields', [__CLASS__, 'edit_account_rivenditori_add_fields'], 10, 1);
        
        //Salvo campi edit-account solo per rivenditori
        add_action('woocommerce_save_account_details', [__CLASS__, 'save_account_rivenditori_fields'], 10, 1);
        
    }

    public static function activation() {
       //Creo il ruolo rivenditore
       self::register_reseller_role();
    }

    public static function deactivation() {
        // Eventuali pulizie o disattivazioni
    }

    public static function uninstall() {
        // Keep WordPress accounts and their content. Remove only this plugin's role/data.
        foreach (get_users(['role' => 'mpwp_reseller']) as $user) {
            $user->remove_role('mpwp_reseller');
            if (!$user->roles) {
                $user->add_role('subscriber');
            }
        }
        foreach (['mpwp_reseller_status', 'mpwp_reseller_discount',
            'mpwp_reseller_product_prices', 'mpwp_reseller_codice_fiscale',
            'mpwp_reseller_partita_iva', 'mpwp_reseller_creation_from'] as $key) {
            delete_metadata('user', 0, $key, '', true);
        }
        delete_option('reseller_show_full_price');
        delete_option('reseller_apply_regular_price');
        remove_role('mpwp_reseller');
        self::unregister_blocks();
    }

    public static function AdminMenu() {
        // Menu principale
        add_menu_page(
            __('Rivenditori', 'mc-reseller-manager'),
            __('Rivenditori', 'mc-reseller-manager'),
            'manage_options',
            'reseller-manager',
            array(__CLASS__, 'reseller_manager_page'),
            'dashicons-businessman',
            20
        );

        // Sottomenu: Aggiungi nuovo
        add_submenu_page(
            'reseller-manager',
            __('Aggiungi nuovo rivenditore', 'mc-reseller-manager'),
            __('Aggiungi nuovo', 'mc-reseller-manager'),
            'manage_options',
            'mpwp-reseller-add',
            array(__CLASS__, 'reseller_manager_add_page')
        );

        // Pagina nascosta, senza voce di menu (Slug necessario per evitare deprecated)
        add_submenu_page(
            'reseller-manager',
            'Profilo Rivenditore',
            'Profilo Rivenditore',
            'manage_options',
            'mpwp-reseller-view',
            array(__CLASS__, 'reseller_profile_page')
        );

        //Nascondo pagine non necessarie
        add_action('admin_head', function() {
            remove_submenu_page('reseller-manager', 'mpwp-reseller-view');
        });

        // Pagina settings
        add_submenu_page(
            'reseller-manager',
            __('Impostazioni', 'mc-reseller-manager'),
            __('Impostazioni', 'mc-reseller-manager'),
            'manage_options',
            'reseller-manager-settings',
            array(__CLASS__, 'reseller_manager_settings_page')
        );
    }

    public static function register_reseller_role() {
        if ( get_role('mpwp_reseller') === null ) {
            add_role(
                'mpwp_reseller',
                'Rivenditore',
                ['read' => true] // può loggarsi
            );
        }
    }

    public static function reseller_manager_page() {
        require_once plugin_dir_path(__FILE__) . 'class-reseller-table.php';
        require_once( plugin_dir_path(__FILE__) . '../pages/admin-view.php');
    }

    private static function set_form_notice($form, $message, $type = 'error') {
        self::$form_notices[$form] = [
            'message' => $message,
            'type'    => $type,
        ];
    }

    public static function get_form_notice($form) {
        return isset(self::$form_notices[$form]) ? self::$form_notices[$form] : null;
    }

    public static function reseller_profile_page() {
        require_once( plugin_dir_path(__FILE__) . '../pages/reseller-profile.php');
    }

    public static function reseller_login_form() {
        ob_start();
        include plugin_dir_path(__FILE__) . '../shortcodes/reseller-login.php';
        return ob_get_clean();
    }

    public static function reseller_signup_form() {
        ob_start();
        include plugin_dir_path(__FILE__) . '../shortcodes/reseller-signup.php';
        return ob_get_clean();
    }

    public static function register_blocks() {
        $blocks_dir = plugin_dir_path(__FILE__) . '../blocks/';
        $blocks = [
            $blocks_dir . 'reseller-login',
            $blocks_dir . 'reseller-signup',
        ];

        foreach ($blocks as $block_path) {
            if (file_exists(trailingslashit($block_path) . 'block.json')) {
                register_block_type($block_path);
            }
        }
    }

    public static function unregister_blocks() {
        $blocks_dir = plugin_dir_path(__FILE__) . '../blocks/';
        $blocks = [
            $blocks_dir . 'reseller-login',
            $blocks_dir . 'reseller-signup',
        ];

        foreach ($blocks as $block_path) {
            if (file_exists(trailingslashit($block_path) . 'block.json')) {
                $metadata = json_decode(file_get_contents(trailingslashit($block_path) . 'block.json'), true);
                if (isset($metadata['name']) && WP_Block_Type_Registry::get_instance()->is_registered($metadata['name'])) {
                    unregister_block_type($metadata['name']);
                }
            }
        }
    }

    public static function reseller_manager_settings_page() {
        require_once( plugin_dir_path(__FILE__) . '../pages/admin-settings.php');
    }

    public static function reseller_manager_add_page() {
        require_once( plugin_dir_path(__FILE__) . '../pages/admin-add.php');
    }

    public static function enqueue_assets() {
        $css_path = plugin_dir_path(__FILE__) . '../assets/css/reseller-manager.css';
        $css_url  = plugins_url('../assets/css/reseller-manager.css', __FILE__);

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'mpwp-reseller-manager',
                $css_url,
                [],
                filemtime($css_path)
            );
        }

        self::enqueue_assets_rivenditori();
    }

    public static function enqueue_assets_rivenditori() {
        //Se account è rivenditore
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        $user = get_userdata($user_id);

        if (in_array('mpwp_reseller', (array) $user->roles)) {
            $css_path = plugin_dir_path(__FILE__) . '../assets/css/rivenditori.css';
            $css_url  = plugins_url('../assets/css/rivenditori.css', __FILE__);

            if (file_exists($css_path)) {
                wp_enqueue_style(
                    'mpwp-reseller-rivenditori',
                    $css_url,
                    ['mpwp-reseller-manager'],
                    filemtime($css_path)
                );
            }
        }
    }

    public static function handle_shortcode_form() {
        if (! isset($_POST['mpwp_reseller_action'])) {
            return;
        }

        foreach ($_POST as $value) {
            if (!is_scalar($value)) {
                return;
            }
        }
        $action = sanitize_key(wp_unslash($_POST['mpwp_reseller_action']));
        $nonce  = isset($_POST['mpwp_reseller_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mpwp_reseller_nonce']))
            : '';

        if (empty($nonce) || ! wp_verify_nonce($nonce, 'mpwp_reseller_' . $action)) {
            return;
        }

        if ($action === 'login') {
            self::handle_login_form();
        } elseif ($action === 'signup') {
            self::handle_signup_form();
        }
    }

    private static function handle_signup_form() {
        $nonce  = isset($_POST['mpwp_reseller_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['mpwp_reseller_nonce']))
        : '';

        if (empty($nonce) || ! wp_verify_nonce($nonce, 'mpwp_reseller_signup')) {
            self::set_form_notice('signup', 'Si e\' verificato un problema di sicurezza. Ricarica la pagina e riprova.');
            return;
        }


        $name     = isset($_POST['mpwp-reseller-signup-name']) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-name'])) : '';
        $codice_fiscale = isset($_POST['mpwp-reseller-signup-cf']) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-cf'])) : '';
        $partita_iva = isset($_POST['mpwp-reseller-signup-piva']) ? sanitize_text_field(wp_unslash($_POST['mpwp-reseller-signup-piva'])) : '';
        $email    = isset($_POST['mpwp-reseller-signup-email']) ? sanitize_email(wp_unslash($_POST['mpwp-reseller-signup-email'])) : '';
        $password = isset($_POST['mpwp-reseller-signup-password']) ? wp_unslash($_POST['mpwp-reseller-signup-password']) : '';

        if (empty($name) || empty($email) || empty($password)) {
            self::set_form_notice('signup', 'Compila tutti i campi richiesti.');
            return;
        }

        if (! is_email($email)) {
            self::set_form_notice('signup', 'Inserisci un indirizzo email valido.');
            return;
        }

        // Evita duplicati
        if (email_exists($email)) {
            self::set_form_notice('signup', 'Questa email e\' gia\' registrata.');
            return;
        }

        // Crea utente WordPress
        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'role'       => 'mpwp_reseller',
            'user_registered' => gmdate('Y-m-d H:i:s'),
            'display_name' => $name,
            'user_nicename' => $name,
            'meta_input' => [
                'mpwp_reseller_codice_fiscale' => $codice_fiscale,
                'mpwp_reseller_partita_iva' => $partita_iva,
                'mpwp_reseller_creation_from'=>"frontend"
            ]
        ]);

        if (is_wp_error($user_id)) {
            self::set_form_notice('signup', 'Errore durante l\'iscrizione. Riprova piu\' tardi.');
            return;
        }

        // IMPOSTA STATO "DISABILITATO" DI DEFAULT
        update_user_meta($user_id, 'mpwp_reseller_status', 'disabled');

        //Invio notifica in attesa di approvazione
        self::set_form_notice(
            'signup',
            __('Registrazione completata. Il tuo account è in approvazione e riceverai una conferma quando sarà attivato.', 'mc-reseller-manager'),
            'success'
        );
    }

    private static function handle_login_form() {
        $nonce  = isset($_POST['mpwp_reseller_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['mpwp_reseller_nonce']))
        : '';

        if (empty($nonce) || ! wp_verify_nonce($nonce, 'mpwp_reseller_login')) {
            self::set_form_notice('login', 'Errore di convalida. Aggiorna la pagina e riprova.');
            return;
        }

        $email    = isset($_POST['login_email']) ? sanitize_email(wp_unslash($_POST['login_email'])) : '';
        $password = isset($_POST['login_password']) ? wp_unslash($_POST['login_password']) : '';
        $remember = ! empty($_POST['rememberme']);

        if (empty($email) || empty($password)) {
            self::set_form_notice('login', 'Inserisci email e password.');
            return;
        }

        $user = wp_authenticate($email, $password);

        if (is_wp_error($user)) {
            self::set_form_notice('login', __('Credenziali errate. Riprova.', 'mc-reseller-manager'));
            return;
        }

        // Controllo stato reseller
        $status = get_user_meta($user->ID, 'mpwp_reseller_status', true);

        if ($status !== 'enabled' || !in_array('mpwp_reseller', (array) $user->roles, true)) {
            self::set_form_notice('login', 
                __('Il tuo account rivenditore non è ancora stato approvato. Riprova più tardi.', 'mc-reseller-manager'),
                'warning'
            );
            return;
        }

        // Login OK
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        // Trigger core login hook to match wp_signon behavior.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action('wp_login', $user->user_login, $user);

        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url();
        wp_safe_redirect($shop_url ? $shop_url : home_url());
        exit;
    }

    private static function is_enabled_reseller() {
        $user = wp_get_current_user();
        return $user->exists()
            && in_array('mpwp_reseller', (array) $user->roles, true)
            && get_user_meta($user->ID, 'mpwp_reseller_status', true) === 'enabled';
    }

    private static function reseller_discount() {
        $value = get_user_meta(get_current_user_id(), 'mpwp_reseller_discount', true);
        return is_numeric($value) ? max(0, min(100, (float) $value)) : 0;
    }

    private static function reseller_price($product) {
        // Edit context bypasses display filters. Never mutate regular/sale prices.
        $price = get_option('reseller_apply_regular_price') == 1
            ? $product->get_regular_price('edit') : $product->get_price('edit');
        if (!is_numeric($price)) {
            return null;
        }
        $prices = get_user_meta(get_current_user_id(), 'mpwp_reseller_product_prices', true);
        if (is_array($prices) && isset($prices[$product->get_id()])
            && is_numeric($prices[$product->get_id()]) && (float) $prices[$product->get_id()] >= 0) {
            return (float) $prices[$product->get_id()];
        }
        return (float) $price * (1 - self::reseller_discount() / 100);
    }

    public static function apply_discount($cart) {
        if (!self::is_enabled_reseller() || get_option('reseller_show_full_price') != 1) {
            return;
        }
        $discount = self::reseller_discount();
        if (!$discount) {
            return;
        }
        foreach ($cart->get_fees() as $fee) {
            if ($fee->name === __('Sconto Rivenditore', 'mc-reseller-manager')) {
                return;
            }
        }
        $cart->add_fee(__('Sconto Rivenditore', 'mc-reseller-manager'), -$cart->get_subtotal() * $discount / 100);
    }

    public static function filter_price_html($price_html, $product) {
        if (!self::is_enabled_reseller() || get_option('reseller_show_full_price') == 1
            || $product->is_type(array('variable', 'grouped'))) {
            return $price_html;
        }
        $price = self::reseller_price($product);
        return $price === null ? $price_html : wc_price(wc_get_price_to_display($product, ['price' => $price]));
    }

    // Retained for callers of the previous public method; not registered as a getter filter.
    public static function filter_sale_price($sale_price, $product) {
        if (!self::is_enabled_reseller() || get_option('reseller_show_full_price') == 1) {
            return $sale_price;
        }
        $price = self::reseller_price($product);
        return $price === null ? $sale_price : $price;
    }

    public static function filter_price($cart) {
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'];
            // Reload persisted catalog values: repeated totals must not compound discounts.
            $original = wc_get_product($product->get_id());
            if (!$original) {
                continue;
            }
            $price = $original->get_price('edit');
            if (self::is_enabled_reseller() && get_option('reseller_show_full_price') != 1) {
                $price = self::reseller_price($original);
            }
            if ($price !== null && is_numeric($price)) {
                $product->set_price($price);
            }
        }
    }

    //Controllo duplicati lato admin
    public static function ajax_check_reseller_field() {
        check_ajax_referer('mpwp_check_reseller_field');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Non autorizzato', 'mc-reseller-manager')], 403);
        }

        $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : '';
        $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if (! $field || $value === '') {
            wp_send_json_error(['message' => __('Parametro mancante', 'mc-reseller-manager')], 400);
        }

        if ($field === 'email') {
            $email  = sanitize_email($value);
            $exists = email_exists($email);

            wp_send_json_success([
                'exists'  => (bool) $exists,
                'message' => $exists
                    ? __('Email gia registrata.', 'mc-reseller-manager')
                    : __('Email disponibile.', 'mc-reseller-manager'),
            ]);
        }

        if ($field === 'name') {
            $name  = sanitize_text_field($value);
            $query = new WP_User_Query([
                'role__in'       => ['mpwp_reseller'],
                'search'         => '*' . esc_attr($name) . '*',
                'search_columns' => ['display_name'],
                'number'         => 1,
            ]);

            $exists = ! empty($query->get_results());

            wp_send_json_success([
                'exists'  => (bool) $exists,
                'message' => $exists
                    ? __('Nome gia usato da un rivenditore.', 'mc-reseller-manager')
                    : __('Nome disponibile.', 'mc-reseller-manager'),
            ]);
        }

        wp_send_json_error(['message' => __('Campo non supportato', 'mc-reseller-manager')], 400);
    }

    public static function send_email_enable($user_id, $status) {
        $user = get_userdata($user_id);
        $email = $user->user_email;
        $name = $user->display_name;

        $creation = get_user_meta($user_id, 'mpwp_reseller_creation_from', true);
        if($creation == "admin"){
            //Genero link per impostare la password
            $mpwp_user  = get_user_by('id', $user_id);
            $mpwp_key   = $mpwp_user ? get_password_reset_key($mpwp_user) : false;
            $mpwp_login = $mpwp_user ? $mpwp_user->user_login : '';
            $reset_link = $mpwp_login ? site_url("wp-login.php?action=rp&key=$mpwp_key&login=" . rawurlencode($mpwp_login), 'login') : '';

            $subject = sprintf(__('Account Abilitato - %s', 'mc-reseller-manager'), get_bloginfo('name'));
            $message = sprintf(
                __("Gentile %s,\n\nIl suo account rivenditore è stato abilitato con successo.\n\n", 'mc-reseller-manager'),
                $name
            );
            $message.= sprintf(
                __("Per impostare la password, clicca sul seguente link: %s", 'mc-reseller-manager'),
                $reset_link
            );
            $message.= __("\n\nGrazie.\n", 'mc-reseller-manager');

           
        }else{
            $subject = sprintf(__('Account Abilitato - %s', 'mc-reseller-manager'), get_bloginfo('name'));
            $message = sprintf(
                __("Gentile %s,\n\nIl suo account rivenditore è stato abilitato con successo.\n\nGrazie.\n", 'mc-reseller-manager'),
                $name
            );

        }

        wp_mail($email, $subject, $message);
    }

    public static function edit_account_rivenditori_add_fields() {
        //Se account è rivenditore
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (in_array('mpwp_reseller', (array) $user->roles)) {
            //Aggiungo campi codice fiscale e partita iva nel form
        ?>
            <p class="form-row form-row-first woocommerce-form-row woocommerce-form-row--first">
                <label for="mpwp_reseller_codice_fiscale"><?php _e('Codice Fiscale', 'mc-reseller-manager'); ?></label>
                <input type="text" id="mpwp_reseller_codice_fiscale" name="mpwp_reseller_codice_fiscale" value="<?php echo esc_attr(get_user_meta($user_id, 'mpwp_reseller_codice_fiscale', true)); ?>" />
            </p>
            <p class="form-row form-row-last woocommerce-form-row woocommerce-form-row--first">
                <label for="mpwp_reseller_partita_iva"><?php _e('Partita IVA', 'mc-reseller-manager'); ?></label>
                <input type="text" id="mpwp_reseller_partita_iva" name="mpwp_reseller_partita_iva" value="<?php echo esc_attr(get_user_meta($user_id, 'mpwp_reseller_partita_iva', true)); ?>" />
            </p>
        <?php
        }
    }

    public static function edit_account_rivenditori_set_fields($fields) {
        //Se account è rivenditore
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (in_array('mpwp_reseller', (array) $user->roles)) {
            //Disattivo campi quando salvo
            unset($fields['account_first_name']);
            unset($fields['account_last_name']);            
        }

        return $fields;
    }

    public static function save_account_rivenditori_fields($user_id) {
        //Se account è rivenditore
        $user = get_userdata($user_id);
        if (in_array('mpwp_reseller', (array) $user->roles)) {
            //Salvo campi codice fiscale e partita iva
            $codice_fiscale = isset($_POST['mpwp_reseller_codice_fiscale']) ? sanitize_text_field(wp_unslash($_POST['mpwp_reseller_codice_fiscale'])) : '';
            $partita_iva = isset($_POST['mpwp_reseller_partita_iva']) ? sanitize_text_field(wp_unslash($_POST['mpwp_reseller_partita_iva'])) : '';

            update_user_meta($user_id, 'mpwp_reseller_codice_fiscale', $codice_fiscale);
            update_user_meta($user_id, 'mpwp_reseller_partita_iva', $partita_iva);
        }
    }
}
