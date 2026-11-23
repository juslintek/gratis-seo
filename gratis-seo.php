<?php
/**
 * Plugin Name: Gratis SEO
 * Plugin URI: https://github.com/juslintek/gratis-seo
 * Description: Comprehensive SEO toolkit. Meta tags, Open Graph, JSON-LD structured data, breadcrumbs and an XML sitemap.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-seo
 */

defined('ABSPATH') || exit;

define('GRATIS_SEO_VERSION', '1.0.0');
define('GRATIS_SEO_FILE', __FILE__);
define('GRATIS_SEO_DIR', plugin_dir_path(__FILE__));
define('GRATIS_SEO_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// Front-end <head> output.
add_action('wp_head', [\Gratis\SEO\MetaTags::class, 'output'], 1);
add_action('wp_head', [\Gratis\SEO\Schema::class, 'output'], 2);

// Gratis SEO is the single source of truth for the canonical URL, so drop
// WordPress core's rel_canonical (otherwise singular/front pages emit two
// <link rel="canonical">). Core registers it in default-filters.php at load,
// before plugins, so this removal takes effect.
remove_action('wp_head', 'rel_canonical');

// Document title rewriting & robots integration.
add_filter('document_title_separator', [\Gratis\SEO\MetaTags::class, 'title_separator']);
add_filter('document_title_parts', [\Gratis\SEO\MetaTags::class, 'title_parts']);
add_filter('wp_robots', [\Gratis\SEO\MetaTags::class, 'filter_robots'], 20);

// Sitemap + breadcrumbs.
add_action('init', [\Gratis\SEO\Sitemap::class, 'register']);
add_action('init', [\Gratis\SEO\Breadcrumbs::class, 'register']);

// Post meta + meta box.
add_action('init', [\Gratis\SEO\MetaBox::class, 'register_meta']);
add_action('add_meta_boxes', [\Gratis\SEO\MetaBox::class, 'register']);
add_action('save_post', [\Gratis\SEO\MetaBox::class, 'save']);

// Settings page.
add_action('admin_menu', [\Gratis\SEO\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\SEO\Settings::class, 'register_settings']);

// WP-CLI.
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-seo', \Gratis\SEO\CLI\SEOCommand::class);
    }
});
