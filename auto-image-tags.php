<?php
/**
 * Plugin Name: Auto Image Tags
 * Plugin URI: https://wordpress.org/plugins/auto-image-tags/
 * Description: Automatically add ALT, TITLE, Caption and Description tags to WordPress media library images. WooCommerce integration and optional translation support.
 * Version: 2.0.0
 * Author: mrbogdan
 * Author URI: https://profiles.wordpress.org/mrbogdan/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: auto-image-tags
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант плагина
define('AUTOIMTA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTOIMTA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTOIMTA_VERSION', '2.0.0');

// Проверка требований
function autoimta_check_requirements() {
    $errors = array();
    
    if (version_compare(PHP_VERSION, '7.2', '<')) {
        $errors[] = 'PHP 7.2 or higher is required. Current version: ' . PHP_VERSION;
    }
    
    global $wp_version;
    if (version_compare($wp_version, '5.0', '<')) {
        $errors[] = 'WordPress 5.0 or higher is required. Current version: ' . $wp_version;
    }
    
    if (!empty($errors)) {
        deactivate_plugins(plugin_basename(__FILE__));
        $error_list = '<li>' . implode('</li><li>', array_map('esc_html', $errors)) . '</li>';
wp_die(
    '<h1>' . esc_html__('Auto Image Tags', 'auto-image-tags') . '</h1>' .
    '<p><strong>' . esc_html__('Activation Error:', 'auto-image-tags') . '</strong></p>' .
    '<ul>' . wp_kses_post($error_list) . '</ul>' .
    '<p><a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Return to Plugins', 'auto-image-tags') . '</a></p>'
);
    }
}
register_activation_hook(__FILE__, 'autoimta_check_requirements');

/**
 * Основной класс плагина
 */
class AUTOIMTA_Plugin {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('add_attachment', array($this, 'handle_image_upload'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        add_action('wp_ajax_autoimta_process_existing_images', array($this, 'ajax_process_existing_images'));
        add_action('wp_ajax_autoimta_get_images_count', array($this, 'ajax_get_images_count'));
        add_action('wp_ajax_autoimta_preview_changes', array($this, 'ajax_preview_changes'));
        add_action('wp_ajax_autoimta_get_filter_options', array($this, 'ajax_get_filter_options'));
        add_action('wp_ajax_autoimta_get_remove_stats', array($this, 'ajax_get_remove_stats'));
        add_action('wp_ajax_autoimta_remove_tags', array($this, 'ajax_remove_tags'));
        add_action('wp_ajax_autoimta_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_autoimta_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_autoimta_test_translation', array($this, 'ajax_test_translation'));
        add_action('wp_ajax_autoimta_get_translation_stats', array($this, 'ajax_get_translation_stats'));
        add_action('wp_ajax_autoimta_translate_batch', array($this, 'ajax_translate_batch'));
        
        // WooCommerce интеграция
        add_action('woocommerce_new_product', array($this, 'handle_woocommerce_product'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'handle_woocommerce_product'), 10, 1);
    }

/**
     * Активация плагина
     */
public function activate() {
    // Установка дефолтных настроек
    if (!get_option('autoimta_settings')) {
        $default_settings = array(
            'alt_format' => 'filename',
            'title_format' => 'filename',
            'caption_format' => 'disabled',
            'description_format' => 'disabled',
            'alt_custom_text' => '',
            'title_custom_text' => '',
            'caption_custom_text' => '',
            'description_custom_text' => '',
            'remove_hyphens' => '1',
            'remove_dots' => '1',
            'capitalize_words' => '1',
            'remove_numbers' => '1',
            'camelcase_split' => '1',
            'remove_size_suffix' => '1',
            'process_on_upload' => '1',
            'overwrite_alt' => '0',
            'overwrite_title' => '0',
            'overwrite_caption' => '0',
            'overwrite_description' => '0',
            'stop_words' => 'DSC, IMG, image, photo, picture, pic, screenshot, foto',
            'custom_stop_words' => '',
            'test_mode' => '0',
            'plugin_language' => 'auto',
            'translation_service' => 'google',
            'translation_google_key' => '',
            'translation_deepl_key' => '',
            'translation_yandex_key' => '',
            'translation_libre_url' => 'https://libretranslate.com',
            'translation_mymemory_email' => '',
            'translation_source_lang' => 'en',
            'translation_target_lang' => 'ru',
            'translation_auto_translate' => '0',
            'translate_alt' => '1',
            'translate_title' => '1',
            'translate_caption' => '0',
            'translate_description' => '0',
            'woocommerce_enabled' => '1',
            'woocommerce_process_gallery' => '1',
            'woocommerce_use_product_title' => '1',
            'woocommerce_use_category' => '1',
            'woocommerce_use_sku' => '0'
        );
        update_option('autoimta_settings', $default_settings);
    }
    
    // Создание таблицы для логов
    $this->create_log_table();
}

    /**
     * Создание таблицы для логов обработки
     */
  private function create_log_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'autoimta_process_log';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id int(11) NOT NULL AUTO_INCREMENT,
        process_date datetime DEFAULT CURRENT_TIMESTAMP,
        total_images int(11) DEFAULT 0,
        processed int(11) DEFAULT 0,
        success int(11) DEFAULT 0,
        skipped int(11) DEFAULT 0,
        errors int(11) DEFAULT 0,
        test_mode tinyint(1) DEFAULT 0,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Очистка при необходимости
    }
    
    /**
     * Инициализация
     */
    public function init() {
        $settings = get_option('autoimta_settings', array());
        $language = isset($settings['plugin_language']) ? $settings['plugin_language'] : 'auto';
        
        if ($language !== 'auto' && !empty($language)) {
            add_filter('determine_locale', function($locale) use ($language) {
                if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'auto-image-tags') !== false) {
                    return $language;
                }
                return $locale;
            }, 10);
        }
    }
    
    /**
     * Добавление меню в админку
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__('Auto Image Tags', 'auto-image-tags'),
            esc_html__('Auto Image Tags', 'auto-image-tags'),
            'manage_options',
            'auto-image-tags',
            array($this, 'admin_page'),
            'dashicons-format-image',
            80
        );
    }
    
    /**
     * Регистрация настроек
     */
    public function register_settings() {
        register_setting('autoimta_settings_group', 'autoimta_settings', array($this, 'sanitize_settings'));
    }
    
    /**
     * Санитизация настроек
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Форматы тегов
        $sanitized['alt_format'] = sanitize_text_field($input['alt_format']);
        $sanitized['title_format'] = sanitize_text_field($input['title_format']);
        $sanitized['caption_format'] = sanitize_text_field($input['caption_format']);
        $sanitized['description_format'] = sanitize_text_field($input['description_format']);
        
        // Кастомные тексты
        $sanitized['alt_custom_text'] = sanitize_text_field($input['alt_custom_text']);
        $sanitized['title_custom_text'] = sanitize_text_field($input['title_custom_text']);
        $sanitized['caption_custom_text'] = sanitize_text_field($input['caption_custom_text']);
        $sanitized['description_custom_text'] = sanitize_text_field($input['description_custom_text']);
        
        // Опции обработки
        $sanitized['remove_hyphens'] = isset($input['remove_hyphens']) ? '1' : '0';
        $sanitized['remove_dots'] = isset($input['remove_dots']) ? '1' : '0';
        $sanitized['capitalize_words'] = isset($input['capitalize_words']) ? '1' : '0';
        $sanitized['remove_numbers'] = isset($input['remove_numbers']) ? '1' : '0';
        $sanitized['camelcase_split'] = isset($input['camelcase_split']) ? '1' : '0';
        $sanitized['remove_size_suffix'] = isset($input['remove_size_suffix']) ? '1' : '0';
        $sanitized['process_on_upload'] = isset($input['process_on_upload']) ? '1' : '0';
        
        // Опции перезаписи
        $sanitized['overwrite_alt'] = isset($input['overwrite_alt']) ? '1' : '0';
        $sanitized['overwrite_title'] = isset($input['overwrite_title']) ? '1' : '0';
        $sanitized['overwrite_caption'] = isset($input['overwrite_caption']) ? '1' : '0';
        $sanitized['overwrite_description'] = isset($input['overwrite_description']) ? '1' : '0';
        
        // Стоп-слова и прочее
        $sanitized['stop_words'] = sanitize_text_field($input['stop_words']);
        $sanitized['custom_stop_words'] = sanitize_text_field($input['custom_stop_words']);
        $sanitized['test_mode'] = isset($input['test_mode']) ? '1' : '0';
        $sanitized['plugin_language'] = sanitize_text_field($input['plugin_language']);
        
        // Настройки перевода
        $sanitized['translation_service'] = sanitize_text_field($input['translation_service']);
        $sanitized['translation_google_key'] = sanitize_text_field($input['translation_google_key']);
        $sanitized['translation_deepl_key'] = sanitize_text_field($input['translation_deepl_key']);
        $sanitized['translation_yandex_key'] = sanitize_text_field($input['translation_yandex_key']);
        $sanitized['translation_libre_url'] = esc_url_raw($input['translation_libre_url']);
        $sanitized['translation_mymemory_email'] = sanitize_email($input['translation_mymemory_email']);
        $sanitized['translation_source_lang'] = sanitize_text_field($input['translation_source_lang']);
        $sanitized['translation_target_lang'] = sanitize_text_field($input['translation_target_lang']);
        $sanitized['translation_auto_translate'] = isset($input['translation_auto_translate']) ? '1' : '0';
        $sanitized['translate_alt'] = isset($input['translate_alt']) ? '1' : '0';
        $sanitized['translate_title'] = isset($input['translate_title']) ? '1' : '0';
        $sanitized['translate_caption'] = isset($input['translate_caption']) ? '1' : '0';
        $sanitized['translate_description'] = isset($input['translate_description']) ? '1' : '0';
        
        // WooCommerce настройки
        $sanitized['woocommerce_enabled'] = isset($input['woocommerce_enabled']) ? '1' : '0';
        $sanitized['woocommerce_process_gallery'] = isset($input['woocommerce_process_gallery']) ? '1' : '0';
        $sanitized['woocommerce_use_product_title'] = isset($input['woocommerce_use_product_title']) ? '1' : '0';
        $sanitized['woocommerce_use_category'] = isset($input['woocommerce_use_category']) ? '1' : '0';
        $sanitized['woocommerce_use_sku'] = isset($input['woocommerce_use_sku']) ? '1' : '0';
        
        return $sanitized;
    }
    
    /**
     * Главная страница админки с табами
     */
public function admin_page() {
    $settings = get_option('autoimta_settings');
    
    // Проверка что настройки существуют
    if (!is_array($settings) || empty($settings)) {
        $settings = array(
            'alt_format' => 'filename',
            'title_format' => 'filename',
            'caption_format' => 'disabled',
            'description_format' => 'disabled',
            'alt_custom_text' => '',
            'title_custom_text' => '',
            'caption_custom_text' => '',
            'description_custom_text' => '',
            'remove_hyphens' => '1',
            'remove_dots' => '1',
            'capitalize_words' => '1',
            'remove_numbers' => '1',
            'camelcase_split' => '1',
            'remove_size_suffix' => '1',
            'process_on_upload' => '1',
            'overwrite_alt' => '0',
            'overwrite_title' => '0',
            'overwrite_caption' => '0',
            'overwrite_description' => '0',
            'stop_words' => 'DSC, IMG, image, photo, picture, pic, screenshot, foto',
            'custom_stop_words' => '',
            'test_mode' => '0',
            'plugin_language' => 'auto',
            'translation_service' => 'google',
            'translation_google_key' => '',
            'translation_deepl_key' => '',
            'translation_yandex_key' => '',
            'translation_libre_url' => 'https://libretranslate.com',
            'translation_mymemory_email' => '',
            'translation_source_lang' => 'en',
            'translation_target_lang' => 'ru',
            'translation_auto_translate' => '0',
            'translate_alt' => '1',
            'translate_title' => '1',
            'translate_caption' => '0',
            'translate_description' => '0',
            'woocommerce_enabled' => '1',
            'woocommerce_process_gallery' => '1',
            'woocommerce_use_product_title' => '1',
            'woocommerce_use_category' => '1',
            'woocommerce_use_sku' => '0'
        );
        // Сохраняем дефолтные настройки
        update_option('autoimta_settings', $settings);
    }
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Табы навигации -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=settings')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=process')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'process' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Process Images', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=preview')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'preview' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Preview', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=stats')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'stats' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Statistics', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=tools')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Tools', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=translation')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'translation' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Translation', 'auto-image-tags'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=auto-image-tags&tab=about')); ?>" 
                   class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('About', 'auto-image-tags'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php 
                switch($active_tab) {
                    case 'process':
                        $this->render_process_tab();
                        break;
                    case 'preview':
                        $this->render_preview_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    case 'about':
                        $this->render_about_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    case 'translation':
                        $this->render_translation_tab();
                        break;
                    case 'settings':
                    default:
                        $this->render_settings_tab($settings);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
	
/**
     * Вкладка настроек
     */
private function render_settings_tab($settings) {
    // КРИТИЧЕСКИ ВАЖНО: Проверка настроек
    if (!is_array($settings) || empty($settings)) {
        $settings = array(
            'alt_format' => 'filename',
            'title_format' => 'filename',
            'caption_format' => 'disabled',
            'description_format' => 'disabled',
            'alt_custom_text' => '',
            'title_custom_text' => '',
            'caption_custom_text' => '',
            'description_custom_text' => '',
            'remove_hyphens' => '1',
            'remove_dots' => '1',
            'capitalize_words' => '1',
            'remove_numbers' => '1',
            'camelcase_split' => '1',
            'remove_size_suffix' => '1',
            'process_on_upload' => '1',
            'overwrite_alt' => '0',
            'overwrite_title' => '0',
            'overwrite_caption' => '0',
            'overwrite_description' => '0',
            'stop_words' => 'DSC, IMG, image, photo, picture, pic, screenshot, foto',
            'custom_stop_words' => '',
            'test_mode' => '0',
            'plugin_language' => 'auto',
            'translation_service' => 'google',
            'translation_google_key' => '',
            'translation_deepl_key' => '',
            'translation_yandex_key' => '',
            'translation_libre_url' => 'https://libretranslate.com',
            'translation_mymemory_email' => '',
            'translation_source_lang' => 'en',
            'translation_target_lang' => 'ru',
            'translation_auto_translate' => '0',
            'translate_alt' => '1',
            'translate_title' => '1',
            'translate_caption' => '0',
            'translate_description' => '0',
            'woocommerce_enabled' => '1',
            'woocommerce_process_gallery' => '1',
            'woocommerce_use_product_title' => '1',
            'woocommerce_use_category' => '1',
            'woocommerce_use_sku' => '0'
        );
    }
    ?>
    <form method="post" action="options.php" class="autoimta-settings-form">
            <?php settings_fields('autoimta_settings_group'); ?>
            
            <!-- Языковые настройки -->
            <h2><?php esc_html_e('Language Settings', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="plugin_language"><?php esc_html_e('Plugin Language', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="autoimta_settings[plugin_language]" id="plugin_language">
                            <option value="auto" <?php selected($settings['plugin_language'], 'auto'); ?>>
                                <?php esc_html_e('Automatic (site language)', 'auto-image-tags'); ?>
                            </option>
                            <option value="ru_RU" <?php selected($settings['plugin_language'], 'ru_RU'); ?>>
                                Русский
                            </option>
                            <option value="en_US" <?php selected($settings['plugin_language'], 'en_US'); ?>>
                                English
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select plugin interface language', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Настройки форматов -->
            <h2><?php esc_html_e('Tag Formats', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <!-- ALT -->
                <tr>
                    <th scope="row">
                        <label for="alt_format"><?php esc_html_e('ALT Tag Format', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="autoimta_settings[alt_format]" id="alt_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['alt_format'], 'disabled'); ?>>
                                <?php esc_html_e('Do not change', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['alt_format'], 'filename'); ?>>
                                <?php esc_html_e('Filename', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['alt_format'], 'posttitle'); ?>>
                                <?php esc_html_e('Post/Page Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="sitename" <?php selected($settings['alt_format'], 'sitename'); ?>>
                                <?php esc_html_e('Site Name', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_posttitle" <?php selected($settings['alt_format'], 'filename_posttitle'); ?>>
                                <?php esc_html_e('Filename + Post Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_sitename" <?php selected($settings['alt_format'], 'filename_sitename'); ?>>
                                <?php esc_html_e('Filename + Site Name', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['alt_format'], 'custom'); ?>>
                                <?php esc_html_e('Custom Text', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="autoimta-checkbox-inline">
                            <input type="checkbox" name="autoimta_settings[overwrite_alt]" value="1" 
                                   <?php checked($settings['overwrite_alt'], '1'); ?>>
                            <?php esc_html_e('Overwrite existing', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr id="alt_custom_row" style="<?php echo ($settings['alt_format'] != 'custom') ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="alt_custom_text"><?php esc_html_e('Custom ALT Text', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="autoimta_settings[alt_custom_text]" id="alt_custom_text" 
                               value="<?php echo esc_attr($settings['alt_custom_text']); ?>" class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Use variables: {filename}, {posttitle}, {sitename}, {category}, {tags}, {author}, {date}, {year}, {month}', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- TITLE -->
                <tr>
                    <th scope="row">
                        <label for="title_format"><?php esc_html_e('TITLE Tag Format', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="autoimta_settings[title_format]" id="title_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['title_format'], 'disabled'); ?>>
                                <?php esc_html_e('Do not change', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['title_format'], 'filename'); ?>>
                                <?php esc_html_e('Filename', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['title_format'], 'posttitle'); ?>>
                                <?php esc_html_e('Post/Page Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="sitename" <?php selected($settings['title_format'], 'sitename'); ?>>
                                <?php esc_html_e('Site Name', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_posttitle" <?php selected($settings['title_format'], 'filename_posttitle'); ?>>
                                <?php esc_html_e('Filename + Post Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_sitename" <?php selected($settings['title_format'], 'filename_sitename'); ?>>
                                <?php esc_html_e('Filename + Site Name', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['title_format'], 'custom'); ?>>
                                <?php esc_html_e('Custom Text', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="autoimta-checkbox-inline">
                            <input type="checkbox" name="autoimta_settings[overwrite_title]" value="1" 
                                   <?php checked($settings['overwrite_title'], '1'); ?>>
                            <?php esc_html_e('Overwrite existing', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr id="title_custom_row" style="<?php echo ($settings['title_format'] != 'custom') ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="title_custom_text"><?php esc_html_e('Custom TITLE Text', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="autoimta_settings[title_custom_text]" id="title_custom_text" 
                               value="<?php echo esc_attr($settings['title_custom_text']); ?>" class="regular-text">
                    </td>
                </tr>
                
                <!-- CAPTION -->
                <tr>
                    <th scope="row">
                        <label for="caption_format"><?php esc_html_e('Caption Format', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="autoimta_settings[caption_format]" id="caption_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['caption_format'], 'disabled'); ?>>
                                <?php esc_html_e('Do not change', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['caption_format'], 'filename'); ?>>
                                <?php esc_html_e('Filename', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['caption_format'], 'posttitle'); ?>>
                                <?php esc_html_e('Post/Page Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['caption_format'], 'custom'); ?>>
                                <?php esc_html_e('Custom Text', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="autoimta-checkbox-inline">
                            <input type="checkbox" name="autoimta_settings[overwrite_caption]" value="1" 
                                   <?php checked($settings['overwrite_caption'], '1'); ?>>
                            <?php esc_html_e('Overwrite existing', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <!-- DESCRIPTION -->
                <tr>
                    <th scope="row">
                        <label for="description_format"><?php esc_html_e('Description Format', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="autoimta_settings[description_format]" id="description_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['description_format'], 'disabled'); ?>>
                                <?php esc_html_e('Do not change', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['description_format'], 'filename'); ?>>
                                <?php esc_html_e('Filename', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['description_format'], 'posttitle'); ?>>
                                <?php esc_html_e('Post/Page Title', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['description_format'], 'custom'); ?>>
                                <?php esc_html_e('Custom Text', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="autoimta-checkbox-inline">
                            <input type="checkbox" name="autoimta_settings[overwrite_description]" value="1" 
                                   <?php checked($settings['overwrite_description'], '1'); ?>>
                            <?php esc_html_e('Overwrite existing', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <!-- Обработка имен файлов -->
            <h2><?php esc_html_e('Filename Processing', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Cleanup Options', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="autoimta_settings[remove_hyphens]" value="1" 
                                       <?php checked($settings['remove_hyphens'], '1'); ?>>
                                <?php esc_html_e('Replace hyphens and underscores with spaces', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[remove_dots]" value="1" 
                                       <?php checked($settings['remove_dots'], '1'); ?>>
                                <?php esc_html_e('Remove dots from filenames', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[capitalize_words]" value="1" 
                                       <?php checked($settings['capitalize_words'], '1'); ?>>
                                <?php esc_html_e('Capitalize first letter of each word', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[remove_numbers]" value="1" 
                                       <?php checked($settings['remove_numbers'], '1'); ?>>
                                <?php esc_html_e('Remove camera numbers (DSC_0001, IMG_20231225)', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[camelcase_split]" value="1" 
                                       <?php checked($settings['camelcase_split'], '1'); ?>>
                                <?php esc_html_e('Split CamelCase (PhotoOfProduct → Photo Of Product)', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[remove_size_suffix]" value="1" 
                                       <?php checked($settings['remove_size_suffix'], '1'); ?>>
                                <?php esc_html_e('Remove size suffixes (-300x200, -scaled, -thumb)', 'auto-image-tags'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stop_words"><?php esc_html_e('Stop Words', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="autoimta_settings[stop_words]" id="stop_words" 
                               value="<?php echo esc_attr((is_array($settings) && isset($settings['stop_words'])) ? $settings['stop_words'] : 'DSC, IMG, image, photo, picture, pic, screenshot, foto'); ?>" class="large-text">
                        <p class="description">
                            <?php esc_html_e('Words to remove from filenames, comma-separated', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Дополнительные настройки -->
            <h2><?php esc_html_e('Additional Settings', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Operation Mode', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="autoimta_settings[process_on_upload]" value="1" 
                                       <?php checked($settings['process_on_upload'], '1'); ?>>
                                <?php esc_html_e('Process images on upload', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label class="autoimta-important-option">
                                <input type="checkbox" name="autoimta_settings[test_mode]" value="1" 
                                       <?php checked($settings['test_mode'], '1'); ?>>
                                <strong><?php esc_html_e('Test Mode (no changes saved)', 'auto-image-tags'); ?></strong>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <!-- WooCommerce интеграция -->
            <?php if (class_exists('WooCommerce')): ?>
            <h2><?php esc_html_e('WooCommerce Integration', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Main Settings', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="autoimta_settings[woocommerce_enabled]" value="1" 
                                       <?php checked($settings['woocommerce_enabled'], '1'); ?>>
                                <strong><?php esc_html_e('Enable WooCommerce product image processing', 'auto-image-tags'); ?></strong>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[woocommerce_process_gallery]" value="1" 
                                       <?php checked($settings['woocommerce_process_gallery'], '1'); ?>>
                                <?php esc_html_e('Process product gallery (additional images)', 'auto-image-tags'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Use in tags:', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="autoimta_settings[woocommerce_use_product_title]" value="1" 
                                       <?php checked($settings['woocommerce_use_product_title'], '1'); ?>>
                                <?php esc_html_e('Product title', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[woocommerce_use_category]" value="1" 
                                       <?php checked($settings['woocommerce_use_category'], '1'); ?>>
                                <?php esc_html_e('Product category', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="autoimta_settings[woocommerce_use_sku]" value="1" 
                                       <?php checked($settings['woocommerce_use_sku'], '1'); ?>>
                                <?php esc_html_e('Product SKU', 'auto-image-tags'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e('This data will be added to processed product image tags', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php else: ?>
            <h2><?php esc_html_e('WooCommerce Integration', 'auto-image-tags'); ?></h2>
            <div class="autoimta-notice autoimta-notice-warning">
                <p><?php esc_html_e('WooCommerce is not installed. Install and activate WooCommerce to use this feature.', 'auto-image-tags'); ?></p>
            </div>
            <?php endif; ?>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
	
	/**
     * Вкладка предпросмотра
     */
    private function render_preview_tab() {
        ?>
        <div class="autoimta-preview-box">
            <h2><?php esc_html_e('Preview Changes', 'auto-image-tags'); ?></h2>
            <p><?php esc_html_e('Here you can see how tags will look after processing, without making real changes.', 'auto-image-tags'); ?></p>
            
            <div class="autoimta-preview-filters">
                <h3><?php esc_html_e('Filters', 'auto-image-tags'); ?></h3>
                <div class="filter-row">
                    <label for="preview_limit"><?php esc_html_e('Number of images:', 'auto-image-tags'); ?></label>
                    <select id="preview_limit">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    
                    <label for="preview_filter"><?php esc_html_e('Show:', 'auto-image-tags'); ?></label>
                    <select id="preview_filter">
                        <option value="all"><?php esc_html_e('All images', 'auto-image-tags'); ?></option>
                        <option value="no_alt"><?php esc_html_e('Without ALT tag', 'auto-image-tags'); ?></option>
                        <option value="no_title"><?php esc_html_e('Without TITLE tag', 'auto-image-tags'); ?></option>
                        <option value="no_tags"><?php esc_html_e('Without tags', 'auto-image-tags'); ?></option>
                    </select>
                    
                    <button id="preview_load_btn" class="button button-primary">
                        <?php esc_html_e('Load Preview', 'auto-image-tags'); ?>
                    </button>
                </div>
            </div>
            
            <div id="preview_results" style="display:none;">
                <h3><?php esc_html_e('Preview Results', 'auto-image-tags'); ?></h3>
                <div id="preview_content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Вкладка обработки изображений
     */
    private function render_process_tab() {
    ?>
    <div class="autoimta-process-box">
        <h2><?php esc_html_e('Process Existing Images', 'auto-image-tags'); ?></h2>
        
        <?php 
        $settings = get_option('autoimta_settings', array());
        if (isset($settings['test_mode']) && $settings['test_mode'] == '1') {
                ?>
                <div class="autoimta-notice autoimta-notice-info">
                    <p><strong><?php esc_html_e('Test Mode Active!', 'auto-image-tags'); ?></strong> 
                    <?php esc_html_e('Changes will not be saved. Disable test mode in settings for real processing.', 'auto-image-tags'); ?></p>
                </div>
                <?php
            }
            ?>
            
            <div class="autoimta-notice autoimta-notice-warning">
                <p><strong><?php esc_html_e('Warning!', 'auto-image-tags'); ?></strong> 
                <?php esc_html_e('It is recommended to create a database backup before bulk processing.', 'auto-image-tags'); ?></p>
            </div>
            
            <div class="autoimta-filters">
                <h3><?php esc_html_e('Processing Filters', 'auto-image-tags'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="date_filter"><?php esc_html_e('Upload Period:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select id="date_filter" class="regular-text">
                                <option value="all"><?php esc_html_e('All time', 'auto-image-tags'); ?></option>
                                <option value="today"><?php esc_html_e('Today', 'auto-image-tags'); ?></option>
                                <option value="week"><?php esc_html_e('Last week', 'auto-image-tags'); ?></option>
                                <option value="month"><?php esc_html_e('Last month', 'auto-image-tags'); ?></option>
                                <option value="year"><?php esc_html_e('Last year', 'auto-image-tags'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="status_filter"><?php esc_html_e('Status:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select id="status_filter" class="regular-text">
                                <option value="all"><?php esc_html_e('All images', 'auto-image-tags'); ?></option>
                                <option value="no_alt"><?php esc_html_e('Without ALT', 'auto-image-tags'); ?></option>
                                <option value="no_title"><?php esc_html_e('Without TITLE', 'auto-image-tags'); ?></option>
                                <option value="no_tags"><?php esc_html_e('Without tags', 'auto-image-tags'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="post_filter"><?php esc_html_e('Post/Page:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select id="post_filter" class="regular-text">
                                <option value="all"><?php esc_html_e('All', 'auto-image-tags'); ?></option>
                                <option value="loading..."><?php esc_html_e('Loading...', 'auto-image-tags'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="autoimta-stats">
                <p><?php esc_html_e('Loading statistics...', 'auto-image-tags'); ?></p>
            </div>
            
            <button id="autoimta-process-btn" class="button button-primary button-hero" disabled>
                <?php esc_html_e('Start Processing', 'auto-image-tags'); ?>
            </button>
            
            <div id="autoimta-progress" style="display:none;">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-text">0%</div>
                </div>
                <p id="autoimta-status-text"></p>
            </div>
            
            <div id="autoimta-results" style="display:none;">
                <h3><?php esc_html_e('Processing Results:', 'auto-image-tags'); ?></h3>
                <div id="autoimta-results-content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Вкладка статистики
     */
    private function render_stats_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoimta_process_log';
        
        // Получаем последние записи из лога
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autoimta_process_log ORDER BY process_date DESC LIMIT %d", 20));
        
        // Общая статистика
        $total_processed = $wpdb->get_var($wpdb->prepare("SELECT SUM(processed) FROM {$wpdb->prefix}autoimta_process_log WHERE 1=%d", 1));
        $total_success = $wpdb->get_var($wpdb->prepare("SELECT SUM(success) FROM {$wpdb->prefix}autoimta_process_log WHERE 1=%d", 1));
        ?>
        <div class="autoimta-stats-box">
            <h2><?php esc_html_e('Processing Statistics', 'auto-image-tags'); ?></h2>
            
            <div class="autoimta-stats-summary">
                <h3><?php esc_html_e('Overall Statistics', 'auto-image-tags'); ?></h3>
                <div class="autoimta-stats-grid">
                    <div class="autoimta-stat-item">
                        <span class="autoimta-stat-label"><?php esc_html_e('Total Processed:', 'auto-image-tags'); ?></span>
                        <span class="autoimta-stat-value"><?php echo absint($total_processed); ?></span>
                    </div>
                    <div class="autoimta-stat-item">
                        <span class="autoimta-stat-label"><?php esc_html_e('Successfully Updated:', 'auto-image-tags'); ?></span>
                        <span class="autoimta-stat-value"><?php echo absint($total_success); ?></span>
                    </div>
                </div>
            </div>
            
            <h3><?php esc_html_e('Processing History', 'auto-image-tags'); ?></h3>
            <?php if ($logs): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Total', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Processed', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Success', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Skipped', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Errors', 'auto-image-tags'); ?></th>
                        <th><?php esc_html_e('Mode', 'auto-image-tags'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->process_date))); ?></td>
                        <td><?php echo absint($log->total_images); ?></td>
                        <td><?php echo absint($log->processed); ?></td>
                        <td><?php echo absint($log->success); ?></td>
                        <td><?php echo absint($log->skipped); ?></td>
                        <td><?php echo absint($log->errors); ?></td>
                        <td><?php echo esc_html($log->test_mode ? esc_html__('Test', 'auto-image-tags') : esc_html__('Normal', 'auto-image-tags')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php esc_html_e('Processing history is empty.', 'auto-image-tags'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Вкладка "О плагине"
     */
    private function render_about_tab() {
        ?>
        <div class="autoimta-about-box">
            <h2><?php esc_html_e('About Auto Image Tags Plugin', 'auto-image-tags'); ?></h2>
            
            <div class="autoimta-info-section">
                <h3><?php esc_html_e('Plugin Information', 'auto-image-tags'); ?></h3>
                <table class="autoimta-info-table">
                    <tr>
                        <td><strong><?php esc_html_e('Version:', 'auto-image-tags'); ?></strong></td>
                        <td><?php echo esc_html(AUTOIMTA_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Author:', 'auto-image-tags'); ?></strong></td>
                        <td>Shapovalov Bogdan</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Telegram:', 'auto-image-tags'); ?></strong></td>
                        <td><a href="https://t.me/shapovalovbogdan" target="_blank" rel="noopener noreferrer">@shapovalovbogdan</a></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('GitHub:', 'auto-image-tags'); ?></strong></td>
                        <td><a href="https://github.com/imrbogdan/auto-image-tags" target="_blank" rel="noopener noreferrer">Repository</a></td>
                    </tr>
                </table>
            </div>
            
            <div class="autoimta-info-section">
                <h3><?php esc_html_e('Plugin Features', 'auto-image-tags'); ?></h3>
                <ul>
                    <li>✅ <?php esc_html_e('Automatic ALT, TITLE, Caption and Description generation', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Preview changes before applying', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Individual overwrite settings for each attribute', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Bulk processing filters (by date, posts, status)', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Advanced filename cleanup', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Stop words to remove unwanted words', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Test mode for safe testing', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Processing history and statistics', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Multilingual support', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Extended template variables', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('Translation system (5 services)', 'auto-image-tags'); ?></li>
                    <li>✅ <?php esc_html_e('WooCommerce integration', 'auto-image-tags'); ?></li>
                </ul>
            </div>

            <div class="autoimta-info-section">
                <h3><?php esc_html_e('What\'s New in Version 2.0.0', 'auto-image-tags'); ?></h3>
                <ul>
                    <li>🆕 <?php esc_html_e('Translation system with 5 services', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('WooCommerce integration', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Tools tab (bulk delete, export/import settings)', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Preview tab with before/after comparison', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Caption and Description support', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Individual overwrite settings', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Advanced filters', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Enhanced filename cleanup', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Custom stop words', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Test mode', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Statistics and history', 'auto-image-tags'); ?></li>
                    <li>🆕 <?php esc_html_e('Language selection', 'auto-image-tags'); ?></li>
                </ul>
            </div>
            
            <div class="autoimta-info-section">
                <h3><?php esc_html_e('Support and Feedback', 'auto-image-tags'); ?></h3>
                <p><?php esc_html_e('If you have questions, suggestions or found a bug, contact me via Telegram.', 'auto-image-tags'); ?></p>
                <p><?php esc_html_e('Plugin is distributed for free to help the WordPress community.', 'auto-image-tags'); ?></p>
                <p><strong><?php esc_html_e('All features are available for free, no Pro version!', 'auto-image-tags'); ?></strong></p>
            </div>
        </div>
        <?php
    }
	
	/**
     * Вкладка инструментов
     */
    private function render_tools_tab() {
        ?>
        <div class="autoimta-tools-box">
            <!-- Массовое удаление тегов -->
            <div class="autoimta-tool-section">
                <h2><?php esc_html_e('Bulk Tag Removal', 'auto-image-tags'); ?></h2>
                <p><?php esc_html_e('Remove ALT, TITLE, Caption and Description from selected images.', 'auto-image-tags'); ?></p>
                
                <div class="autoimta-notice autoimta-notice-warning">
                    <p><strong><?php esc_html_e('Warning!', 'auto-image-tags'); ?></strong> 
                    <?php esc_html_e('This action is irreversible. It is recommended to create a database backup before removal.', 'auto-image-tags'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('What to remove:', 'auto-image-tags'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" id="remove_alt" checked>
                                    <?php esc_html_e('ALT tags', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" id="remove_title" checked>
                                    <?php esc_html_e('TITLE (image title)', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" id="remove_caption">
                                    <?php esc_html_e('Caption', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" id="remove_description">
                                    <?php esc_html_e('Description', 'auto-image-tags'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Filters:', 'auto-image-tags'); ?></th>
                        <td>
                            <label>
                                <?php esc_html_e('Upload period:', 'auto-image-tags'); ?>
                                <select id="remove_date_filter">
                                    <option value="all"><?php esc_html_e('All time', 'auto-image-tags'); ?></option>
                                    <option value="today"><?php esc_html_e('Today', 'auto-image-tags'); ?></option>
                                    <option value="week"><?php esc_html_e('Last week', 'auto-image-tags'); ?></option>
                                    <option value="month"><?php esc_html_e('Last month', 'auto-image-tags'); ?></option>
                                    <option value="year"><?php esc_html_e('Last year', 'auto-image-tags'); ?></option>
                                </select>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <div id="remove-stats">
                    <p><?php esc_html_e('Loading statistics...', 'auto-image-tags'); ?></p>
                </div>
                
                <button id="remove-tags-btn" class="button button-secondary button-hero" disabled>
                    <?php esc_html_e('Remove Tags', 'auto-image-tags'); ?>
                </button>
                
                <div id="remove-progress" style="display:none;">
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: 0%;"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    <p id="remove-status-text"></p>
                </div>
                
                <div id="remove-results" style="display:none;">
                    <h3><?php esc_html_e('Removal Results:', 'auto-image-tags'); ?></h3>
                    <div id="remove-results-content"></div>
                </div>
            </div>
            
            <hr style="margin: 40px 0; border: none; border-top: 1px solid #ddd;">
            
            <!-- Экспорт/Импорт настроек -->
            <div class="autoimta-tool-section">
                <h2><?php esc_html_e('Export/Import Settings', 'auto-image-tags'); ?></h2>
                <p><?php esc_html_e('Save plugin settings to a file or load previously saved settings.', 'auto-image-tags'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Export settings:', 'auto-image-tags'); ?></th>
                        <td>
                            <button id="export-settings-btn" class="button button-secondary">
                                <?php esc_html_e('📥 Download Settings', 'auto-image-tags'); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e('Saves current plugin settings to a JSON file', 'auto-image-tags'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Import settings:', 'auto-image-tags'); ?></th>
                        <td>
                            <input type="file" id="import-settings-file" accept=".json" style="display:none;">
                            <button id="import-settings-btn" class="button button-secondary">
                                <?php esc_html_e('📤 Upload Settings', 'auto-image-tags'); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e('Loads settings from a previously saved JSON file', 'auto-image-tags'); ?>
                            </p>
                            <div id="import-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Вкладка перевода
     */
    private function render_translation_tab() {
    $settings = get_option('autoimta_settings', array());
    
    // Проверка настроек
    if (!is_array($settings) || empty($settings)) {
        $settings = array(
            'translation_service' => 'google',
            'translation_google_key' => '',
            'translation_deepl_key' => '',
            'translation_yandex_key' => '',
            'translation_libre_url' => 'https://libretranslate.com',
            'translation_mymemory_email' => '',
            'translation_source_lang' => 'en',
            'translation_target_lang' => 'ru',
            'translation_auto_translate' => '0',
            'translate_alt' => '1',
            'translate_title' => '1',
            'translate_caption' => '0',
            'translate_description' => '0'
        );
    }
        ?>
        <div class="autoimta-translation-box">
            <h2><?php esc_html_e('Automatic Image Tag Translation', 'auto-image-tags'); ?></h2>
            <p><?php esc_html_e('Translate ALT, TITLE, Caption and Description using various translation services.', 'auto-image-tags'); ?></p>
            
            <form method="post" action="options.php" class="autoimta-settings-form">
                <?php settings_fields('autoimta_settings_group'); ?>
                
                <!-- Выбор сервиса перевода -->
                <h3><?php esc_html_e('API Settings', 'auto-image-tags'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="translation_service"><?php esc_html_e('Translation Service:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select name="autoimta_settings[translation_service]" id="translation_service" class="regular-text">
                                <option value="google" <?php selected($settings['translation_service'], 'google'); ?>>Google Translate</option>
                                <option value="deepl" <?php selected($settings['translation_service'], 'deepl'); ?>>DeepL</option>
                                <option value="yandex" <?php selected($settings['translation_service'], 'yandex'); ?>>Yandex Translator</option>
                                <option value="libre" <?php selected($settings['translation_service'], 'libre'); ?>>LibreTranslate (free)</option>
                                <option value="mymemory" <?php selected($settings['translation_service'], 'mymemory'); ?>>MyMemory (free)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- Google Translate API -->
                <div id="google-settings" class="translation-service-settings" style="<?php echo $settings['translation_service'] != 'google' ? 'display:none;' : ''; ?>">
                    <h4>Google Translate API</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translation_google_key"><?php esc_html_e('API Key:', 'auto-image-tags'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="autoimta_settings[translation_google_key]" id="translation_google_key" 
                                       value="<?php echo esc_attr($settings['translation_google_key']); ?>" class="large-text">
                                <p class="description">
                                    <?php esc_html_e('Get API key:', 'auto-image-tags'); ?> 
                                    <a href="https://cloud.google.com/translate/docs/setup" target="_blank" rel="noopener noreferrer">cloud.google.com/translate</a>
                                    <br><?php esc_html_e('⚠️ Paid service. First $300 free for new users.', 'auto-image-tags'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- DeepL API -->
                <div id="deepl-settings" class="translation-service-settings" style="<?php echo $settings['translation_service'] != 'deepl' ? 'display:none;' : ''; ?>">
                    <h4>DeepL API</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translation_deepl_key"><?php esc_html_e('API Key:', 'auto-image-tags'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="autoimta_settings[translation_deepl_key]" id="translation_deepl_key" 
                                       value="<?php echo esc_attr($settings['translation_deepl_key']); ?>" class="large-text">
                                <p class="description">
                                    <?php esc_html_e('Get API key:', 'auto-image-tags'); ?> 
                                    <a href="https://www.deepl.com/pro-api" target="_blank" rel="noopener noreferrer">deepl.com/pro-api</a>
                                    <br><?php esc_html_e('✅ Free: 500,000 characters per month', 'auto-image-tags'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Яндекс.Переводчик API -->
                <div id="yandex-settings" class="translation-service-settings" style="<?php echo $settings['translation_service'] != 'yandex' ? 'display:none;' : ''; ?>">
                    <h4>Yandex Translator API</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translation_yandex_key"><?php esc_html_e('API Key:', 'auto-image-tags'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="autoimta_settings[translation_yandex_key]" id="translation_yandex_key" 
                                       value="<?php echo esc_attr($settings['translation_yandex_key']); ?>" class="large-text">
                                <p class="description">
                                    <?php esc_html_e('Get API key:', 'auto-image-tags'); ?> 
                                    <a href="https://cloud.yandex.ru/docs/translate/" target="_blank" rel="noopener noreferrer">cloud.yandex.ru/translate</a>
                                    <br><?php esc_html_e('✅ Free: 1,000,000 characters per month', 'auto-image-tags'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- LibreTranslate -->
                <div id="libre-settings" class="translation-service-settings" style="<?php echo $settings['translation_service'] != 'libre' ? 'display:none;' : ''; ?>">
                    <h4>LibreTranslate</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translation_libre_url"><?php esc_html_e('Server URL:', 'auto-image-tags'); ?></label>
                            </th>
                            <td>
                                <input type="url" name="autoimta_settings[translation_libre_url]" id="translation_libre_url" 
                                       value="<?php echo esc_attr($settings['translation_libre_url']); ?>" class="large-text">
                                <p class="description">
                                    <?php esc_html_e('Public server:', 'auto-image-tags'); ?> <code>https://libretranslate.com</code>
                                    <br><?php esc_html_e('Server list:', 'auto-image-tags'); ?> 
                                    <a href="https://github.com/LibreTranslate/LibreTranslate#mirrors" target="_blank" rel="noopener noreferrer">github.com/LibreTranslate</a>
                                    <br><?php esc_html_e('✅ Completely free and open-source', 'auto-image-tags'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- MyMemory -->
                <div id="mymemory-settings" class="translation-service-settings" style="<?php echo $settings['translation_service'] != 'mymemory' ? 'display:none;' : ''; ?>">
                    <h4>MyMemory API</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="translation_mymemory_email"><?php esc_html_e('Email (optional):', 'auto-image-tags'); ?></label>
                            </th>
                            <td>
                                <input type="email" name="autoimta_settings[translation_mymemory_email]" id="translation_mymemory_email" 
                                       value="<?php echo esc_attr($settings['translation_mymemory_email']); ?>" class="large-text">
                                <p class="description">
                                    <?php esc_html_e('Email increases limit from 5,000 to 10,000 characters per day', 'auto-image-tags'); ?>
                                    <br><?php esc_html_e('Information:', 'auto-image-tags'); ?> 
                                    <a href="https://mymemory.translated.net/doc/spec.php" target="_blank" rel="noopener noreferrer">mymemory.translated.net</a>
                                    <br><?php esc_html_e('✅ Free: 5,000 characters per day (10,000 with email)', 'auto-image-tags'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Языки перевода -->
                <h3><?php esc_html_e('Language Settings', 'auto-image-tags'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="translation_source_lang"><?php esc_html_e('Source Language:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select name="autoimta_settings[translation_source_lang]" id="translation_source_lang" class="regular-text">
                                <option value="en" <?php selected($settings['translation_source_lang'], 'en'); ?>>English</option>
                                <option value="ru" <?php selected($settings['translation_source_lang'], 'ru'); ?>>Русский</option>
                                <option value="de" <?php selected($settings['translation_source_lang'], 'de'); ?>>Deutsch</option>
                                <option value="fr" <?php selected($settings['translation_source_lang'], 'fr'); ?>>Français</option>
                                <option value="es" <?php selected($settings['translation_source_lang'], 'es'); ?>>Español</option>
                                <option value="it" <?php selected($settings['translation_source_lang'], 'it'); ?>>Italiano</option>
                                <option value="pt" <?php selected($settings['translation_source_lang'], 'pt'); ?>>Português</option>
                                <option value="zh" <?php selected($settings['translation_source_lang'], 'zh'); ?>>中文</option>
                                <option value="ja" <?php selected($settings['translation_source_lang'], 'ja'); ?>>日本語</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="translation_target_lang"><?php esc_html_e('Target Language:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <select name="autoimta_settings[translation_target_lang]" id="translation_target_lang" class="regular-text">
                                <option value="ru" <?php selected($settings['translation_target_lang'], 'ru'); ?>>Русский</option>
                                <option value="en" <?php selected($settings['translation_target_lang'], 'en'); ?>>English</option>
                                <option value="de" <?php selected($settings['translation_target_lang'], 'de'); ?>>Deutsch</option>
                                <option value="fr" <?php selected($settings['translation_target_lang'], 'fr'); ?>>Français</option>
                                <option value="es" <?php selected($settings['translation_target_lang'], 'es'); ?>>Español</option>
                                <option value="it" <?php selected($settings['translation_target_lang'], 'it'); ?>>Italiano</option>
                                <option value="pt" <?php selected($settings['translation_target_lang'], 'pt'); ?>>Português</option>
                                <option value="zh" <?php selected($settings['translation_target_lang'], 'zh'); ?>>中文</option>
                                <option value="ja" <?php selected($settings['translation_target_lang'], 'ja'); ?>>日本語</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- Что переводить -->
                <h3><?php esc_html_e('What to Translate', 'auto-image-tags'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Fields to translate:', 'auto-image-tags'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="autoimta_settings[translate_alt]" value="1" 
                                           <?php checked($settings['translate_alt'], '1'); ?>>
                                    <?php esc_html_e('ALT tags', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="autoimta_settings[translate_title]" value="1" 
                                           <?php checked($settings['translate_title'], '1'); ?>>
                                    <?php esc_html_e('TITLE (image title)', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="autoimta_settings[translate_caption]" value="1" 
                                           <?php checked($settings['translate_caption'], '1'); ?>>
                                    <?php esc_html_e('Caption', 'auto-image-tags'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="autoimta_settings[translate_description]" value="1" 
                                           <?php checked($settings['translate_description'], '1'); ?>>
                                    <?php esc_html_e('Description', 'auto-image-tags'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-translation:', 'auto-image-tags'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoimta_settings[translation_auto_translate]" value="1" 
                                       <?php checked($settings['translation_auto_translate'], '1'); ?>>
                                <?php esc_html_e('Automatically translate tags when uploading images', 'auto-image-tags'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(esc_html__('Save Settings', 'auto-image-tags')); ?>
            </form>
            
            <hr style="margin: 40px 0;">
            
            <!-- Тестовый перевод -->
            <div class="autoimta-test-translation">
                <h3><?php esc_html_e('Test Translation', 'auto-image-tags'); ?></h3>
                <p><?php esc_html_e('Check API functionality before bulk translation', 'auto-image-tags'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_text"><?php esc_html_e('Text to translate:', 'auto-image-tags'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="test_text" value="Beautiful sunset over the ocean" class="large-text">
                        </td>
                    </tr>
                </table>
                
                <button id="test-translation-btn" class="button button-secondary">
                    <?php esc_html_e('Test Translation', 'auto-image-tags'); ?>
                </button>
                
                <div id="test-translation-result" style="margin-top: 20px;"></div>
            </div>
            
            <hr style="margin: 40px 0;">
            
            <!-- Массовый перевод -->
            <div class="autoimta-mass-translation">
                <h3><?php esc_html_e('Bulk Translation', 'auto-image-tags'); ?></h3>
                <p><?php esc_html_e('Translate all existing image tags', 'auto-image-tags'); ?></p>
                
                <div class="autoimta-notice autoimta-notice-warning">
                    <p><strong><?php esc_html_e('Warning!', 'auto-image-tags'); ?></strong> 
                    <?php esc_html_e('Bulk translation may take time and require API credits.', 'auto-image-tags'); ?></p>
                </div>
                
                <div id="translation-stats">
                    <p><?php esc_html_e('Loading statistics...', 'auto-image-tags'); ?></p>
                </div>
                
                <button id="start-translation-btn" class="button button-primary button-hero" disabled>
                    <?php esc_html_e('Start Translation', 'auto-image-tags'); ?>
                </button>
                
                <div id="translation-progress" style="display:none;">
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: 0%;"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    <p id="translation-status-text"></p>
                </div>
                
                <div id="translation-results" style="display:none;">
                    <h3><?php esc_html_e('Translation Results:', 'auto-image-tags'); ?></h3>
                    <div id="translation-results-content"></div>
                </div>
            </div>
        </div>
        <?php
    }
	
	/**
     * AJAX: Получение количества изображений
     */
    public function ajax_get_images_count() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? array_map('sanitize_text_field', wp_unslash($_POST['filters'])) : array();
        
        // Базовые аргументы запроса
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 1000,
            'fields' => 'ids'
        );
        
        // Фильтр по дате
        if (!empty($filters['date']) && $filters['date'] !== 'all') {
            $date_query = array();
            switch ($filters['date']) {
                case 'today':
                    $date_query = array('after' => 'today', 'inclusive' => true);
                    break;
                case 'week':
                    $date_query = array('after' => '1 week ago', 'inclusive' => true);
                    break;
                case 'month':
                    $date_query = array('after' => '1 month ago', 'inclusive' => true);
                    break;
                case 'year':
                    $date_query = array('after' => '1 year ago', 'inclusive' => true);
                    break;
            }
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
        }
        
        // Фильтр по посту
        if (!empty($filters['post']) && $filters['post'] !== 'all') {
            $args['post_parent'] = intval($filters['post']);
        }
        
        $query = new WP_Query($args);
        $total = $query->found_posts;
        
        if ($total > 5000) {
            wp_send_json_error(array(
                'message' => esc_html__('Too many images to count. Use filters.', 'auto-image-tags')
            ));
            return;
        }
        
        $without_alt = 0;
        $without_title = 0;
        $needs_processing = 0;
        
        foreach ($query->posts as $id) {
            $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
            $title = get_the_title($id);
            
            // Фильтр по статусу
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'no_alt' && !empty($alt)) continue;
                if ($filters['status'] === 'no_title' && !empty($title)) continue;
                if ($filters['status'] === 'no_tags' && (!empty($alt) && !empty($title))) continue;
            }
            
            if (empty($alt)) {
                $without_alt++;
            }
            if (empty($title)) {
                $without_title++;
            }
            
            // Подсчет изображений для обработки
            $will_process = false;
            if (isset($settings['alt_format']) && $settings['alt_format'] !== 'disabled' && (isset($settings['overwrite_alt']) && $settings['overwrite_alt'] == '1' || empty($alt))) {
                $will_process = true;
            }
            if (isset($settings['title_format']) && $settings['title_format'] !== 'disabled' && (isset($settings['overwrite_title']) && $settings['overwrite_title'] == '1' || empty($title))) {
                $will_process = true;
            }
            if ($will_process) {
                $needs_processing++;
            }
        }
        
        wp_send_json_success(array(
            'total' => $total,
            'without_alt' => $without_alt,
            'without_title' => $without_title,
            'needs_processing' => $needs_processing
        ));
    }
    
    /**
     * AJAX: Предпросмотр изменений
     */
    public function ajax_preview_changes() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $filter = isset($_POST['filter']) ? sanitize_text_field(wp_unslash($_POST['filter'])) : 'all';
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        $images = array();
        
        foreach ($query->posts as $attachment_id) {
            // Текущие значения
            $current = array(
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'title' => get_the_title($attachment_id),
                'caption' => wp_get_attachment_caption($attachment_id),
                'description' => get_post_field('post_content', $attachment_id)
            );
            
            // Новые значения (симуляция обработки)
            $new_values = $this->simulate_processing($attachment_id);
            
            // Получаем миниатюру
            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            
            $images[] = array(
                'id' => $attachment_id,
                'filename' => basename(get_attached_file($attachment_id)),
                'thumb' => $thumb ? esc_url($thumb[0]) : '',
                'current' => $current,
                'new' => $new_values
            );
        }
        
        wp_send_json_success(array('images' => $images));
    }
    
    /**
     * AJAX: Получение опций фильтра
     */
    public function ajax_get_filter_options() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        // Получаем посты с прикрепленными изображениями
        global $wpdb;
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT p.ID, p.post_title 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->posts} a ON a.post_parent = p.ID 
            WHERE a.post_type = %s
            AND a.post_mime_type LIKE %s
            ORDER BY p.post_title
            LIMIT 500
        ", 'attachment', 'image%'));
        
        wp_send_json_success(array('posts' => $posts));
    }
    
    /**
     * AJAX: Обработка существующих изображений
     */
    public function ajax_process_existing_images() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;
        $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? array_map('sanitize_text_field', wp_unslash($_POST['filters'])) : array();
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids'
        );
        
        // Применяем фильтры
        if (!empty($filters['date']) && $filters['date'] !== 'all') {
            $date_query = array();
            switch ($filters['date']) {
                case 'today':
                    $date_query = array('after' => 'today', 'inclusive' => true);
                    break;
                case 'week':
                    $date_query = array('after' => '1 week ago', 'inclusive' => true);
                    break;
                case 'month':
                    $date_query = array('after' => '1 month ago', 'inclusive' => true);
                    break;
                case 'year':
                    $date_query = array('after' => '1 year ago', 'inclusive' => true);
                    break;
            }
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
        }
        
        if (!empty($filters['post']) && $filters['post'] !== 'all') {
            $args['post_parent'] = intval($filters['post']);
        }
        
        $query = new WP_Query($args);
        $processed = 0;
        $success = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($query->posts as $attachment_id) {
            $result = $this->process_single_image($attachment_id);
            if ($result === 'success') {
                $success++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
            $processed++;
        }
        
        $has_more = ($offset + $batch_size) < $query->found_posts;
        
        // Сохраняем в лог, если не тестовый режим и обработка завершена
        if (!$has_more && isset($settings['test_mode']) && $settings['test_mode'] != '1') {
            $this->save_process_log($query->found_posts, $processed, $success, $skipped, $errors, isset($settings['test_mode']) && $settings['test_mode'] == '1');
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'skipped' => $skipped,
            'has_more' => $has_more,
            'test_mode' => isset($settings['test_mode']) && $settings['test_mode'] == '1'
        ));
    }
    
    /**
     * AJAX: Получение статистики для удаления
     */
    public function ajax_get_remove_stats() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $date_filter = isset($_POST['date_filter']) ? sanitize_text_field(wp_unslash($_POST['date_filter'])) : 'all';
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if ($date_filter !== 'all') {
            $date_query = array();
            switch ($date_filter) {
                case 'today':
                    $date_query = array('after' => 'today', 'inclusive' => true);
                    break;
                case 'week':
                    $date_query = array('after' => '1 week ago', 'inclusive' => true);
                    break;
                case 'month':
                    $date_query = array('after' => '1 month ago', 'inclusive' => true);
                    break;
                case 'year':
                    $date_query = array('after' => '1 year ago', 'inclusive' => true);
                    break;
            }
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
        }
        
        $query = new WP_Query($args);
        
        wp_send_json_success(array(
            'total' => $query->found_posts
        ));
    }
    
    /**
     * AJAX: Удаление тегов
     */
    public function ajax_remove_tags() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $remove_types = isset($_POST['remove_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['remove_types'])) : array();
        $date_filter = isset($_POST['date_filter']) ? sanitize_text_field(wp_unslash($_POST['date_filter'])) : 'all';
        $batch_size = 20;
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids'
        );
        
        if ($date_filter !== 'all') {
            $date_query = array();
            switch ($date_filter) {
                case 'today':
                    $date_query = array('after' => 'today', 'inclusive' => true);
                    break;
                case 'week':
                    $date_query = array('after' => '1 week ago', 'inclusive' => true);
                    break;
                case 'month':
                    $date_query = array('after' => '1 month ago', 'inclusive' => true);
                    break;
                case 'year':
                    $date_query = array('after' => '1 year ago', 'inclusive' => true);
                    break;
            }
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
        }
        
        $query = new WP_Query($args);
        $processed = 0;
        
        foreach ($query->posts as $attachment_id) {
            if (in_array('alt', $remove_types)) {
                delete_post_meta($attachment_id, '_wp_attachment_image_alt');
            }
            
            $update_data = array('ID' => $attachment_id);
            $need_update = false;
            
            if (in_array('title', $remove_types)) {
                $update_data['post_title'] = '';
                $need_update = true;
            }
            
            if (in_array('caption', $remove_types)) {
                $update_data['post_excerpt'] = '';
                $need_update = true;
            }
            
            if (in_array('description', $remove_types)) {
                $update_data['post_content'] = '';
                $need_update = true;
            }
            
            if ($need_update) {
                wp_update_post($update_data);
            }
            
            $processed++;
        }
        
        $has_more = ($offset + $batch_size) < $query->found_posts;
        
        wp_send_json_success(array(
            'processed' => $processed,
            'has_more' => $has_more,
            'total_processed' => $offset + $processed
        ));
    }
    
    /**
     * AJAX: Экспорт настроек
     */
    public function ajax_export_settings() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        
        wp_send_json_success(array(
            'settings' => $settings
        ));
    }
    
    /**
     * AJAX: Импорт настроек
     */
    public function ajax_import_settings() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = isset($_POST['settings']) ? json_decode(wp_unslash($_POST['settings']), true) : array();
        
        if (empty($settings)) {
            wp_send_json_error(array('message' => esc_html__('Settings are empty', 'auto-image-tags')));
            return;
        }
        
        // Санитизация импортированных настроек
        $sanitized = $this->sanitize_settings($settings);
        
        update_option('autoimta_settings', $sanitized);
        
        wp_send_json_success(array(
            'message' => esc_html__('Settings successfully imported', 'auto-image-tags')
        ));
    }
    
    /**
     * AJAX: Тестовый перевод
     */
    public function ajax_test_translation() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
        
        if (empty($text)) {
            wp_send_json_error(array('message' => esc_html__('Text to translate is empty', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        $translation = $this->translate_text($text, $settings);
        
        if (is_wp_error($translation)) {
            wp_send_json_error(array('message' => $translation->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'translation' => $translation,
            'service' => $settings['translation_service']
        ));
    }

    /**
     * AJAX: Получение статистики для перевода
     */
    public function ajax_get_translation_stats() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        
        // Считаем изображения с тегами для перевода
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        $total = 0;
        
        foreach ($query->posts as $attachment_id) {
            $has_tags = false;
            
            if ($settings['translate_alt'] == '1') {
                $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                if (!empty($alt)) $has_tags = true;
            }
            
            if ($settings['translate_title'] == '1') {
                $title = get_the_title($attachment_id);
                if (!empty($title)) $has_tags = true;
            }
            
            if ($settings['translate_caption'] == '1') {
                $caption = wp_get_attachment_caption($attachment_id);
                if (!empty($caption)) $has_tags = true;
            }
            
            if ($settings['translate_description'] == '1') {
                $description = get_post_field('post_content', $attachment_id);
                if (!empty($description)) $has_tags = true;
            }
            
            if ($has_tags) {
                $total++;
            }
        }
        
        wp_send_json_success(array('total' => $total));
    }

    /**
     * AJAX: Массовый перевод (батч)
     */
    public function ajax_translate_batch() {
        check_ajax_referer('autoimta_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'auto-image-tags')));
            return;
        }
        
        $settings = get_option('autoimta_settings', array());
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 5; // Небольшие батчи для API лимитов
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        $processed = 0;
        $success = 0;
        $errors = 0;
        
        foreach ($query->posts as $attachment_id) {
            $result = $this->translate_image_tags($attachment_id, $settings);
            
            if ($result['success']) {
                $success++;
            } else {
                $errors++;
            }
            
            $processed++;
        }
        
        $has_more = ($offset + $batch_size) < $query->found_posts;
        
        wp_send_json_success(array(
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'has_more' => $has_more
        ));
    }
	
	/**
     * Сохранение лога обработки
     */
    private function save_process_log($total, $processed, $success, $skipped, $errors, $test_mode) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoimta_process_log';
        
        $result = $wpdb->insert(
    $wpdb->prefix . 'autoimta_process_log',
    array(
        'total_images' => absint($total),
        'processed' => absint($processed),
        'success' => absint($success),
        'skipped' => absint($skipped),
        'errors' => absint($errors),
        'test_mode' => $test_mode ? 1 : 0
    ),
    array('%d', '%d', '%d', '%d', '%d', '%d')
);
        
        
    }
    
    /**
     * Обработка загрузки изображения
     */
    public function handle_image_upload($attachment_id) {
        $settings = get_option('autoimta_settings');
        
        if (!isset($settings['process_on_upload']) || $settings['process_on_upload'] != '1') {
            return;
        }
        
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $this->process_single_image($attachment_id);
    }
    
    /**
     * Симуляция обработки (для предпросмотра)
     */
    private function simulate_processing($attachment_id) {
        $settings = get_option('autoimta_settings');
        
        // Получаем данные
        $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
        $post_id = wp_get_post_parent_id($attachment_id);
        $post_title = $post_id ? get_the_title($post_id) : '';
        $site_name = get_bloginfo('name');
        
        // Очистка имени файла
        $filename = $this->clean_filename($filename, $settings);
        
        $new_values = array(
            'alt' => '',
            'title' => '',
            'caption' => '',
            'description' => ''
        );
        
        // Генерируем новые значения
        if (isset($settings['alt_format']) && $settings['alt_format'] !== 'disabled') {
            $new_values['alt'] = $this->generate_tag_text($settings['alt_format'], $settings['alt_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if (isset($settings['title_format']) && $settings['title_format'] !== 'disabled') {
            $new_values['title'] = $this->generate_tag_text($settings['title_format'], $settings['title_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if (isset($settings['caption_format']) && $settings['caption_format'] !== 'disabled') {
            $new_values['caption'] = $this->generate_tag_text($settings['caption_format'], $settings['caption_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if (isset($settings['description_format']) && $settings['description_format'] !== 'disabled') {
            $new_values['description'] = $this->generate_tag_text($settings['description_format'], $settings['description_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        return $new_values;
    }
    
    /**
     * Обработка одного изображения
     */
    private function process_single_image($attachment_id) {
        $settings = get_option('autoimta_settings');
        
        // В тестовом режиме ничего не сохраняем
        if (isset($settings['test_mode']) && $settings['test_mode'] == '1') {
            return 'success';
        }
        
        // Получаем текущие значения
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $current_title = get_the_title($attachment_id);
        $current_caption = wp_get_attachment_caption($attachment_id);
        $current_description = get_post_field('post_content', $attachment_id);
        
        // Получаем данные для формирования тегов
        $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
        $post_id = wp_get_post_parent_id($attachment_id);
        $post_title = $post_id ? get_the_title($post_id) : '';
        $site_name = get_bloginfo('name');
        
        // Очистка имени файла
        $filename = $this->clean_filename($filename, $settings);
        
        $updated = false;
        
        // ALT
        if (isset($settings['alt_format']) && $settings['alt_format'] !== 'disabled') {
            if ((isset($settings['overwrite_alt']) && $settings['overwrite_alt'] == '1') || empty($current_alt)) {
                $alt_text = $this->generate_tag_text($settings['alt_format'], $settings['alt_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($alt_text)) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                    $updated = true;
                }
            }
        }
        
        // TITLE, CAPTION, DESCRIPTION
        $attachment_data = array();
        $need_update = false;
        
        // Title
        if (isset($settings['title_format']) && $settings['title_format'] !== 'disabled') {
            if ((isset($settings['overwrite_title']) && $settings['overwrite_title'] == '1') || empty($current_title)) {
                $title_text = $this->generate_tag_text($settings['title_format'], $settings['title_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($title_text)) {
                    $attachment_data['ID'] = $attachment_id;
                    $attachment_data['post_title'] = sanitize_text_field($title_text);
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Caption
        if (isset($settings['caption_format']) && $settings['caption_format'] !== 'disabled') {
            if ((isset($settings['overwrite_caption']) && $settings['overwrite_caption'] == '1') || empty($current_caption)) {
                $caption_text = $this->generate_tag_text($settings['caption_format'], $settings['caption_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($caption_text)) {
                    if (!isset($attachment_data['ID'])) {
                        $attachment_data['ID'] = $attachment_id;
                    }
                    $attachment_data['post_excerpt'] = sanitize_text_field($caption_text);
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Description
        if (isset($settings['description_format']) && $settings['description_format'] !== 'disabled') {
            if ((isset($settings['overwrite_description']) && $settings['overwrite_description'] == '1') || empty($current_description)) {
                $description_text = $this->generate_tag_text($settings['description_format'], $settings['description_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($description_text)) {
                    if (!isset($attachment_data['ID'])) {
                        $attachment_data['ID'] = $attachment_id;
                    }
                    $attachment_data['post_content'] = sanitize_textarea_field($description_text);
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Обновляем пост если нужно
        if ($need_update) {
            wp_update_post($attachment_data);
        }
        
        return $updated ? 'success' : 'skipped';
    }
    
    /**
     * Очистка имени файла
     */
private function clean_filename($filename, $settings) {
    // Удаление номеров камер (DSC_0001, IMG_20231225, etc.)
    if (isset($settings['remove_numbers']) && $settings['remove_numbers'] == '1') {
        $filename = preg_replace('/^(DSC|IMG|DCIM|PHOTO|PIC)[-_]?\d+/i', '', $filename);
        $filename = preg_replace('/^\d{8}[-_]\d{6}/', '', $filename);
    }
    
    // Удаление суффиксов размеров
    if (isset($settings['remove_size_suffix']) && $settings['remove_size_suffix'] == '1') {
        $filename = preg_replace('/-\d+x\d+$/', '', $filename);
        $filename = preg_replace('/-(scaled|thumb|thumbnail|medium|large)$/', '', $filename);
    }
    
    // Обработка CamelCase
    if (isset($settings['camelcase_split']) && $settings['camelcase_split'] == '1') {
        $filename = preg_replace('/([a-z])([A-Z])/', '$1 $2', $filename);
    }
    
    // Замена дефисов и подчеркиваний
    if (isset($settings['remove_hyphens']) && $settings['remove_hyphens'] == '1') {
        $filename = str_replace(array('-', '_'), ' ', $filename);
    }
    
    // Удаление точек
    if (isset($settings['remove_dots']) && $settings['remove_dots'] == '1') {
        $filename = str_replace('.', ' ', $filename);
    }
    
    // Удаление стоп-слов
    $stop_words = array_merge(
        array_map('trim', explode(',', isset($settings['stop_words']) ? $settings['stop_words'] : '')),
        array_map('trim', explode(',', isset($settings['custom_stop_words']) ? $settings['custom_stop_words'] : ''))
    );
    
    foreach ($stop_words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            $filename = preg_replace($pattern, '', $filename);
        }
    }
    
    // Очистка лишних пробелов
    $filename = preg_replace('/\s+/', ' ', $filename);
    $filename = trim($filename);
    
    // Капитализация
    if (isset($settings['capitalize_words']) && $settings['capitalize_words'] == '1') {
        $filename = ucwords(strtolower($filename));
    }
    
    return $filename;
}
    
    /**
     * Генерация текста тега
     */
    private function generate_tag_text($format, $custom_text, $filename, $post_title, $site_name, $post_id = 0) {
        $text = '';
        
        switch ($format) {
            case 'disabled':
                return '';
            case 'filename':
                $text = $filename;
                break;
            case 'posttitle':
                $text = $post_title ?: $filename;
                break;
            case 'sitename':
                $text = $site_name;
                break;
            case 'filename_posttitle':
                $text = $filename;
                if ($post_title) {
                    $text .= ' - ' . $post_title;
                }
                break;
            case 'filename_sitename':
                $text = $filename . ' - ' . $site_name;
                break;
            case 'custom':
                // Расширенные переменные
                $category = '';
                $tags = '';
                $author = '';
                
                if ($post_id) {
                    $categories = get_the_category($post_id);
                    if (!empty($categories)) {
                        $category = $categories[0]->name;
                    }
                    
                    $post_tags = get_the_tags($post_id);
                    if ($post_tags) {
                        $tag_names = array_map(function($tag) { return $tag->name; }, $post_tags);
                        $tags = implode(', ', $tag_names);
                    }
                    
                    $post = get_post($post_id);
                    if ($post) {
                        $author = get_the_author_meta('display_name', $post->post_author);
                    }
                }
                
                $date = wp_date(get_option('date_format'));
                $year = wp_date('Y');
                $month = wp_date('F');
                
                $text = str_replace(
                    array('{filename}', '{posttitle}', '{sitename}', '{category}', '{tags}', '{author}', '{date}', '{year}', '{month}'),
                    array($filename, $post_title, $site_name, $category, $tags, $author, $date, $year, $month),
                    $custom_text
                );
                break;
        }
        
        return trim($text);
    }
    
    /**
     * Перевод текста через выбранный API
     */
    private function translate_text($text, $settings) {
        if (empty($text)) {
            return new WP_Error('empty_text', esc_html__('Text to translate is empty', 'auto-image-tags'));
        }
        
        $service = isset($settings['translation_service']) ? $settings['translation_service'] : 'google';
        $source_lang = isset($settings['translation_source_lang']) ? $settings['translation_source_lang'] : 'en';
        $target_lang = isset($settings['translation_target_lang']) ? $settings['translation_target_lang'] : 'ru';
        
        switch ($service) {
            case 'google':
                return $this->translate_google($text, $source_lang, $target_lang, $settings);
            case 'deepl':
                return $this->translate_deepl($text, $source_lang, $target_lang, $settings);
            case 'yandex':
                return $this->translate_yandex($text, $source_lang, $target_lang, $settings);
            case 'libre':
                return $this->translate_libre($text, $source_lang, $target_lang, $settings);
            case 'mymemory':
                return $this->translate_mymemory($text, $source_lang, $target_lang, $settings);
            default:
                return new WP_Error('invalid_service', esc_html__('Unknown translation service', 'auto-image-tags'));
        }
    }

    /**
     * Google Translate API
     */
    private function translate_google($text, $source_lang, $target_lang, $settings) {
        $api_key = isset($settings['translation_google_key']) ? $settings['translation_google_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', esc_html__('Google Translate API key not specified', 'auto-image-tags'));
        }
        
        $url = 'https://translation.googleapis.com/language/translate/v2';
        
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'body' => array(
                'q' => $text,
                'source' => $source_lang,
                'target' => $target_lang,
                'key' => $api_key,
                'format' => 'text'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }
        
        if (isset($data['data']['translations'][0]['translatedText'])) {
            return sanitize_text_field($data['data']['translations'][0]['translatedText']);
        }
        
        return new WP_Error('invalid_response', esc_html__('Invalid API response', 'auto-image-tags'));
    }

    /**
     * DeepL API
     */
    private function translate_deepl($text, $source_lang, $target_lang, $settings) {
        $api_key = isset($settings['translation_deepl_key']) ? $settings['translation_deepl_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', esc_html__('DeepL API key not specified', 'auto-image-tags'));
        }
        
        // Определяем URL (бесплатный или платный API)
        $url = (strpos($api_key, ':fx') !== false) 
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
        
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'DeepL-Auth-Key ' . $api_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'text' => $text,
                'source_lang' => strtoupper($source_lang),
                'target_lang' => strtoupper($target_lang)
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['message'])) {
            return new WP_Error('api_error', sanitize_text_field($data['message']));
        }
        
        if (isset($data['translations'][0]['text'])) {
            return sanitize_text_field($data['translations'][0]['text']);
        }
        
        return new WP_Error('invalid_response', esc_html__('Invalid API response', 'auto-image-tags'));
    }

    /**
     * Яндекс.Переводчик API
     */
    private function translate_yandex($text, $source_lang, $target_lang, $settings) {
        $api_key = isset($settings['translation_yandex_key']) ? $settings['translation_yandex_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', esc_html__('Yandex Translator API key not specified', 'auto-image-tags'));
        }
        
        $url = 'https://translate.api.cloud.yandex.net/translate/v2/translate';
        
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Api-Key ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'texts' => array($text),
                'sourceLanguageCode' => $source_lang,
                'targetLanguageCode' => $target_lang
            ))
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['message'])) {
            return new WP_Error('api_error', sanitize_text_field($data['message']));
        }
        
        if (isset($data['translations'][0]['text'])) {
            return sanitize_text_field($data['translations'][0]['text']);
        }
        
        return new WP_Error('invalid_response', esc_html__('Invalid API response', 'auto-image-tags'));
    }

    /**
     * LibreTranslate API
     */
    private function translate_libre($text, $source_lang, $target_lang, $settings) {
        $api_url = isset($settings['translation_libre_url']) ? $settings['translation_libre_url'] : 'https://libretranslate.com';
        $url = trailingslashit($api_url) . 'translate';
        
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'q' => $text,
                'source' => $source_lang,
                'target' => $target_lang,
                'format' => 'text'
            ))
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', sanitize_text_field($data['error']));
        }
        
        if (isset($data['translatedText'])) {
            return sanitize_text_field($data['translatedText']);
        }
        
        return new WP_Error('invalid_response', esc_html__('Invalid API response', 'auto-image-tags'));
    }

    /**
     * MyMemory API
     */
    private function translate_mymemory($text, $source_lang, $target_lang, $settings) {
        $email = isset($settings['translation_mymemory_email']) ? $settings['translation_mymemory_email'] : '';
        
        $url = 'https://api.mymemory.translated.net/get';
        $args = array(
            'q' => $text,
            'langpair' => $source_lang . '|' . $target_lang
        );
        
        if (!empty($email)) {
            $args['de'] = $email;
        }
        
        $response = wp_remote_get(add_query_arg($args, $url), array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['responseStatus']) && $data['responseStatus'] != 200) {
            $error_msg = isset($data['responseDetails']) ? sanitize_text_field($data['responseDetails']) : esc_html__('API error', 'auto-image-tags');
            return new WP_Error('api_error', $error_msg);
        }
        
        if (isset($data['responseData']['translatedText'])) {
            return sanitize_text_field($data['responseData']['translatedText']);
        }
        
        return new WP_Error('invalid_response', esc_html__('Invalid API response', 'auto-image-tags'));
    }

    /**
     * Перевод тегов изображения
     */
    private function translate_image_tags($attachment_id, $settings) {
        $result = array('success' => false, 'errors' => array());
        
        // ALT
        if (isset($settings['translate_alt']) && $settings['translate_alt'] == '1') {
            $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($alt)) {
                $translated = $this->translate_text($alt, $settings);
                if (!is_wp_error($translated)) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($translated));
                    $result['success'] = true;
                } else {
                    $result['errors'][] = 'ALT: ' . $translated->get_error_message();
                }
            }
        }
        
        // TITLE, CAPTION, DESCRIPTION
        $attachment_data = array('ID' => $attachment_id);
        $need_update = false;
        
        if (isset($settings['translate_title']) && $settings['translate_title'] == '1') {
            $title = get_the_title($attachment_id);
            if (!empty($title)) {
                $translated = $this->translate_text($title, $settings);
                if (!is_wp_error($translated)) {
                    $attachment_data['post_title'] = sanitize_text_field($translated);
                    $need_update = true;
                    $result['success'] = true;
                } else {
                    $result['errors'][] = 'TITLE: ' . $translated->get_error_message();
                }
            }
        }
        
        if (isset($settings['translate_caption']) && $settings['translate_caption'] == '1') {
            $caption = wp_get_attachment_caption($attachment_id);
            if (!empty($caption)) {
                $translated = $this->translate_text($caption, $settings);
                if (!is_wp_error($translated)) {
                    $attachment_data['post_excerpt'] = sanitize_text_field($translated);
                    $need_update = true;
                    $result['success'] = true;
                } else {
                    $result['errors'][] = 'CAPTION: ' . $translated->get_error_message();
                }
            }
        }
        
        if (isset($settings['translate_description']) && $settings['translate_description'] == '1') {
            $description = get_post_field('post_content', $attachment_id);
            if (!empty($description)) {
                $translated = $this->translate_text($description, $settings);
                if (!is_wp_error($translated)) {
                    $attachment_data['post_content'] = sanitize_textarea_field($translated);
                    $need_update = true;
                    $result['success'] = true;
                } else {
                    $result['errors'][] = 'DESCRIPTION: ' . $translated->get_error_message();
                }
            }
        }
        
        if ($need_update) {
            wp_update_post($attachment_data);
        }
        
        return $result;
    }

    /**
     * Обработка товара WooCommerce
     */
    public function handle_woocommerce_product($product_id) {
        $settings = get_option('autoimta_settings', array());
        
        // Проверяем, включена ли интеграция
        if (!isset($settings['woocommerce_enabled']) || $settings['woocommerce_enabled'] != '1') {
            return;
        }
        
        // Проверяем, установлен ли WooCommerce
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Получаем данные товара
        $product_title = $product->get_name();
        $product_sku = $product->get_sku();
        
        // Получаем категории
        $category_names = array();
        if (isset($settings['woocommerce_use_category']) && $settings['woocommerce_use_category'] == '1') {
            $terms = get_the_terms($product_id, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $category_names[] = $term->name;
                }
            }
        }
        $product_category = !empty($category_names) ? implode(', ', $category_names) : '';
        
        // Обрабатываем главное изображение
        $thumbnail_id = $product->get_image_id();
        if ($thumbnail_id) {
            $this->process_woocommerce_image($thumbnail_id, $product_title, $product_category, $product_sku, $settings);
        }
        
        // Обрабатываем галерею
        if (isset($settings['woocommerce_process_gallery']) && $settings['woocommerce_process_gallery'] == '1') {
            $gallery_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_ids)) {
                foreach ($gallery_ids as $gallery_id) {
                    $this->process_woocommerce_image($gallery_id, $product_title, $product_category, $product_sku, $settings);
                }
            }
        }
    }

 /**
     * Обработка изображения товара WooCommerce
     */
    private function process_woocommerce_image($attachment_id, $product_title, $product_category, $product_sku, $settings) {
        // В тестовом режиме ничего не сохраняем
        if (isset($settings['test_mode']) && $settings['test_mode'] == '1') {
            return;
        }
        
        // Получаем текущие значения
        $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $current_title = get_the_title($attachment_id);
        
        // Формируем базовый текст
        $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
        $filename = $this->clean_filename($filename, $settings);
        
        // Создаём текст с учётом WooCommerce данных
        $woo_text = $this->generate_woocommerce_text($filename, $product_title, $product_category, $product_sku, $settings);
        
        $updated = false;
        
        // ALT
        if (isset($settings['alt_format']) && $settings['alt_format'] !== 'disabled') {
            if ((isset($settings['overwrite_alt']) && $settings['overwrite_alt'] == '1') || empty($current_alt)) {
                if (!empty($woo_text)) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($woo_text));
                    $updated = true;
                }
            }
        }
        
        // TITLE
        $attachment_data = array();
        $need_update = false;
        
        if (isset($settings['title_format']) && $settings['title_format'] !== 'disabled') {
            if ((isset($settings['overwrite_title']) && $settings['overwrite_title'] == '1') || empty($current_title)) {
                if (!empty($woo_text)) {
                    $attachment_data['ID'] = $attachment_id;
                    $attachment_data['post_title'] = sanitize_text_field($woo_text);
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Caption
        if (isset($settings['caption_format']) && $settings['caption_format'] !== 'disabled') {
            $current_caption = wp_get_attachment_caption($attachment_id);
            if ((isset($settings['overwrite_caption']) && $settings['overwrite_caption'] == '1') || empty($current_caption)) {
                if (!isset($attachment_data['ID'])) {
                    $attachment_data['ID'] = $attachment_id;
                }
                $attachment_data['post_excerpt'] = sanitize_text_field($woo_text);
                $need_update = true;
                $updated = true;
            }
        }
        
        // Обновляем если нужно
        if ($need_update) {
            wp_update_post($attachment_data);
        }
        
        return $updated;
    }

    /**
     * Генерация текста для WooCommerce изображений
     */
    private function generate_woocommerce_text($filename, $product_title, $product_category, $product_sku, $settings) {
        $parts = array();
        
        // Название файла (очищенное)
        if (!empty($filename)) {
            $parts[] = $filename;
        }
        
        // Название товара
        if (isset($settings['woocommerce_use_product_title']) && $settings['woocommerce_use_product_title'] == '1' && !empty($product_title)) {
            $parts[] = $product_title;
        }
        
        // Категория
        if (isset($settings['woocommerce_use_category']) && $settings['woocommerce_use_category'] == '1' && !empty($product_category)) {
            $parts[] = $product_category;
        }
        
        // SKU
        if (isset($settings['woocommerce_use_sku']) && $settings['woocommerce_use_sku'] == '1' && !empty($product_sku)) {
            $parts[] = 'SKU: ' . $product_sku;
        }
        
        return implode(' - ', array_filter($parts));
    }
    
    /**
     * Подключение стилей и скриптов для админки
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'auto-image-tags') === false) {
            return;
        }
        
        // Регистрируем и подключаем CSS
        wp_enqueue_style(
            'autoimta-admin-css',
            AUTOIMTA_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            AUTOIMTA_VERSION
        );
        
        // Регистрируем и подключаем JS
        wp_enqueue_script(
            'autoimta-admin-js',
            AUTOIMTA_PLUGIN_URL . 'assets/js/admin-main.js',
            array('jquery'),
            AUTOIMTA_VERSION,
            true
        );
        
        // Передаём данные в JS
        wp_localize_script('autoimta-admin-js', 'autoimtaData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('autoimta_ajax_nonce'),
            'strings' => array(
                'loading' => esc_html__('Loading...', 'auto-image-tags'),
                'processed' => esc_html__('Processed:', 'auto-image-tags'),
                'success' => esc_html__('Success:', 'auto-image-tags'),
                'errors' => esc_html__('Errors:', 'auto-image-tags'),
                'skipped' => esc_html__('Skipped:', 'auto-image-tags'),
                'confirm_process' => esc_html__('Are you sure you want to start processing images?', 'auto-image-tags'),
                'confirm_test' => esc_html__('Run test processing? (changes will not be saved)', 'auto-image-tags'),
                'confirm_remove' => esc_html__('Are you sure? This action cannot be undone!', 'auto-image-tags'),
                'confirm_translate' => esc_html__('Start bulk translation of all tags?', 'auto-image-tags'),
                'empty' => esc_html__('empty', 'auto-image-tags'),
                'no_changes' => esc_html__('no changes', 'auto-image-tags'),
                'all' => esc_html__('All', 'auto-image-tags'),
                'total_images' => esc_html__('Total images:', 'auto-image-tags'),
                'without_alt' => esc_html__('Without ALT:', 'auto-image-tags'),
                'without_title' => esc_html__('Without TITLE:', 'auto-image-tags'),
                'will_be_processed' => esc_html__('Will be processed:', 'auto-image-tags'),
                'no_images' => esc_html__('No images to process with selected filters.', 'auto-image-tags'),
                'completed' => esc_html__('Processing completed!', 'auto-image-tags'),
                'test_mode' => esc_html__('TEST MODE', 'auto-image-tags'),
                'test_run' => esc_html__('This was a test run. Changes were not saved.', 'auto-image-tags'),
                'successfully_processed' => esc_html__('Successfully processed:', 'auto-image-tags'),
                'removal_completed' => esc_html__('Removal completed!', 'auto-image-tags'),
                'images_processed' => esc_html__('Images processed:', 'auto-image-tags'),
                'translation_completed' => esc_html__('Translation completed!', 'auto-image-tags'),
                'successfully_translated' => esc_html__('Successfully translated:', 'auto-image-tags'),
                'removed' => esc_html__('Removed:', 'auto-image-tags'),
                'translated' => esc_html__('Translated:', 'auto-image-tags'),
                'original' => esc_html__('Original:', 'auto-image-tags'),
                'translation' => esc_html__('Translation:', 'auto-image-tags'),
                'settings_imported' => esc_html__('Settings successfully imported!', 'auto-image-tags'),
                'invalid_file' => esc_html__('Error: invalid file format', 'auto-image-tags'),
                'select_at_least_one' => esc_html__('Select at least one tag type to remove', 'auto-image-tags'),
                'enter_text' => esc_html__('Enter text to translate', 'auto-image-tags'),
                'translating' => esc_html__('Translating...', 'auto-image-tags'),
                'test_translation' => esc_html__('Test Translation', 'auto-image-tags'),
                'connection_error' => esc_html__('Connection error', 'auto-image-tags'),
                'found_images_with_tags' => esc_html__('Found images with tags:', 'auto-image-tags'),
                'found_images' => esc_html__('Found images:', 'auto-image-tags'),
                'start_processing' => esc_html__('Start Processing', 'auto-image-tags'),
                'process_again' => esc_html__('Process Again', 'auto-image-tags'),
                'remove_tags' => esc_html__('Remove Tags', 'auto-image-tags'),
                'start_translation' => esc_html__('Start Translation', 'auto-image-tags')
            )
        ));
    }
}

// Инициализация плагина
AUTOIMTA_Plugin::getInstance();