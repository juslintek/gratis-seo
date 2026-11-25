<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Per-post / per-page SEO meta box: custom title, meta description, canonical
 * override, robots directives (noindex/nofollow) and a social image override.
 * Values are stored as post meta and consumed by MetaTags and Schema.
 */
final class MetaBox {

    private const NONCE_ACTION = 'gratis_seo_save_meta';
    private const NONCE_FIELD  = 'gratis_seo_meta_nonce';

    /** Meta keys managed by the box. */
    public const KEY_TITLE     = '_gratis_seo_title';
    public const KEY_DESC      = '_gratis_seo_desc';
    public const KEY_CANONICAL = '_gratis_seo_canonical';
    public const KEY_NOINDEX   = '_gratis_seo_noindex';
    public const KEY_NOFOLLOW  = '_gratis_seo_nofollow';
    public const KEY_OG_IMAGE  = '_gratis_seo_og_image';

    /**
     * Register typed post meta (REST-exposed) for the public post types.
     */
    public static function register_meta(): void {
        $auth_callback = static function (bool $allowed, string $meta_key, int $object_id = 0): bool {
            unset($allowed, $meta_key);
            return $object_id > 0 ? current_user_can('edit_post', $object_id) : current_user_can('edit_posts');
        };

        $text_keys = [self::KEY_TITLE, self::KEY_DESC];
        foreach ($text_keys as $key) {
            register_post_meta(
                '',
                $key,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback'     => $auth_callback,
                ]
            );
        }

        $url_keys = [self::KEY_CANONICAL, self::KEY_OG_IMAGE];
        foreach ($url_keys as $key) {
            register_post_meta(
                '',
                $key,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => $auth_callback,
                ]
            );
        }

        foreach ([self::KEY_NOINDEX, self::KEY_NOFOLLOW] as $key) {
            register_post_meta(
                '',
                $key,
                [
                    'type'              => 'boolean',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => static function ($value): bool {
                        return (bool) $value;
                    },
                    'auth_callback'     => $auth_callback,
                ]
            );
        }
    }

    /**
     * Enqueue meta box styles cleanly on post editor screens.
     */
    public static function enqueue_styles(string $hook_suffix): void {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_add_inline_style(
            'common',
            '.gseo-field{margin:0 0 16px}.gseo-field label{font-weight:600;display:block;margin-bottom:4px}.gseo-field input[type=text],.gseo-field textarea{width:100%}.gseo-field .description{color:#646970}.gseo-count{float:right;color:#646970;font-weight:400}'
        );
    }

    /**
     * Add the meta box to every public post type's edit screen.
     */
    public static function register(): void {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            if ('attachment' === $post_type) {
                continue;
            }
            add_meta_box(
                'gratis-seo-meta',
                __('SEO', 'gratis-seo'),
                [__CLASS__, 'render'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the meta box markup.
     *
     * @param \WP_Post $post Current post.
     */
    public static function render(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $title     = (string) get_post_meta($post->ID, self::KEY_TITLE, true);
        $desc      = (string) get_post_meta($post->ID, self::KEY_DESC, true);
        $canonical = (string) get_post_meta($post->ID, self::KEY_CANONICAL, true);
        $noindex   = (int) get_post_meta($post->ID, self::KEY_NOINDEX, true);
        $nofollow  = (int) get_post_meta($post->ID, self::KEY_NOFOLLOW, true);
        $og_image  = (string) get_post_meta($post->ID, self::KEY_OG_IMAGE, true);

        echo '<div class="gseo-field">';
        echo '<label for="gseo_title">' . esc_html__('SEO title', 'gratis-seo') . '</label>';
        echo '<input type="text" id="gseo_title" name="gseo_title" value="' . esc_attr($title) . '" placeholder="' . esc_attr(get_the_title($post)) . '">';
        echo '<p class="description">' . esc_html__('Overrides the title tag for this content. Aim for under 60 characters.', 'gratis-seo') . '</p>';
        echo '</div>';

        echo '<div class="gseo-field">';
        echo '<label for="gseo_desc">' . esc_html__('Meta description', 'gratis-seo') . '</label>';
        echo '<textarea id="gseo_desc" name="gseo_desc" rows="3">' . esc_textarea($desc) . '</textarea>';
        echo '<p class="description">' . esc_html__('Shown in search results. Aim for 120-160 characters. Falls back to an excerpt.', 'gratis-seo') . '</p>';
        echo '</div>';

        echo '<div class="gseo-field">';
        echo '<label for="gseo_og_image">' . esc_html__('Social image URL', 'gratis-seo') . '</label>';
        echo '<input type="text" id="gseo_og_image" name="gseo_og_image" value="' . esc_attr($og_image) . '">';
        echo '<p class="description">' . esc_html__('Overrides the Open Graph / Twitter image. Falls back to the featured image.', 'gratis-seo') . '</p>';
        echo '</div>';

        echo '<div class="gseo-field">';
        echo '<label for="gseo_canonical">' . esc_html__('Canonical URL', 'gratis-seo') . '</label>';
        echo '<input type="text" id="gseo_canonical" name="gseo_canonical" value="' . esc_attr($canonical) . '">';
        echo '<p class="description">' . esc_html__('Optional. Point search engines at a different canonical URL.', 'gratis-seo') . '</p>';
        echo '</div>';

        echo '<div class="gseo-field">';
        echo '<label><input type="checkbox" name="gseo_noindex" value="1"' . checked($noindex, 1, false) . '> ';
        echo esc_html__('Discourage search engines from indexing this content (noindex).', 'gratis-seo') . '</label><br>';
        echo '<label><input type="checkbox" name="gseo_nofollow" value="1"' . checked($nofollow, 1, false) . '> ';
        echo esc_html__('Tell search engines not to follow links on this content (nofollow).', 'gratis-seo') . '</label>';
        echo '</div>';
    }

    /**
     * Persist the meta box values.
     *
     * @param int $post_id Saved post ID.
     */
    public static function save(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]))
            : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Nonce + capability verified above; helper reads are safe.
        $title     = isset($_POST['gseo_title']) ? sanitize_text_field(wp_unslash($_POST['gseo_title'])) : '';
        $desc      = isset($_POST['gseo_desc']) ? sanitize_textarea_field(wp_unslash($_POST['gseo_desc'])) : '';
        $canonical = isset($_POST['gseo_canonical']) ? esc_url_raw(wp_unslash($_POST['gseo_canonical'])) : '';
        $og_image  = isset($_POST['gseo_og_image']) ? esc_url_raw(wp_unslash($_POST['gseo_og_image'])) : '';

        self::update_or_delete($post_id, self::KEY_TITLE, $title);
        self::update_or_delete($post_id, self::KEY_DESC, $desc);
        self::update_or_delete($post_id, self::KEY_CANONICAL, $canonical);
        self::update_or_delete($post_id, self::KEY_OG_IMAGE, $og_image);

        update_post_meta($post_id, self::KEY_NOINDEX, isset($_POST['gseo_noindex']) ? 1 : 0);
        update_post_meta($post_id, self::KEY_NOFOLLOW, isset($_POST['gseo_nofollow']) ? 1 : 0);
    }

    /**
     * Store a non-empty value or remove the meta entirely when blanked.
     */
    private static function update_or_delete(int $post_id, string $key, string $value): void {
        if ('' === $value) {
            delete_post_meta($post_id, $key);
            return;
        }
        update_post_meta($post_id, $key, $value);
    }
}
