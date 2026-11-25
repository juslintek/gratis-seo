<?php
/**
 * Uninstall gratis-seo.
 *
 * Removes all plugin options and post meta.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('gratis_seo_settings');

foreach ([
    '_gratis_seo_title',
    '_gratis_seo_desc',
    '_gratis_seo_canonical',
    '_gratis_seo_noindex',
    '_gratis_seo_nofollow',
    '_gratis_seo_og_image',
] as $gratis_seo_meta_key) {
    delete_post_meta_by_key($gratis_seo_meta_key);
}
