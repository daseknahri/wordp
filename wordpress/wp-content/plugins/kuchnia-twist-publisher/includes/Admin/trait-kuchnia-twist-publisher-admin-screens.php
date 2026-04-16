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
        wp_enqueue_style(
            'kuchnia-twist-admin',
            plugins_url('admin.css', KUCHNIA_TWIST_PUBLISHER_FILE),
            [],
            Kuchnia_Twist_Publisher::VERSION
        );
        wp_enqueue_script(
            'kuchnia-twist-admin',
            plugins_url('admin.js', KUCHNIA_TWIST_PUBLISHER_FILE),
            ['jquery', 'media-editor'],
            Kuchnia_Twist_Publisher::VERSION,
            true
        );
    }
}
