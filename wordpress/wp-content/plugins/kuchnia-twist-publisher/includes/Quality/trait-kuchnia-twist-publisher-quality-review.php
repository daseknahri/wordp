<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Review_Trait
{
    private function job_generated_snapshot(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        if (!$generated) {
            return [];
        }

        $content_package = $this->normalized_generated_content_package($generated, $job);
        $channels = $this->generated_channels($generated, $job);
        $facebook_channel = is_array($channels['facebook'] ?? null) ? $channels['facebook'] : [];
        $facebook_groups_channel = is_array($channels['facebook_groups'] ?? null) ? $channels['facebook_groups'] : [];
        $pinterest_channel = is_array($channels['pinterest'] ?? null) ? $channels['pinterest'] : [];
        $has_facebook_groups_scaffold = !empty($facebook_groups_channel['draft']) && is_array($facebook_groups_channel['draft']);
        $has_pinterest_scaffold = !empty($pinterest_channel['draft']) && is_array($pinterest_channel['draft']);
        $content_html = (string) ($content_package['content_html'] ?? '');
        $content_pages = is_array($content_package['content_pages'] ?? null) ? $content_package['content_pages'] : [];
        $page_count = !empty($content_pages)
            ? count(array_filter($content_pages, static fn ($page): bool => trim((string) $page) !== ''))
            : max(1, count(array_filter(preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: [])));
        if (empty($content_pages) && $content_html !== '') {
            $content_pages = array_values(array_filter(preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: []));
        }
        $page_flow = $this->normalize_generated_page_flow(
            is_array($content_package['page_flow'] ?? null) ? $content_package['page_flow'] : [],
            $content_pages
        );
        $page_labels = $this->format_article_page_flow_labels($page_flow);
        $word_count = $content_html !== '' ? str_word_count(wp_strip_all_tags($content_html)) : 0;
        $h2_count = $content_html !== '' ? preg_match_all('/<h2\b/i', $content_html) : 0;
        $internal_links = $content_html !== '' ? $this->count_internal_links($content_html) : 0;
        $opening_paragraph = '';
        if ($content_html !== '' && preg_match('/<p>(.*?)<\/p>/is', $content_html, $opening_match)) {
            $opening_paragraph = sanitize_text_field(wp_strip_all_tags((string) ($opening_match[1] ?? '')));
        }
        $headings = [];
        if ($content_html !== '' && preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $content_html, $heading_matches)) {
            $headings = array_values(array_filter(array_map(
                static fn ($heading): string => sanitize_text_field(wp_strip_all_tags((string) $heading)),
                (array) ($heading_matches[1] ?? [])
            )));
        }
        $social_pack = is_array($facebook_channel['selected'] ?? null) ? $facebook_channel['selected'] : [];
        $machine_meta = $this->job_content_machine_meta($job);
        $validator_summary = $this->job_quality_summary($job);

        return array_filter([
            'title'           => sanitize_text_field((string) ($content_package['title'] ?? '')),
            'slug'            => sanitize_title((string) ($content_package['slug'] ?? '')),
            'excerpt'         => sanitize_text_field((string) ($content_package['excerpt'] ?? '')),
            'seo_description' => sanitize_text_field((string) ($content_package['seo_description'] ?? '')),
            'image_prompt'    => sanitize_textarea_field((string) ($content_package['image_prompt'] ?? '')),
            'image_alt'       => sanitize_text_field((string) ($content_package['image_alt'] ?? '')),
            'package_contract'=> sanitize_text_field((string) ($content_package['contract_version'] ?? '')),
            'input_mode'      => sanitize_text_field((string) ($content_package['profile']['input_mode'] ?? '')),
            'rendering_mode'  => sanitize_text_field((string) ($content_package['profile']['rendering_mode'] ?? '')),
            'package_layer'   => sanitize_text_field((string) (($content_package['quality_summary']['stage_status'] ?? '') ?: ($validator_summary['package_quality']['stage_status'] ?? ''))),
            'facebook_contract' => sanitize_text_field((string) ($facebook_channel['contract_version'] ?? '')),
            'facebook_adapter'=> sanitize_text_field((string) ($facebook_channel['profile']['adapter'] ?? '')),
            'facebook_layer'  => sanitize_text_field((string) (($facebook_channel['quality_summary']['pool_quality_status'] ?? '') ?: ($validator_summary['channel_quality']['facebook']['pool_quality_status'] ?? ''))),
            'facebook_groups_contract' => sanitize_text_field((string) ($facebook_groups_channel['contract_version'] ?? '')),
            'facebook_groups_adapter' => sanitize_text_field((string) ($facebook_groups_channel['profile']['adapter'] ?? '')),
            'facebook_groups_ready' => $has_facebook_groups_scaffold && !empty($facebook_groups_channel['draft']) ? __('Prepared', 'kuchnia-twist') : '',
            'pinterest_contract' => sanitize_text_field((string) ($pinterest_channel['contract_version'] ?? '')),
            'pinterest_adapter' => sanitize_text_field((string) ($pinterest_channel['profile']['adapter'] ?? '')),
            'pinterest_ready' => $has_pinterest_scaffold && !empty($pinterest_channel['draft']) ? __('Prepared', 'kuchnia-twist') : '',
            'word_count'      => $word_count,
            'page_count'      => (int) ($validator_summary['page_count'] ?? $page_count),
            'page_labels'     => $page_labels,
            'page_flow'       => $page_flow,
            'h2_count'        => (int) $h2_count,
            'internal_links'  => (int) $internal_links,
            'opening_paragraph' => $opening_paragraph,
            'social_variants' => count($social_pack),
            'shortest_page_words' => (int) ($validator_summary['shortest_page_words'] ?? 0),
            'strong_page_openings' => (int) ($validator_summary['strong_page_openings'] ?? 0),
            'unique_page_labels' => (int) ($validator_summary['unique_page_labels'] ?? 0),
            'strong_page_labels' => (int) ($validator_summary['strong_page_labels'] ?? 0),
            'strong_page_summaries' => (int) ($validator_summary['strong_page_summaries'] ?? 0),
            'unique_social_hooks' => (int) ($validator_summary['unique_social_hooks'] ?? 0),
            'unique_social_openings' => (int) ($validator_summary['unique_social_openings'] ?? 0),
            'unique_social_angles' => (int) ($validator_summary['unique_social_angles'] ?? 0),
            'strong_social_variants' => (int) ($validator_summary['strong_social_variants'] ?? 0),
            'target_pages'    => (int) ($validator_summary['target_pages'] ?? 0),
            'quality_status'  => sanitize_key((string) ($validator_summary['quality_status'] ?? '')),
            'quality_score'   => isset($validator_summary['quality_score']) ? (int) $validator_summary['quality_score'] : 0,
            'editorial_readiness' => sanitize_key((string) ($validator_summary['editorial_readiness'] ?? '')),
            'editorial_highlights' => !empty($validator_summary['editorial_highlights']) && is_array($validator_summary['editorial_highlights']) ? array_values(array_filter(array_map('sanitize_text_field', $validator_summary['editorial_highlights']))) : [],
            'editorial_watchouts' => !empty($validator_summary['editorial_watchouts']) && is_array($validator_summary['editorial_watchouts']) ? array_values(array_filter(array_map('sanitize_text_field', $validator_summary['editorial_watchouts']))) : [],
            'blocking_checks' => !empty($validator_summary['blocking_checks']) && is_array($validator_summary['blocking_checks']) ? $validator_summary['blocking_checks'] : [],
            'warning_checks'  => !empty($validator_summary['warning_checks']) && is_array($validator_summary['warning_checks']) ? $validator_summary['warning_checks'] : [],
            'failed_checks'   => !empty($validator_summary['failed_checks']) && is_array($validator_summary['failed_checks']) ? $validator_summary['failed_checks'] : [],
            'headings'        => $headings,
        ]);
    }

    private function job_recipe_snapshot(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $package = $this->normalized_generated_content_package($generated, $job);
        $recipe = is_array($package['recipe'] ?? null) ? $package['recipe'] : [];
        if (!$recipe) {
            return [];
        }

        $ingredients = array_values(array_filter(array_map(
            static fn ($item): string => sanitize_text_field((string) $item),
            is_array($recipe['ingredients'] ?? null) ? $recipe['ingredients'] : []
        )));
        $instructions = array_values(array_filter(array_map(
            static fn ($item): string => sanitize_text_field((string) $item),
            is_array($recipe['instructions'] ?? null) ? $recipe['instructions'] : []
        )));

        return [
            'prep_time'          => sanitize_text_field((string) ($recipe['prep_time'] ?? '')),
            'cook_time'          => sanitize_text_field((string) ($recipe['cook_time'] ?? '')),
            'total_time'         => sanitize_text_field((string) ($recipe['total_time'] ?? '')),
            'yield'              => sanitize_text_field((string) ($recipe['yield'] ?? '')),
            'ingredients_count'  => count($ingredients),
            'instructions_count' => count($instructions),
            'ingredients'        => $ingredients,
            'instructions'       => $instructions,
        ];
    }

    private function job_distribution_stats(array $job): array
    {
        $distribution = $this->job_facebook_distribution($job);
        $pages = is_array($distribution['pages'] ?? null) ? $distribution['pages'] : [];

        $stats = [
            'total'     => count($pages),
            'completed' => 0,
            'failed'    => 0,
        ];

        foreach ($pages as $page) {
            $status = (string) ($page['status'] ?? '');
            if ($status === 'completed') {
                $stats['completed'] += 1;
                continue;
            }

            if (in_array($status, ['post_failed', 'comment_failed'], true)) {
                $stats['failed'] += 1;
            }
        }

        return $stats;
    }
}
