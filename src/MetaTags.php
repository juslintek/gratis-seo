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

        // Meta description.
        if ('' !== $desc) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }

        // Robots (skip standalone tag when WP core wp_robots is active; handled via filter_robots()).
        if (!function_exists('has_filter') || !has_filter('wp_head', 'wp_robots')) {
            $robots = self::get_robots();
            if ('' !== $robots) {
                echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
            }
        }

        // Canonical.
        if ('' !== $url) {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }

        // Open Graph (valid OGP built-in types: article for single posts, website otherwise).
        $og_type = is_singular('post') ? 'article' : 'website';
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ('' !== $desc) {
            echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        }
        if ('' !== $url) {
            echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        }
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
        if ('' !== $desc) {
            echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
        }
        $twitter_site = Settings::get_string('twitter_site');
        if ('' !== $twitter_site) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
        }
        if ('' !== $image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Resolve the meta description: per-post override, then front-page
     * home_description setting, then excerpt/content, then the site tagline.
     */
    private static function get_description(): string {
        if (is_singular()) {
            $post_id = (int) get_queried_object_id();
            $custom  = (string) get_post_meta($post_id, MetaBox::KEY_DESC, true);
            if ('' !== $custom) {
                return $custom;
            }
        }

        // Static front pages satisfy both is_front_page() and is_singular(), so
        // honor the explicit home_description setting before scraping block HTML.
        if (is_front_page()) {
            $home = Settings::get_string('home_description');
            if ('' !== $home) {
                return $home;
            }
        }

        if (is_singular()) {
            $post = get_post((int) get_queried_object_id());
            if ($post instanceof \WP_Post) {
                if ('' !== trim((string) $post->post_excerpt)) {
                    return wp_strip_all_tags((string) $post->post_excerpt);
                }
                $content_desc = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 30);
                if ('' !== trim($content_desc)) {
                    return $content_desc;
                }
                // Empty excerpt and content (e.g. a block-built static front
                // page): fall through to the site tagline so we never emit an
                // empty description.
            }
        }

        // Blog page (page_for_posts): read its per-post SEO description.
        if (is_home()) {
            $page_id = (int) get_option('page_for_posts');
            if ($page_id > 0) {
                $custom = (string) get_post_meta($page_id, MetaBox::KEY_DESC, true);
                if ('' !== $custom) {
                    return $custom;
                }
            }
        }

        // CPT archives: look up a page whose slug matches the archive rewrite
        // slug and read its per-post SEO description override.
        if (is_post_type_archive()) {
            $post_type = (string) get_query_var('post_type');
            $obj       = get_post_type_object($post_type);
            if ($obj instanceof \WP_Post_Type) {
                $rewrite      = $obj->rewrite;
                $archive_slug = is_array($rewrite) && isset($rewrite['slug'])
                    ? (string) $rewrite['slug']
                    : $post_type;
                $pages = get_posts([
                    'post_type'              => 'page',
                    'name'                   => $archive_slug,
                    'numberposts'            => 1,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]);
                if (!empty($pages) && $pages[0] instanceof \WP_Post) {
                    $custom = (string) get_post_meta($pages[0]->ID, MetaBox::KEY_DESC, true);
                    if ('' !== $custom) {
                        return $custom;
                    }
                }
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

        if (is_home()) {
            $page_for_posts = (int) get_option('page_for_posts');
            if ($page_for_posts > 0) {
                $link = get_permalink($page_for_posts);
                return is_string($link) ? $link : home_url('/blog/');
            }
            return home_url('/');
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link = get_term_link($term);
                return is_string($link) ? $link : '';
            }
            return '';
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
     * Merge Gratis SEO robots directives into WordPress core's wp_robots array
     * so the document emits a single canonical <meta name="robots"> tag.
     *
     * @param array<string,bool|string> $robots Existing WordPress robots directives.
     * @return array<string,bool|string>
     */
    public static function filter_robots(array $robots): array {
        foreach (self::get_robots_directives() as $directive) {
            if ('noindex' === $directive) {
                unset($robots['index']);
                $robots['noindex'] = true;
            } elseif ('nofollow' === $directive) {
                unset($robots['follow']);
                $robots['nofollow'] = true;
            } elseif ('index' === $directive) {
                unset($robots['noindex']);
                $robots['index'] = true;
            } elseif ('follow' === $directive) {
                unset($robots['nofollow']);
                $robots['follow'] = true;
            } elseif ('' !== $directive) {
                $robots[$directive] = true;
            }
        }
        return $robots;
    }

    /**
     * Collect sanitized robots directives for the current request.
     *
     * @return list<string>
     */
    private static function get_robots_directives(): array {
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

        return array_values(array_filter(array_map('sanitize_text_field', $directives), static fn(string $d): bool => '' !== $d));
    }

    /**
     * Build the robots meta value from per-post directives.
     */
    private static function get_robots(): string {
        return implode(', ', self::get_robots_directives());
    }

    /**
     * Open Graph locale derived from the site language.
     */
    private static function get_locale(): string {
        $locale = get_locale();
        return '' !== $locale ? $locale : 'en_US';
    }
}
