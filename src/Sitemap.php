<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Lightweight XML sitemap served at /sitemap.xml. Lists the homepage, published
 * pages, posts and public custom post types. Gated by the enable_sitemap
 * setting.
 */
final class Sitemap {

    public static function register(): void {
        add_action('template_redirect', [__CLASS__, 'serve']);
    }

    public static function serve(): void {
        if (!Settings::get_bool('enable_sitemap', true)) {
            return;
        }

        $request = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';
        $path = (string) wp_parse_url($request, PHP_URL_PATH);
        if ('/sitemap.xml' !== $path) {
            return;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage.
        $out .= self::url(home_url('/'), gmdate('c'), 'daily', '1.0');

        // Pages.
        $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1]);
        foreach ($pages as $page) {
            if ((int) get_option('page_on_front') === $page->ID || get_post_meta($page->ID, MetaBox::KEY_NOINDEX, true)) {
                continue;
            }
            $out .= self::url((string) get_permalink($page), (string) get_the_modified_date('c', $page), 'weekly', '0.8');
        }

        // Posts.
        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded sitemap cap.
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1000]);
        foreach ($posts as $post) {
            if (get_post_meta($post->ID, MetaBox::KEY_NOINDEX, true)) {
                continue;
            }
            $out .= self::url((string) get_permalink($post), (string) get_the_modified_date('c', $post), 'monthly', '0.6');
        }

        // Public custom post types.
        $cpts = get_post_types(['public' => true, '_builtin' => false]);
        foreach ($cpts as $cpt) {
            // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded sitemap cap.
            $items = get_posts(['post_type' => $cpt, 'post_status' => 'publish', 'numberposts' => 500]);
            foreach ($items as $item) {
                if (get_post_meta($item->ID, MetaBox::KEY_NOINDEX, true)) {
                    continue;
                }
                $out .= self::url((string) get_permalink($item), (string) get_the_modified_date('c', $item), 'monthly', '0.5');
            }
        }

        $out .= '</urlset>';

        // Whole document is built from esc_url() locs and literal markup.
        echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function url(string $loc, string $lastmod, string $freq, string $priority): string {
        return '<url><loc>' . esc_url($loc) . '</loc><lastmod>' . esc_html($lastmod)
            . '</lastmod><changefreq>' . esc_html($freq) . '</changefreq><priority>'
            . esc_html($priority) . '</priority></url>' . "\n";
    }
}
