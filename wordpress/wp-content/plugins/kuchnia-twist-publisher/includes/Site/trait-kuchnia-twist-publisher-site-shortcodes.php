<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Site_Shortcodes_Trait
{
    public function register_shortcodes(): void
    {
        add_shortcode('kuchnia_twist_editor_name', [$this, 'shortcode_editor_name']);
        add_shortcode('kuchnia_twist_editor_role', [$this, 'shortcode_editor_role']);
        add_shortcode('kuchnia_twist_editor_public_email', [$this, 'shortcode_editor_public_email']);
        add_shortcode('kuchnia_twist_editor_business_email', [$this, 'shortcode_editor_business_email']);
        add_shortcode('kuchnia_twist_link', [$this, 'shortcode_internal_link']);
    }

    public function shortcode_editor_name(): string
    {
        $settings = $this->get_settings();
        if ($settings['editor_name'] !== '') {
            return esc_html($settings['editor_name']);
        }

        $user = get_userdata($this->default_editor_user_id());
        if ($user instanceof WP_User && $user->display_name !== '') {
            return esc_html($user->display_name);
        }

        return esc_html(get_bloginfo('name'));
    }

    public function shortcode_editor_role(): string
    {
        $settings = $this->get_settings();
        $role = $settings['editor_role'] !== '' ? $settings['editor_role'] : __('Founding editor', 'kuchnia-twist');
        return esc_html($role);
    }

    public function shortcode_editor_public_email(): string
    {
        $settings = $this->get_settings();
        $email = is_email($settings['editor_public_email']) ? $settings['editor_public_email'] : get_option('admin_email');
        return esc_html(antispambot((string) $email));
    }

    public function shortcode_editor_business_email(): string
    {
        $settings = $this->get_settings();
        $email = is_email($settings['editor_business_email']) ? $settings['editor_business_email'] : '';
        return esc_html(antispambot((string) $email));
    }

    public function shortcode_internal_link($atts, $content = ''): string
    {
        $atts = shortcode_atts([
            'slug' => '',
        ], $atts, 'kuchnia_twist_link');

        $slug = sanitize_title((string) $atts['slug']);
        $label = trim((string) $content);

        if ($slug === '' || $label === '') {
            return esc_html($label);
        }

        $url = '';
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
        }

        if ($url === '') {
            $post = get_page_by_path($slug, OBJECT, 'post');
            if ($post instanceof WP_Post) {
                $url = get_permalink($post);
            }
        }

        if ($url === '') {
            $term = get_category_by_slug($slug);
            if ($term instanceof WP_Term) {
                $url = get_category_link($term);
            }
        }

        if ($url === '' || is_wp_error($url)) {
            return esc_html($label);
        }

        return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
    }
}
