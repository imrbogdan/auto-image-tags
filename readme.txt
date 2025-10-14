=== Auto Image Tags ===
Contributors: shapovalovbogdan
Donate link: https://t.me/shapovalovbogdan
Tags: images, alt, title, seo, media, accessibility, woocommerce, translation, bulk
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 2.0.0
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically add ALT, TITLE, Caption and Description tags to images. Includes WooCommerce integration, translation support, and bulk processing tools.

== Description ==

**Auto Image Tags** is a powerful WordPress plugin that automatically generates and manages ALT, TITLE, Caption, and Description tags for your media library images. Perfect for SEO optimization, accessibility improvements, and WooCommerce stores!

= 🎯 Key Features =

* **Automatic Tag Generation** - Automatically adds tags when uploading new images
* **WooCommerce Integration** - Process product images with title, category, and SKU
* **Translation System** - Translate tags using 5 different services (Google, DeepL, Yandex, LibreTranslate, MyMemory)
* **Bulk Processing** - Process thousands of existing images with smart filters
* **Preview Changes** - See "before → after" comparison before applying
* **Advanced Filters** - Filter by date, post/page, or tag status
* **Test Mode** - Try settings without saving changes
* **Export/Import** - Backup and restore settings as JSON
* **Processing History** - Track all operations with detailed statistics
* **Multilingual Interface** - Available in English and Russian

= 📋 Tag Generation Options =

Choose from multiple formats for each tag type (ALT, TITLE, Caption, Description):

* **Filename** - Use cleaned image filename
* **Post/Page Title** - Use parent post or page title
* **Site Name** - Use your website name
* **Filename + Post Title** - Combine filename with post title
* **Filename + Site Name** - Combine filename with site name
* **Custom Template** - Create custom templates with variables:
  - {filename} - Image filename
  - {posttitle} - Post/page title
  - {sitename} - Website name
  - {category} - Post category
  - {tags} - Post tags
  - {author} - Post author
  - {date} - Current date
  - {year} - Current year
  - {month} - Current month

= 🔧 Filename Processing Options =

Transform filenames into clean, readable text:

* **Replace hyphens/underscores** with spaces
* **Remove dots** from filenames
* **Capitalize words** - Make each word start with capital letter
* **Remove camera numbers** - Remove DSC_0001, IMG_20231225, etc.
* **Split CamelCase** - Convert PhotoOfProduct → Photo Of Product
* **Remove size suffixes** - Remove -300x200, -scaled, -thumb, etc.
* **Stop words** - Remove unwanted words from filenames
* **Custom stop words** - Add your own words to remove

= 🛠️ Advanced Tools =

**Bulk Tag Removal:**
* Remove ALT, TITLE, Caption, or Description from selected images
* Filter by date range
* Irreversible action with safety confirmation

**Settings Management:**
* Export all settings to JSON file
* Import settings from backup
* Transfer settings between sites

**Translation Features:**
* **5 Translation Services:**
  - Google Translate (paid, $300 free for new users)
  - DeepL (free: 500,000 chars/month)
  - Yandex Translator (free: 1,000,000 chars/month)
  - LibreTranslate (free, open-source)
  - MyMemory (free: 5,000-10,000 chars/day)
* **Test translations** before mass processing
* **Automatic translation** on image upload
* Support for 9 languages: English, Russian, German, French, Spanish, Italian, Portuguese, Chinese, Japanese

= 🛒 WooCommerce Integration =

**Automatic Product Image Processing:**
* Process product featured images
* Process product gallery images
* Include product data in tags:
  - Product title
  - Product category
  - Product SKU

**Example:** Image tags like "Red T-Shirt - Clothing - SKU: TS001"

= 🎨 Smart Filters =

Process only the images you need:

* **Date Filters:** Today, Last week, Last month, Last year, All time
* **Post/Page Filter:** Images from specific posts or pages
* **Status Filter:** Images without ALT, without TITLE, without any tags
* **Overwrite Options:** Choose which tags to overwrite (ALT, TITLE, Caption, Description)

= 📊 Statistics & History =

* Track total processed images
* View success/error rates
* Review processing history (last 20 operations)
* Test mode indicator in logs

= 💡 Use Cases =

1. **SEO Optimization** - Improve image search rankings with proper ALT tags
2. **Accessibility** - Make your site accessible with descriptive image tags
3. **WooCommerce Stores** - Auto-tag thousands of product images
4. **Multilingual Sites** - Translate image tags to target language
5. **Media Library Cleanup** - Bulk process years of old images
6. **Content Migration** - Clean up imported images

= 🌍 Multilingual Support =

* Plugin interface available in English and Russian
* Can be translated to any language via translate.wordpress.org
* Translation system supports 9 languages

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New**
3. Search for "Auto Image Tags"
4. Click **Install Now** and then **Activate**
5. Go to **Auto Image Tags** in admin menu
6. Configure your settings and start processing!

= Manual Installation =

1. Download the plugin ZIP file
2. Upload it via **Plugins → Add New → Upload Plugin**
3. Or unzip and upload to `/wp-content/plugins/auto-image-tags/`
4. Activate the plugin through the **Plugins** menu
5. Go to **Auto Image Tags** to configure settings

= First-Time Setup =

1. Go to **Auto Image Tags → Settings**
2. Choose tag formats for ALT, TITLE, Caption, Description
3. Configure filename processing options
4. Set overwrite preferences for each tag type
5. Use **Preview** tab to test on sample images
6. Use **Process Images** tab to bulk process existing images

== Frequently Asked Questions ==

= Will existing tags be overwritten? =

You can choose individually for each tag type (ALT, TITLE, Caption, Description) whether to:
- Overwrite existing values
- Only update empty values

This gives you full control over what gets changed.

= Can I preview changes before applying them? =

Yes! Use the **Preview** tab to see a "before → after" comparison table. This shows exactly what will change without saving anything to the database.

= Does it work with WooCommerce? =

Yes! Version 2.0 includes full WooCommerce integration:
- Automatically processes product images on save/update
- Includes product title, category, and SKU in tags
- Works with product galleries
- Can be disabled if not needed

= Can I translate image tags? =

Yes! Version 2.0 includes a translation system with 5 services:
- Google Translate (paid)
- DeepL (500k free chars/month)
- Yandex (1M free chars/month)
- LibreTranslate (free, open-source)
- MyMemory (5-10k free chars/day)

You can test translations before mass processing.

= What is Test Mode? =

Test Mode runs the entire processing without saving changes to the database. Perfect for:
- Testing new settings
- Checking results before real processing
- Verifying tag formats
- Training purposes

Results are marked as "TEST MODE" in statistics.

= Can I undo changes? =

The plugin modifies your database directly. To undo changes:
- Restore from database backup (recommended to create before bulk processing)
- Use Test Mode to preview before applying
- Export settings before making changes

= How do I process existing images? =

1. Go to **Auto Image Tags → Process Images**
2. Use filters to select which images to process
3. Check the statistics to see how many images will be affected
4. Click **Start Processing**
5. Monitor progress bar
6. Review results in Statistics tab

= Can I remove tags from images? =

Yes! Version 2.0 includes a bulk tag removal tool:
1. Go to **Auto Image Tags → Tools**
2. Select which tags to remove (ALT, TITLE, Caption, Description)
3. Choose date filter if needed
4. Click **Remove Tags**

⚠️ This action is irreversible!

= How do I export/import settings? =

**Export:**
1. Go to **Auto Image Tags → Tools**
2. Click **Download Settings**
3. Save the JSON file

**Import:**
1. Go to **Auto Image Tags → Tools**
2. Click **Upload Settings**
3. Select your JSON file
4. Settings will be imported and page reloads

Perfect for transferring settings between sites!

= Does it work with large media libraries? =

Yes! The plugin uses AJAX batch processing:
- Processes images in small batches (10-20 at a time)
- Shows real-time progress
- Handles thousands of images without timeout
- Filters help you process only what you need

= Is it compatible with page builders? =

Yes! The plugin works at the media library level, so it's compatible with all page builders:
- Elementor
- Gutenberg
- WPBakery
- Divi
- Beaver Builder
- And all others!

= Does it affect site performance? =

No! Processing happens in the WordPress admin panel only. Your public site performance is not affected.

== Screenshots ==

1. **Settings Tab** - Configure tag formats and processing options
2. **Process Images Tab** - Bulk processing with filters and progress tracking
3. **Preview Tab** - See before/after comparison without saving
4. **Statistics Tab** - View processing history and metrics
5. **Tools Tab** - Export/import settings and bulk tag removal
6. **Translation Tab** - Configure translation services and mass translate
7. **WooCommerce Settings** - Integration options for product images

== Changelog ==

= 2.0.0 (2025-01-15) =

**🎉 MAJOR UPDATE - Complete Plugin Rewrite**

**NEW FEATURES:**

* **Translation System**
  - 5 translation services: Google Translate, DeepL, Yandex, LibreTranslate, MyMemory
  - Test translations before processing
  - Automatic translation on upload
  - Support for 9 languages
  - Batch translation with progress tracking

* **WooCommerce Integration**
  - Automatic product image processing
  - Product gallery support
  - Use product title, category, and SKU in tags
  - Triggered on product save/update
  - Can be disabled if not needed

* **Tools Tab**
  - Bulk tag removal (ALT, TITLE, Caption, Description)
  - Export settings to JSON
  - Import settings from JSON
  - Transfer settings between sites

* **Preview Tab**
  - Before/after comparison table
  - See changes without saving
  - Process sample images
  - Filter preview results

* **Caption and Description Support**
  - Full support for Caption (post_excerpt)
  - Full support for Description (post_content)
  - Individual settings for each tag type

* **Individual Overwrite Settings**
  - Choose separately for ALT, TITLE, Caption, Description
  - More control over what gets changed
  - Safer bulk processing

* **Advanced Filters**
  - Filter by date range (today, week, month, year)
  - Filter by post/page
  - Filter by tag status (no ALT, no TITLE, no tags)
  - Combine filters for precise selection

* **Enhanced Filename Cleanup**
  - Split CamelCase (PhotoOfProduct → Photo Of Product)
  - Remove camera numbers (DSC_0001, IMG_20231225)
  - Remove size suffixes (-300x200, -scaled, -thumb)
  - Custom stop words
  - Remove dots option

* **Test Mode**
  - Run processing without saving
  - Verify results before applying
  - Marked in statistics as "TEST MODE"

* **Statistics & History**
  - Processing history (last 20 operations)
  - Total processed images counter
  - Success/skip/error rates
  - Test mode indicator

* **Interface Improvements**
  - Language selection (English/Russian)
  - Better progress tracking
  - Responsive admin interface
  - Cleaner navigation with tabs
  - Better error messages

**IMPROVEMENTS:**

* Better code structure and organization
* Enhanced security (proper escaping, nonce verification)
* Improved performance for large media libraries
* Better AJAX error handling
* More detailed logging
* Optimized database queries
* Better memory management

**TECHNICAL:**

* WordPress 6.8 compatibility tested
* PHP 7.2+ requirement
* Proper prefixing for all functions
* Enqueued scripts/styles (no inline code)
* Full translation ready
* WPCS coding standards

**FIXED:**

* Various minor bugs from 1.x versions
* Memory issues with large libraries
* Progress tracking accuracy
* Translation domain issues
* Filter combination bugs

= 1.2.0 (2024-10-10) =

* Added Caption and Description support
* Added preview functionality with before/after table
* Individual overwrite settings for each tag type
* Processing filters (date, post, status)
* Enhanced filename cleanup (CamelCase, camera numbers)
* Stop words with custom additions
* Test mode for safe testing
* Processing history and statistics
* Language selection (Russian/English)
* Dot removal option
* Extended template variables: {category}, {tags}, {author}, {date}, {year}, {month}
* Performance improvements for large libraries
* Enhanced security with AJAX sanitization
* Bug fixes and stability improvements

= 1.1.0 (2024-08-15) =

* Full English language support
* Tabbed interface for better navigation
* Enhanced image statistics
* Overwrite existing tags option
* Improved processing logic
* Better UI design

= 1.0.0 (2024-06-01) =

* Initial release
* Automatic ALT and TITLE tag generation
* Bulk image processing
* Flexible tag formation settings
* AJAX processing for large libraries
* Custom text with variables support

== Upgrade Notice ==

= 2.0.0 =
🎉 MAJOR UPDATE! Translation system, WooCommerce integration, tools tab, preview mode, and many more features. Highly recommended upgrade! Please backup your database before upgrading.

= 1.2.0 =
Major update with Caption/Description support, preview mode, filters, test mode and many improvements. Recommended for all users.

= 1.1.0 =
Added English language support and improved interface. Recommended for all users.

== Support ==

Need help or have suggestions?

* **Telegram:** [@shapovalovbogdan](https://t.me/shapovalovbogdan)
* **GitHub:** [Auto Image Tags Repository](https://github.com/imrbogdan/auto-image-tags)

**This plugin is FREE with ALL features included - no Pro version!**

== Privacy Policy ==

This plugin does NOT collect or store any personal data.

**When using translation features:**
- Text is sent to third-party translation services (Google, DeepL, Yandex, LibreTranslate, MyMemory)
- Each service has its own privacy policy
- No data is stored by this plugin
- API keys are stored locally in your WordPress database

== System Requirements ==

* WordPress 5.0 or higher
* PHP 7.2 or higher
* Database write permissions
* JavaScript enabled (for admin panel)
* cURL enabled (for translation features)

== Credits ==

Developed by **Shapovalov Bogdan**

Special thanks to the WordPress community for feedback and suggestions!

== Translations ==

* English - included
* Russian - included
* More languages coming soon via translate.wordpress.org

Want to translate this plugin? Visit [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/auto-image-tags/)