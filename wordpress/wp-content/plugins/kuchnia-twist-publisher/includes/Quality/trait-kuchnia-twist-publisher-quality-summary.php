<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Summary_Trait
{
    private function quality_status_label(string $status): string
    {
        return match (sanitize_key($status)) {
            'pass'  => __('Quality Pass', 'kuchnia-twist'),
            'warn'  => __('Quality Warn', 'kuchnia-twist'),
            'block' => __('Quality Block', 'kuchnia-twist'),
            default => __('Quality Unknown', 'kuchnia-twist'),
        };
    }

    private function quality_status_class(string $status): string
    {
        $normalized = sanitize_key($status);
        if (!in_array($normalized, ['pass', 'warn', 'block'], true)) {
            return 'quality-warn';
        }

        return 'quality-' . $normalized;
    }

    private function editorial_readiness_label(string $status): string
    {
        return match (sanitize_key($status)) {
            'ready'   => __('Test Ready', 'kuchnia-twist'),
            'review'  => __('Needs Review', 'kuchnia-twist'),
            'blocked' => __('Blocked', 'kuchnia-twist'),
            default   => __('Needs Review', 'kuchnia-twist'),
        };
    }

    private function editorial_readiness_class(string $status): string
    {
        return match (sanitize_key($status)) {
            'ready'   => 'quality-pass',
            'blocked' => 'quality-block',
            default   => 'quality-warn',
        };
    }

    private function build_editorial_readiness_summary(array $args): array
    {
        $quality_status = sanitize_key((string) ($args['quality_status'] ?? 'warn'));
        $quality_score = (int) ($args['quality_score'] ?? 0);
        $title_strong = !empty($args['title_strong']);
        $opening_alignment_score = (int) ($args['opening_alignment_score'] ?? 0);
        $page_count = (int) ($args['page_count'] ?? 1);
        $strong_page_openings = (int) ($args['strong_page_openings'] ?? 0);
        $strong_page_summaries = (int) ($args['strong_page_summaries'] ?? 0);
        $target_pages = (int) ($args['target_pages'] ?? 0);
        $strong_social_variants = (int) ($args['strong_social_variants'] ?? 0);
        $lead_social_score = (int) ($args['lead_social_score'] ?? 0);
        $lead_social_specific = !empty($args['lead_social_specific']);
        $lead_social_front_loaded = !empty($args['lead_social_front_loaded']);
        $lead_social_promise_sync = !empty($args['lead_social_promise_sync']);
        $blocking_checks = !empty($args['blocking_checks']) && is_array($args['blocking_checks']) ? array_values($args['blocking_checks']) : [];
        $warning_checks = !empty($args['warning_checks']) && is_array($args['warning_checks']) ? array_values($args['warning_checks']) : [];

        $readiness = 'review';
        if ($quality_status === 'block') {
            $readiness = 'blocked';
        } elseif (
            $quality_score >= 88
            && $title_strong
            && $opening_alignment_score >= 2
            && $page_count >= 2
            && $page_count <= 3
            && $strong_page_openings >= $page_count
            && $strong_page_summaries >= $page_count
            && $strong_social_variants >= max(1, $target_pages)
            && $lead_social_score >= 18
            && $lead_social_specific
            && $lead_social_front_loaded
            && $lead_social_promise_sync
        ) {
            $readiness = 'ready';
        }

        $highlights = [];
        if ($title_strong && $opening_alignment_score >= 2) {
            $highlights[] = __('Headline and page-one opening land the same promise.', 'kuchnia-twist');
        }
        if ($page_count >= 2 && $page_count <= 3 && $strong_page_summaries >= $page_count) {
            $highlights[] = sprintf(
                __('Article flow feels intentional across %d pages.', 'kuchnia-twist'),
                $page_count
            );
        }
        if ($strong_social_variants >= max(1, $target_pages) && $lead_social_score >= 18) {
            $highlights[] = __('Social pack has a strong lead and enough usable variants.', 'kuchnia-twist');
        }
        if (empty($highlights) && $quality_status !== 'block' && $quality_score >= 75) {
            $highlights[] = __('Core package is usable for live testing.', 'kuchnia-twist');
        }

        $messages = $this->quality_failed_check_messages();
        $watchouts = [];
        foreach (array_slice(array_merge($blocking_checks, $warning_checks), 0, 3) as $failed_check) {
            $watchouts[] = sanitize_text_field((string) ($messages[(string) $failed_check] ?? $this->format_human_label((string) $failed_check)));
        }
        if (empty($watchouts) && $readiness === 'ready') {
            $watchouts[] = __('No major editorial warnings.', 'kuchnia-twist');
        }

        return [
            'editorial_readiness' => $readiness,
            'editorial_highlights' => array_values(array_filter($highlights, static fn ($item): bool => trim((string) $item) !== '')),
            'editorial_watchouts' => array_values(array_filter($watchouts, static fn ($item): bool => trim((string) $item) !== '')),
        ];
    }

    private function job_content_machine_meta(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $request   = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $meta = is_array($generated['content_machine'] ?? null)
            ? $generated['content_machine']
            : (is_array($request['content_machine'] ?? null) ? $request['content_machine'] : []);

        return [
            'prompt_version'      => sanitize_text_field((string) ($meta['prompt_version'] ?? '')),
            'publication_profile' => sanitize_text_field((string) ($meta['publication_profile'] ?? '')),
            'content_preset'      => sanitize_key((string) ($meta['content_preset'] ?? $job['content_type'] ?? '')),
            'validator_summary'   => is_array($meta['validator_summary'] ?? null) ? $meta['validator_summary'] : [],
        ];
    }

    private function job_quality_summary(array $job): array
    {
        $machine_meta = $this->job_content_machine_meta($job);
        $validator_summary = is_array($machine_meta['validator_summary'] ?? null) ? $machine_meta['validator_summary'] : [];
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $calculated = $generated ? $this->build_job_quality_summary($job, $generated) : [];

        $quality_checks = is_array($validator_summary['quality_checks'] ?? null)
            ? $validator_summary['quality_checks']
            : [];
        if (!$quality_checks && !empty($calculated['quality_checks'])) {
            $quality_checks = $calculated['quality_checks'];
        }

        return [
            'quality_score' => isset($validator_summary['quality_score'])
                ? (int) $validator_summary['quality_score']
                : (int) ($calculated['quality_score'] ?? 0),
            'quality_status' => sanitize_key((string) ($validator_summary['quality_status'] ?? ($calculated['quality_status'] ?? ''))),
            'package_quality' => is_array($validator_summary['package_quality'] ?? null)
                ? $validator_summary['package_quality']
                : (array) ($calculated['package_quality'] ?? []),
            'channel_quality' => is_array($validator_summary['channel_quality'] ?? null)
                ? $validator_summary['channel_quality']
                : (array) ($calculated['channel_quality'] ?? []),
            'blocking_checks' => !empty($validator_summary['blocking_checks']) && is_array($validator_summary['blocking_checks'])
                ? $validator_summary['blocking_checks']
                : (array) ($calculated['blocking_checks'] ?? []),
            'warning_checks' => !empty($validator_summary['warning_checks']) && is_array($validator_summary['warning_checks'])
                ? $validator_summary['warning_checks']
                : (array) ($calculated['warning_checks'] ?? []),
            'failed_checks' => !empty($validator_summary['failed_checks']) && is_array($validator_summary['failed_checks'])
                ? $validator_summary['failed_checks']
                : (array) ($calculated['failed_checks'] ?? []),
            'editorial_readiness' => sanitize_key((string) ($validator_summary['editorial_readiness'] ?? ($calculated['editorial_readiness'] ?? ''))),
            'editorial_highlights' => !empty($validator_summary['editorial_highlights']) && is_array($validator_summary['editorial_highlights'])
                ? array_values(array_filter(array_map('sanitize_text_field', $validator_summary['editorial_highlights'])))
                : (array) ($calculated['editorial_highlights'] ?? []),
            'editorial_watchouts' => !empty($validator_summary['editorial_watchouts']) && is_array($validator_summary['editorial_watchouts'])
                ? array_values(array_filter(array_map('sanitize_text_field', $validator_summary['editorial_watchouts'])))
                : (array) ($calculated['editorial_watchouts'] ?? []),
            'quality_checks' => $quality_checks,
            'target_pages' => isset($validator_summary['target_pages'])
                ? (int) $validator_summary['target_pages']
                : (int) ($quality_checks['target_pages'] ?? 0),
            'social_variants' => isset($validator_summary['social_variants'])
                ? (int) $validator_summary['social_variants']
                : (int) ($quality_checks['social_variants'] ?? 0),
            'page_count' => isset($quality_checks['page_count'])
                ? (int) $quality_checks['page_count']
                : 0,
            'shortest_page_words' => isset($quality_checks['shortest_page_words'])
                ? (int) $quality_checks['shortest_page_words']
                : 0,
            'unique_page_labels' => isset($quality_checks['unique_page_labels'])
                ? (int) $quality_checks['unique_page_labels']
                : 0,
            'strong_page_labels' => isset($quality_checks['strong_page_labels'])
                ? (int) $quality_checks['strong_page_labels']
                : 0,
            'strong_page_summaries' => isset($quality_checks['strong_page_summaries'])
                ? (int) $quality_checks['strong_page_summaries']
                : 0,
            'unique_social_hooks' => isset($validator_summary['unique_social_hooks'])
                ? (int) $validator_summary['unique_social_hooks']
                : (int) ($quality_checks['unique_social_hooks'] ?? 0),
            'unique_social_openings' => isset($validator_summary['unique_social_openings'])
                ? (int) $validator_summary['unique_social_openings']
                : (int) ($quality_checks['unique_social_openings'] ?? 0),
            'unique_social_angles' => isset($validator_summary['unique_social_angles'])
                ? (int) $validator_summary['unique_social_angles']
                : (int) ($quality_checks['unique_social_angles'] ?? 0),
            'strong_social_variants' => isset($validator_summary['strong_social_variants'])
                ? (int) $validator_summary['strong_social_variants']
                : (int) ($quality_checks['strong_social_variants'] ?? 0),
            'article_title_score' => isset($validator_summary['article_title_score'])
                ? (int) $validator_summary['article_title_score']
                : (int) ($quality_checks['title_score'] ?? 0),
            'article_title_strong' => array_key_exists('article_title_strong', $validator_summary)
                ? !empty($validator_summary['article_title_strong'])
                : !empty($quality_checks['title_strong']),
            'article_title_front_load_score' => isset($validator_summary['article_title_front_load_score'])
                ? (int) $validator_summary['article_title_front_load_score']
                : (int) ($quality_checks['title_front_load_score'] ?? 0),
            'article_opening_alignment_score' => isset($validator_summary['article_opening_alignment_score'])
                ? (int) $validator_summary['article_opening_alignment_score']
                : (int) ($quality_checks['opening_alignment_score'] ?? 0),
            'article_opening_front_load_score' => isset($validator_summary['article_opening_front_load_score'])
                ? (int) $validator_summary['article_opening_front_load_score']
                : (int) ($quality_checks['opening_front_load_score'] ?? 0),
            'article_excerpt_signal_score' => isset($validator_summary['article_excerpt_signal_score'])
                ? (int) $validator_summary['article_excerpt_signal_score']
                : (int) ($quality_checks['excerpt_signal_score'] ?? 0),
            'article_excerpt_front_load_score' => isset($validator_summary['article_excerpt_front_load_score'])
                ? (int) $validator_summary['article_excerpt_front_load_score']
                : (int) ($quality_checks['excerpt_front_load_score'] ?? 0),
            'article_seo_signal_score' => isset($validator_summary['article_seo_signal_score'])
                ? (int) $validator_summary['article_seo_signal_score']
                : (int) ($quality_checks['seo_signal_score'] ?? 0),
            'article_seo_front_load_score' => isset($validator_summary['article_seo_front_load_score'])
                ? (int) $validator_summary['article_seo_front_load_score']
                : (int) ($quality_checks['seo_front_load_score'] ?? 0),
            'article_excerpt_adds_value' => array_key_exists('article_excerpt_adds_value', $validator_summary)
                ? !empty($validator_summary['article_excerpt_adds_value'])
                : !empty($quality_checks['excerpt_adds_value']),
            'article_opening_adds_value' => array_key_exists('article_opening_adds_value', $validator_summary)
                ? !empty($validator_summary['article_opening_adds_value'])
                : !empty($quality_checks['opening_adds_value']),
            'social_pool_size' => isset($validator_summary['social_pool_size'])
                ? (int) $validator_summary['social_pool_size']
                : (int) ($quality_checks['social_pool_size'] ?? 0),
            'strong_social_candidates' => isset($validator_summary['strong_social_candidates'])
                ? (int) $validator_summary['strong_social_candidates']
                : (int) ($quality_checks['strong_social_candidates'] ?? 0),
            'specific_social_candidates' => isset($validator_summary['specific_social_candidates'])
                ? (int) $validator_summary['specific_social_candidates']
                : (int) ($quality_checks['specific_social_candidates'] ?? 0),
            'unique_hook_form_candidates' => isset($validator_summary['unique_hook_form_candidates'])
                ? (int) $validator_summary['unique_hook_form_candidates']
                : (int) ($quality_checks['unique_hook_form_candidates'] ?? 0),
            'immediacy_social_candidates' => isset($validator_summary['immediacy_social_candidates'])
                ? (int) $validator_summary['immediacy_social_candidates']
                : (int) ($quality_checks['immediacy_social_candidates'] ?? 0),
            'promise_sync_candidates' => isset($validator_summary['promise_sync_candidates'])
                ? (int) $validator_summary['promise_sync_candidates']
                : (int) ($quality_checks['promise_sync_candidates'] ?? 0),
            'relatable_social_candidates' => isset($validator_summary['relatable_social_candidates'])
                ? (int) $validator_summary['relatable_social_candidates']
                : (int) ($quality_checks['relatable_social_candidates'] ?? 0),
            'recognition_social_candidates' => isset($validator_summary['recognition_social_candidates'])
                ? (int) $validator_summary['recognition_social_candidates']
                : (int) ($quality_checks['recognition_social_candidates'] ?? 0),
            'conversation_social_candidates' => isset($validator_summary['conversation_social_candidates'])
                ? (int) $validator_summary['conversation_social_candidates']
                : (int) ($quality_checks['conversation_social_candidates'] ?? 0),
            'savvy_social_candidates' => isset($validator_summary['savvy_social_candidates'])
                ? (int) $validator_summary['savvy_social_candidates']
                : (int) ($quality_checks['savvy_social_candidates'] ?? 0),
            'identity_shift_social_candidates' => isset($validator_summary['identity_shift_social_candidates'])
                ? (int) $validator_summary['identity_shift_social_candidates']
                : (int) ($quality_checks['identity_shift_social_candidates'] ?? 0),
            'proof_social_candidates' => isset($validator_summary['proof_social_candidates'])
                ? (int) $validator_summary['proof_social_candidates']
                : (int) ($quality_checks['proof_social_candidates'] ?? 0),
            'actionable_social_candidates' => isset($validator_summary['actionable_social_candidates'])
                ? (int) $validator_summary['actionable_social_candidates']
                : (int) ($quality_checks['actionable_social_candidates'] ?? 0),
            'consequence_social_candidates' => isset($validator_summary['consequence_social_candidates'])
                ? (int) $validator_summary['consequence_social_candidates']
                : (int) ($quality_checks['consequence_social_candidates'] ?? 0),
            'habit_shift_social_candidates' => isset($validator_summary['habit_shift_social_candidates'])
                ? (int) $validator_summary['habit_shift_social_candidates']
                : (int) ($quality_checks['habit_shift_social_candidates'] ?? 0),
            'focused_social_candidates' => isset($validator_summary['focused_social_candidates'])
                ? (int) $validator_summary['focused_social_candidates']
                : (int) ($quality_checks['focused_social_candidates'] ?? 0),
            'scannable_social_candidates' => isset($validator_summary['scannable_social_candidates'])
                ? (int) $validator_summary['scannable_social_candidates']
                : (int) ($quality_checks['scannable_social_candidates'] ?? 0),
            'two_step_social_candidates' => isset($validator_summary['two_step_social_candidates'])
                ? (int) $validator_summary['two_step_social_candidates']
                : (int) ($quality_checks['two_step_social_candidates'] ?? 0),
            'anchored_social_candidates' => isset($validator_summary['anchored_social_candidates'])
                ? (int) $validator_summary['anchored_social_candidates']
                : (int) ($quality_checks['anchored_social_candidates'] ?? 0),
            'novelty_social_candidates' => isset($validator_summary['novelty_social_candidates'])
                ? (int) $validator_summary['novelty_social_candidates']
                : (int) ($quality_checks['novelty_social_candidates'] ?? 0),
            'front_loaded_social_candidates' => isset($validator_summary['front_loaded_social_candidates'])
                ? (int) $validator_summary['front_loaded_social_candidates']
                : (int) ($quality_checks['front_loaded_social_candidates'] ?? 0),
            'curiosity_social_candidates' => isset($validator_summary['curiosity_social_candidates'])
                ? (int) $validator_summary['curiosity_social_candidates']
                : (int) ($quality_checks['curiosity_social_candidates'] ?? 0),
            'resolution_social_candidates' => isset($validator_summary['resolution_social_candidates'])
                ? (int) $validator_summary['resolution_social_candidates']
                : (int) ($quality_checks['resolution_social_candidates'] ?? 0),
            'contrast_social_candidates' => isset($validator_summary['contrast_social_candidates'])
                ? (int) $validator_summary['contrast_social_candidates']
                : (int) ($quality_checks['contrast_social_candidates'] ?? 0),
            'pain_point_social_candidates' => isset($validator_summary['pain_point_social_candidates'])
                ? (int) $validator_summary['pain_point_social_candidates']
                : (int) ($quality_checks['pain_point_social_candidates'] ?? 0),
            'payoff_social_candidates' => isset($validator_summary['payoff_social_candidates'])
                ? (int) $validator_summary['payoff_social_candidates']
                : (int) ($quality_checks['payoff_social_candidates'] ?? 0),
            'high_scoring_social_candidates' => isset($validator_summary['high_scoring_social_candidates'])
                ? (int) $validator_summary['high_scoring_social_candidates']
                : (int) ($quality_checks['high_scoring_social_candidates'] ?? 0),
            'specific_social_variants' => isset($validator_summary['specific_social_variants'])
                ? (int) $validator_summary['specific_social_variants']
                : (int) ($quality_checks['specific_social_variants'] ?? 0),
            'unique_social_hook_forms' => isset($validator_summary['unique_social_hook_forms'])
                ? (int) $validator_summary['unique_social_hook_forms']
                : (int) ($quality_checks['unique_social_hook_forms'] ?? 0),
            'anchored_variants' => isset($validator_summary['anchored_variants'])
                ? (int) $validator_summary['anchored_variants']
                : (int) ($quality_checks['anchored_variants'] ?? 0),
            'novelty_variants' => isset($validator_summary['novelty_variants'])
                ? (int) $validator_summary['novelty_variants']
                : (int) ($quality_checks['novelty_variants'] ?? 0),
            'relatable_variants' => isset($validator_summary['relatable_variants'])
                ? (int) $validator_summary['relatable_variants']
                : (int) ($quality_checks['relatable_variants'] ?? 0),
            'recognition_variants' => isset($validator_summary['recognition_variants'])
                ? (int) $validator_summary['recognition_variants']
                : (int) ($quality_checks['recognition_variants'] ?? 0),
            'conversation_variants' => isset($validator_summary['conversation_variants'])
                ? (int) $validator_summary['conversation_variants']
                : (int) ($quality_checks['conversation_variants'] ?? 0),
            'savvy_variants' => isset($validator_summary['savvy_variants'])
                ? (int) $validator_summary['savvy_variants']
                : (int) ($quality_checks['savvy_variants'] ?? 0),
            'identity_shift_variants' => isset($validator_summary['identity_shift_variants'])
                ? (int) $validator_summary['identity_shift_variants']
                : (int) ($quality_checks['identity_shift_variants'] ?? 0),
            'proof_variants' => isset($validator_summary['proof_variants'])
                ? (int) $validator_summary['proof_variants']
                : (int) ($quality_checks['proof_variants'] ?? 0),
            'actionable_variants' => isset($validator_summary['actionable_variants'])
                ? (int) $validator_summary['actionable_variants']
                : (int) ($quality_checks['actionable_variants'] ?? 0),
            'immediacy_variants' => isset($validator_summary['immediacy_variants'])
                ? (int) $validator_summary['immediacy_variants']
                : (int) ($quality_checks['immediacy_variants'] ?? 0),
            'promise_sync_variants' => isset($validator_summary['promise_sync_variants'])
                ? (int) $validator_summary['promise_sync_variants']
                : (int) ($quality_checks['promise_sync_variants'] ?? 0),
            'consequence_variants' => isset($validator_summary['consequence_variants'])
                ? (int) $validator_summary['consequence_variants']
                : (int) ($quality_checks['consequence_variants'] ?? 0),
            'habit_shift_variants' => isset($validator_summary['habit_shift_variants'])
                ? (int) $validator_summary['habit_shift_variants']
                : (int) ($quality_checks['habit_shift_variants'] ?? 0),
            'focused_variants' => isset($validator_summary['focused_variants'])
                ? (int) $validator_summary['focused_variants']
                : (int) ($quality_checks['focused_variants'] ?? 0),
            'scannable_variants' => isset($validator_summary['scannable_variants'])
                ? (int) $validator_summary['scannable_variants']
                : (int) ($quality_checks['scannable_variants'] ?? 0),
            'two_step_variants' => isset($validator_summary['two_step_variants'])
                ? (int) $validator_summary['two_step_variants']
                : (int) ($quality_checks['two_step_variants'] ?? 0),
            'front_loaded_social_variants' => isset($validator_summary['front_loaded_social_variants'])
                ? (int) $validator_summary['front_loaded_social_variants']
                : (int) ($quality_checks['front_loaded_social_variants'] ?? 0),
            'curiosity_variants' => isset($validator_summary['curiosity_variants'])
                ? (int) $validator_summary['curiosity_variants']
                : (int) ($quality_checks['curiosity_variants'] ?? 0),
            'resolution_variants' => isset($validator_summary['resolution_variants'])
                ? (int) $validator_summary['resolution_variants']
                : (int) ($quality_checks['resolution_variants'] ?? 0),
            'contrast_variants' => isset($validator_summary['contrast_variants'])
                ? (int) $validator_summary['contrast_variants']
                : (int) ($quality_checks['contrast_variants'] ?? 0),
            'pain_point_variants' => isset($validator_summary['pain_point_variants'])
                ? (int) $validator_summary['pain_point_variants']
                : (int) ($quality_checks['pain_point_variants'] ?? 0),
            'payoff_variants' => isset($validator_summary['payoff_variants'])
                ? (int) $validator_summary['payoff_variants']
                : (int) ($quality_checks['payoff_variants'] ?? 0),
            'selected_social_average_score' => isset($validator_summary['selected_social_average_score'])
                ? (float) $validator_summary['selected_social_average_score']
                : (float) ($quality_checks['selected_social_average_score'] ?? 0),
            'lead_social_score' => isset($validator_summary['lead_social_score'])
                ? (int) $validator_summary['lead_social_score']
                : (int) ($quality_checks['lead_social_score'] ?? 0),
            'lead_social_hook_form' => !empty($validator_summary['lead_social_hook_form'])
                ? sanitize_key((string) $validator_summary['lead_social_hook_form'])
                : sanitize_key((string) ($quality_checks['lead_social_hook_form'] ?? '')),
            'lead_social_specific' => array_key_exists('lead_social_specific', $validator_summary)
                ? !empty($validator_summary['lead_social_specific'])
                : !empty($quality_checks['lead_social_specific']),
            'lead_social_anchored' => array_key_exists('lead_social_anchored', $validator_summary)
                ? !empty($validator_summary['lead_social_anchored'])
                : !empty($quality_checks['lead_social_anchored']),
            'lead_social_novelty' => array_key_exists('lead_social_novelty', $validator_summary)
                ? !empty($validator_summary['lead_social_novelty'])
                : !empty($quality_checks['lead_social_novelty']),
            'lead_social_relatable' => array_key_exists('lead_social_relatable', $validator_summary)
                ? !empty($validator_summary['lead_social_relatable'])
                : !empty($quality_checks['lead_social_relatable']),
            'lead_social_recognition' => array_key_exists('lead_social_recognition', $validator_summary)
                ? !empty($validator_summary['lead_social_recognition'])
                : !empty($quality_checks['lead_social_recognition']),
            'lead_social_conversation' => array_key_exists('lead_social_conversation', $validator_summary)
                ? !empty($validator_summary['lead_social_conversation'])
                : !empty($quality_checks['lead_social_conversation']),
            'lead_social_savvy' => array_key_exists('lead_social_savvy', $validator_summary)
                ? !empty($validator_summary['lead_social_savvy'])
                : !empty($quality_checks['lead_social_savvy']),
            'lead_social_identity_shift' => array_key_exists('lead_social_identity_shift', $validator_summary)
                ? !empty($validator_summary['lead_social_identity_shift'])
                : !empty($quality_checks['lead_social_identity_shift']),
            'lead_social_proof' => array_key_exists('lead_social_proof', $validator_summary)
                ? !empty($validator_summary['lead_social_proof'])
                : !empty($quality_checks['lead_social_proof']),
            'lead_social_actionable' => array_key_exists('lead_social_actionable', $validator_summary)
                ? !empty($validator_summary['lead_social_actionable'])
                : !empty($quality_checks['lead_social_actionable']),
            'lead_social_immediacy' => array_key_exists('lead_social_immediacy', $validator_summary)
                ? !empty($validator_summary['lead_social_immediacy'])
                : !empty($quality_checks['lead_social_immediacy']),
            'lead_social_promise_sync' => array_key_exists('lead_social_promise_sync', $validator_summary)
                ? !empty($validator_summary['lead_social_promise_sync'])
                : !empty($quality_checks['lead_social_promise_sync']),
            'lead_social_consequence' => array_key_exists('lead_social_consequence', $validator_summary)
                ? !empty($validator_summary['lead_social_consequence'])
                : !empty($quality_checks['lead_social_consequence']),
            'lead_social_habit_shift' => array_key_exists('lead_social_habit_shift', $validator_summary)
                ? !empty($validator_summary['lead_social_habit_shift'])
                : !empty($quality_checks['lead_social_habit_shift']),
            'lead_social_focused' => array_key_exists('lead_social_focused', $validator_summary)
                ? !empty($validator_summary['lead_social_focused'])
                : !empty($quality_checks['lead_social_focused']),
            'lead_social_scannable' => array_key_exists('lead_social_scannable', $validator_summary)
                ? !empty($validator_summary['lead_social_scannable'])
                : !empty($quality_checks['lead_social_scannable']),
            'lead_social_two_step' => array_key_exists('lead_social_two_step', $validator_summary)
                ? !empty($validator_summary['lead_social_two_step'])
                : !empty($quality_checks['lead_social_two_step']),
            'lead_social_curiosity' => array_key_exists('lead_social_curiosity', $validator_summary)
                ? !empty($validator_summary['lead_social_curiosity'])
                : !empty($quality_checks['lead_social_curiosity']),
            'lead_social_resolved' => array_key_exists('lead_social_resolved', $validator_summary)
                ? !empty($validator_summary['lead_social_resolved'])
                : !empty($quality_checks['lead_social_resolved']),
            'lead_social_contrast' => array_key_exists('lead_social_contrast', $validator_summary)
                ? !empty($validator_summary['lead_social_contrast'])
                : !empty($quality_checks['lead_social_contrast']),
            'lead_social_front_loaded' => array_key_exists('lead_social_front_loaded', $validator_summary)
                ? !empty($validator_summary['lead_social_front_loaded'])
                : !empty($quality_checks['lead_social_front_loaded']),
            'lead_social_pain_point' => array_key_exists('lead_social_pain_point', $validator_summary)
                ? !empty($validator_summary['lead_social_pain_point'])
                : !empty($quality_checks['lead_social_pain_point']),
            'lead_social_payoff' => array_key_exists('lead_social_payoff', $validator_summary)
                ? !empty($validator_summary['lead_social_payoff'])
                : !empty($quality_checks['lead_social_payoff']),
            'distribution_source' => sanitize_key((string) ($validator_summary['distribution_source'] ?? '')),
        ];
    }
}
