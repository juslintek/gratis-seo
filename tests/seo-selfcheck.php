<?php
/**
 * Standalone self-check for Gratis SEO Sitemap & MetaBox edge cases.
 *
 * Verifies:
 *  - Sitemap::bypass_core_redirect() on root, subdirectory, query string,
 *    unrelated paths, and when enable_sitemap is disabled.
 *  - MetaBox::register_meta() auth_callback accepts string and int $object_id
 *    under strict_types=1 and enforces per-post edit_post capability.
 *  - MetaBox::enqueue_styles() tolerates null/non-string hook suffixes.
 *  - MetaBox::save() skips post revisions without modifying post meta.
 *
 * Run: php tests/seo-selfcheck.php
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.WP.AlternativeFunctions

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');

$GLOBALS['_gseo_test'] = [
    'home_base'      => 'https://example.com',
    'options'        => ['gratis_seo_settings' => ['enable_sitemap' => 1]],
    'registered'     => [],
    'inline_styles'  => [],
    'caps'           => [],
    'revisions'      => [99 => true],
    'meta_writes'    => [],
];

function get_option(string $name, $fallback = false) {
    return $GLOBALS['_gseo_test']['options'][$name] ?? $fallback;
}
function apply_filters(string $tag, $value) {
    unset($tag);
    return $value;
}
function home_url(string $path = ''): string {
    return rtrim($GLOBALS['_gseo_test']['home_base'], '/') . '/' . ltrim($path, '/');
}
function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}
function wp_unslash($value) {
    return $value;
}
function sanitize_text_field($str): string {
    return trim(strip_tags((string) $str));
}
function register_post_meta(string $post_type, string $meta_key, array $args): bool {
    $GLOBALS['_gseo_test']['registered'][$meta_key] = $args;
    return true;
}
function current_user_can(string $cap, ...$args): bool {
    $key = $cap . (isset($args[0]) ? ':' . $args[0] : '');
    return !empty($GLOBALS['_gseo_test']['caps'][$key]);
}
function wp_add_inline_style(string $handle, string $css): bool {
    $GLOBALS['_gseo_test']['inline_styles'][] = [$handle, $css];
    return true;
}
function wp_is_post_revision(int $post_id): bool {
    return !empty($GLOBALS['_gseo_test']['revisions'][$post_id]);
}
function wp_verify_nonce(string $nonce, string $action): bool {
    return 'valid' === $nonce && 'gratis_seo_save_meta' === $action;
}
function update_post_meta(int $post_id, string $key, $value): bool {
    $GLOBALS['_gseo_test']['meta_writes'][] = [$post_id, $key, $value];
    return true;
}
function delete_post_meta(int $post_id, string $key): bool {
    $GLOBALS['_gseo_test']['meta_writes'][] = [$post_id, $key, null];
    return true;
}

require __DIR__ . '/../src/Settings.php';
require __DIR__ . '/../src/MetaBox.php';
require __DIR__ . '/../src/Sitemap.php';

use Gratis\SEO\MetaBox;
use Gratis\SEO\Sitemap;

$failures = 0;
$check = static function (string $name, bool $cond) use (&$failures): void {
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $name . "\n";
    if (!$cond) {
        ++$failures;
    }
};

// --- Sitemap::bypass_core_redirect ----------------------------------------
$GLOBALS['_gseo_test']['home_base'] = 'https://example.com';
$_SERVER['REQUEST_URI'] = '/sitemap.xml';
$check('root /sitemap.xml bypasses core 404 redirect', true === Sitemap::bypass_core_redirect(false));

$_SERVER['REQUEST_URI'] = '/sitemap.xml?cache=1';
$check('root /sitemap.xml with query string bypasses', true === Sitemap::bypass_core_redirect(false));

$_SERVER['REQUEST_URI'] = '/not-sitemap.xml';
$check('unrelated path does not bypass', false === Sitemap::bypass_core_redirect(false));

$GLOBALS['_gseo_test']['home_base'] = 'https://example.com/blog';
$_SERVER['REQUEST_URI'] = '/blog/sitemap.xml';
$check('subdirectory /blog/sitemap.xml bypasses', true === Sitemap::bypass_core_redirect(false));

$_SERVER['REQUEST_URI'] = '/sitemap.xml';
$check('subdirectory install does not hijack root /sitemap.xml', false === Sitemap::bypass_core_redirect(false));

$GLOBALS['_gseo_test']['options']['gratis_seo_settings'] = ['enable_sitemap' => 0];
$_SERVER['REQUEST_URI'] = '/blog/sitemap.xml';
$check('enable_sitemap=false falls back to core WP sitemaps', false === Sitemap::bypass_core_redirect(false));

// --- MetaBox strict_types auth_callback & enqueue_styles ------------------
MetaBox::register_meta();
$auth = $GLOBALS['_gseo_test']['registered'][MetaBox::KEY_TITLE]['auth_callback'] ?? null;
$check('MetaBox registered auth_callback', is_callable($auth));

$GLOBALS['_gseo_test']['caps'] = ['edit_post:42' => true];
$check('auth_callback accepts numeric string object_id under strict_types', true === $auth(false, MetaBox::KEY_TITLE, '42'));
$check('auth_callback denies post without edit_post cap', false === $auth(false, MetaBox::KEY_TITLE, '43'));

MetaBox::enqueue_styles(null);
MetaBox::enqueue_styles('options-general.php');
$check('enqueue_styles ignores null and non-editor screens', 0 === count($GLOBALS['_gseo_test']['inline_styles']));
MetaBox::enqueue_styles('post.php');
$check('enqueue_styles attaches inline style on post.php', 1 === count($GLOBALS['_gseo_test']['inline_styles']));

// --- MetaBox::save skips revisions ----------------------------------------
$_POST['gratis_seo_meta_nonce'] = 'valid';
$_POST['gseo_title'] = 'Should not save';
$GLOBALS['_gseo_test']['caps'] = ['edit_post:99' => true];
MetaBox::save(99);
$check('MetaBox::save skips post revisions', 0 === count($GLOBALS['_gseo_test']['meta_writes']));

echo "\n" . ($failures === 0 ? 'ALL CHECKS PASSED' : "{$failures} CHECK(S) FAILED") . "\n";
exit($failures === 0 ? 0 : 1);
