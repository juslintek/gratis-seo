<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Structured data (Schema.org JSON-LD). Emits a single @graph containing the
 * site Organization/Person, the WebSite (with SearchAction), the current
 * WebPage/Article, and a BreadcrumbList. Each block is filterable and the whole
 * graph can be short-circuited via the gratis_seo_schema_graph filter.
 */
final class Schema {

    /**
     * Output the JSON-LD graph for the current request.
     */
    public static function output(): void {
        if (!Settings::get_bool('enable_schema', true)) {
            return;
        }

        $graph = array_values(array_filter([
            self::publisher(),
            self::website(),
            self::breadcrumbs(),
            self::primary_entity(),
            self::faq_page(),
        ]));

        /**
         * Filter the full Schema.org @graph node list.
         *
         * @param array<int,array<string,mixed>> $graph Graph nodes.
         */
        $graph = (array) apply_filters('gratis_seo_schema_graph', $graph);
        if (empty($graph)) {
            return;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        echo '<script type="application/ld+json">'
            . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
            . '</script>' . "\n";
    }

    /**
     * Extract FAQPage JSON-LD from native HTML <details><summary> blocks (such
     * as core/details) in the current post content so structured data always
     * matches visible page copy without hardcoding site-specific text.
     *
     * @return array<string,mixed>|null
     */
    public static function extract_faq_from_html(string $html): ?array {
        if ('' === $html || false === stripos($html, '<details')) {
            return null;
        }

        if (!preg_match_all('/<details\b[^>]*>(?:(?!<\/details>).)*?<summary\b[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $html, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $normalize = static function (string $fragment): string {
            // Preserve word boundaries across adjacent block elements (e.g. </p><p>).
            $spaced  = preg_replace('/<\/?(?:p|div|li|br|h[1-6]|blockquote|tr|td|th)\b[^>]*>/i', ' ', $fragment) ?? $fragment;
            $plain   = wp_strip_all_tags($spaced);
            $decoded = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim(preg_replace('/\s+/u', ' ', $decoded) ?? '');
        };

        $entities = [];
        foreach ($matches as $match) {
            $question = $normalize($match[1]);
            $answer   = $normalize($match[2]);
            if ('' === $question || '' === $answer) {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        if (empty($entities)) {
            return null;
        }

        return [
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Build FAQPage schema node for the current singular post if it contains
     * FAQ accordion (<details><summary>) markup.
     *
     * @return array<string,mixed>|null
     */
    private static function faq_page(): ?array {
        if (!is_singular()) {
            return null;
        }

        $post = get_post((int) get_queried_object_id());
        if (!$post instanceof \WP_Post) {
            return null;
        }

        return self::extract_faq_from_html((string) $post->post_content);
    }

    /**
     * The publishing entity (Organization or Person) with sameAs profiles.
     *
     * @return array<string,mixed>
     */
    private static function publisher(): array {
        $type = Settings::get_string('org_type', 'Organization');
        $name = Settings::get_string('org_name');
        if ('' === $name) {
            $name = (string) get_bloginfo('name');
        }

        $node = [
            '@type' => $type,
            '@id'   => home_url('/#publisher'),
            'name'  => $name,
            'url'   => home_url('/'),
        ];

        $logo = Settings::get_string('org_logo');
        if ('' !== $logo) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo,
            ];
            if ('Organization' === $type) {
                $node['image'] = $logo;
            }
        }

        $profiles = Settings::social_profiles();
        if (!empty($profiles)) {
            $node['sameAs'] = $profiles;
        }

        return $node;
    }

    /**
     * The WebSite node with a filterable SearchAction URL template.
     *
     * @return array<string,mixed>
     */
    private static function website(): array {
        $search_url = (string) apply_filters(
            'gratis_seo_search_url_template',
            home_url('/?s={search_term_string}')
        );

        return [
            '@type'           => 'WebSite',
            '@id'             => home_url('/#website'),
            'name'            => (string) get_bloginfo('name'),
            'url'             => home_url('/'),
            'description'     => (string) get_bloginfo('description'),
            'publisher'       => ['@id' => home_url('/#publisher')],
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $search_url,
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Breadcrumb list for the current request.
     *
     * @return array<string,mixed>|null
     */
    private static function breadcrumbs(): ?array {
        if (!Settings::get_bool('enable_breadcrumbs', true)) {
            return null;
        }

        $trail = Breadcrumbs::trail();
        if (count($trail) < 2) {
            return null;
        }

        $items = [];
        foreach ($trail as $i => $crumb) {
            $entry = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => (string) $crumb['name'],
            ];
            if ('' !== $crumb['url']) {
                $entry['item'] = (string) $crumb['url'];
            }
            $items[] = $entry;
        }

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * The primary entity for the request: an Article for single posts,
     * otherwise a WebPage.
     *
     * @return array<string,mixed>|null
     */
    private static function primary_entity(): ?array {
        if (is_singular('post')) {
            return self::article();
        }

        if (is_singular()) {
            return [
                '@type'     => 'WebPage',
                '@id'       => (string) get_permalink() . '#webpage',
                'url'       => (string) get_permalink(),
                'name'      => get_the_title(),
                'isPartOf'  => ['@id' => home_url('/#website')],
            ];
        }

        if (is_front_page()) {
            return [
                '@type'    => 'WebPage',
                '@id'      => home_url('/#webpage'),
                'url'      => home_url('/'),
                'name'     => (string) get_bloginfo('name'),
                'isPartOf' => ['@id' => home_url('/#website')],
            ];
        }

        return null;
    }

    /**
     * Article node for a single blog post.
     *
     * @return array<string,mixed>
     */
    private static function article(): array {
        $permalink = (string) get_permalink();

        $node = [
            '@type'         => 'Article',
            '@id'           => $permalink . '#article',
            'headline'      => get_the_title(),
            'url'           => $permalink,
            'datePublished' => get_the_date('c'),
            'dateModified'  => get_the_modified_date('c'),
            'author'        => [
                '@type' => 'Person',
                'name'  => (string) get_the_author(),
            ],
            'publisher'     => ['@id' => home_url('/#publisher')],
            'mainEntityOfPage' => ['@id' => $permalink . '#webpage'],
        ];

        if (has_post_thumbnail()) {
            $image = get_the_post_thumbnail_url(null, 'large');
            if (is_string($image) && '' !== $image) {
                $node['image'] = $image;
            }
        }

        return $node;
    }
}
