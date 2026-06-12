<?php declare(strict_types=1);

namespace Gratis\SEO;

defined('ABSPATH') || exit;

/**
 * Global SEO defaults, stored as a single option array and exposed via the
 * WordPress Settings API on a page under the Settings menu. These values feed
 * MetaTags, Schema and Breadcrumbs as fallbacks when a per-post override is
 * absent.
 */
final class Settings {

    /** Option name holding all settings. */
    public const OPTION = 'gratis_seo_settings';

    /** Settings group / page slug. */
    private const GROUP = 'gratis_seo_settings_group';
    private const PAGE  = 'gratis-seo';

    /**
     * Default values for every setting.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return [
            'title_separator'  => '-',
            'home_title'       => '',
            'home_description' => '',
            'org_type'         => 'Organization',
            'org_name'         => '',
            'org_logo'         => '',
            'default_og_image' => '',
            'twitter_site'     => '',
            'social_profiles'  => '',
            'enable_schema'    => 1,
            'enable_breadcrumbs' => 1,
            'enable_sitemap'   => 1,
        ];
    }

    /**
     * Fetch the merged settings array (stored values over defaults), filterable.
     *
     * @return array<string,mixed>
     */
    public static function all(): array {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        /** @var array<string,mixed> $merged */
        $merged = array_merge(self::defaults(), $stored);

        /**
         * Filter the resolved SEO settings.
         *
         * @param array<string,mixed> $merged Merged settings.
         */
        return (array) apply_filters('gratis_seo_settings', $merged);
    }

    /**
     * String accessor for a single setting.
     */
    public static function get_string(string $key, string $fallback = ''): string {
        $all = self::all();
        return isset($all[$key]) ? (string) $all[$key] : $fallback;
    }

    /**
     * Boolean accessor for a single setting.
     */
    public static function get_bool(string $key, bool $fallback = false): bool {
        $all = self::all();
        return isset($all[$key]) ? (bool) $all[$key] : $fallback;
    }

    /**
     * Return the configured social profile URLs as a clean list.
     *
     * @return list<string>
     */
    public static function social_profiles(): array {
        $raw   = self::get_string('social_profiles');
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $urls  = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' !== $line) {
                $urls[] = $line;
            }
        }
        return $urls;
    }

    /**
     * Register the settings page under the Settings menu.
     */
    public static function register_menu(): void {
        add_options_page(
            __('Gratis SEO', 'gratis-seo'),
            __('Gratis SEO', 'gratis-seo'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Register the option and its sanitisation callback.
     */
    public static function register_settings(): void {
        register_setting(
            self::GROUP,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    /**
     * Sanitise the incoming settings array.
     *
     * @param mixed $input Raw option input.
     * @return array<string,mixed>
     */
    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];

        $org_type = isset($input['org_type']) ? sanitize_key((string) $input['org_type']) : 'Organization';
        $org_type = in_array($org_type, ['organization', 'person'], true) ? $org_type : 'organization';
        // Schema.org expects PascalCase type names.
        $org_type = 'person' === $org_type ? 'Person' : 'Organization';

        $profiles_raw = isset($input['social_profiles']) ? (string) $input['social_profiles'] : '';
        $profile_lines = preg_split('/\r\n|\r|\n/', $profiles_raw) ?: [];
        $profiles = [];
        foreach ($profile_lines as $line) {
            $line = esc_url_raw(trim($line));
            if ('' !== $line) {
                $profiles[] = $line;
            }
        }

        $separator = isset($input['title_separator']) ? sanitize_text_field((string) $input['title_separator']) : '-';
        if ('' === $separator) {
            $separator = '-';
        }

        return [
            'title_separator'    => $separator,
            'home_title'         => isset($input['home_title']) ? sanitize_text_field((string) $input['home_title']) : '',
            'home_description'   => isset($input['home_description']) ? sanitize_text_field((string) $input['home_description']) : '',
            'org_type'           => $org_type,
            'org_name'           => isset($input['org_name']) ? sanitize_text_field((string) $input['org_name']) : '',
            'org_logo'           => isset($input['org_logo']) ? esc_url_raw((string) $input['org_logo']) : '',
            'default_og_image'   => isset($input['default_og_image']) ? esc_url_raw((string) $input['default_og_image']) : '',
            'twitter_site'       => isset($input['twitter_site']) ? sanitize_text_field((string) $input['twitter_site']) : '',
            'social_profiles'    => implode("\n", $profiles),
            'enable_schema'      => empty($input['enable_schema']) ? 0 : 1,
            'enable_breadcrumbs' => empty($input['enable_breadcrumbs']) ? 0 : 1,
            'enable_sitemap'     => empty($input['enable_sitemap']) ? 0 : 1,
        ];
    }

    /**
     * Render the settings screen.
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-seo'));
        }

        $settings = self::all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis SEO', 'gratis-seo') . '</h1>';
        echo '<p>' . esc_html__('Global defaults for meta tags, social previews and structured data. Per-post overrides live in the SEO box on each edit screen.', 'gratis-seo') . '</p>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        self::text_row($settings, 'title_separator', __('Title separator', 'gratis-seo'), __('Character placed between the page title and the site name.', 'gratis-seo'), 'small-text');
        self::text_row($settings, 'home_title', __('Home title', 'gratis-seo'), __('Title tag for the front page. Leave blank to use the site title.', 'gratis-seo'), 'large-text');
        self::textarea_row($settings, 'home_description', __('Home description', 'gratis-seo'), __('Meta description for the front page. Leave blank to use the site tagline.', 'gratis-seo'));

        echo '<tr><th scope="row"><label for="gseo_org_type">' . esc_html__('Site represents', 'gratis-seo') . '</label></th><td>';
        echo '<select id="gseo_org_type" name="' . esc_attr(self::OPTION) . '[org_type]">';
        $org_types = [
            'Organization' => __('An organization', 'gratis-seo'),
            'Person'       => __('A person', 'gratis-seo'),
        ];
        foreach ($org_types as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected((string) $settings['org_type'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Controls the top-level Schema.org entity for the site.', 'gratis-seo') . '</p>';
        echo '</td></tr>';

        self::text_row($settings, 'org_name', __('Organization / person name', 'gratis-seo'), __('Used in structured data. Falls back to the site title.', 'gratis-seo'), 'regular-text');
        self::text_row($settings, 'org_logo', __('Logo URL', 'gratis-seo'), __('Absolute URL to the logo image used in structured data.', 'gratis-seo'), 'large-text');
        self::text_row($settings, 'default_og_image', __('Default social image', 'gratis-seo'), __('Fallback Open Graph / Twitter image when a post has none.', 'gratis-seo'), 'large-text');
        self::text_row($settings, 'twitter_site', __('Twitter / X handle', 'gratis-seo'), __('Site account, including the @ (e.g. @gratis).', 'gratis-seo'), 'regular-text');
        self::textarea_row($settings, 'social_profiles', __('Social profile URLs', 'gratis-seo'), __('One profile URL per line. Emitted as sameAs links in structured data.', 'gratis-seo'));

        self::checkbox_row($settings, 'enable_schema', __('Structured data', 'gratis-seo'), __('Output JSON-LD schema (Organization, WebSite, Article, BreadcrumbList).', 'gratis-seo'));
        self::checkbox_row($settings, 'enable_breadcrumbs', __('Breadcrumbs', 'gratis-seo'), __('Enable the [gratis_breadcrumbs] shortcode and BreadcrumbList schema.', 'gratis-seo'));
        self::checkbox_row($settings, 'enable_sitemap', __('XML sitemap', 'gratis-seo'), __('Serve an XML sitemap at /sitemap.xml.', 'gratis-seo'));

        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Output a single text input settings row.
     *
     * @param array<string,mixed> $settings Current settings.
     */
    private static function text_row(array $settings, string $key, string $label, string $description, string $css_class = 'regular-text'): void {
        $id = 'gseo_' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="text" id="' . esc_attr($id) . '" class="' . esc_attr($css_class) . '" name="' . esc_attr(self::OPTION) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) ($settings[$key] ?? '')) . '">';
        echo '<p class="description">' . esc_html($description) . '</p>';
        echo '</td></tr>';
    }

    /**
     * Output a single textarea settings row.
     *
     * @param array<string,mixed> $settings Current settings.
     */
    private static function textarea_row(array $settings, string $key, string $label, string $description): void {
        $id = 'gseo_' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        echo '<textarea id="' . esc_attr($id) . '" class="large-text" rows="3" name="' . esc_attr(self::OPTION) . '[' . esc_attr($key) . ']">' . esc_textarea((string) ($settings[$key] ?? '')) . '</textarea>';
        echo '<p class="description">' . esc_html($description) . '</p>';
        echo '</td></tr>';
    }

    /**
     * Output a single checkbox settings row.
     *
     * @param array<string,mixed> $settings Current settings.
     */
    private static function checkbox_row(array $settings, string $key, string $label, string $description): void {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[' . esc_attr($key) . ']" value="1"' . checked((int) ($settings[$key] ?? 0), 1, false) . '> ';
        echo esc_html($description) . '</label>';
        echo '</td></tr>';
    }
}
