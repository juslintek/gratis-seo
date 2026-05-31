<?php
/**
 * Plugin Name: Gratis SEO
 * Description: Comprehensive SEO toolkit. Meta tags, sitemaps, schema, breadcrumbs, redirects. Replaces Yoast/RankMath ($49-99/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 * Text Domain: gratis-seo
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';

add_action('wp_head', [\Gratis\SEO\MetaTags::class, 'output'], 1);
add_action('init', [\Gratis\SEO\Sitemap::class, 'register']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-seo', \Gratis\SEO\CLI\SEOCommand::class);
    }
});
