<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Site_Trait
{
    private function install(): void
    {
        global $wpdb;
        $table           = $this->table_name();
        $events_table    = $this->events_table_name();
        $ideas_table     = $this->ideas_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_uuid char(36) NOT NULL,
                topic varchar(191) NOT NULL,
                content_type varchar(32) NOT NULL,
                title_override varchar(255) NOT NULL DEFAULT '',
                blog_image_id bigint(20) unsigned NULL,
                facebook_image_id bigint(20) unsigned NULL,
                status varchar(32) NOT NULL DEFAULT 'queued',
                stage varchar(32) NOT NULL DEFAULT 'queued',
                retry_target varchar(32) NOT NULL DEFAULT '',
                created_by bigint(20) unsigned NULL,
                post_id bigint(20) unsigned NULL,
                featured_image_id bigint(20) unsigned NULL,
                facebook_image_result_id bigint(20) unsigned NULL,
                facebook_post_id varchar(191) NOT NULL DEFAULT '',
                facebook_comment_id varchar(191) NOT NULL DEFAULT '',
                permalink text NULL,
                error_message longtext NULL,
                request_payload longtext NULL,
                generated_payload longtext NULL,
                facebook_caption longtext NULL,
                group_share_kit longtext NULL,
                publish_on datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                last_attempt_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_uuid (job_uuid),
                KEY status_created (status, created_at),
                KEY status_publish (status, publish_on)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$events_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id bigint(20) unsigned NOT NULL,
                event_type varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT '',
                stage varchar(32) NOT NULL DEFAULT '',
                message text NULL,
                context_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY job_created (job_id, created_at),
                KEY event_type_created (event_type, created_at)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$ideas_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                dish_name varchar(191) NOT NULL,
                preferred_angle varchar(64) NOT NULL DEFAULT '',
                operator_note text NULL,
                status varchar(32) NOT NULL DEFAULT 'idea',
                linked_job_id bigint(20) unsigned NULL,
                linked_post_id bigint(20) unsigned NULL,
                created_by bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status_updated (status, updated_at),
                KEY linked_job (linked_job_id),
                KEY linked_post (linked_post_id)
            ) {$charset_collate};"
        );

        update_option(self::OPTION_KEY, wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings()));
        update_option(self::WORKER_STATUS_KEY, wp_parse_args(get_option(self::WORKER_STATUS_KEY, []), $this->default_worker_status()));
        update_option(self::VERSION_KEY, self::VERSION, false);

        $this->ensure_site_identity();
        $this->ensure_category('recipe');
        $this->ensure_category('food_fact');
        $this->ensure_category('food_story');
        $this->ensure_core_pages();
        $this->ensure_launch_posts();
    }

    private function ensure_core_pages(): void
    {
        $pages = $this->core_pages();

        foreach ($pages as $slug => $page) {
            $existing_page   = get_page_by_path($slug, OBJECT, 'page');
            $page_id         = 0;
            $did_seed_update = false;
            $seed_hash       = $this->core_page_seed_hash($page);

            if (!$existing_page instanceof WP_Post) {
                $page_id = (int) wp_insert_post([
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $slug,
                    'post_excerpt' => (string) ($page['excerpt'] ?? ''),
                    'post_content' => $page['content'],
                ]);
                $did_seed_update = $page_id > 0;
            } elseif (
                $this->should_refresh_core_page($existing_page)
                || ($this->is_seed_managed_core_page($existing_page) && $this->current_core_page_hash($existing_page) !== $seed_hash)
            ) {
                $page_id = (int) wp_update_post([
                    'ID'           => $existing_page->ID,
                    'post_title'   => $page['title'],
                    'post_excerpt' => (string) ($page['excerpt'] ?? $existing_page->post_excerpt),
                    'post_content' => $page['content'],
                ]);
                $did_seed_update = $page_id > 0;
            } else {
                $page_id = (int) $existing_page->ID;
            }

            if ($did_seed_update && $page_id > 0) {
                update_post_meta($page_id, self::CORE_PAGE_SEED_HASH_META, $seed_hash);
            }

            if ($did_seed_update && $page_id > 0 && !empty($page['seo_description'])) {
                update_post_meta($page_id, 'kuchnia_twist_seo_description', (string) $page['seo_description']);
            }

            if ($did_seed_update && $page_id > 0 && !empty($page['featured_asset'])) {
                $this->maybe_assign_local_featured_image(
                    $page_id,
                    (string) $page['featured_asset'],
                    $page['title'],
                    (string) ($page['featured_alt'] ?? $page['title'])
                );
            }
        }
    }

    private function core_page_seed_hash(array $page): string
    {
        return md5(wp_json_encode([
            'title'           => (string) ($page['title'] ?? ''),
            'excerpt'         => (string) ($page['excerpt'] ?? ''),
            'content'         => (string) ($page['content'] ?? ''),
            'seo_description' => (string) ($page['seo_description'] ?? ''),
        ]));
    }

    private function current_core_page_hash(WP_Post $page): string
    {
        return md5(wp_json_encode([
            'title'           => (string) $page->post_title,
            'excerpt'         => (string) $page->post_excerpt,
            'content'         => (string) $page->post_content,
            'seo_description' => (string) get_post_meta($page->ID, 'kuchnia_twist_seo_description', true),
        ]));
    }

    private function is_seed_managed_core_page(WP_Post $page): bool
    {
        $stored_hash = (string) get_post_meta($page->ID, self::CORE_PAGE_SEED_HASH_META, true);

        if ($stored_hash === '') {
            return false;
        }

        return hash_equals($stored_hash, $this->current_core_page_hash($page));
    }

    private function core_pages(): array
    {
        return kuchnia_twist_launch_core_pages();
    }

    private function ensure_launch_posts(): void
    {
        $launch_posts = array_values(array_filter(
            kuchnia_twist_launch_posts(),
            fn (array $post): bool => $this->is_active_content_type((string) ($post['content_type'] ?? ''))
        ));
        $editor_id   = $this->default_editor_user_id();
        $total_posts = count($launch_posts);

        foreach ($launch_posts as $index => $post) {
            $existing = get_page_by_path($post['slug'], OBJECT, 'post');
            $seeded   = $existing instanceof WP_Post ? (bool) get_post_meta($existing->ID, '_kuchnia_twist_launch_seed', true) : false;
            $date_gmt = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - (($total_posts - $index) * DAY_IN_SECONDS));

            $post_data = [
                'post_type'     => 'post',
                'post_status'   => 'publish',
                'post_title'    => $post['title'],
                'post_name'     => $post['slug'],
                'post_excerpt'  => $post['excerpt'],
                'post_content'  => $post['content_html'],
                'post_author'   => $editor_id,
                'post_category' => [$this->ensure_category($post['content_type'])],
                'post_date_gmt' => $date_gmt,
                'post_date'     => get_date_from_gmt($date_gmt),
            ];

            if (!$existing instanceof WP_Post) {
                $post_id = wp_insert_post($post_data, true);
                if (is_wp_error($post_id)) {
                    continue;
                }
                add_post_meta($post_id, '_kuchnia_twist_launch_seed', 1, true);
            } elseif ($seeded) {
                $post_id = wp_update_post(array_merge($post_data, ['ID' => $existing->ID]), true);
                if (is_wp_error($post_id)) {
                    continue;
                }
            } else {
                $post_id = $existing->ID;
            }

            update_post_meta($post_id, 'kuchnia_twist_content_type', $post['content_type']);
            update_post_meta($post_id, 'kuchnia_twist_recipe_data', $post['recipe'] ?? []);
            update_post_meta($post_id, 'kuchnia_twist_seo_description', $post['seo_description']);

            if (!empty($post['featured_asset'])) {
                $this->maybe_assign_local_featured_image(
                    $post_id,
                    (string) $post['featured_asset'],
                    $post['title'],
                    (string) ($post['featured_alt'] ?? $post['title'])
                );
            }
        }
    }

    private function should_refresh_core_page(WP_Post $page): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($page->post_content))));

        if ($text === '') {
            return true;
        }

        $starter_markers = [
            'replace this placeholder',
            'replace this starter copy',
            'use this page to add your editorial email',
            'use this page to explain what cookies',
            'use this page to describe how recipes are developed',
            'this starter page should be replaced',
            'kuchniatwist is a food journal built around three pillars',
            'not launching with display ads',
            'does not use newsletter tools, advertising tags, affiliate scripts, or third-party analytics platforms',
            'does not run a broader advertising or tracking stack at launch',
            'not launching with advertising cookies',
            'the launch version of kuchniatwist does not rely on ad-tech cookies',
            'not yet using the kinds of systems that require a more complex preference center',
            'the trust layer around the archive matters as much as the archive itself',
            'a useful site should feel maintained in public',
            'same public trust layer as this policy',
            'add your preferred public email address here before launch',
            'business or partnership email: add it here if you want a separate contact line',
            'the goal of this page is simple: make contact feel clear, legitimate, and easy to understand',
            'if you add a public inbox, it helps to set a simple expectation',
            'suggested text:',
            'our website address is: https://kuchniatwist.pl',
            'visitor comments may be checked through an automated spam detection service',
            'an anonymized string created from your email address',
            'if you upload images to the website, you should avoid uploading images with embedded location data',
            'if you leave a comment on our site you may opt-in to saving your name, email address and website in cookies',
            'if you visit our login page, we will set a temporary cookie',
            'if you request a password reset, your ip address will be included in the reset email',
            'for users that register on our website (if any)',
            'this does not include any data we are obliged to keep for administrative, legal, or security purposes',
            'these websites may collect data about you, use cookies, embed additional third-party tracking',
        ];

        foreach ($starter_markers as $marker) {
            if (strpos($text, $marker) !== false) {
                return true;
            }
        }

        return str_word_count($text) < 45;
    }

    private function maybe_assign_local_featured_image(int $post_id, string $relative_path, string $title, string $alt): void
    {
        $attachment_id = $this->import_local_asset($relative_path, $title, $alt, $post_id);
        if ($attachment_id > 0) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    private function import_local_asset(string $relative_path, string $title, string $alt, int $parent_id = 0): int
    {
        $existing = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'meta_key'       => '_kuchnia_twist_launch_asset',
            'meta_value'     => $relative_path,
            'fields'         => 'ids',
        ]);

        if ($existing) {
            $attachment_id = (int) $existing[0];
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
            if ($parent_id > 0) {
                wp_update_post(['ID' => $attachment_id, 'post_parent' => $parent_id]);
            }
            return $attachment_id;
        }

        $source_path = trailingslashit(KUCHNIA_TWIST_PUBLISHER_DIR) . 'assets/launch/' . ltrim($relative_path, '/');
        if (!file_exists($source_path)) {
            return 0;
        }

        $binary = file_get_contents($source_path);
        if ($binary === false) {
            return 0;
        }

        $upload = wp_upload_bits(basename($source_path), null, $binary);
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachment_id = wp_insert_attachment([
            'post_title'     => $title,
            'post_parent'    => $parent_id,
            'post_mime_type' => wp_check_filetype($upload['file'])['type'] ?? 'image/jpeg',
            'guid'           => $upload['url'],
        ], $upload['file'], $parent_id);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        update_post_meta($attachment_id, '_kuchnia_twist_launch_asset', $relative_path);

        return (int) $attachment_id;
    }

    private function ensure_category(string $content_type): int
    {
        $map = [
            'recipe'     => ['name' => __('Recipes', 'kuchnia-twist'), 'slug' => 'recipes'],
            'food_fact'  => ['name' => __('Food Facts', 'kuchnia-twist'), 'slug' => 'food-facts'],
            'food_story' => ['name' => __('Food Stories', 'kuchnia-twist'), 'slug' => 'food-stories'],
        ];

        $target = $map[$content_type] ?? $map['recipe'];
        $term   = get_category_by_slug($target['slug']);

        if ($term instanceof WP_Term) {
            return (int) $term->term_id;
        }

        return (int) wp_create_category($target['name']);
    }
}
