<?php
/**
 * Plugin Name: Student Class Manager
 * Plugin URI: https://eco.isdigitaal.nl
 * Description: Manage student classes with automatic page creation and login redirection for Sensei LMS
 * Version: 1.0.0
 * Author: Meneer Otten
 * Text Domain: student-class-manager
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SCM_VERSION', '1.0.0');

class StudentClassManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'student_classes';
        $this->init();
    }
    
    public function init() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init_hooks'));
    }
    
    public function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        add_action('wp_ajax_scm_delete_class', array($this, 'ajax_delete_class'));
        add_action('wp_ajax_scm_remove_from_class', array($this, 'ajax_remove_from_class'));
        
        add_action('show_user_profile', array($this, 'show_user_class_field'));
        add_action('edit_user_profile', array($this, 'show_user_class_field'));
        add_action('personal_options_update', array($this, 'save_user_class_field'));
        add_action('edit_user_profile_update', array($this, 'save_user_class_field'));
        
        add_action('register_form', array($this, 'add_registration_class_field'));
        add_action('register_post', array($this, 'save_registration_class_field'), 10, 3);
        add_filter('registration_errors', array($this, 'validate_registration_class_field'), 10, 3);
        
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 100, 3);
        add_filter('sensei_login_redirect_url', array($this, 'sensei_login_redirect'), 100, 2);
        add_action('wp_login', array($this, 'force_custom_redirect'), 100, 2);
        
        add_shortcode('student_greeting', array($this, 'student_greeting_shortcode'));
        add_filter('the_content', array($this, 'replace_greeting_placeholder'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_assets();
        
        if (!get_option('scm_email_domain')) {
            add_option('scm_email_domain', 'nassauvincent.nl');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            class_name varchar(100) NOT NULL,
            page_id bigint(20) DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY class_name (class_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_assets() {
        $assets_dir = SCM_PLUGIN_PATH . 'assets';
        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
        }
        
        $admin_css = '.toplevel_page_student-classes .wrap { max-width: none !important; margin: 20px 20px 0 0 !important; }
.toplevel_page_student-classes .card { max-width: none !important; width: 100% !important; margin-bottom: 20px; }
.wp-list-table { width: 100% !important; }
.form-table th { width: 200px; }
.delete-class { color: #a00 !important; border-color: #a00 !important; }
.delete-class:hover { background: #a00 !important; color: white !important; }
.remove-from-class { color: #d63384 !important; border-color: #d63384 !important; }
.remove-from-class:hover { background: #d63384 !important; color: white !important; }
.button-small { padding: 4px 8px !important; font-size: 12px !important; }
@media screen and (max-width: 768px) {
    .toplevel_page_student-classes .wrap { margin: 10px 5px 0 0 !important; }
    .form-table th, .form-table td { display: block; width: 100% !important; }
}';
        
        file_put_contents($assets_dir . '/admin.css', $admin_css);
        
        $admin_js = 'jQuery(document).ready(function($) {
    $(".delete-class").click(function() {
        var classId = $(this).data("class-id");
        if (confirm(scm_ajax.confirm_delete)) {
            $.ajax({
                url: scm_ajax.ajax_url,
                type: "POST",
                data: { action: "scm_delete_class", class_id: classId, nonce: scm_ajax.nonce },
                success: function(response) {
                    if (response.success) location.reload();
                    else alert("Fout: " + response.data);
                }
            });
        }
    });
    $(".remove-from-class").click(function() {
        var userId = $(this).data("user-id");
        if (confirm("Weet je zeker dat je deze leerling uit de klas wilt verwijderen?")) {
            $.ajax({
                url: scm_ajax.ajax_url,
                type: "POST", 
                data: { action: "scm_remove_from_class", user_id: userId, nonce: scm_ajax.nonce },
                success: function(response) {
                    if (response.success) location.reload();
                    else alert("Fout: " + response.data);
                }
            });
        }
    });
});';
        
        file_put_contents($assets_dir . '/admin.js', $admin_js);
        
        $frontend_css = '.student-class-welcome { max-width: 1200px; width: 100%; margin: 0 auto; padding: 20px; }
.student-greeting { background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%); padding: 30px; border-radius: 12px; margin: 20px 0; border-left: 5px solid #0073aa; box-shadow: 0 2px 10px rgba(0,115,170,0.1); }
.student-greeting h2 { color: #0073aa; margin-top: 0; font-size: 28px; }
.class-navigation { background: #ffffff; padding: 30px; border-radius: 12px; margin: 25px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #e1e8ed; }
.class-navigation ul { list-style: none; padding: 0; }
.class-navigation li { padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
.class-navigation li:before { content: "✓ "; color: #46b450; font-weight: bold; }
.class-info { background: #f7f7f7; padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #d1d5db; }
@media (max-width: 768px) {
    .student-class-welcome { padding: 10px; }
    .student-greeting, .class-navigation, .class-info { padding: 15px; margin: 10px 0; }
}';
        
        file_put_contents($assets_dir . '/frontend.css', $frontend_css);
    }
    
    public function register_settings() {
        register_setting('scm_settings', 'scm_email_domain');
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_student-classes' !== $hook && 'student-classes_page_student-classes-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_script('scm-admin-js', SCM_PLUGIN_URL . 'assets/admin.js', array('jquery'), SCM_VERSION, true);
        wp_localize_script('scm-admin-js', 'scm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scm_delete_class'),
            'confirm_delete' => 'Weet je zeker dat je deze klas wilt verwijderen?'
        ));
        
        wp_enqueue_style('scm-admin-css', SCM_PLUGIN_URL . 'assets/admin.css', array(), SCM_VERSION);
        
        add_action('admin_head', array($this, 'admin_head_styles'));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('scm-frontend-css', SCM_PLUGIN_URL . 'assets/frontend.css', array(), SCM_VERSION);
    }
    
    public function admin_head_styles() {
        echo '<style>
        .toplevel_page_student-classes .notice:not(.scm-notice),
        .student-classes_page_student-classes-settings .notice:not(.scm-notice) { display: none !important; }
        .toplevel_page_student-classes #wpbody-content { padding-right: 0 !important; }
        </style>';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Student Classes',
            'Student Classes',
            'manage_options',
            'student-classes',
            array($this, 'main_admin_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'student-classes',
            'Settings',
            'Settings',
            'manage_options',
            'student-classes-settings',
            array($this, 'settings_page')
        );
    }
    
public function settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'scm_settings')) {
            update_option('scm_email_domain', sanitize_text_field($_POST['scm_email_domain']));
            echo '<div class="notice notice-success scm-notice"><p>Instellingen opgeslagen!</p></div>';
        }
        
        $email_domain = get_option('scm_email_domain', 'nassauvincent.nl');
        
        echo '<div class="wrap">
            <h1>Student Class Manager Settings</h1>
            <form method="post">';
        wp_nonce_field('scm_settings');
        echo '<table class="form-table">
                <tr>
                    <th>Email Domain</th>
                    <td>
                        <input name="scm_email_domain" type="text" value="' . esc_attr($email_domain) . '" class="regular-text" />
                        <p class="description">Het domein voor automatisch gegenereerde emails</p>
                    </td>
                </tr>
            </table>';
        submit_button();
        echo '</form></div>';
    }
    
    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles) && in_array('subscriber', $user->roles)) {
            return $this->get_student_class_page_url($user->ID);
        }
        return $redirect_to;
    }
    
    public function sensei_login_redirect($redirect_url, $user) {
        if (isset($user->roles) && is_array($user->roles) && in_array('subscriber', $user->roles)) {
            return $this->get_student_class_page_url($user->ID);
        }
        return $redirect_url;
    }
    
    public function force_custom_redirect($user_login, $user) {
        if (isset($user->roles) && is_array($user->roles) && in_array('subscriber', $user->roles)) {
            $redirect_url = $this->get_student_class_page_url($user->ID);
            if ($redirect_url && $redirect_url !== home_url()) {
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
    
    private function get_student_class_page_url($user_id) {
        $student_class = get_user_meta($user_id, 'student_class', true);
        
        if (!$student_class) {
            return home_url();
        }
        
        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare("SELECT page_id FROM {$this->table_name} WHERE class_name = %s", $student_class));
        
        if ($class && $class->page_id) {
            return get_permalink($class->page_id);
        }
        
        return home_url();
    }
    
    public function student_greeting_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Log in om je persoonlijke welkomstbericht te zien.</p>';
        }
        
        $current_user = wp_get_current_user();
        $student_class = get_user_meta($current_user->ID, 'student_class', true);
        
        $greeting = '<div class="student-greeting">';
        $greeting .= '<h2>Hallo ' . esc_html($current_user->display_name) . '!</h2>';
        
        if ($student_class) {
            $greeting .= '<p>Welkom terug in klas <strong>' . esc_html($student_class) . '</strong>.</p>';
        } else {
            $greeting .= '<p>Je bent nog niet toegewezen aan een klas. Neem contact op met je docent.</p>';
        }
        
        $greeting .= '</div>';
        
        return $greeting;
    }
    
    public function replace_greeting_placeholder($content) {
        if (strpos($content, '[student_greeting]') !== false) {
            $content = str_replace('[student_greeting]', $this->student_greeting_shortcode(array()), $content);
        }
        return $content;
    }
    
    private function get_default_class_page_content($class_name) {
        return '<div class="student-class-welcome">
    <h1>Welkom bij klas ' . esc_html($class_name) . '</h1>
    
    [student_greeting]
    
    <p>Hier vind je alle informatie en materialen voor jouw klas. Gebruik de navigatie hieronder om te beginnen met leren!</p>
    
    <div class="class-navigation">
        <h3>Wat kun je hier doen?</h3>
        <ul>
            <li>Bekijk je lessen en voortgang</li>
            <li>Download studiematerialen</li>
            <li>Bekijk aankondigingen van je docent</li>
            <li>Neem contact op met je klasgenoten</li>
        </ul>
    </div>
    
    <div class="class-info">
        <h3>Klasinformatie</h3>
        <p><strong>Klas:</strong> ' . esc_html($class_name) . '</p>
        <p><strong>Docent:</strong> Meneer Otten</p>
        <p><strong>School:</strong> Nassau Vincent</p>
    </div>
</div>';
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('student-class-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

// Initialize the plugin
new StudentClassManager();submit']) && wp_verify_nonce($_POST['_wpnonce'], 'scm_settings')) {
            update_option('scm_email_domain', sanitize_text_field($_POST['scm_email_domain']));
            echo '<div class="notice notice-success scm-notice"><p>Instellingen opgeslagen!</p></div>';
        }
        
        $email_domain = get_option('scm_email_domain', 'nassauvincent.nl');
        
        echo '<div class="wrap">
            <h1>Student Class Manager Settings</h1>
            <form method="post">';
        wp_nonce_field('scm_settings');
        echo '<table class="form-table">
                <tr>
                    <th>Email Domain</th>
                    <td>
                        <input name="scm_email_domain" type="text" value="' . esc_attr($email_domain) . '" class="regular-text" />
                        <p class="description">Het domein voor automatisch gegenereerde emails</p>
                    </td>
                </tr>
            </table>';
        submit_button();
        echo '</form></div>';
    }
    
    public function main_admin_page() {
        $active_tab = isset($_GET['view_class']) ? 'view_class' : (isset($_GET['tab']) ? $_GET['tab'] : 'classes');
        
        echo '<div class="wrap">
            <h1>Student Classes Manager</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=student-classes&tab=classes" class="nav-tab ' . ($active_tab == 'classes' ? 'nav-tab-active' : '') . '">Manage Classes</a>
                <a href="?page=student-classes&tab=assign" class="nav-tab ' . ($active_tab == 'assign' ? 'nav-tab-active' : '') . '">Assign Students</a>
                <a href="?page=student-classes&tab=bulk" class="nav-tab ' . ($active_tab == 'bulk' ? 'nav-tab-active' : '') . '">Bulk Import</a>';
                
        if (isset($_GET['view_class'])) {
            echo '<a href="?page=student-classes&view_class=' . esc_attr($_GET['view_class']) . '" class="nav-tab nav-tab-active">Klas Details</a>';
        }
        
        echo '</h2>';
        
        switch($active_tab) {
            case 'assign': 
                $this->assign_students_tab(); 
                break;
            case 'bulk': 
                $this->bulk_import_tab(); 
                break;
            case 'view_class': 
                $this->view_class_tab(); 
                break;
            default: 
                $this->manage_classes_tab(); 
                break;
        }
        
        echo '</div>';
    }
    
    public function manage_classes_tab() {
        global $wpdb;
        
        if (isset($_POST['action']) && $_POST['action'] == 'add_class' && wp_verify_nonce($_POST['_wpnonce'], 'add_class')) {
            $class_name = sanitize_text_field($_POST['class_name']);
            
            if (!empty($class_name)) {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE class_name = %s", $class_name));
                
                if (!$existing) {
                    $page_id = wp_insert_post(array(
                        'post_title' => $class_name,
                        'post_content' => $this->get_default_class_page_content($class_name),
                        'post_status' => 'publish',
                        'post_type' => 'page'
                    ));
                    
                    if ($page_id) {
                        $wpdb->insert($this->table_name, array('class_name' => $class_name, 'page_id' => $page_id));
                        echo '<div class="notice notice-success scm-notice"><p>Klas "' . esc_html($class_name) . '" aangemaakt!</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error scm-notice"><p>Klas bestaat al!</p></div>';
                }
            }
        }
        
        $classes = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY class_name");
        
        echo '<div class="card">
            <h2>Nieuwe Klas</h2>
            <form method="post">';
        wp_nonce_field('add_class');
        echo '<input type="hidden" name="action" value="add_class">
            <table class="form-table">
                <tr><th>Klasnaam</th><td><input name="class_name" type="text" required /></td></tr>
            </table>';
        submit_button('Klas Toevoegen');
        echo '</form></div>';
        
        if (!empty($classes)) {
            echo '<div class="card">
                <h2>Bestaande Klassen</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Klasnaam</th><th>Leerlingen</th><th>Datum</th><th>Acties</th></tr></thead>
                    <tbody>';
                    
            foreach ($classes as $class) {
                $student_count = count(get_users(array('meta_key' => 'student_class', 'meta_value' => $class->class_name)));
                echo '<tr>
                    <td><strong>' . esc_html($class->class_name) . '</strong></td>
                    <td>' . $student_count . '</td>
                    <td>' . date('d-m-Y', strtotime($class->created_date)) . '</td>
                    <td>
                        <a href="?page=student-classes&view_class=' . urlencode($class->class_name) . '" class="button button-primary button-small">Bekijk</a>
                        <button class="button delete-class" data-class-id="' . $class->id . '">Verwijder</button>
                    </td>
                </tr>';
            }
            
            echo '</tbody></table></div>';
        }
    }
    
    public function assign_students_tab() {
        global $wpdb;
        
        if (isset($_POST['action']) && $_POST['action'] == 'assign_student' && wp_verify_nonce($_POST['_wpnonce'], 'assign_student')) {
            $user_id = intval($_POST['user_id']);
            $class_name = sanitize_text_field($_POST['class_name']);
            
            if ($user_id && $class_name) {
                update_user_meta($user_id, 'student_class', $class_name);
                echo '<div class="notice notice-success scm-notice"><p>Leerling toegewezen!</p></div>';
            }
        }
        
        $classes = $wpdb->get_results("SELECT class_name FROM {$this->table_name} ORDER BY class_name");
        $students = get_users(array('role' => 'subscriber'));
        
        echo '<div class="card">
            <h2>Toewijzing</h2>
            <form method="post">';
        wp_nonce_field('assign_student');
        echo '<input type="hidden" name="action" value="assign_student">
            <table class="form-table">
                <tr><th>Leerling</th><td>
                    <select name="user_id" required>
                        <option value="">Selecteer...</option>';
        foreach ($students as $student) {
            echo '<option value="' . $student->ID . '">' . esc_html($student->display_name . ' (' . $student->user_email . ')') . '</option>';
        }
        echo '</select></td></tr>
                <tr><th>Klas</th><td>
                    <select name="class_name" required>
                        <option value="">Selecteer...</option>';
        foreach ($classes as $class) {
            echo '<option value="' . esc_attr($class->class_name) . '">' . esc_html($class->class_name) . '</option>';
        }
        echo '</select></td></tr>
            </table>';
        submit_button('Toewijzen');
        echo '</form></div>';
    }
    
    public function bulk_import_tab() {
        global $wpdb;
        
        if (isset($_POST['action']) && $_POST['action'] == 'bulk_import' && wp_verify_nonce($_POST['_wpnonce'], 'bulk_import')) {
            $csv_data = sanitize_textarea_field($_POST['csv_data']);
            $class_name = sanitize_text_field($_POST['class_name']);
            
            if (!empty($csv_data) && !empty($class_name)) {
                $lines = explode("\n", $csv_data);
                $imported = 0;
                $errors = array();
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $data = str_getcsv($line);
                    if (count($data) < 3) continue;
                    
                    $username = sanitize_user($data[0]);
                    $email = sanitize_email($data[1]);
                    $display_name = sanitize_text_field($data[2]);
                    
                    if (username_exists($username) || email_exists($email)) {
                        $errors[] = "User $username/$email already exists";
                        continue;
                    }
                    
                    $user_id = wp_create_user($username, wp_generate_password(), $email);
                    
                    if (is_wp_error($user_id)) {
                        $errors[] = "Error creating $username: " . $user_id->get_error_message();
                        continue;
                    }
                    
                    $user = new WP_User($user_id);
                    $user->set_role('subscriber');
                    wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
                    update_user_meta($user_id, 'student_class', $class_name);
                    $imported++;
                }
                
                echo '<div class="notice notice-success scm-notice"><p>' . $imported . ' leerlingen geïmporteerd!</p></div>';
                if (!empty($errors)) {
                    echo '<div class="notice notice-warning scm-notice"><p>Errors:<br>' . implode('<br>', $errors) . '</p></div>';
                }
            }
        }
        
        $classes = $wpdb->get_results("SELECT class_name FROM {$this->table_name} ORDER BY class_name");
        
        echo '<div class="card">
            <h2>CSV Import</h2>
            <p>Format: <strong>username,email,full_name</strong> (one per line)</p>
            <form method="post">';
        wp_nonce_field('bulk_import');
        echo '<input type="hidden" name="action" value="bulk_import">
            <table class="form-table">
                <tr><th>Klas</th><td>
                    <select name="class_name" required>
                        <option value="">Selecteer...</option>';
        foreach ($classes as $class) {
            echo '<option value="' . esc_attr($class->class_name) . '">' . esc_html($class->class_name) . '</option>';
        }
        echo '</select></td></tr>
                <tr><th>CSV Data</th><td>
                    <textarea name="csv_data" rows="10" class="large-text" required placeholder="janjansen,jan@email.com,Jan Jansen"></textarea>
                </td></tr>
            </table>';
        submit_button('Import');
        echo '</form></div>';
    }
    
    public function view_class_tab() {
        if (!isset($_GET['view_class'])) {
            echo '<div class="notice notice-error scm-notice"><p>Geen klas geselecteerd.</p></div>';
            return;
        }
        
        $class_name = sanitize_text_field($_GET['view_class']);
        
        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE class_name = %s", $class_name));
        
        if (!$class) {
            echo '<div class="notice notice-error scm-notice"><p>Klas niet gevonden.</p></div>';
            return;
        }
        
        $students = get_users(array(
            'meta_key' => 'student_class',
            'meta_value' => $class_name,
            'role' => 'subscriber'
        ));
        
        echo '<div class="card">
            <h2>Klas: ' . esc_html($class->class_name) . '</h2>
            <table class="form-table">
                <tr><th>Aangemaakt:</th><td>' . date('d-m-Y H:i', strtotime($class->created_date)) . '</td></tr>
                <tr><th>Aantal Leerlingen:</th><td>' . count($students) . '</td></tr>';
                
        if ($class->page_id) {
            echo '<tr><th>Klas Pagina:</th><td>
                <a href="' . get_edit_post_link($class->page_id) . '" target="_blank">Bewerk</a> | 
                <a href="' . get_permalink($class->page_id) . '" target="_blank">Bekijk</a>
            </td></tr>';
        }
        
        echo '</table></div>';
        
        if (!empty($students)) {
            echo '<div class="card">
                <h3>Leerlingen</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Naam</th><th>Email</th><th>Acties</th></tr></thead>
                    <tbody>';
                    
            foreach ($students as $student) {
                echo '<tr>
                    <td>' . esc_html($student->display_name) . '</td>
                    <td>' . esc_html($student->user_email) . '</td>
                    <td>
                        <a href="' . get_edit_user_link($student->ID) . '" class="button button-small">Bewerk</a>
                        <button class="button button-small remove-from-class" data-user-id="' . $student->ID . '">Verwijder</button>
                    </td>
                </tr>';
            }
            
            echo '</tbody></table></div>';
        }
    }
    
    public function ajax_delete_class() {
        check_ajax_referer('scm_delete_class', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $class_id = intval($_POST['class_id']);
        
        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $class_id));
        
        if ($class) {
            if ($class->page_id) {
                wp_delete_post($class->page_id, true);
            }
            
            $users = get_users(array('meta_key' => 'student_class', 'meta_value' => $class->class_name));
            foreach ($users as $user) {
                delete_user_meta($user->ID, 'student_class');
            }
            
            $wpdb->delete($this->table_name, array('id' => $class_id));
            wp_send_json_success('Klas verwijderd');
        } else {
            wp_send_json_error('Klas niet gevonden');
        }
    }
    
    public function ajax_remove_from_class() {
        check_ajax_referer('scm_delete_class', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        
        if ($user_id) {
            delete_user_meta($user_id, 'student_class');
            wp_send_json_success('Leerling verwijderd uit klas');
        } else {
            wp_send_json_error('Ongeldige gebruiker');
        }
    }
    
    public function show_user_class_field($user) {
        if (!current_user_can('edit_users')) return;
        
        $student_class = get_user_meta($user->ID, 'student_class', true);
        
        global $wpdb;
        $classes = $wpdb->get_results("SELECT class_name FROM {$this->table_name} ORDER BY class_name");
        
        echo '<h3>Klas</h3>
        <table class="form-table">
            <tr><th>Klas</th><td>
                <select name="student_class">
                    <option value="">Geen klas</option>';
        foreach ($classes as $class) {
            echo '<option value="' . esc_attr($class->class_name) . '"' . selected($student_class, $class->class_name, false) . '>' . esc_html($class->class_name) . '</option>';
        }
        echo '</select></td></tr>
        </table>';
    }
    
    public function save_user_class_field($user_id) {
        if (!current_user_can('edit_users')) return;
        
        if (isset($_POST['student_class'])) {
            $class = sanitize_text_field($_POST['student_class']);
            if (empty($class)) {
                delete_user_meta($user_id, 'student_class');
            } else {
                update_user_meta($user_id, 'student_class', $class);
            }
        }
    }
    
    public function add_registration_class_field() {
        global $wpdb;
        $classes = $wpdb->get_results("SELECT class_name FROM {$this->table_name} ORDER BY class_name");
        
        if (empty($classes)) return;
        
        echo '<p>
            <label for="student_class">Klas<br />
                <select name="student_class" id="student_class" class="input" required>
                    <option value="">Selecteer je klas...</option>';
        foreach ($classes as $class) {
            echo '<option value="' . esc_attr($class->class_name) . '">' . esc_html($class->class_name) . '</option>';
        }
        echo '</select>
            </label>
        </p>';
    }
    
    public function validate_registration_class_field($errors, $sanitized_user_login, $user_email) {
        if (empty($_POST['student_class'])) {
            $errors->add('student_class_error', 'Selecteer een klas.');
        }
        return $errors;
    }
    
    public function save_registration_class_field($user_id, $password, $meta) {
        if (isset($_POST['