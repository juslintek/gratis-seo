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
            . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>' . "\n";
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
     * The WebSite node with a search action.
     *
     * @return array<string,mixed>
     */
    private static function website(): array {
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
                    'urlTemplate' => home_url('/?s={search_term_string}'),
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
