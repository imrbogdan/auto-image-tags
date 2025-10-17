=== Auto Image Tags ===
Contributors: mrbogdan
Tags: seo, images, alt, media, woocommerce
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 2.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically add ALT, TITLE, Caption and Description to images. WooCommerce integration and translation support.

== Description ==

Auto Image Tags automatically generates ALT tags, TITLE attributes, captions and descriptions for all images in your WordPress media library. The plugin processes filenames intelligently, removing camera markers, splitting CamelCase, and applying customizable rules to create SEO-friendly image tags.

**Key Features:**

* Automatic generation of ALT, TITLE, Caption and Description
* Preview changes before applying
* Individual overwrite settings for each attribute
* Bulk processing with advanced filters
* Test mode for safe testing
* Complete processing history and statistics
* WooCommerce product image integration
* Multi-language translation system (5 services)
* Export/Import settings

**Perfect for:**

* SEO optimization
* Accessibility compliance
* WooCommerce stores
* Multi-language sites
* Bulk image management

== External Services ==

This plugin can optionally connect to external translation services to translate image tags. Translation features are completely optional and disabled by default.

**Google Translate API**
* Used for: Translating image tags (ALT, TITLE, Caption, Description)
* Data sent: Text to translate, source/target language
* When: Only when user enables translation and provides API key
* Service: https://cloud.google.com/translate
* Terms: https://cloud.google.com/terms
* Privacy: https://policies.google.com/privacy

**DeepL API**
* Used for: Translating image tags
* Data sent: Text to translate, source/target language
* When: Only when user enables translation and provides API key
* Service: https://www.deepl.com/pro-api
* Terms: https://www.deepl.com/terms
* Privacy: https://www.deepl.com/privacy

**Yandex Translator API**
* Used for: Translating image tags
* Data sent: Text to translate, source/target language
* When: Only when user enables translation and provides API key
* Service: https://cloud.yandex.com/services/translate
* Terms: https://yandex.com/legal/cloud_terms_of_use
* Privacy: https://yandex.com/legal/confidential

**LibreTranslate**
* Used for: Translating image tags
* Data sent: Text to translate, source/target language
* When: Only when user enables translation and provides server URL
* Service: https://libretranslate.com
* Terms: Open source, self-hosted option available
* Privacy: https://github.com/LibreTranslate/LibreTranslate

**MyMemory Translation API**
* Used for: Translating image tags
* Data sent: Text to translate, source/target language, optional email
* When: Only when user enables translation
* Service: https://mymemory.translated.net
* Terms: https://mymemory.translated.net/doc/terms.php
* Privacy: https://mymemory.translated.net/doc/privacy.php

**Important Notes:**
* All translation services are OPTIONAL and disabled by default
* No data is sent unless user actively enables translation and configures API keys
* Users must review and accept terms of service for each translation provider they choose to use
* Translation can be completely avoided by not enabling this feature

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/auto-image-tags/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Auto Image Tags settings to configure

== Frequently Asked Questions ==

= Does this work with WooCommerce? =

Yes! Version 2.0 includes full WooCommerce integration for product images.

= Will it overwrite my existing tags? =

Only if you enable the "Overwrite existing" option for each tag type. You have individual control.

= Can I preview changes before applying? =

Yes! Use the Preview tab to see exactly what will change.

= Does it support translation? =

Yes! Supports 5 translation services (Google, DeepL, Yandex, LibreTranslate, MyMemory). All are optional.

= Is there a Pro version? =

No! All features are completely free, no Pro version exists.

== Screenshots ==

1. Main settings page with tag formats
2. Process images with advanced filters
3. Preview changes before applying
4. Statistics and processing history

== Changelog ==

= 2.0.0 =
* NEW: Translation system with 5 API services (optional)
* NEW: WooCommerce product image integration
* NEW: Preview tab with before/after comparison
* NEW: Tools tab (bulk delete, export/import settings)
* NEW: Caption and Description support
* NEW: Individual overwrite settings for each tag
* NEW: Advanced processing filters
* NEW: Test mode for safe testing
* NEW: Complete statistics and history
* NEW: Full Russian translation
* IMPROVED: Enhanced filename cleanup
* IMPROVED: Extended template variables
* IMPROVED: Better UI/UX with 7 tabs
* IMPROVED: AJAX batch processing

= 1.2.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update with translation system, WooCommerce integration, and many new features. Backup recommended before updating.