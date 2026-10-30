<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Reseller_Table extends WP_List_Table {

    private static $confirm_context = null;

    public function __construct() {
        parent::__construct([
            'singular' => 'reseller',
            'plural'   => 'resellers',
            'ajax'     => false
        ]);

        // Handle action anche se init è già passato
        if (did_action('init')) {
            self::handle_action();
        } else {
            add_action('init', [__CLASS__, 'handle_action']);
        }
    }

    public static function handle_action() {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $id     = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;

        if (! $action || ! $id || ! isset($_GET['_wpnonce'])) {
            return;
        }

        $supported_actions = ['enable', 'disable', 'delete'];

        if (! in_array($action, $supported_actions, true)) {
            return;
        }

        $target = get_userdata($id);
        if (!current_user_can('manage_options') || !$target
            || !in_array('mpwp_reseller', (array) $target->roles, true)
            || ($action === 'delete' && !current_user_can('delete_user', $id))) {
            wp_die(esc_html__('Non autorizzato.', 'mc-reseller-manager'));
        }

        $nonce = self::validate_action_nonce($action, $id);

        if (! self::is_confirmed()) {
            self::prompt_action_confirmation($action, $id, $nonce);
            return;
        }

        if ($action === 'enable') {
            update_user_meta($id, 'mpwp_reseller_status', 'enabled');
            //Invio mail di conferma
            Reseller_Manager::send_email_enable($id, 'enabled');
            wp_safe_redirect(admin_url('admin.php?page=reseller-manager&message=enabled'));
            exit;
        }

        if ($action === 'disable') {
            update_user_meta($id, 'mpwp_reseller_status', 'disabled');
            wp_safe_redirect(admin_url('admin.php?page=reseller-manager&message=disabled'));
            exit;
        }



        if ($action === 'delete') {
            wp_delete_user($id);
            wp_safe_redirect(admin_url('admin.php?page=reseller-manager&message=deleted'));
            exit;
        }
    }

    public function get_columns() {
        return [
            'name'    => __('Nome', 'mc-reseller-manager'),
            'email'   => __('Email', 'mc-reseller-manager'),
            'status'  => __('Stato', 'mc-reseller-manager'),
            'actions' => __('Azioni', 'mc-reseller-manager')
        ];
    }

    public function prepare_items() {

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];
        $data = $this->get_data();

        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $data;
    }

    public function get_data() {
        //Estraggo i reseller dal DB
        $resellers = get_users([
            'role' => 'mpwp_reseller',
            'number' => -1
        ]);

        $data = [];
        foreach ($resellers as $reseller) {
            $data[] = [
                'id' => $reseller->ID,
                'name' => $reseller->display_name,
                'email' => $reseller->user_email,
                'status' => get_user_meta($reseller->ID, 'mpwp_reseller_status', true)
            ];
        }

        return $data;
    }

    public function column_default($item, $column_name) {

        switch ($column_name) {
            case 'status':
                if ($item['status'] === 'enabled') {
                    return '<span style="color:green;font-weight:bold;">' . __('Abilitato', 'mc-reseller-manager') . '</span>';
                } else {
                    return '<span style="color:red;font-weight:bold;">' . __('Disabilitato', 'mc-reseller-manager') . '</span>';
                }

            default:
                return $item[$column_name] ?? '';
        }
    }


    // Colonna AZIONI
    public function column_actions($item) {

        $id = intval($item['id']);

        // Url dei pulsanti
        $enable_url = wp_nonce_url(
            admin_url("admin.php?page=reseller-manager&action=enable&id=$id"),
            'enable_reseller_' . $id
        );

        $disable_url = wp_nonce_url(
            admin_url("admin.php?page=reseller-manager&action=disable&id=$id"),
            'disable_reseller_' . $id
        );

        $delete_url = wp_nonce_url(
            admin_url("admin.php?page=reseller-manager&action=delete&id=$id"),
            'delete_reseller_' . $id
        );

        $view_profile_url = wp_nonce_url(
            admin_url("admin.php?page=mpwp-reseller-view&id=$id"),
            'view_profile_reseller_' . $id
        );

        // Mostra il pulsante corretto
        if ($item['status'] === 'enabled') {
            $toggle_btn = "<a href='$disable_url' class='button button-secondary'>" . __('Disabilita', 'mc-reseller-manager') . "</a>";
        } else {
            $toggle_btn = "<a href='$enable_url' class='button button-primary'>" . __('Abilita', 'mc-reseller-manager') . "</a>";
        }

        // Pulsante elimina
        $delete_btn = "<a href='$delete_url' class='button button-danger' style='background:#a00; color:#fff; border:#a00;'>" . __('Elimina', 'mc-reseller-manager') . "</a>";

        // Pulsante visualizza profilo
        $view_profile_btn = "<a href='$view_profile_url' class='button' style='background:#666;border:#666;color:#fff;'>" . __('Visualizza profilo', 'mc-reseller-manager') . "</a>";

        return $view_profile_btn . " " . $toggle_btn . " " . $delete_btn . " ";
    }

    private static function validate_action_nonce($action, $user_id) {
        $nonce_action = sprintf('%s_reseller_%d', $action, $user_id);
        $nonce        = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! $nonce || ! wp_verify_nonce($nonce, $nonce_action)) {
            wp_nonce_ays($nonce_action);
            exit;
        }

        return $nonce;
    }

    private static function is_confirmed() {
        return isset($_GET['confirm']) && $_GET['confirm'] === '1';
    }

    private static function prompt_action_confirmation($action, $user_id, $nonce) {
        $reseller = get_userdata($user_id);
        /* translators: 1: reseller name */
        $name     = $reseller ? $reseller->display_name : sprintf(__('ID #%d', 'mc-reseller-manager'), $user_id);

        $action_labels = [
            'enable'  => __('Abilita', 'mc-reseller-manager'),
            'disable' => __('Disabilita', 'mc-reseller-manager'),
            'delete'  => __('Elimina', 'mc-reseller-manager'),
        ];

        $action_verbs = [
            'enable'  => __('abilitare', 'mc-reseller-manager'),
            'disable' => __('disabilitare', 'mc-reseller-manager'),
            'delete'  => __('eliminare', 'mc-reseller-manager'),
        ];

        $confirm_url = add_query_arg(
            [
                'page'    => 'reseller-manager',
                'action'  => $action,
                'id'      => $user_id,
                '_wpnonce'=> $nonce,
                'confirm' => 1,
            ],
            admin_url('admin.php')
        );

        self::$confirm_context = [
            'title'         => __('Sei sicuro?', 'mc-reseller-manager'),
            'message'       => sprintf(
                /* translators: 1: verb, 2: reseller name */
                __('Vuoi davvero %1$s il rivenditore "%2$s"?', 'mc-reseller-manager'),
                $action_verbs[$action],
                $name
            ),
            'confirm_url'   => $confirm_url,
            'cancel_url'    => admin_url('admin.php?page=reseller-manager'),
            'confirm_label' => $action_labels[$action],
        ];

        add_filter('wp_die_handler', [__CLASS__, 'use_confirm_die_handler']);
        wp_nonce_ays('mpwp_reseller_confirm');
        exit;
    }

    public static function use_confirm_die_handler($handler) {
        return [__CLASS__, 'render_confirm_die'];
    }

    public static function render_confirm_die($message, $title = '', $args = array()) {
        remove_filter('wp_die_handler', [__CLASS__, 'use_confirm_die_handler'], 10);
        $context = self::$confirm_context;
        self::$confirm_context = null;

        if (! $context) {
            _default_wp_die_handler($message, $title, $args);
            return;
        }

        $html = '<h2>' . esc_html($context['title']) . '</h2>';
        $html .= '<p>' . esc_html($context['message']) . '</p>';
        $html .= '<p class="submit">';
        $html .= '<a class="button button-primary" href="' . esc_url($context['confirm_url']) . '">'
            . esc_html($context['confirm_label']) . '</a> ';
        $html .= '<a class="button button-secondary" href="' . esc_url($context['cancel_url']) . '">'
            . esc_html__('Annulla', 'mc-reseller-manager') . '</a>';
        $html .= '</p>';

        _default_wp_die_handler($html, esc_html($context['title']), $args);
    }
}
