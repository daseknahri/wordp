<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Site_Identity_Trait
{
    private function ensure_site_identity(): void
    {
        $title = trim((string) get_option('blogname', ''));
        if ($title === '' || in_array(strtolower($title), ['my blog', 'site title', 'my wordpress blog', 'wordpress'], true)) {
            update_option('blogname', 'kuchniatwist');
        }

        $tagline = trim((string) get_option('blogdescription', ''));
        if ($tagline === '' || in_array(strtolower($tagline), ['just another wordpress site', 'another wordpress site'], true)) {
            update_option('blogdescription', 'Warm home cooking, useful food facts, and story-led kitchen essays.');
        }
    }

    private function maybe_migrate_legacy_branding(): void
    {
        if (get_option('kuchnia_twist_branding_migrated')) {
            return;
        }

        $search = [
            'Dali Recipes',
            'Dali Recipies',
            'Dali Recipe',
            'Dali recipes',
            'Dali recipies',
            'Dali recipe',
            'Dali',
            'dali recipes',
            'dali recipies',
            'dali recipe',
            'dali',
        ];
        $replace = 'kuchniatwist';

        update_option('blogname', $replace);
        $description = (string) get_option('blogdescription', '');
        if ($description !== '') {
            update_option('blogdescription', str_ireplace($search, $replace, $description));
        }

        $settings = get_option(self::OPTION_KEY, []);
        if (is_array($settings) && !empty($settings)) {
            $settings = $this->replace_branding_recursive($settings, $search, $replace);
            update_option(self::OPTION_KEY, $settings);
        }

        $this->migrate_posts_branding($search, $replace);
        $this->migrate_terms_branding($search, $replace);

        update_option('kuchnia_twist_branding_migrated', 1, false);
    }

    private function replace_branding_recursive($value, array $search, string $replace)
    {
        if (is_string($value)) {
            return str_ireplace($search, $replace, $value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replace_branding_recursive($item, $search, $replace);
            }
        }

        return $value;
    }

    private function migrate_posts_branding(array $search, string $replace): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $like = '%dali%';
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s",
                $like,
                $like,
                $like
            )
        );

        if (!$post_ids) {
            return;
        }

        foreach ($post_ids as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post) {
                continue;
            }

            $updated = [
                'ID'           => $post->ID,
                'post_title'   => str_ireplace($search, $replace, (string) $post->post_title),
                'post_content' => str_ireplace($search, $replace, (string) $post->post_content),
                'post_excerpt' => str_ireplace($search, $replace, (string) $post->post_excerpt),
            ];

            wp_update_post($updated);
        }
    }

    private function migrate_terms_branding(array $search, string $replace): void
    {
        $terms = get_terms([
            'taxonomy'   => ['category', 'post_tag'],
            'hide_empty' => false,
            'name__like' => 'dali',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $new_name = str_ireplace($search, $replace, $term->name);
            $new_desc = str_ireplace($search, $replace, (string) $term->description);
            if ($new_name === $term->name && $new_desc === $term->description) {
                continue;
            }

            wp_update_term($term->term_id, $term->taxonomy, [
                'name'        => $new_name,
                'slug'        => sanitize_title($new_name),
                'description' => $new_desc,
            ]);
        }
    }

    private function default_editor_user_id(): int
    {
        $settings = $this->get_settings();
        $email = sanitize_email($settings['editor_public_email'] ?? '');
        if ($email !== '') {
            $user = get_user_by('email', $email);
            if ($user instanceof WP_User) {
                return (int) $user->ID;
            }
        }

        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $user = get_user_by('email', (string) $admin_email);
            if ($user instanceof WP_User) {
                return (int) $user->ID;
            }
        }

        return (int) get_current_user_id();
    }
}
