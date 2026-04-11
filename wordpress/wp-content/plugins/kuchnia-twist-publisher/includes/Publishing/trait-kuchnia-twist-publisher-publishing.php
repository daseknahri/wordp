<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Publishing_Trait
{
    private function sanitize_post_content_with_page_breaks(string $content_html): string
    {
        $content_html = trim($content_html);
        if ($content_html === '') {
            return '';
        }

        $pages = preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: [];
        $pages = array_values(array_filter(array_map(
            static fn (string $page): string => trim(wp_kses_post($page)),
            $pages
        ), static fn (string $page): bool => $page !== ''));

        if (empty($pages)) {
            return '';
        }

        return implode("\n<!--nextpage-->\n", $pages);
    }

    private function resolved_publish_payload(array $params, array $generated, array $job): array
    {
        $content_package = $this->normalized_generated_content_package($generated, $job);

        return [
            'content_type'      => sanitize_key((string) ($params['content_type'] ?? $content_package['content_type'] ?? $job['content_type'] ?? 'recipe')),
            'title'             => sanitize_text_field((string) ($params['title'] ?? $content_package['title'] ?? '')),
            'slug'              => sanitize_title((string) ($params['slug'] ?? $content_package['slug'] ?? '')),
            'excerpt'           => sanitize_text_field((string) ($params['excerpt'] ?? $content_package['excerpt'] ?? '')),
            'seo_description'   => sanitize_text_field((string) ($params['seo_description'] ?? $content_package['seo_description'] ?? '')),
            'content_html'      => $this->sanitize_post_content_with_page_breaks((string) ($params['content_html'] ?? $content_package['content_html'] ?? '')),
            'featured_image_id' => (int) ($params['featured_image_id'] ?? 0),
            'facebook_image_id' => (int) ($params['facebook_image_id'] ?? 0),
        ];
    }

    private function validate_generated_publish_payload(array $params, array $generated, array $job): ?WP_Error
    {
        $publish_payload = $this->resolved_publish_payload($params, $generated, $job);
        $summary = $this->build_job_quality_summary($job, $generated, $publish_payload);
        $blocking_checks = $summary['blocking_checks'] ?? [];
        $messages = $this->quality_failed_check_messages();

        if (in_array('missing_target_pages', $blocking_checks, true)) {
            return new WP_Error('missing_facebook_pages', __('Article jobs must keep at least one target Facebook page attached before publish.', 'kuchnia-twist'), ['status' => 400]);
        }

        $settings = $this->get_settings();
        if (($settings['image_generation_mode'] ?? '') === 'manual_only' && in_array('missing_manual_images', $blocking_checks, true)) {
            return new WP_Error('launch_media_required', __('Manual-only image handling requires both real uploaded blog and Facebook images before publish.', 'kuchnia-twist'), ['status' => 400]);
        }

        if (in_array('duplicate_conflict', $blocking_checks, true)) {
            return new WP_Error('duplicate_post', __('A post with the same title or slug already exists, so this generated article was blocked.', 'kuchnia-twist'), ['status' => 409]);
        }

        if (!empty($blocking_checks) || (($summary['quality_status'] ?? '') === 'block')) {
            $first_failed = (string) ($blocking_checks[0] ?? 'quality_gate_failed');
            return new WP_Error(
                $first_failed,
                $messages[$first_failed] ?? __('The generated article package did not meet the quality gate for publish.', 'kuchnia-twist'),
                [
                    'status'        => 400,
                    'quality_score' => (int) ($summary['quality_score'] ?? 0),
                    'failed_checks' => $blocking_checks,
                ]
            );
        }

        $active_pages = [];
        foreach ($this->facebook_pages($settings, true, true) as $page) {
            $active_pages[(string) ($page['page_id'] ?? '')] = true;
        }

        $selected_pages = $this->job_selected_pages($job);
        $active_selected_pages = array_filter(
            $selected_pages,
            static function (array $page) use ($active_pages): bool {
                $page_id = (string) ($page['page_id'] ?? '');
                return $page_id !== '' && isset($active_pages[$page_id]);
            }
        );

        if (!$active_selected_pages) {
            return new WP_Error('inactive_facebook_pages', __('The selected Facebook pages are no longer active in Settings, so this recipe was blocked before publish.', 'kuchnia-twist'), ['status' => 400]);
        }

        return null;
    }

    private function count_internal_links(string $content_html): int
    {
        $shortcodes = preg_match_all('/\[kuchnia_twist_link\s+slug=/i', $content_html);
        $anchors    = preg_match_all('/<a\s+[^>]*href=/i', $content_html);

        return (int) $shortcodes + (int) $anchors;
    }
}
