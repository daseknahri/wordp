<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Shared_Content_Trait
{
    private function find_conflicting_post_id(string $candidate, int $exclude_post_id = 0): int
    {
        global $wpdb;

        $normalized = sanitize_title($candidate);
        if ($normalized === '') {
            return 0;
        }

        $existing = get_page_by_path($normalized, OBJECT, 'post');
        if ($existing instanceof WP_Post && $existing->ID !== $exclude_post_id) {
            return (int) $existing->ID;
        }

        $title_match = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = 'post'
                  AND post_status IN ('publish', 'draft', 'future', 'pending', 'private')
                  AND LOWER(post_title) = LOWER(%s)
                  AND ID <> %d
                ORDER BY ID DESC
                LIMIT 1",
                $candidate,
                $exclude_post_id
            )
        );

        return $title_match;
    }
}
