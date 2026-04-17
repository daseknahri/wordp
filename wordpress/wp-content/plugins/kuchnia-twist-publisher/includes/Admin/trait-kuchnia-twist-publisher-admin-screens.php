<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Screens_Trait
{
    public function register_admin_pages(): void
    {
        add_menu_page(
            __('kuchniatwist', 'kuchnia-twist'),
            __('kuchniatwist', 'kuchnia-twist'),
            'publish_posts',
            'kuchnia-twist-publisher',
            [$this, 'render_publisher_page'],
            'dashicons-carrot',
            26
        );

        add_submenu_page(
            'kuchnia-twist-publisher',
            __('Publishing Settings', 'kuchnia-twist'),
            __('Settings', 'kuchnia-twist'),
            'manage_options',
            'kuchnia-twist-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        $allowed_hooks = [
            'toplevel_page_kuchnia-twist-publisher',
            'kuchnia-twist-publisher_page_kuchnia-twist-settings',
            'kuchnia-twist_page_kuchnia-twist-settings',
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_media();
        $admin_css_path = KUCHNIA_TWIST_PUBLISHER_DIR . 'admin.css';
        $admin_js_path  = KUCHNIA_TWIST_PUBLISHER_DIR . 'admin.js';
        wp_enqueue_style(
            'kuchnia-twist-admin',
            plugins_url('admin.css', KUCHNIA_TWIST_PUBLISHER_FILE),
            [],
            file_exists($admin_css_path) ? (string) filemtime($admin_css_path) : Kuchnia_Twist_Publisher::VERSION
        );
        wp_enqueue_script(
            'kuchnia-twist-admin',
            plugins_url('admin.js', KUCHNIA_TWIST_PUBLISHER_FILE),
            ['jquery', 'media-editor'],
            file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : Kuchnia_Twist_Publisher::VERSION,
            true
        );
    }
}
