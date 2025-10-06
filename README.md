# Auto Image Tags

Automatic ALT and TITLE tags generation for images in WordPress media library.

## Description

Auto Image Tags is a powerful WordPress plugin that automatically generates ALT, TITLE, Caption, and Description tags for your images. It helps improve your website's SEO and accessibility by ensuring all images have proper metadata.

## Features

- ✅ Automatic generation of ALT, TITLE, Caption, and Description tags
- ✅ Preview changes before applying them
- ✅ Individual overwrite settings for each attribute
- ✅ Bulk processing with advanced filters (by date, post, status)
- ✅ Advanced filename cleanup options
- ✅ Stop words removal
- ✅ Test mode for safe checking
- ✅ Processing history and statistics
- ✅ Multi-language support (English, Russian)
- ✅ Extended variables in custom templates

## Installation

1. Download the plugin files
2. Upload the `auto-image-tags` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to 'Auto Image Tags' in the admin menu to configure settings

## Usage

### Basic Setup

1. Navigate to **Auto Image Tags → Settings**
2. Choose tag formats (filename, post title, site name, or custom)
3. Configure filename cleanup options
4. Set stop words to remove from filenames
5. Save settings

### Bulk Processing

1. Go to **Auto Image Tags → Process Images**
2. Use filters to select specific images
3. Review statistics
4. Click "Start Processing"

### Preview Changes

1. Navigate to **Auto Image Tags → Preview**
2. Select number of images to preview
3. Click "Load Preview" to see before/after comparison

## Tag Format Options

- **Filename**: Use cleaned image filename
- **Post Title**: Use parent post/page title
- **Site Name**: Use WordPress site name
- **Filename + Post Title**: Combine filename with post title
- **Filename + Site Name**: Combine filename with site name
- **Custom**: Use custom template with variables

## Custom Template Variables

Available variables for custom templates:
- `{filename}` - Image filename
- `{posttitle}` - Parent post title
- `{sitename}` - Site name
- `{category}` - Post category
- `{tags}` - Post tags
- `{author}` - Post author
- `{date}` - Current date
- `{year}` - Current year
- `{month}` - Current month

Example: `{filename} - {sitename}`

## Filename Cleanup Options

- Replace hyphens and underscores with spaces
- Remove dots from filenames
- Capitalize first letter of each word
- Remove camera numbers (DSC_0001, IMG_20231225)
- Split CamelCase (PhotoOfProduct → Photo Of Product)
- Remove size suffixes (-300x200, -scaled, -thumb)

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Changelog

### 1.2.0
- Added preview functionality with before/after comparison
- Added Caption and Description support
- Individual overwrite settings for each attribute
- Advanced filters for bulk processing
- Improved filename cleanup (CamelCase, camera numbers)
- Stop words with custom additions
- Test mode
- Processing history and statistics
- Multi-language support
- Extended template variables

### 1.0.0
- Initial release

## Support

If you have questions or found a bug, please contact via [Telegram](https://t.me/shapovalovbogdan)

## License

This plugin is free software distributed under the GPL v3 license.

## Author

Shapovalov Bogdan - [Telegram](https://t.me/shapovalovbogdan)