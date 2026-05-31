<?php declare(strict_types=1);
namespace Gratis\SEO;

final class MetaTags
{
    public static function output(): void
    {
        if (is_admin()) return;

        $title = wp_get_document_title();
        $desc = self::getDescription();
        $url = self::getCanonicalUrl();
        $image = self::getImage();

        // Canonical
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";

        // Open Graph
        echo '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        if ($image) echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";

        // Twitter Card
        echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
        if ($image) echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";

        // Schema.org JSON-LD
        self::schemaOutput();
    }

    private static function getDescription(): string
    {
        if (is_singular()) {
            $custom = get_post_meta(get_the_ID(), '_gratis_seo_desc', true);
            if ($custom) return $custom;
            $post = get_post();
            return $post ? wp_trim_words(strip_tags($post->post_content), 25) : '';
        }
        return get_bloginfo('description');
    }

    private static function getCanonicalUrl(): string
    {
        if (is_singular()) return get_permalink();
        if (is_home()) return home_url('/');
        if (is_category() || is_tag() || is_tax()) return get_term_link(get_queried_object());
        return home_url($_SERVER['REQUEST_URI'] ?? '/');
    }

    private static function getImage(): string
    {
        if (is_singular() && has_post_thumbnail()) {
            return get_the_post_thumbnail_url(null, 'large');
        }
        return '';
    }

    private static function schemaOutput(): void
    {
        $schema = ['@context' => 'https://schema.org'];

        if (is_front_page()) {
            $schema['@type'] = 'WebSite';
            $schema['name'] = get_bloginfo('name');
            $schema['url'] = home_url('/');
            $schema['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ];
        } elseif (is_singular('post')) {
            $schema['@type'] = 'Article';
            $schema['headline'] = get_the_title();
            $schema['datePublished'] = get_the_date('c');
            $schema['dateModified'] = get_the_modified_date('c');
            $schema['author'] = ['@type' => 'Person', 'name' => get_the_author()];
            if (has_post_thumbnail()) {
                $schema['image'] = get_the_post_thumbnail_url(null, 'large');
            }
        }

        if (count($schema) > 1) {
            echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
    }
}
