<?php declare(strict_types=1);
namespace Gratis\SEO;

final class Sitemap
{
    public static function register(): void
    {
        add_action('template_redirect', [__CLASS__, 'serve']);
    }

    public static function serve(): void
    {
        if ($_SERVER['REQUEST_URI'] !== '/sitemap.xml') return;

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        echo self::url(home_url('/'), gmdate('c'), 'daily', '1.0');

        // Pages
        $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1]);
        foreach ($pages as $page) {
            if ((int) get_option('page_on_front') === $page->ID) continue;
            echo self::url(get_permalink($page), get_the_modified_date('c', $page), 'weekly', '0.8');
        }

        // Posts
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1000]);
        foreach ($posts as $post) {
            echo self::url(get_permalink($post), get_the_modified_date('c', $post), 'monthly', '0.6');
        }

        // Custom post types
        $cpts = get_post_types(['public' => true, '_builtin' => false]);
        foreach ($cpts as $cpt) {
            $items = get_posts(['post_type' => $cpt, 'post_status' => 'publish', 'numberposts' => 500]);
            foreach ($items as $item) {
                echo self::url(get_permalink($item), get_the_modified_date('c', $item), 'monthly', '0.5');
            }
        }

        echo '</urlset>';
        exit;
    }

    private static function url(string $loc, string $lastmod, string $freq, string $priority): string
    {
        return "<url><loc>" . esc_url($loc) . "</loc><lastmod>{$lastmod}</lastmod><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
    }
}
