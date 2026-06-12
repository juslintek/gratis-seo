<?php declare(strict_types=1);
namespace Gratis\SEO\CLI;

final class SEOCommand {

    /**
     * Audit SEO for the site.
     * [<url>] : Specific URL to audit.
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args.
     * @param array<string,string> $assoc_args Associative args (unused; required by WP-CLI).
     */
    public function audit($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
        $url = $args[0] ?? home_url('/');
        \WP_CLI::log("Auditing: {$url}");

        $response = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false]);
        if (is_wp_error($response)) { \WP_CLI::error($response->get_error_message()); }

        $html = wp_remote_retrieve_body($response);
        $issues = [];

        // Title
        preg_match('/<title>([^<]*)<\/title>/', $html, $m);
        $title = $m[1] ?? '';
        if (!$title) $issues[] = 'Missing title tag';
        elseif (strlen($title) > 60) $issues[] = 'Title too long (' . strlen($title) . ' chars)';

        // Meta description
        if (!preg_match('/name=["\']description["\']/', $html)) $issues[] = 'Missing meta description';

        // Canonical
        if (!str_contains($html, 'rel="canonical"')) $issues[] = 'Missing canonical URL';

        // Schema
        if (!str_contains($html, 'application/ld+json')) $issues[] = 'No Schema.org markup';

        // OG tags
        if (!str_contains($html, 'og:title')) $issues[] = 'Missing Open Graph tags';

        // H1
        preg_match_all('/<h1/i', $html, $h1s);
        if (count($h1s[0]) === 0) $issues[] = 'No H1 tag';
        elseif (count($h1s[0]) > 1) $issues[] = 'Multiple H1 tags (' . count($h1s[0]) . ')';

        $score = max(0, 100 - count($issues) * 15);
        \WP_CLI::log("Score: {$score}/100");
        if ($issues) {
            foreach ($issues as $i) \WP_CLI::log("  ✗ {$i}");
        } else {
            \WP_CLI::success('No issues found.');
        }
    }

    /**
     * Generate sitemap.
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional args (unused; required by WP-CLI).
     * @param array<string,string> $assoc_args Associative args (unused; required by WP-CLI).
     */
    public function sitemap($args, $assoc_args): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
        $pages = wp_count_posts('page')->publish;
        $posts = wp_count_posts('post')->publish;
        \WP_CLI::log('Sitemap available at: ' . home_url('/sitemap.xml'));
        \WP_CLI::log("Pages: {$pages} | Posts: {$posts}");
        \WP_CLI::success('Sitemap is auto-generated on request.');
    }
}
