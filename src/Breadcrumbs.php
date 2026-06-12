<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Breadcrumb trail generator. Produces an ordered list of crumbs for the
 * current request, used both to render the [gratis_breadcrumbs] shortcode and
 * to feed the BreadcrumbList structured data in Schema.
 */
final class Breadcrumbs {

    /**
     * Register the shortcode.
     */
    public static function register(): void {
        add_shortcode('gratis_breadcrumbs', [__CLASS__, 'shortcode']);
    }

    /**
     * Build the ordered crumb trail for the current request.
     *
     * Each crumb is an array of {name, url}. The final crumb (current page)
     * has an empty url.
     *
     * @return list<array{name:string,url:string}>
     */
    public static function trail(): array {
        $crumbs = [
            ['name' => __('Home', 'gratis-seo'), 'url' => home_url('/')],
        ];

        if (is_front_page()) {
            return $crumbs;
        }

        if (is_singular()) {
            $post = get_post();
            if ($post instanceof \WP_Post) {
                if ('post' === $post->post_type) {
                    $cats = get_the_category($post->ID);
                    if (!empty($cats)) {
                        $cat  = $cats[0];
                        $link = get_category_link($cat->term_id);
                        $crumbs[] = ['name' => $cat->name, 'url' => is_string($link) ? $link : ''];
                    }
                } else {
                    foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
                        $crumbs[] = [
                            'name' => get_the_title($ancestor_id),
                            'url'  => (string) get_permalink($ancestor_id),
                        ];
                    }
                }
                $crumbs[] = ['name' => get_the_title($post), 'url' => ''];
            }
            return $crumbs;
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $crumbs[] = ['name' => $term->name, 'url' => ''];
            }
            return $crumbs;
        }

        if (is_author()) {
            $crumbs[] = ['name' => (string) get_the_author(), 'url' => ''];
            return $crumbs;
        }

        if (is_search()) {
            /* translators: %s: search query. */
            $crumbs[] = ['name' => sprintf(__('Search results for "%s"', 'gratis-seo'), get_search_query()), 'url' => ''];
            return $crumbs;
        }

        if (is_404()) {
            $crumbs[] = ['name' => __('Page not found', 'gratis-seo'), 'url' => ''];
            return $crumbs;
        }

        if (is_post_type_archive()) {
            $crumbs[] = ['name' => (string) post_type_archive_title('', false), 'url' => ''];
            return $crumbs;
        }

        if (is_year()) {
            $crumbs[] = ['name' => (string) get_the_date('Y'), 'url' => ''];
        } elseif (is_month()) {
            $crumbs[] = ['name' => (string) get_the_date('F Y'), 'url' => ''];
        } elseif (is_day()) {
            $crumbs[] = ['name' => (string) get_the_date(), 'url' => ''];
        } elseif (is_home()) {
            $page_id = (int) get_option('page_for_posts');
            if ($page_id > 0) {
                $crumbs[] = ['name' => get_the_title($page_id), 'url' => ''];
            }
        }

        return $crumbs;
    }

    /**
     * Render the breadcrumb trail as accessible navigation markup.
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     */
    public static function shortcode($atts = []): string {
        if (!Settings::get_bool('enable_breadcrumbs', true)) {
            return '';
        }

        $atts = shortcode_atts(['separator' => '/'], (array) $atts, 'gratis_breadcrumbs');
        $crumbs = self::trail();
        if (count($crumbs) < 2) {
            return '';
        }

        $separator = '<span class="gseo-breadcrumbs__sep" aria-hidden="true"> ' . esc_html((string) $atts['separator']) . ' </span>';

        $items = [];
        $last  = count($crumbs) - 1;
        foreach ($crumbs as $i => $crumb) {
            $name = esc_html((string) $crumb['name']);
            if ($i === $last || '' === $crumb['url']) {
                $items[] = '<span class="gseo-breadcrumbs__current" aria-current="page">' . $name . '</span>';
            } else {
                $items[] = '<a class="gseo-breadcrumbs__link" href="' . esc_url((string) $crumb['url']) . '">' . $name . '</a>';
            }
        }

        return '<nav class="gseo-breadcrumbs" aria-label="' . esc_attr__('Breadcrumb', 'gratis-seo') . '">'
            . implode($separator, $items)
            . '</nav>';
    }
}
