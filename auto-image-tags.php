<?php
/**
 * Plugin Name: Auto Image Tags
 * Plugin URI: https://github.com/imrbogdan/auto-image-tags
 * Description: Автоматическое добавление alt и title тегов к изображениям в медиатеке WordPress
 * Version: 1.2.0
 * Author: Shapovalov Bogdan
 * Author URI: https://t.me/shapovalovbogdan
 * License: GPL v3 or later
 * Text Domain: auto-image-tags
 * Domain Path: /languages
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Определение констант плагина
define('AIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIT_PLUGIN_VERSION', '1.2.0');

/**
 * Основной класс плагина
 */
class AutoImageTags {
    
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
        
        add_action('wp_ajax_ait_process_existing_images', array($this, 'ajax_process_existing_images'));
        add_action('wp_ajax_ait_get_images_count', array($this, 'ajax_get_images_count'));
        add_action('wp_ajax_ait_preview_changes', array($this, 'ajax_preview_changes'));
        add_action('wp_ajax_ait_get_filter_options', array($this, 'ajax_get_filter_options'));
    }
	
    /**
     * Активация плагина
     */
    public function activate() {
        // Установка дефолтных настроек
        if (!get_option('ait_settings')) {
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
                'plugin_language' => 'auto'
            );
            update_option('ait_settings', $default_settings);
        }
        
        // Создание таблицы для логов
        $this->create_log_table();
    }
    
    /**
     * Создание таблицы для логов обработки
     */
    private function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ait_process_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            process_date datetime DEFAULT CURRENT_TIMESTAMP,
            total_images int(11) DEFAULT 0,
            processed int(11) DEFAULT 0,
            success int(11) DEFAULT 0,
            skipped int(11) DEFAULT 0,
            errors int(11) DEFAULT 0,
            test_mode tinyint(1) DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
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
    $settings = get_option('ait_settings', array());
    $language = isset($settings['plugin_language']) ? $settings['plugin_language'] : 'auto';
    
    if ($language !== 'auto' && !empty($language)) {
        add_filter('determine_locale', function($locale) use ($language) {
            if (isset($_GET['page']) && strpos($_GET['page'], 'auto-image-tags') !== false) {
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
            __('Auto Image Tags', 'auto-image-tags'),
            __('Auto Image Tags', 'auto-image-tags'),
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
        register_setting('ait_settings_group', 'ait_settings', array($this, 'sanitize_settings'));
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
        
        return $sanitized;
    }
    
    /**
     * Главная страница админки с табами
     */
    public function admin_page() {
        $settings = get_option('ait_settings');
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Табы навигации -->
            <nav class="nav-tab-wrapper">
                <a href="?page=auto-image-tags&tab=settings" 
                   class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Настройки', 'auto-image-tags'); ?>
                </a>
                <a href="?page=auto-image-tags&tab=process" 
                   class="nav-tab <?php echo $active_tab == 'process' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Обработка изображений', 'auto-image-tags'); ?>
                </a>
                <a href="?page=auto-image-tags&tab=preview" 
                   class="nav-tab <?php echo $active_tab == 'preview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Предпросмотр', 'auto-image-tags'); ?>
                </a>
                <a href="?page=auto-image-tags&tab=stats" 
                   class="nav-tab <?php echo $active_tab == 'stats' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Статистика', 'auto-image-tags'); ?>
                </a>
                <a href="?page=auto-image-tags&tab=about" 
                   class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('О плагине', 'auto-image-tags'); ?>
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
        ?>
        <form method="post" action="options.php" class="ait-settings-form">
            <?php settings_fields('ait_settings_group'); ?>
            
            <!-- Языковые настройки -->
            <h2><?php _e('Языковые настройки', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="plugin_language"><?php _e('Язык плагина', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="ait_settings[plugin_language]" id="plugin_language">
                            <option value="auto" <?php selected($settings['plugin_language'], 'auto'); ?>>
                                <?php _e('Автоматически (язык сайта)', 'auto-image-tags'); ?>
                            </option>
                            <option value="ru_RU" <?php selected($settings['plugin_language'], 'ru_RU'); ?>>
                                Русский
                            </option>
                            <option value="en_US" <?php selected($settings['plugin_language'], 'en_US'); ?>>
                                English
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Выберите язык интерфейса плагина', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Настройки форматов -->
            <h2><?php _e('Форматы тегов', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <!-- ALT -->
                <tr>
                    <th scope="row">
                        <label for="alt_format"><?php _e('Формат ALT тега', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="ait_settings[alt_format]" id="alt_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['alt_format'], 'disabled'); ?>>
                                <?php _e('Не изменять', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['alt_format'], 'filename'); ?>>
                                <?php _e('Название файла', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['alt_format'], 'posttitle'); ?>>
                                <?php _e('Заголовок записи/страницы', 'auto-image-tags'); ?>
                            </option>
                            <option value="sitename" <?php selected($settings['alt_format'], 'sitename'); ?>>
                                <?php _e('Название сайта', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_posttitle" <?php selected($settings['alt_format'], 'filename_posttitle'); ?>>
                                <?php _e('Название файла + Заголовок записи', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_sitename" <?php selected($settings['alt_format'], 'filename_sitename'); ?>>
                                <?php _e('Название файла + Название сайта', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['alt_format'], 'custom'); ?>>
                                <?php _e('Произвольный текст', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="ait-checkbox-inline">
                            <input type="checkbox" name="ait_settings[overwrite_alt]" value="1" 
                                   <?php checked($settings['overwrite_alt'], '1'); ?>>
                            <?php _e('Перезаписывать существующие', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr id="alt_custom_row" style="<?php echo ($settings['alt_format'] != 'custom') ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="alt_custom_text"><?php _e('Произвольный ALT текст', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ait_settings[alt_custom_text]" id="alt_custom_text" 
                               value="<?php echo esc_attr($settings['alt_custom_text']); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Используйте {filename}, {posttitle}, {sitename}, {category}, {tags}, {author}, {date}, {year}, {month} как переменные', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
                
                <!-- TITLE -->
                <tr>
                    <th scope="row">
                        <label for="title_format"><?php _e('Формат TITLE тега', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="ait_settings[title_format]" id="title_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['title_format'], 'disabled'); ?>>
                                <?php _e('Не изменять', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['title_format'], 'filename'); ?>>
                                <?php _e('Название файла', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['title_format'], 'posttitle'); ?>>
                                <?php _e('Заголовок записи/страницы', 'auto-image-tags'); ?>
                            </option>
                            <option value="sitename" <?php selected($settings['title_format'], 'sitename'); ?>>
                                <?php _e('Название сайта', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_posttitle" <?php selected($settings['title_format'], 'filename_posttitle'); ?>>
                                <?php _e('Название файла + Заголовок записи', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename_sitename" <?php selected($settings['title_format'], 'filename_sitename'); ?>>
                                <?php _e('Название файла + Название сайта', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['title_format'], 'custom'); ?>>
                                <?php _e('Произвольный текст', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="ait-checkbox-inline">
                            <input type="checkbox" name="ait_settings[overwrite_title]" value="1" 
                                   <?php checked($settings['overwrite_title'], '1'); ?>>
                            <?php _e('Перезаписывать существующие', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr id="title_custom_row" style="<?php echo ($settings['title_format'] != 'custom') ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="title_custom_text"><?php _e('Произвольный TITLE текст', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ait_settings[title_custom_text]" id="title_custom_text" 
                               value="<?php echo esc_attr($settings['title_custom_text']); ?>" class="regular-text">
                    </td>
                </tr>
                
                <!-- CAPTION -->
                <tr>
                    <th scope="row">
                        <label for="caption_format"><?php _e('Формат Caption (подпись)', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="ait_settings[caption_format]" id="caption_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['caption_format'], 'disabled'); ?>>
                                <?php _e('Не изменять', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['caption_format'], 'filename'); ?>>
                                <?php _e('Название файла', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['caption_format'], 'posttitle'); ?>>
                                <?php _e('Заголовок записи/страницы', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['caption_format'], 'custom'); ?>>
                                <?php _e('Произвольный текст', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="ait-checkbox-inline">
                            <input type="checkbox" name="ait_settings[overwrite_caption]" value="1" 
                                   <?php checked($settings['overwrite_caption'], '1'); ?>>
                            <?php _e('Перезаписывать существующие', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
                
                <!-- DESCRIPTION -->
                <tr>
                    <th scope="row">
                        <label for="description_format"><?php _e('Формат Description (описание)', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <select name="ait_settings[description_format]" id="description_format" class="regular-text">
                            <option value="disabled" <?php selected($settings['description_format'], 'disabled'); ?>>
                                <?php _e('Не изменять', 'auto-image-tags'); ?>
                            </option>
                            <option value="filename" <?php selected($settings['description_format'], 'filename'); ?>>
                                <?php _e('Название файла', 'auto-image-tags'); ?>
                            </option>
                            <option value="posttitle" <?php selected($settings['description_format'], 'posttitle'); ?>>
                                <?php _e('Заголовок записи/страницы', 'auto-image-tags'); ?>
                            </option>
                            <option value="custom" <?php selected($settings['description_format'], 'custom'); ?>>
                                <?php _e('Произвольный текст', 'auto-image-tags'); ?>
                            </option>
                        </select>
                        <label class="ait-checkbox-inline">
                            <input type="checkbox" name="ait_settings[overwrite_description]" value="1" 
                                   <?php checked($settings['overwrite_description'], '1'); ?>>
                            <?php _e('Перезаписывать существующие', 'auto-image-tags'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <!-- Обработка имен файлов -->
            <h2><?php _e('Обработка имен файлов', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Опции очистки', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="ait_settings[remove_hyphens]" value="1" 
                                       <?php checked($settings['remove_hyphens'], '1'); ?>>
                                <?php _e('Заменять дефисы и подчеркивания на пробелы', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="ait_settings[remove_dots]" value="1" 
                                       <?php checked($settings['remove_dots'], '1'); ?>>
                                <?php _e('Удалять точки из имен файлов', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="ait_settings[capitalize_words]" value="1" 
                                       <?php checked($settings['capitalize_words'], '1'); ?>>
                                <?php _e('Делать первую букву каждого слова заглавной', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="ait_settings[remove_numbers]" value="1" 
                                       <?php checked($settings['remove_numbers'], '1'); ?>>
                                <?php _e('Удалять номера камер (DSC_0001, IMG_20231225)', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="ait_settings[camelcase_split]" value="1" 
                                       <?php checked($settings['camelcase_split'], '1'); ?>>
                                <?php _e('Разделять CamelCase (PhotoOfProduct → Photo Of Product)', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="ait_settings[remove_size_suffix]" value="1" 
                                       <?php checked($settings['remove_size_suffix'], '1'); ?>>
                                <?php _e('Удалять суффиксы размеров (-300x200, -scaled, -thumb)', 'auto-image-tags'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stop_words"><?php _e('Стоп-слова', 'auto-image-tags'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ait_settings[stop_words]" id="stop_words" 
                               value="<?php echo esc_attr($settings['stop_words']); ?>" class="large-text">
                        <p class="description">
                            <?php _e('Слова для удаления из имен файлов, разделенные запятыми', 'auto-image-tags'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Дополнительные настройки -->
            <h2><?php _e('Дополнительные настройки', 'auto-image-tags'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Режим работы', 'auto-image-tags'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="ait_settings[process_on_upload]" value="1" 
                                       <?php checked($settings['process_on_upload'], '1'); ?>>
                                <?php _e('Обрабатывать изображения при загрузке', 'auto-image-tags'); ?>
                            </label>
                            <br>
                            <label class="ait-important-option">
                                <input type="checkbox" name="ait_settings[test_mode]" value="1" 
                                       <?php checked($settings['test_mode'], '1'); ?>>
                                <strong><?php _e('Тестовый режим (без сохранения изменений)', 'auto-image-tags'); ?></strong>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Показ/скрытие полей для произвольного текста
            $('#alt_format').on('change', function() {
                $('#alt_custom_row').toggle($(this).val() === 'custom');
            });
            
            $('#title_format').on('change', function() {
                $('#title_custom_row').toggle($(this).val() === 'custom');
            });
            
            $('#caption_format').on('change', function() {
                $('#caption_custom_row').toggle($(this).val() === 'custom');
            });
            
            $('#description_format').on('change', function() {
                $('#description_custom_row').toggle($(this).val() === 'custom');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Вкладка предпросмотра
     */
    private function render_preview_tab() {
        ?>
        <div class="ait-preview-box">
            <h2><?php _e('Предпросмотр изменений', 'auto-image-tags'); ?></h2>
            <p><?php _e('Здесь вы можете посмотреть, как будут выглядеть теги после обработки, без внесения реальных изменений.', 'auto-image-tags'); ?></p>
            
            <div class="ait-preview-filters">
                <h3><?php _e('Фильтры', 'auto-image-tags'); ?></h3>
                <div class="filter-row">
                    <label for="preview_limit"><?php _e('Количество изображений:', 'auto-image-tags'); ?></label>
                    <select id="preview_limit">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    
                    <label for="preview_filter"><?php _e('Показать:', 'auto-image-tags'); ?></label>
                    <select id="preview_filter">
                        <option value="all"><?php _e('Все изображения', 'auto-image-tags'); ?></option>
                        <option value="no_alt"><?php _e('Без ALT тега', 'auto-image-tags'); ?></option>
                        <option value="no_title"><?php _e('Без TITLE тега', 'auto-image-tags'); ?></option>
                        <option value="no_tags"><?php _e('Без тегов', 'auto-image-tags'); ?></option>
                    </select>
                    
                    <button id="preview_load_btn" class="button button-primary">
                        <?php _e('Загрузить предпросмотр', 'auto-image-tags'); ?>
                    </button>
                </div>
            </div>
            
            <div id="preview_results" style="display:none;">
                <h3><?php _e('Результаты предпросмотра', 'auto-image-tags'); ?></h3>
                <div id="preview_content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#preview_load_btn').on('click', function() {
                var limit = $('#preview_limit').val();
                var filter = $('#preview_filter').val();
                
                $('#preview_content').html('<p><?php _e('Загрузка...', 'auto-image-tags'); ?></p>');
                $('#preview_results').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ait_preview_changes',
                        limit: limit,
                        filter: filter,
                        nonce: '<?php echo esc_attr(wp_create_nonce('ait_ajax_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr>';
                            html += '<th><?php _e('Изображение', 'auto-image-tags'); ?></th>';
                            html += '<th><?php _e('Текущие значения', 'auto-image-tags'); ?></th>';
                            html += '<th><?php _e('Новые значения', 'auto-image-tags'); ?></th>';
                            html += '</tr></thead><tbody>';
                            
                            response.data.images.forEach(function(img) {
                                html += '<tr>';
                                html += '<td><img src="' + img.thumb + '" style="max-width:100px;"><br>' + img.filename + '</td>';
                                html += '<td>';
                                html += '<strong>ALT:</strong> ' + (img.current.alt || '<em><?php _e('пусто', 'auto-image-tags'); ?></em>') + '<br>';
                                html += '<strong>TITLE:</strong> ' + (img.current.title || '<em><?php _e('пусто', 'auto-image-tags'); ?></em>') + '<br>';
                                html += '<strong>CAPTION:</strong> ' + (img.current.caption || '<em><?php _e('пусто', 'auto-image-tags'); ?></em>') + '<br>';
                                html += '<strong>DESCRIPTION:</strong> ' + (img.current.description || '<em><?php _e('пусто', 'auto-image-tags'); ?></em>');
                                html += '</td>';
                                html += '<td>';
                                html += '<strong>ALT:</strong> <span class="' + (img.new.alt !== img.current.alt ? 'changed' : '') + '">' + (img.new.alt || '<em><?php _e('без изменений', 'auto-image-tags'); ?></em>') + '</span><br>';
                                html += '<strong>TITLE:</strong> <span class="' + (img.new.title !== img.current.title ? 'changed' : '') + '">' + (img.new.title || '<em><?php _e('без изменений', 'auto-image-tags'); ?></em>') + '</span><br>';
                                html += '<strong>CAPTION:</strong> <span class="' + (img.new.caption !== img.current.caption ? 'changed' : '') + '">' + (img.new.caption || '<em><?php _e('без изменений', 'auto-image-tags'); ?></em>') + '</span><br>';
                                html += '<strong>DESCRIPTION:</strong> <span class="' + (img.new.description !== img.current.description ? 'changed' : '') + '">' + (img.new.description || '<em><?php _e('без изменений', 'auto-image-tags'); ?></em>') + '</span>';
                                html += '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                            $('#preview_content').html(html);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Вкладка обработки изображений
     */
    private function render_process_tab() {
        ?>
        <div class="ait-process-box">
            <h2><?php _e('Обработка существующих изображений', 'auto-image-tags'); ?></h2>
            
            <?php 
            $settings = get_option('ait_settings');
            if ($settings['test_mode'] == '1') {
                ?>
                <div class="ait-notice ait-notice-info">
                    <p><strong><?php _e('Тестовый режим активен!', 'auto-image-tags'); ?></strong> 
                    <?php _e('Изменения не будут сохранены. Отключите тестовый режим в настройках для реальной обработки.', 'auto-image-tags'); ?></p>
                </div>
                <?php
            }
            ?>
            
            <div class="ait-notice ait-notice-warning">
                <p><strong><?php _e('Внимание!', 'auto-image-tags'); ?></strong> 
                <?php _e('Перед массовой обработкой рекомендуется создать резервную копию базы данных.', 'auto-image-tags'); ?></p>
            </div>
            
            <div class="ait-filters">
    <h3><?php _e('Фильтры обработки', 'auto-image-tags'); ?></h3>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="date_filter"><?php _e('Период загрузки:', 'auto-image-tags'); ?></label>
            </th>
            <td>
                <select id="date_filter" class="regular-text">
                    <option value="all"><?php _e('Все время', 'auto-image-tags'); ?></option>
                    <option value="today"><?php _e('Сегодня', 'auto-image-tags'); ?></option>
                    <option value="week"><?php _e('Последняя неделя', 'auto-image-tags'); ?></option>
                    <option value="month"><?php _e('Последний месяц', 'auto-image-tags'); ?></option>
                    <option value="year"><?php _e('Последний год', 'auto-image-tags'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="status_filter"><?php _e('Статус:', 'auto-image-tags'); ?></label>
            </th>
            <td>
                <select id="status_filter" class="regular-text">
                    <option value="all"><?php _e('Все изображения', 'auto-image-tags'); ?></option>
                    <option value="no_alt"><?php _e('Без ALT', 'auto-image-tags'); ?></option>
                    <option value="no_title"><?php _e('Без TITLE', 'auto-image-tags'); ?></option>
                    <option value="no_tags"><?php _e('Без тегов', 'auto-image-tags'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="post_filter"><?php _e('Пост/Страница:', 'auto-image-tags'); ?></label>
            </th>
            <td>
                <select id="post_filter" class="regular-text">
                    <option value="all"><?php _e('Все', 'auto-image-tags'); ?></option>
                    <option value="loading..."><?php _e('Загрузка...', 'auto-image-tags'); ?></option>
                </select>
            </td>
        </tr>
    </table>
</div>
            
            <div id="ait-stats">
                <p><?php _e('Загрузка статистики...', 'auto-image-tags'); ?></p>
            </div>
            
            <button id="ait-process-btn" class="button button-primary button-hero" disabled>
                <?php _e('Начать обработку', 'auto-image-tags'); ?>
            </button>
            
            <div id="ait-progress" style="display:none;">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0%;"></div>
                    </div>
                    <div class="progress-text">0%</div>
                </div>
                <p id="ait-status-text"></p>
            </div>
            
            <div id="ait-results" style="display:none;">
                <h3><?php _e('Результаты обработки:', 'auto-image-tags'); ?></h3>
                <div id="ait-results-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var totalImages = 0;
            var processedImages = 0;
            var successCount = 0;
            var errorCount = 0;
            var skippedCount = 0;
            
            // Загрузка списка постов для фильтра
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ait_get_filter_options',
                    nonce: '<?php echo esc_attr(wp_create_nonce('ait_ajax_nonce')); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="all"><?php _e('Все', 'auto-image-tags'); ?></option>';
                        response.data.posts.forEach(function(post) {
                            options += '<option value="' + post.ID + '">' + post.post_title + '</option>';
                        });
                        $('#post_filter').html(options);
                    }
                }
            });
            
            // Получение количества изображений с фильтрами
            function getImagesCount() {
                var filters = {
                    date: $('#date_filter').val(),
                    status: $('#status_filter').val(),
                    post: $('#post_filter').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ait_get_images_count',
                        filters: filters,
                        nonce: '<?php echo esc_attr(wp_create_nonce('ait_ajax_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            totalImages = response.data.total;
                            var withoutAlt = response.data.without_alt;
                            var withoutTitle = response.data.without_title;
                            var needsProcessing = response.data.needs_processing;
                            
                            $('#ait-stats').html(
                                '<div class="ait-stats-grid">' +
                                '<div class="ait-stat-item">' +
                                    '<span class="ait-stat-label"><?php _e('Всего изображений:', 'auto-image-tags'); ?></span>' +
                                    '<span class="ait-stat-value">' + totalImages + '</span>' +
                                '</div>' +
                                '<div class="ait-stat-item">' +
                                    '<span class="ait-stat-label"><?php _e('Без ALT тега:', 'auto-image-tags'); ?></span>' +
                                    '<span class="ait-stat-value">' + withoutAlt + '</span>' +
                                '</div>' +
                                '<div class="ait-stat-item">' +
                                    '<span class="ait-stat-label"><?php _e('Без TITLE тега:', 'auto-image-tags'); ?></span>' +
                                    '<span class="ait-stat-value">' + withoutTitle + '</span>' +
                                '</div>' +
                                '<div class="ait-stat-item">' +
                                    '<span class="ait-stat-label"><?php _e('Будет обработано:', 'auto-image-tags'); ?></span>' +
                                    '<span class="ait-stat-value">' + needsProcessing + '</span>' +
                                '</div>' +
                                '</div>'
                            );
                            
                            if (totalImages > 0) {
                                $('#ait-process-btn').prop('disabled', false);
                            } else {
                                $('#ait-stats').append('<p class="notice notice-info"><?php _e('Нет изображений для обработки с выбранными фильтрами.', 'auto-image-tags'); ?></p>');
                            }
                        }
                    }
                });
            }
            
            // Обновление статистики при изменении фильтров
            $('#date_filter, #status_filter, #post_filter').on('change', function() {
                getImagesCount();
            });
            
            // Обработка изображений
            function processImages(offset) {
                var filters = {
                    date: $('#date_filter').val(),
                    status: $('#status_filter').val(),
                    post: $('#post_filter').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ait_process_existing_images',
                        offset: offset,
                        filters: filters,
                        nonce: '<?php echo esc_attr(wp_create_nonce('ait_ajax_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            processedImages += response.data.processed;
                            successCount += response.data.success;
                            errorCount += response.data.errors;
                            skippedCount += response.data.skipped;
                            
                            var progress = Math.round((processedImages / totalImages) * 100);
                            $('.progress-bar-fill').css('width', progress + '%');
                            $('.progress-text').text(progress + '%');
                            $('#ait-status-text').html(
                                '<?php _e('Обработано:', 'auto-image-tags'); ?> ' + processedImages + ' / ' + totalImages +
                                ' <span class="ait-status-details">(' +
                                '<?php _e('Успешно:', 'auto-image-tags'); ?> ' + successCount +
                                ', <?php _e('Пропущено:', 'auto-image-tags'); ?> ' + skippedCount +
                                ', <?php _e('Ошибок:', 'auto-image-tags'); ?> ' + errorCount +
                                ')</span>'
                            );
                            
                            if (response.data.has_more) {
                                processImages(offset + response.data.processed);
                            } else {
                                // Завершение обработки
                                $('#ait-progress').hide();
                                var modeText = response.data.test_mode ? ' (<?php _e('ТЕСТОВЫЙ РЕЖИМ', 'auto-image-tags'); ?>)' : '';
                                $('#ait-results-content').html(
                                    '<div class="notice notice-success">' +
                                    '<p><strong><?php _e('Обработка завершена!', 'auto-image-tags'); ?></strong>' + modeText + '</p>' +
                                    '<ul>' +
                                    '<li><?php _e('Успешно обработано:', 'auto-image-tags'); ?> ' + successCount + '</li>' +
                                    '<li><?php _e('Пропущено:', 'auto-image-tags'); ?> ' + skippedCount + '</li>' +
                                    '<li><?php _e('Ошибок:', 'auto-image-tags'); ?> ' + errorCount + '</li>' +
                                    '</ul>' +
                                    (response.data.test_mode ? '<p><?php _e('Это был тестовый прогон. Изменения не были сохранены.', 'auto-image-tags'); ?></p>' : '') +
                                    '</div>'
                                );
                                $('#ait-results').show();
                                $('#ait-process-btn').text('<?php _e('Обработать заново', 'auto-image-tags'); ?>').prop('disabled', false);
                                getImagesCount();
                            }
                        }
                    }
                });
            }
            
            // Начало обработки
            $('#ait-process-btn').on('click', function() {
                var confirmMsg = '<?php _e('Вы уверены, что хотите начать обработку изображений?', 'auto-image-tags'); ?>';
                <?php if ($settings['test_mode'] == '1'): ?>
                confirmMsg = '<?php _e('Запустить тестовую обработку? (изменения не будут сохранены)', 'auto-image-tags'); ?>';
                <?php endif; ?>
                
                if (confirm(confirmMsg)) {
                    $(this).prop('disabled', true);
                    $('#ait-progress').show();
                    $('#ait-results').hide();
                    processedImages = 0;
                    successCount = 0;
                    errorCount = 0;
                    skippedCount = 0;
                    processImages(0);
                }
            });
            
            // Инициализация
            getImagesCount();
        });
        </script>
        <?php
    }
    
    /**
     * Вкладка статистики
     */
    private function render_stats_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ait_process_log';
        
        // Получаем последние записи из лога
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY process_date DESC LIMIT 20");
        
        // Общая статистика
        $total_processed = $wpdb->get_var("SELECT SUM(processed) FROM $table_name");
        $total_success = $wpdb->get_var("SELECT SUM(success) FROM $table_name");
        ?>
        <div class="ait-stats-box">
            <h2><?php _e('Статистика обработки', 'auto-image-tags'); ?></h2>
            
            <div class="ait-stats-summary">
                <h3><?php _e('Общая статистика', 'auto-image-tags'); ?></h3>
                <div class="ait-stats-grid">
                    <div class="ait-stat-item">
                        <span class="ait-stat-label"><?php _e('Всего обработано:', 'auto-image-tags'); ?></span>
                        <span class="ait-stat-value"><?php echo intval($total_processed); ?></span>
                    </div>
                    <div class="ait-stat-item">
                        <span class="ait-stat-label"><?php _e('Успешно обновлено:', 'auto-image-tags'); ?></span>
                        <span class="ait-stat-value"><?php echo intval($total_success); ?></span>
                    </div>
                </div>
            </div>
            
            <h3><?php _e('История обработок', 'auto-image-tags'); ?></h3>
            <?php if ($logs): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Дата', 'auto-image-tags'); ?></th>
                        <th><?php _e('Всего', 'auto-image-tags'); ?></th>
                        <th><?php _e('Обработано', 'auto-image-tags'); ?></th>
                        <th><?php _e('Успешно', 'auto-image-tags'); ?></th>
                        <th><?php _e('Пропущено', 'auto-image-tags'); ?></th>
                        <th><?php _e('Ошибок', 'auto-image-tags'); ?></th>
                        <th><?php _e('Режим', 'auto-image-tags'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(wp_date(...)); ?></td>
						<td><?php echo absint($log->total_images); ?></td>
                        <td><?php echo $log->processed; ?></td>
                        <td><?php echo $log->success; ?></td>
                        <td><?php echo $log->skipped; ?></td>
                        <td><?php echo $log->errors; ?></td>
                        <td><?php echo esc_html($log->test_mode ? __('Тест', 'auto-image-tags') : __('Обычный', 'auto-image-tags')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php _e('История обработок пуста.', 'auto-image-tags'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

	/**
     * Вкладка "О плагине"
     */
    private function render_about_tab() {
        ?>
        <div class="ait-about-box">
            <h2><?php _e('О плагине Auto Image Tags', 'auto-image-tags'); ?></h2>
            
            <div class="ait-info-section">
                <h3><?php _e('Информация о плагине', 'auto-image-tags'); ?></h3>
                <table class="ait-info-table">
                    <tr>
                        <td><strong><?php _e('Версия:', 'auto-image-tags'); ?></strong></td>
                        <td><?php echo esc_html(AIT_PLUGIN_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Автор:', 'auto-image-tags'); ?></strong></td>
                        <td>Shapovalov Bogdan</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Telegram:', 'auto-image-tags'); ?></strong></td>
                        <td><a href="https://t.me/shapovalovbogdan" target="_blank">@shapovalovbogdan</a></td>
                    </tr>
                </table>
            </div>
            
            <div class="ait-info-section">
    <h3><?php _e('Возможности плагина', 'auto-image-tags'); ?></h3>
    <ul>
        <li>✅ <?php _e('Автоматическое добавление ALT, TITLE, Caption и Description', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Предпросмотр изменений перед применением', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Индивидуальная настройка перезаписи для каждого атрибута', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Фильтры для массовой обработки (по датам, постам, статусу)', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Продвинутая очистка имен файлов', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Стоп-слова для удаления лишних слов', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Тестовый режим для безопасной проверки', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('История обработок и статистика', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Поддержка нескольких языков', 'auto-image-tags'); ?></li>
        <li>✅ <?php _e('Расширенные переменные в шаблонах', 'auto-image-tags'); ?></li>
    </ul>
</div>

<div class="ait-info-section">
    <h3><?php _e('Что нового в версии 1.2.0', 'auto-image-tags'); ?></h3>
    <ul>
        <li>🆕 <?php _e('Предпросмотр изменений с таблицей "было → станет"', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Поддержка Caption и Description', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Индивидуальные настройки перезаписи для каждого атрибута', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Фильтры для обработки (по датам, постам, статусу)', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Улучшенная очистка имен (CamelCase, удаление номеров камер)', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Стоп-слова с возможностью добавления своих', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Тестовый режим', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Статистика и история обработок', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Выбор языка плагина', 'auto-image-tags'); ?></li>
        <li>🆕 <?php _e('Удаление точек из имен файлов', 'auto-image-tags'); ?></li>
    </ul>
</div>
            
            <div class="ait-info-section">
                <h3><?php _e('Поддержка и обратная связь', 'auto-image-tags'); ?></h3>
                <p><?php _e('Если у вас есть вопросы, предложения или вы нашли ошибку, свяжитесь со мной через Telegram.', 'auto-image-tags'); ?></p>
                <p><?php _e('Плагин распространяется бесплатно для помощи сообществу WordPress.', 'auto-image-tags'); ?></p>
                <p><strong><?php _e('Все функции доступны бесплатно, без Pro версии!', 'auto-image-tags'); ?></strong></p>
            </div>
        </div>
        <?php
    }
    
/**
 * AJAX: Получение количества изображений
 */
public function ajax_get_images_count() {
    check_ajax_referer('ait_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Недостаточно прав', 'auto-image-tags')));
        return;
    }
    
    $settings = get_option('ait_settings', array());
    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? array_map('sanitize_text_field', $_POST['filters']) : array();
    
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
            'message' => __('Слишком много изображений для подсчета. Используйте фильтры.', 'auto-image-tags')
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
    check_ajax_referer('ait_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Недостаточно прав', 'auto-image-tags')));
        return;
    }
    
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
    
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
            'thumb' => $thumb ? $thumb[0] : '',
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
    check_ajax_referer('ait_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Недостаточно прав', 'auto-image-tags')));
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
    check_ajax_referer('ait_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Недостаточно прав', 'auto-image-tags')));
        return;
    }
    
    $settings = get_option('ait_settings', array());
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 10;
    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? array_map('sanitize_text_field', $_POST['filters']) : array();
    
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
 * Сохранение лога обработки
 */
private function save_process_log($total, $processed, $success, $skipped, $errors, $test_mode) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ait_process_log';
    
    $result = $wpdb->insert(
        $table_name,
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
    
    if ($result === false) {
        error_log('AIT Plugin: Failed to save log - ' . $wpdb->last_error);
    }
}
    
    /**
     * Обработка загрузки изображения
     */
    public function handle_image_upload($attachment_id) {
        $settings = get_option('ait_settings');
        
        if ($settings['process_on_upload'] != '1') {
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
        $settings = get_option('ait_settings');
        
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
        if ($settings['alt_format'] !== 'disabled') {
            $new_values['alt'] = $this->generate_tag_text($settings['alt_format'], $settings['alt_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if ($settings['title_format'] !== 'disabled') {
            $new_values['title'] = $this->generate_tag_text($settings['title_format'], $settings['title_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if ($settings['caption_format'] !== 'disabled') {
            $new_values['caption'] = $this->generate_tag_text($settings['caption_format'], $settings['caption_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        if ($settings['description_format'] !== 'disabled') {
            $new_values['description'] = $this->generate_tag_text($settings['description_format'], $settings['description_custom_text'], $filename, $post_title, $site_name, $post_id);
        }
        
        return $new_values;
    }
    
    /**
     * Обработка одного изображения
     */
    private function process_single_image($attachment_id) {
        $settings = get_option('ait_settings');
        
        // В тестовом режиме ничего не сохраняем
        if ($settings['test_mode'] == '1') {
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
        if ($settings['alt_format'] !== 'disabled') {
            if ($settings['overwrite_alt'] == '1' || empty($current_alt)) {
                $alt_text = $this->generate_tag_text($settings['alt_format'], $settings['alt_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($alt_text)) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                    $updated = true;
                }
            }
        }
        
        // TITLE, CAPTION, DESCRIPTION
        $attachment_data = array();
        $need_update = false;
        
        // Title
        if ($settings['title_format'] !== 'disabled') {
            if ($settings['overwrite_title'] == '1' || empty($current_title)) {
                $title_text = $this->generate_tag_text($settings['title_format'], $settings['title_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($title_text)) {
                    $attachment_data['ID'] = $attachment_id;
                    $attachment_data['post_title'] = $title_text;
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Caption
        if ($settings['caption_format'] !== 'disabled') {
            if ($settings['overwrite_caption'] == '1' || empty($current_caption)) {
                $caption_text = $this->generate_tag_text($settings['caption_format'], $settings['caption_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($caption_text)) {
                    if (!isset($attachment_data['ID'])) {
                        $attachment_data['ID'] = $attachment_id;
                    }
                    $attachment_data['post_excerpt'] = $caption_text;
                    $need_update = true;
                    $updated = true;
                }
            }
        }
        
        // Description
        if ($settings['description_format'] !== 'disabled') {
            if ($settings['overwrite_description'] == '1' || empty($current_description)) {
                $description_text = $this->generate_tag_text($settings['description_format'], $settings['description_custom_text'], $filename, $post_title, $site_name, $post_id);
                if (!empty($description_text)) {
                    if (!isset($attachment_data['ID'])) {
                        $attachment_data['ID'] = $attachment_id;
                    }
                    $attachment_data['post_content'] = $description_text;
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
            if (@preg_match($pattern, '') !== false) {
                $filename = preg_replace($pattern, '', $filename);
            }
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
     * Подключение стилей и скриптов для админки
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'auto-image-tags') === false) {
            return;
        }
?>
        <style>
            /* Основные стили */
            .ait-settings-form {
                margin-top: 20px;
            }
            
            .ait-info-box,
            .ait-process-box,
            .ait-preview-box,
            .ait-stats-box,
            .ait-about-box {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
                max-width: 1200px;
            }
            
            /* Табы */
            .tab-content {
                margin-top: 20px;
            }
            
            /* Фильтры */
            .ait-filters,
            .ait-preview-filters {
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                border-radius: 4px;
                padding: 15px;
                margin: 20px 0;
            }
            
            .filter-group,
            .filter-row {
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .filter-group label,
            .filter-row label {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            /* Чекбоксы в строку */
            .ait-checkbox-inline {
                margin-left: 15px;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            /* Статистика */
            .ait-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            
            .ait-stat-item {
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                border-radius: 4px;
                padding: 15px;
                text-align: center;
            }
            
            .ait-stat-label {
                display: block;
                color: #666;
                font-size: 13px;
                margin-bottom: 5px;
            }
            
            .ait-stat-value {
                display: block;
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
            }
            
            /* Прогресс бар */
            .progress-bar-wrapper {
                margin: 20px 0;
            }
            
            .progress-bar {
                width: 100%;
                height: 30px;
                background: #f1f1f1;
                border-radius: 15px;
                overflow: hidden;
                position: relative;
                box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .progress-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #2271b1, #135e96);
                border-radius: 15px;
                transition: width 0.3s ease;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .progress-text {
                text-align: center;
                margin-top: 10px;
                font-weight: bold;
                font-size: 16px;
            }
            
            #ait-status-text {
                text-align: center;
                margin-top: 10px;
                color: #666;
            }
            
            .ait-status-details {
                font-size: 12px;
                color: #999;
            }
            
            /* Уведомления */
            .ait-notice {
                padding: 12px;
                margin: 15px 0;
                border-left: 4px solid;
                background: #fff;
            }
            
            .ait-notice-warning {
                border-left-color: #ffb900;
                background: #fff8e5;
            }
            
            .ait-notice-success {
                border-left-color: #00a32a;
                background: #edfaef;
            }
            
            .ait-notice-info {
                border-left-color: #00a0d2;
                background: #e5f5fa;
            }
            
            /* Кнопки */
            .button-hero {
                font-size: 16px !important;
                line-height: 1.4 !important;
                padding: 8px 16px !important;
                height: auto !important;
            }
            
            #ait-process-btn {
                margin-top: 20px;
            }
            
            /* Результаты */
            #ait-results {
                margin-top: 30px;
            }
            
            #ait-results .notice {
                padding: 15px;
            }
            
            #ait-results ul {
                margin: 10px 0 0 20px;
                list-style: disc;
            }
            
            /* Предпросмотр */
            #preview_results table {
                margin-top: 20px;
            }
            
            #preview_results .changed {
                background: #fff3cd;
                padding: 2px 5px;
                border-radius: 3px;
            }
            
            /* О плагине */
            .ait-info-section {
                margin-bottom: 30px;
            }
            
            .ait-info-section h3 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #23282d;
                border-bottom: 1px solid #e2e4e7;
                padding-bottom: 10px;
            }
            
            .ait-info-table {
                width: 100%;
                max-width: 500px;
            }
            
            .ait-info-table td {
                padding: 8px 0;
            }
            
            .ait-info-table td:first-child {
                width: 150px;
            }
            
            .ait-info-section ul {
                margin-left: 20px;
                line-height: 1.8;
            }
            
            /* Важная опция */
            .ait-important-option {
                background: #fff8e5;
                padding: 5px 10px;
                border-radius: 3px;
                display: inline-block;
                margin-top: 5px;
            }
            
            /* Адаптивность */
            @media screen and (max-width: 782px) {
                .ait-stats-grid {
                    grid-template-columns: 1fr;
                }
                
                .filter-group,
                .filter-row {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .ait-info-box,
                .ait-process-box,
                .ait-preview-box,
                .ait-stats-box,
                .ait-about-box {
                    padding: 15px;
                }
            }
</style>
        <?php
    }
}

// Инициализация плагина
AutoImageTags::getInstance();