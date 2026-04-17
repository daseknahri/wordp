<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Job_Action_Support_Trait
{
    private function job_allows_schedule_actions(array $job): bool
    {
        return (string) ($job['content_type'] ?? '') === 'recipe'
            && (string) ($job['status'] ?? '') === 'scheduled';
    }

    private function job_has_core_package(array $job): bool
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $package = $this->normalized_generated_content_package($generated, $job);
        $has_pages = !empty($package['content_pages']) && is_array($package['content_pages']);

        return !empty($package['title']) && !empty($package['slug']) && (!empty($package['content_html']) || $has_pages);
    }

    private function job_retry_target(array $job): string
    {
        $distribution = $this->job_facebook_distribution($job);
        if (!empty($job['post_id']) && !empty($distribution['pages']) && is_array($distribution['pages'])) {
            foreach ($distribution['pages'] as $page) {
                if (empty($page['post_id'])) {
                    return 'facebook';
                }
                if (empty($page['comment_id'])) {
                    return 'comment';
                }
            }

            return 'comment';
        }

        if (!empty($job['post_id'])) {
            return empty($job['facebook_post_id']) ? 'facebook' : 'comment';
        }

        return $this->job_has_core_package($job) ? 'publish' : 'full';
    }

    private function recent_duplicate_job_id(string $content_type, string $topic, string $title_candidate): int
    {
        global $wpdb;

        $recent_jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, topic, title_override
                FROM {$this->table_name()}
                WHERE content_type = %s
                  AND created_by = %d
                  AND status IN ('queued', 'generating', 'scheduled', 'publishing_blog', 'publishing_facebook')
                  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                ORDER BY id DESC
                LIMIT 25",
                $content_type,
                get_current_user_id()
            ),
            ARRAY_A
        );

        $normalized_topic = sanitize_title($topic);
        $normalized_title = sanitize_title($title_candidate);

        foreach ($recent_jobs ?: [] as $recent_job) {
            $recent_topic = sanitize_title((string) ($recent_job['topic'] ?? ''));
            $recent_title = sanitize_title((string) ($recent_job['title_override'] ?? $recent_job['topic'] ?? ''));

            if (
                ($normalized_topic !== '' && $recent_topic === $normalized_topic) ||
                ($normalized_title !== '' && $recent_title === $normalized_title)
            ) {
                return (int) ($recent_job['id'] ?? 0);
            }
        }

        return 0;
    }

    private function handle_media_upload(string $field): int
    {
        if (empty($_FILES[$field]['name'])) {
            return 0;
        }

        $this->raise_media_processing_limits();

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload($field, 0);

        if (is_wp_error($attachment_id)) {
            wp_die(esc_html($attachment_id->get_error_message()));
        }

        return (int) $attachment_id;
    }
}
