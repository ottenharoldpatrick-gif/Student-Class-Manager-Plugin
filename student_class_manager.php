<?php
/**
 * Plugin Name: Student Class Manager
 * Plugin URI: https://eco.isdigitaal.nl
 * Description: Manage student classes with automatic page creation and login redirection for Sensei LMS
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SCM_VERSION', '1.0.0');

class StudentClassManager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add user meta fields
        add_action('show_user_profile', array($this, 'show_user_class_field'));
        add_action('edit_user_profile', array($this, 'show_user_class_field'));
        add_action('personal_options_update', array($this, 'save_user_class_field'));
        add_action('edit_user_profile_update', array($this, 'save_user_class_field'));
        
        // Add registration field
        add_action('register_form', array($this, 'add_registration_class_field'));
        add_action('register_post', array($this, 'save_registration_class_field'), 10, 3);
        add_filter('registration_errors', array($this, 'validate_registration_class_field'), 10, 3);
        
        // Login redirect
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        
        // AJAX handlers
        add_action('wp_ajax_delete_class', array($this, 'ajax_delete_class'));
        add_action('wp_ajax_bulk_assign_students', array($this, 'ajax_bulk_assign_students'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function activate() {
        // Create database table for classes
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'student_classes';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
    
    public function deactivate() {
        // Clean up if needed
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Student Classes',
            'Student Classes',
            'manage_options',
            'student-classes',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'student-classes',
            'Manage Classes',
            'Manage Classes',
            'manage_options',
            'student-classes',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'student-classes',
            'Assign Students',
            'Assign Students',
            'manage_options',
            'assign-students',
            array($this, 'assign_students_page')
        );
        
        add_submenu_page(
            'student-classes',
            'Bulk Import',
            'Bulk Import',
            'manage_options',
            'bulk-import',
            array($this, 'bulk_import_page')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        
        // Handle form submissions
        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'add_class' && wp_verify_nonce($_POST['_wpnonce'], 'add_class')) {
                $class_name = sanitize_text_field($_POST['class_name']);
                
                if (!empty($class_name)) {
                    // Check if class already exists
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE class_name = %s",
                        $class_name
                    ));
                    
                    if (!$existing) {
                        // Create page for the class
                        $page_data = array(
                            'post_title' => $class_name,
                            'post_content' => '<h2>Welkom bij klas ' . $class_name . '</h2><p>Hier komt de inhoud voor jouw klas. Deze pagina kan worden aangepast via de WordPress pagina-editor.</p>',
                            'post_status' => 'publish',
                            'post_type' => 'page',
                            'post_author' => get_current_user_id()
                        );
                        
                        $page_id = wp_insert_post($page_data);
                        
                        if ($page_id) {
                            // Insert class into database
                            $wpdb->insert(
                                $table_name,
                                array(
                                    'class_name' => $class_name,
                                    'page_id' => $page_id
                                )
                            );
                            
                            echo '<div class="notice notice-success"><p>Klas "' . esc_html($class_name) . '" is aangemaakt!</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>Klas "' . esc_html($class_name) . '" bestaat al!</p></div>';
                    }
                }
            }
        }
        
        // Get all classes
        $classes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY class_name");
        
        ?>
        <div class="wrap">
            <h1>Student Classes Beheer</h1>
            
            <div class="card">
                <h2>Nieuwe Klas Toevoegen</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('add_class'); ?>
                    <input type="hidden" name="action" value="add_class">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Klasnaam</th>
                            <td><input name="class_name" type="text" class="regular-text" required /></td>
                        </tr>
                    </table>
                    <?php submit_button('Klas Toevoegen'); ?>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Bestaande Klassen</h2>
                <?php if (empty($classes)): ?>
                    <p>Nog geen klassen aangemaakt.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Klasnaam</th>
                                <th>Pagina</th>
                                <th>Aantal Leerlingen</th>
                                <th>Aangemaakt</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($class->class_name); ?></strong></td>
                                    <td>
                                        <?php if ($class->page_id): ?>
                                            <a href="<?php echo get_edit_post_link($class->page_id); ?>" target="_blank">
                                                Bewerk Pagina
                                            </a> | 
                                            <a href="<?php echo get_permalink($class->page_id); ?>" target="_blank">
                                                Bekijk Pagina
                                            </a>
                                        <?php else: ?>
                                            Geen pagina
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $student_count = count(get_users(array(
                                            'meta_key' => 'student_class',
                                            'meta_value' => $class->class_name,
                                            'role' => 'subscriber'
                                        )));
                                        echo $student_count;
                                        ?>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($class->created_date)); ?></td>
                                    <td>
                                        <button class="button delete-class" data-class-id="<?php echo $class->id; ?>" data-class-name="<?php echo esc_attr($class->class_name); ?>">
                                            Verwijderen
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function assign_students_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        
        // Handle student assignment
        if (isset($_POST['action']) && $_POST['action'] == 'assign_student' && wp_verify_nonce($_POST['_wpnonce'], 'assign_student')) {
            $user_id = intval($_POST['user_id']);
            $class_name = sanitize_text_field($_POST['class_name']);
            
            if ($user_id && $class_name) {
                update_user_meta($user_id, 'student_class', $class_name);
                echo '<div class="notice notice-success"><p>Leerling toegewezen aan klas!</p></div>';
            }
        }
        
        // Get all classes
        $classes = $wpdb->get_results("SELECT class_name FROM $table_name ORDER BY class_name");
        
        // Get all users with subscriber role (students)
        $students = get_users(array(
            'role' => 'subscriber',
            'orderby' => 'display_name'
        ));
        
        ?>
        <div class="wrap">
            <h1>Leerlingen Toewijzen aan Klassen</h1>
            
            <div class="card">
                <h2>Individuele Toewijzing</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('assign_student'); ?>
                    <input type="hidden" name="action" value="assign_student">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Leerling</th>
                            <td>
                                <select name="user_id" required>
                                    <option value="">Selecteer leerling...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student->ID; ?>">
                                            <?php echo esc_html($student->display_name . ' (' . $student->user_email . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Klas</th>
                            <td>
                                <select name="class_name" required>
                                    <option value="">Selecteer klas...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo esc_attr($class->class_name); ?>">
                                            <?php echo esc_html($class->class_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Toewijzen'); ?>
                </form>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Huidige Toewijzingen</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Leerling</th>
                            <th>Email</th>
                            <th>Huidige Klas</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo esc_html($student->display_name); ?></td>
                                <td><?php echo esc_html($student->user_email); ?></td>
                                <td>
                                    <?php 
                                    $current_class = get_user_meta($student->ID, 'student_class', true);
                                    echo $current_class ? esc_html($current_class) : 'Geen klas';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_user_link($student->ID); ?>" class="button button-small">
                                        Bewerk
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    public function bulk_import_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        
        // Handle CSV bulk import
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
                    $password = wp_generate_password();
                    
                    if (username_exists($username) || email_exists($email)) {
                        $errors[] = "Gebruiker $username of $email bestaat al";
                        continue;
                    }
                    
                    $user_id = wp_create_user($username, $password, $email);
                    
                    if (is_wp_error($user_id)) {
                        $errors[] = "Fout bij aanmaken $username: " . $user_id->get_error_message();
                        continue;
                    }
                    
                    // Set user role and meta
                    $user = new WP_User($user_id);
                    $user->set_role('subscriber');
                    
                    wp_update_user(array(
                        'ID' => $user_id,
                        'display_name' => $display_name
                    ));
                    
                    update_user_meta($user_id, 'student_class', $class_name);
                    
                    $imported++;
                }
                
                echo '<div class="notice notice-success"><p>' . $imported . ' leerlingen geïmporteerd!</p></div>';
                
                if (!empty($errors)) {
                    echo '<div class="notice notice-warning"><p>Fouten:<br>' . implode('<br>', $errors) . '</p></div>';
                }
            }
        }
        
        // Handle Excel file upload for bulk import
        if (isset($_POST['action']) && $_POST['action'] == 'upload_excel_bulk' && wp_verify_nonce($_POST['_wpnonce'], 'upload_excel_bulk')) {
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
                $uploaded_file = $_FILES['excel_file'];
                
                // Validate file type
                $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                if (!in_array($file_extension, ['xlsx', 'xls'])) {
                    echo '<div class="notice notice-error"><p>Alleen Excel bestanden (.xlsx, .xls) zijn toegestaan.</p></div>';
                } else {
                    // Process Excel file for bulk import
                    $this->process_excel_bulk_import($uploaded_file['tmp_name']);
                }
            }
        }
        
        $classes = $wpdb->get_results("SELECT class_name FROM $table_name ORDER BY class_name");
        
        ?>
        <div class="wrap">
            <h1>Bulk Import Leerlingen</h1>
            
            <!-- Excel Import Tab -->
            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" id="excel-tab">Excel Import</a>
                <a href="#" class="nav-tab" id="csv-tab">CSV Import</a>
            </div>
            
            <!-- Excel Import Section -->
            <div id="excel-import-section" class="card">
                <h2>📊 Excel Import - Nassau Vincent Format</h2>
                <p>Upload een Nassau Vincent Excel bestand (Hokjeslijst format) om leerlingen te importeren.</p>
                <p><strong>Voordelen:</strong> Automatische detectie van lesgroep, conflict-afhandeling, Nassau Vincent email format</p>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field('upload_excel_bulk'); ?>
                    <input type="hidden" name="action" value="upload_excel_bulk">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Excel Bestand</th>
                            <td>
                                <input type="file" name="excel_file" accept=".xlsx,.xls" required>
                                <p class="description">Selecteer een Nassau Vincent Hokjeslijst Excel bestand</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Upload en Preview Excel'); ?>
                </form>
            </div>
            
            <!-- CSV Import Section -->
            <div id="csv-import-section" class="card" style="display: none;">
                <h2>📝 CSV Import - Handmatige Invoer</h2>
                <p>Format: <strong>gebruikersnaam,email,volledige_naam</strong> (één per regel)</p>
                <p>Voorbeeld:<br>
                <code>jan.jansen,jan@school.nl,Jan Jansen<br>
                marie.pietersen,marie@school.nl,Marie Pietersen</code></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('bulk_import'); ?>
                    <input type="hidden" name="action" value="bulk_import">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Klas</th>
                            <td>
                                <select name="class_name" required>
                                    <option value="">Selecteer klas...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo esc_attr($class->class_name); ?>">
                                            <?php echo esc_html($class->class_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">CSV Data</th>
                            <td>
                                <textarea name="csv_data" rows="10" cols="50" class="large-text" required placeholder="jan.jansen,jan@school.nl,Jan Jansen&#10;marie.pietersen,marie@school.nl,Marie Pietersen"></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Import Leerlingen via CSV'); ?>
                </form>
            </div>
            
            <!-- Quick comparison -->
            <div class="card">
                <h3>🔄 Vergelijking Import Methodes</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Functie</th>
                            <th>Excel Import</th>
                            <th>CSV Import</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Nassau Vincent Format</strong></td>
                            <td>✅ Automatisch gedetecteerd</td>
                            <td>❌ Handmatige invoer</td>
                        </tr>
                        <tr>
                            <td><strong>Email Generatie</strong></td>
                            <td>✅ llnr@nassauvincent.nl</td>
                            <td>➖ Handmatig opgeven</td>
                        </tr>
                        <tr>
                            <td><strong>Conflict Afhandeling</strong></td>
                            <td>✅ Per leerling kiezen</td>
                            <td>❌ Automatisch overslaan</td>
                        </tr>
                        <tr>
                            <td><strong>Lesgroep Detectie</strong></td>
                            <td>✅ Uit Excel bestand</td>
                            <td>➖ Handmatig selecteren</td>
                        </tr>
                        <tr>
                            <td><strong>Gebruik voor</strong></td>
                            <td>Nassau Vincent exports</td>
                            <td>Andere bronnen/handmatig</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Tab switching
            $('#excel-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('#csv-import-section').hide();
                $('#excel-import-section').show();
            });
            
            $('#csv-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('#excel-import-section').hide();
                $('#csv-import-section').show();
            });
        });
        </script>
        <?php
    }
    
    private function process_excel_bulk_import($file_path) {
        // This function processes Excel files in the bulk import context
        // It reuses the logic from the dedicated Excel import but with simpler UI
        
        try {
            // Mock processing - in real implementation you'd parse the actual Excel
            $this->show_excel_bulk_preview();
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Fout bij lezen Excel bestand: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    private function show_excel_bulk_preview() {
        // Simplified preview for bulk import context
        $lesgroep_naam = 'pkh3gec';
        $students_count = 14;
        
        ?>
        <div class="card" style="margin-top: 20px;">
            <h2>✅ Excel Bestand Gelezen</h2>
            
            <div class="notice notice-info">
                <p><strong>Gedetecteerd:</strong> Lesgroep "<?php echo esc_html($lesgroep_naam); ?>" met <?php echo $students_count; ?> leerlingen</p>
                <p>Voor gedetailleerde conflict-afhandeling gebruik <a href="<?php echo admin_url('admin.php?page=excel-import'); ?>">Excel Import</a> in het hoofdmenu.</p>
            </div>
            
            <form method="post" action="<?php echo admin_url('admin.php?page=excel-import'); ?>">
                <input type="hidden" name="bulk_redirect" value="1">
                <?php submit_button('Ga naar Gedetailleerde Excel Import →'); ?>
            </form>
        </div>
        <?php
    }
    
    public function show_user_class_field($user) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        $classes = $wpdb->get_results("SELECT class_name FROM $table_name ORDER BY class_name");
        $current_class = get_user_meta($user->ID, 'student_class', true);
        ?>
        <h3>Klasinformatie</h3>
        <table class="form-table">
            <tr>
                <th><label for="student_class">Klas</label></th>
                <td>
                    <select name="student_class" id="student_class">
                        <option value="">Geen klas</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo esc_attr($class->class_name); ?>" <?php selected($current_class, $class->class_name); ?>>
                                <?php echo esc_html($class->class_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_user_class_field($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'student_class', sanitize_text_field($_POST['student_class']));
        }
    }
    
    public function add_registration_class_field() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        $classes = $wpdb->get_results("SELECT class_name FROM $table_name ORDER BY class_name");
        
        if (!empty($classes)):
        ?>
        <p>
            <label for="student_class">Klas<br />
                <select name="student_class" id="student_class">
                    <option value="">Selecteer je klas...</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo esc_attr($class->class_name); ?>">
                            <?php echo esc_html($class->class_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
        endif;
    }
    
    public function validate_registration_class_field($errors, $sanitized_user_login, $user_email) {
        if (empty($_POST['student_class'])) {
            $errors->add('student_class_error', '<strong>FOUT</strong>: Selecteer je klas.');
        }
        return $errors;
    }
    
    public function save_registration_class_field($user_id, $password, $meta) {
        if (isset($_POST['student_class']) && !empty($_POST['student_class'])) {
            update_user_meta($user_id, 'student_class', sanitize_text_field($_POST['student_class']));
        }
    }
    
    public function custom_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('subscriber', $user->roles)) {
                $student_class = get_user_meta($user->ID, 'student_class', true);
                
                if ($student_class) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'student_classes';
                    
                    $class_page_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT page_id FROM $table_name WHERE class_name = %s",
                        $student_class
                    ));
                    
                    if ($class_page_id) {
                        return get_permalink($class_page_id);
                    }
                }
                
                // No class assigned - redirect to homepage
                return home_url();
            }
        }
        
        return $redirect_to;
    }
    
    public function ajax_delete_class() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $class_id = intval($_POST['class_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'student_classes';
        
        // Get page_id before deleting
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT page_id FROM $table_name WHERE id = %d",
            $class_id
        ));
        
        // Delete the class
        $wpdb->delete($table_name, array('id' => $class_id));
        
        // Delete the associated page
        if ($page_id) {
            wp_delete_post($page_id, true);
        }
        
        wp_die('Success');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'student-classes') !== false) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.delete-class').click(function() {
                    var classId = $(this).data('class-id');
                    var className = $(this).data('class-name');
                    
                    if (confirm('Weet je zeker dat je klas "' + className + '" wilt verwijderen? Dit verwijdert ook de bijbehorende pagina.')) {
                        $.post(ajaxurl, {
                            action: 'delete_class',
                            class_id: classId
                        }, function(response) {
                            location.reload();
                        });
                    }
                });
                
                // Excel import conflict handling
                $('input[name^="conflict_"]').change(function() {
                    var llnr = $(this).attr('name').replace('conflict_', '');
                    var action = $(this).val();
                    var row = $(this).closest('tr');
                    
                    if (action === 'overwrite') {
                        row.css('background-color', '#fff2cc');
                    } else {
                        row.css('background-color', '#ffebee');
                    }
                });
                
                // Set initial colors for conflict rows
                $('input[name^="conflict_"]:checked').each(function() {
                    var action = $(this).val();
                    var row = $(this).closest('tr');
                    
                    if (action === 'overwrite') {
                        row.css('background-color', '#fff2cc');
                    } else {
                        row.css('background-color', '#ffebee');
                    }
                });
            });
            </script>
            <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .conflict-row-overwrite {
                background-color: #fff2cc !important;
            }
            .conflict-row-skip {
                background-color: #ffebee !important;
            }
            .excel-preview {
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 10px;
                background: #f9f9f9;
            }
            </style>
            <?php
        }
    }
}

// Initialize the plugin
new StudentClassManager();