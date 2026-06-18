<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Front-end <head> output: canonical, robots, Open Graph and Twitter Card meta
 * tags. Title-tag rewriting is handled via the document title filters. Per-post
 * overrides (MetaBox) take precedence over the global Settings defaults.
 */
final class MetaTags {

    /**
     * Filter the document title separator.
     */
    public static function title_separator(string $sep): string {
        $custom = Settings::get_string('title_separator');
        return '' !== $custom ? $custom : $sep;
    }

    /**
     * Filter the document title parts (apply per-post title + home title).
     *
     * @param array<string,string> $parts Title parts.
     * @return array<string,string>
     */
    public static function title_parts(array $parts): array {
        if (is_front_page()) {
            $home = Settings::get_string('home_title');
            if ('' !== $home) {
                return ['title' => $home];
            }
            return $parts;
        }

        if (is_singular()) {
            $custom = (string) get_post_meta((int) get_queried_object_id(), MetaBox::KEY_TITLE, true);
            if ('' !== $custom) {
                $parts['title'] = $custom;
            }
        }

        return $parts;
    }

    /**
     * Output the meta tags in <head>.
     */
    public static function output(): void {
        if (is_admin()) {
            return;
        }

        $title = wp_get_document_title();
        $desc  = self::get_description();
        $url   = self::get_canonical_url();
        $image = self::get_image();

        // Robots.
        $robots = self::get_robots();
        if ('' !== $robots) {
            echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
        }

        // Canonical.
        if ('' !== $url) {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }

        // Open Graph.
        $og_type = is_singular('post') ? 'article' : 'website';
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr((string) get_bloginfo('name')) . '">' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr(self::get_locale()) . '">' . "\n";
        if ('' !== $image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }

        if ('article' === $og_type) {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
        }

        // Twitter Card.
        echo '<meta name="twitter:card" content="' . esc_attr($image ? 'summary_large_image' : 'summary') . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
        $twitter_site = Settings::get_string('twitter_site');
        if ('' !== $twitter_site) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
        }
        if ('' !== $image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Resolve the meta description: per-post override, then excerpt/content,
     * then global home description, then the site tagline.
     */
    private static function get_description(): string {
        if (is_singular()) {
            $post_id = (int) get_queried_object_id();
            $custom  = (string) get_post_meta($post_id, MetaBox::KEY_DESC, true);
            if ('' !== $custom) {
                return $custom;
            }
            $post = get_post($post_id);
            if ($post instanceof \WP_Post) {
                if ('' !== trim((string) $post->post_excerpt)) {
                    return wp_strip_all_tags((string) $post->post_excerpt);
                }
                $content_desc = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 30);
                if ('' !== trim($content_desc)) {
                    return $content_desc;
                }
                // Empty excerpt and content (e.g. a block-built static front
                // page): fall through to the front-page / tagline fallback so
                // we never emit an empty description.
            }
        }

        if (is_front_page()) {
            $home = Settings::get_string('home_description');
            if ('' !== $home) {
                return $home;
            }
        }

        return (string) get_bloginfo('description');
    }

    /**
     * Resolve the canonical URL: per-post override, then context.
     */
    private static function get_canonical_url(): string {
        if (is_singular()) {
            $custom = (string) get_post_meta((int) get_queried_object_id(), MetaBox::KEY_CANONICAL, true);
            if ('' !== $custom) {
                return $custom;
            }
            $permalink = get_permalink();
            return is_string($permalink) ? $permalink : '';
        }

        if (is_front_page()) {
            return home_url('/');
        }

        if (is_category() || is_tag() || is_tax()) {
            $link = get_term_link(get_queried_object());
            return is_string($link) ? $link : '';
        }

        if (is_post_type_archive()) {
            $link = get_post_type_archive_link((string) get_query_var('post_type'));
            return is_string($link) ? $link : '';
        }

        return '';
    }

    /**
     * Resolve the social image: per-post override, featured image, then default.
     */
    private static function get_image(): string {
        if (is_singular()) {
            $custom = (string) get_post_meta((int) get_queried_object_id(), MetaBox::KEY_OG_IMAGE, true);
            if ('' !== $custom) {
                return $custom;
            }
            if (has_post_thumbnail()) {
                $thumb = get_the_post_thumbnail_url(null, 'large');
                if (is_string($thumb) && '' !== $thumb) {
                    return $thumb;
                }
            }
        }

        return Settings::get_string('default_og_image');
    }

    /**
     * Build the robots meta value from per-post directives.
     */
    private static function get_robots(): string {
        $directives = [];

        if (is_singular()) {
            $post_id = (int) get_queried_object_id();
            if (get_post_meta($post_id, MetaBox::KEY_NOINDEX, true)) {
                $directives[] = 'noindex';
            }
            if (get_post_meta($post_id, MetaBox::KEY_NOFOLLOW, true)) {
                $directives[] = 'nofollow';
            }
        }

        if (is_search() || is_404()) {
            $directives[] = 'noindex';
            $directives[] = 'follow';
        }

        $directives = array_values(array_unique($directives));

        /**
         * Filter the robots directives for the current request.
         *
         * @param list<string> $directives Robots directives.
         */
        $directives = (array) apply_filters('gratis_seo_robots', $directives);

        return implode(', ', array_map('sanitize_text_field', $directives));
    }

    /**
     * Open Graph locale derived from the site language.
     */
    private static function get_locale(): string {
        $locale = get_locale();
        return '' !== $locale ? $locale : 'en_US';
    }
}
