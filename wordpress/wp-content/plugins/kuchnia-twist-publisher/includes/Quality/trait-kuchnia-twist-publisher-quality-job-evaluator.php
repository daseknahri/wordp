<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Job_Evaluator_Trait
{
    private function build_job_quality_summary(array $job, array $generated, array $overrides = []): array
    {
        $settings        = $this->get_settings();
        $content_package = $this->normalized_generated_content_package($generated, $job);
        $channels        = $this->generated_channels($generated, $job);
        $facebook_channel = is_array($channels['facebook'] ?? null) ? $channels['facebook'] : [];
        $content_type    = sanitize_key((string) ($overrides['content_type'] ?? $content_package['content_type'] ?? $generated['content_type'] ?? $job['content_type'] ?? 'recipe'));
        $title           = sanitize_text_field((string) ($overrides['title'] ?? $content_package['title'] ?? $generated['title'] ?? ''));
        $slug            = sanitize_title((string) ($overrides['slug'] ?? $content_package['slug'] ?? $generated['slug'] ?? ''));
        $excerpt         = sanitize_text_field((string) ($overrides['excerpt'] ?? $content_package['excerpt'] ?? $generated['excerpt'] ?? ''));
        $seo_description = sanitize_text_field((string) ($overrides['seo_description'] ?? $content_package['seo_description'] ?? $generated['seo_description'] ?? ''));
        $content_html    = (string) ($overrides['content_html'] ?? $content_package['content_html'] ?? $generated['content_html'] ?? '');
        $content_pages   = is_array($content_package['content_pages'] ?? null) ? $content_package['content_pages'] : [];
        $selected_pages  = $this->job_selected_pages($job);
        $social_candidates = is_array($facebook_channel['candidates'] ?? null) ? $facebook_channel['candidates'] : [];
        $social_pack     = is_array($facebook_channel['selected'] ?? null) ? $facebook_channel['selected'] : [];
        $recipe          = is_array($content_package['recipe'] ?? null) ? $content_package['recipe'] : [];
        $featured_image  = isset($overrides['featured_image_id']) ? (int) $overrides['featured_image_id'] : (int) ($job['featured_image_id'] ?: $job['blog_image_id']);
        $facebook_image  = isset($overrides['facebook_image_id']) ? (int) $overrides['facebook_image_id'] : (int) ($job['facebook_image_result_id'] ?: $job['facebook_image_id'] ?: $featured_image);
        $minimum_words   = [
            'recipe'     => 1200,
            'food_fact'  => 1100,
            'food_story' => 1100,
        ][$content_type] ?? 1100;
        $contract_meta = $this->resolve_contract_job_flags($generated);
        $typed_contract_job = !empty($contract_meta['typed_contract_job']);
        $contract_checks = $typed_contract_job
            ? $this->canonical_contract_checks($generated, $job, count($selected_pages))
            : [
                'package_contract_enforced' => false,
                'channel_contract_enforced' => false,
                'warning_checks' => [],
            ];
        $strict_contract_mode = ($settings['strict_contract_mode'] ?? '0') === '1';
        $contract_blocking_checks = ($strict_contract_mode && $typed_contract_job)
            ? array_values(array_filter($contract_checks['warning_checks'], static fn (string $check): bool => str_ends_with($check, '_contract_drift')))
            : [];
        if (empty($content_pages) && $content_html !== '') {
            $content_pages = array_values(array_filter(preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: []));
        }
        $page_flow = $this->normalize_generated_page_flow(
            is_array($content_package['page_flow'] ?? null) ? $content_package['page_flow'] : [],
            $content_pages
        );
        $page_word_counts = array_values(array_filter(array_map(
            static fn ($page): int => str_word_count(wp_strip_all_tags((string) $page)),
            $content_pages
        )));
        $page_count      = !empty($content_pages) ? count($content_pages) : 1;
        $shortest_page_words = !empty($page_word_counts) ? min($page_word_counts) : 0;
        $strong_page_openings = 0;
        foreach ($content_pages as $page_index => $page_html) {
            if ($this->page_starts_with_expected_lead((string) $page_html, (int) $page_index)) {
                $strong_page_openings++;
            }
        }
        $page_label_fingerprints = array_values(array_filter(array_map(
            fn ($page): string => $this->normalize_page_flow_label_fingerprint((string) ((is_array($page) ? ($page['label'] ?? '') : ''))),
            $page_flow
        )));
        $unique_page_labels = count(array_unique($page_label_fingerprints));
        $strong_page_labels = count(array_filter($page_flow, fn ($page): bool => is_array($page) && $this->page_flow_label_looks_strong((string) ($page['label'] ?? ''), (int) ($page['index'] ?? 0))));
        $strong_page_summaries = count(array_filter($page_flow, fn ($page): bool => is_array($page) && $this->page_flow_summary_looks_strong((string) ($page['summary'] ?? ''), (string) ($page['label'] ?? ''))));
        $word_count      = str_word_count(wp_strip_all_tags($content_html));
        $h2_count        = substr_count(strtolower($content_html), '<h2');
        $internal_links  = $this->count_internal_links($content_html);
        $excerpt_words   = str_word_count($excerpt);
        $seo_words       = str_word_count($seo_description);
        $opening_paragraph = $this->extract_opening_paragraph_text($content_html);
        $title_score = $this->headline_specificity_score($title, $content_type, (string) ($job['topic'] ?? ''));
        $title_strong = $this->title_looks_strong($title, (string) ($job['topic'] ?? ''), $content_type);
        $title_front_load_score = $this->front_loaded_click_signal_score($title, $content_type);
        $excerpt_front_load_score = $this->front_loaded_click_signal_score($excerpt, $content_type);
        $seo_front_load_score = $this->front_loaded_click_signal_score($seo_description, $content_type);
        $opening_front_load_score = $this->front_loaded_click_signal_score($opening_paragraph, $content_type);
        $opening_alignment_score = $this->opening_promise_alignment_score($title, $opening_paragraph);
        $excerpt_adds_value = $this->excerpt_adds_new_value($title, $excerpt);
        $opening_adds_value = $this->opening_paragraph_adds_new_value($content_html, $title, $excerpt);
        $excerpt_signal_score = $this->excerpt_click_signal_score($excerpt, $title, $opening_paragraph);
        $seo_signal_score = $this->seo_description_signal_score($seo_description, $title, $excerpt);
        $recipe_complete = $content_type !== 'recipe' || (!empty($recipe['ingredients']) && !empty($recipe['instructions']));
        $image_ready     = $settings['image_generation_mode'] === 'manual_only'
            ? ($featured_image > 0 && $facebook_image > 0)
            : ($featured_image > 0 && $facebook_image > 0);
        $target_pages    = count($selected_pages);
        $social_variants = count($social_pack);
        $unique_variants = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_variant_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_hooks = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_hook_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_openings = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_opening_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_angles = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalize_hook_angle_key((string) ((is_array($variant) ? ($variant['angle_key'] ?? $variant['angleKey'] ?? '') : '')), $content_type),
            $social_pack
        ))));
        $unique_hook_form_candidates = count(array_unique(array_filter(array_map(
            fn ($variant): string => is_array($variant) ? $this->classify_social_hook_form($variant) : '',
            $social_candidates
        ))));
        $unique_hook_forms = count(array_unique(array_filter(array_map(
            fn ($variant): string => is_array($variant) ? $this->classify_social_hook_form($variant) : '',
            $social_pack
        ))));
        $social_pool_size = count(array_filter($social_candidates, 'is_array'));
        $strong_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && !$this->social_variant_looks_weak($variant, $title, $content_type, $excerpt)));
        $specific_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_specificity_score($variant, $title, $excerpt, $content_type) >= 2));
        $anchored_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_anchor_signal($variant, $title, $excerpt)));
        $novelty_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_novelty_score($variant, $title, $excerpt, $content_type) >= 2));
        $relatable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_relatability_signal($variant, $title, $excerpt, $content_type)));
        $recognition_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_self_recognition_signal($variant, $title, $excerpt, $content_type)));
        $conversation_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_conversation_signal($variant, $title, $excerpt, $content_type)));
        $savvy_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_savvy_signal($variant, $title, $excerpt, $content_type)));
        $identity_shift_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_identity_shift_signal($variant, $title, $excerpt, $content_type)));
        $proof_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_proof_signal($variant, $title, $excerpt, $content_type)));
        $actionable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_actionability_signal($variant, $title, $excerpt, $content_type)));
        $immediacy_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_immediacy_signal($variant, $title, $excerpt, $content_type)));
        $consequence_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_consequence_signal($variant, $title, $excerpt, $content_type)));
        $habit_shift_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_habit_shift_signal($variant, $title, $excerpt, $content_type)));
        $focused_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_focus_signal($variant, $title, $excerpt, $content_type)));
        $promise_sync_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_promise_sync_signal($variant, $title, $excerpt, $content_type)));
        $scannable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_scannability_signal($variant, $content_type)));
        $two_step_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_two_step_signal($variant, $title, $excerpt, $content_type)));
        $front_loaded_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_front_loaded_signal($variant, $content_type)));
        $curiosity_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_curiosity_signal($variant, $title, $excerpt, $content_type)));
        $resolution_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_resolves_early($variant, $title, $excerpt, $content_type)));
        $contrast_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_contrast_signal($variant)));
        $pain_point_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_pain_point_signal($variant)));
        $payoff_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_payoff_signal($variant)));
        $high_scoring_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_score($variant, $title, $excerpt, $content_type) >= 18));
        $strong_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && !$this->social_variant_looks_weak($variant, $title, $content_type, $excerpt)));
        $specific_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_specificity_score($variant, $title, $excerpt, $content_type) >= 2));
        $anchored_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_anchor_signal($variant, $title, $excerpt)));
        $novelty_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_novelty_score($variant, $title, $excerpt, $content_type) >= 2));
        $relatable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_relatability_signal($variant, $title, $excerpt, $content_type)));
        $recognition_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_self_recognition_signal($variant, $title, $excerpt, $content_type)));
        $conversation_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_conversation_signal($variant, $title, $excerpt, $content_type)));
        $savvy_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_savvy_signal($variant, $title, $excerpt, $content_type)));
        $identity_shift_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_identity_shift_signal($variant, $title, $excerpt, $content_type)));
        $proof_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_proof_signal($variant, $title, $excerpt, $content_type)));
        $actionable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_actionability_signal($variant, $title, $excerpt, $content_type)));
        $immediacy_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_immediacy_signal($variant, $title, $excerpt, $content_type)));
        $consequence_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_consequence_signal($variant, $title, $excerpt, $content_type)));
        $habit_shift_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_habit_shift_signal($variant, $title, $excerpt, $content_type)));
        $focused_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_focus_signal($variant, $title, $excerpt, $content_type)));
        $promise_sync_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_promise_sync_signal($variant, $title, $excerpt, $content_type)));
        $scannable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_scannability_signal($variant, $content_type)));
        $two_step_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_two_step_signal($variant, $title, $excerpt, $content_type)));
        $curiosity_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_curiosity_signal($variant, $title, $excerpt, $content_type)));
        $resolution_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_resolves_early($variant, $title, $excerpt, $content_type)));
        $contrast_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_contrast_signal($variant)));
        $front_loaded_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_front_loaded_signal($variant, $content_type)));
        $pain_point_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_pain_point_signal($variant)));
        $payoff_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_payoff_signal($variant)));
        $selected_social_scores = array_values(array_filter(array_map(
            fn ($variant): ?int => is_array($variant) ? $this->social_variant_score($variant, $title, $excerpt, $content_type) : null,
            $social_pack
        ), static fn ($score): bool => is_numeric($score)));
        $selected_social_average_score = !empty($selected_social_scores)
            ? round(array_sum($selected_social_scores) / max(1, count($selected_social_scores)), 1)
            : 0;
        $lead_variant = !empty($social_pack[0]) && is_array($social_pack[0]) ? $social_pack[0] : [];
        $lead_social_score = !empty($lead_variant) ? $this->social_variant_score($lead_variant, $title, $excerpt, $content_type) : 0;
        $lead_social_hook_form = !empty($lead_variant) ? $this->classify_social_hook_form($lead_variant) : '';
        $lead_social_specific = !empty($lead_variant) && $this->social_variant_specificity_score($lead_variant, $title, $excerpt, $content_type) >= 2;
        $lead_social_anchored = !empty($lead_variant) && $this->social_variant_anchor_signal($lead_variant, $title, $excerpt);
        $lead_social_novelty = !empty($lead_variant) && $this->social_variant_novelty_score($lead_variant, $title, $excerpt, $content_type) >= 2;
        $lead_social_relatable = !empty($lead_variant) && $this->social_variant_relatability_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_recognition = !empty($lead_variant) && $this->social_variant_self_recognition_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_conversation = !empty($lead_variant) && $this->social_variant_conversation_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_savvy = !empty($lead_variant) && $this->social_variant_savvy_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_identity_shift = !empty($lead_variant) && $this->social_variant_identity_shift_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_proof = !empty($lead_variant) && $this->social_variant_proof_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_actionable = !empty($lead_variant) && $this->social_variant_actionability_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_immediacy = !empty($lead_variant) && $this->social_variant_immediacy_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_consequence = !empty($lead_variant) && $this->social_variant_consequence_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_habit_shift = !empty($lead_variant) && $this->social_variant_habit_shift_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_focused = !empty($lead_variant) && $this->social_variant_focus_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_promise_sync = !empty($lead_variant) && $this->social_variant_promise_sync_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_scannable = !empty($lead_variant) && $this->social_variant_scannability_signal($lead_variant, $content_type);
        $lead_social_two_step = !empty($lead_variant) && $this->social_variant_two_step_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_curiosity = !empty($lead_variant) && $this->social_variant_curiosity_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_resolved = !empty($lead_variant) && $this->social_variant_resolves_early($lead_variant, $title, $excerpt, $content_type);
        $lead_social_contrast = !empty($lead_variant) && $this->social_variant_contrast_signal($lead_variant);
        $lead_social_front_loaded = !empty($lead_variant) && $this->social_variant_front_loaded_signal($lead_variant, $content_type);
        $lead_social_pain_point = !empty($lead_variant) && $this->social_variant_pain_point_signal($lead_variant);
        $lead_social_payoff = !empty($lead_variant) && $this->social_variant_payoff_signal($lead_variant);
        $duplicate_risk = $title === '' || $slug === ''
            ? false
            : ($this->find_conflicting_post_id($slug, (int) ($job['post_id'] ?? 0)) > 0 || $this->find_conflicting_post_id($title, (int) ($job['post_id'] ?? 0)) > 0);

        $blocking_checks = $contract_blocking_checks;
        $warning_checks = array_values(array_filter($contract_checks['warning_checks'], static fn (string $check): bool => !in_array($check, $contract_blocking_checks, true)));
        if ($title === '' || $slug === '' || trim(wp_strip_all_tags($content_html)) === '') {
            $blocking_checks[] = 'missing_core_fields';
        }
        if ($content_type === 'recipe' && !$recipe_complete) {
            $blocking_checks[] = 'missing_recipe';
        }
        if ($settings['image_generation_mode'] === 'manual_only' && (!$featured_image || !$facebook_image)) {
            $blocking_checks[] = 'missing_manual_images';
        }
        if ($duplicate_risk) {
            $blocking_checks[] = 'duplicate_conflict';
        }
        if ($target_pages < 1) {
            $blocking_checks[] = 'missing_target_pages';
        }
        if ($word_count < $minimum_words) {
            $warning_checks[] = 'thin_content';
        }
        if (!$title_strong || $title_score < 3) {
            $warning_checks[] = 'weak_title';
        }
        if ($excerpt_words < 12 || !$excerpt_adds_value || $excerpt_signal_score < 3) {
            $warning_checks[] = 'weak_excerpt';
        }
        if ($seo_words < 12 || $seo_signal_score < 3) {
            $warning_checks[] = 'weak_seo';
        }
        if ($opening_alignment_score < 2 || !$opening_adds_value) {
            $warning_checks[] = 'weak_title_alignment';
        }
        if ($page_count < 2 || $page_count > 3) {
            $warning_checks[] = 'weak_pagination';
        }
        if ($page_count > 1 && $shortest_page_words > 0 && $shortest_page_words < 140) {
            $warning_checks[] = 'weak_page_balance';
        }
        if ($page_count > 1 && $strong_page_openings < $page_count) {
            $warning_checks[] = 'weak_page_openings';
        }
        if ($page_count > 1 && count($page_flow) < $page_count) {
            $warning_checks[] = 'weak_page_flow';
        }
        if ($page_count > 1 && $strong_page_labels < $page_count) {
            $warning_checks[] = 'weak_page_labels';
        }
        if ($page_count > 1 && $unique_page_labels < $page_count) {
            $warning_checks[] = 'repetitive_page_labels';
        }
        if ($page_count > 1 && $strong_page_summaries < $page_count) {
            $warning_checks[] = 'weak_page_summaries';
        }
        if ($h2_count < 2) {
            $warning_checks[] = 'weak_structure';
        }
        if ($internal_links < 3) {
            $warning_checks[] = 'missing_internal_links';
        }
        if ($social_variants < max(1, $target_pages)) {
            $warning_checks[] = 'social_pack_incomplete';
        }
        if ($social_variants > 0 && $unique_variants < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_pack_repetitive';
        }
        if ($social_variants > 1 && $unique_hooks < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_hooks_repetitive';
        }
        if ($social_variants > 1 && $unique_openings < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_openings_repetitive';
        }
        if ($target_pages > 1 && $unique_angles < min($target_pages, count($this->social_angle_presets($content_type)))) {
            $warning_checks[] = 'social_angles_repetitive';
        }
        if ($target_pages > 1 && $unique_hook_forms < max(2, min(3, $target_pages))) {
            $warning_checks[] = 'social_hook_forms_thin';
        }
        if ($strong_social_variants < max(1, $target_pages)) {
            $warning_checks[] = 'weak_social_copy';
        }
        if ($target_pages > 0 && ($lead_social_score < 16 || !$lead_social_specific || !$lead_social_anchored || !$lead_social_novelty || !$lead_social_relatable || !$lead_social_recognition || !$lead_social_focused || !$lead_social_promise_sync || !$lead_social_scannable || !$lead_social_two_step || (($lead_social_curiosity || $lead_social_contrast) && !$lead_social_resolved) || (!$lead_social_pain_point && !$lead_social_payoff && !$lead_social_consequence && !$lead_social_habit_shift && !$lead_social_savvy && !$lead_social_identity_shift) || !$lead_social_front_loaded)) {
            $warning_checks[] = 'weak_social_lead';
        }
        if ($specific_social_variants < max(1, min(max(1, $target_pages), 2))) {
            $warning_checks[] = 'social_specificity_thin';
        }
        if ($target_pages > 0 && $anchored_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_anchor_thin';
        }
        if ($target_pages > 0 && $novelty_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_novelty_thin';
        }
        if ($target_pages > 1 && $relatable_variants < 1) {
            $warning_checks[] = 'social_relatability_thin';
        }
        if ($target_pages > 1 && $recognition_variants < 1) {
            $warning_checks[] = 'social_recognition_thin';
        }
        if ($target_pages > 1 && $conversation_variants < 1) {
            $warning_checks[] = 'social_conversation_thin';
        }
        if ($target_pages > 1 && $savvy_variants < 1) {
            $warning_checks[] = 'social_savvy_thin';
        }
        if ($target_pages > 1 && $identity_shift_variants < 1) {
            $warning_checks[] = 'social_identity_shift_thin';
        }
        if ($target_pages > 0 && $front_loaded_social_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_front_load_thin';
        }
        if ($target_pages > 1 && $curiosity_variants < 1) {
            $warning_checks[] = 'social_curiosity_thin';
        }
        if ($target_pages > 1 && $resolution_variants < 1) {
            $warning_checks[] = 'social_resolution_thin';
        }
        if ($target_pages > 1 && $contrast_variants < 1) {
            $warning_checks[] = 'social_contrast_thin';
        }
        if ($target_pages > 1 && $pain_point_variants < 1) {
            $warning_checks[] = 'social_pain_points_thin';
        }
        if ($target_pages > 1 && $payoff_variants < 1) {
            $warning_checks[] = 'social_payoffs_thin';
        }
        if ($target_pages > 1 && $proof_variants < 1) {
            $warning_checks[] = 'social_proof_thin';
        }
        if ($target_pages > 1 && $actionable_variants < 1) {
            $warning_checks[] = 'social_actionability_thin';
        }
        if ($target_pages > 1 && $immediacy_variants < 1) {
            $warning_checks[] = 'social_immediacy_thin';
        }
        if ($target_pages > 1 && $consequence_variants < 1) {
            $warning_checks[] = 'social_consequence_thin';
        }
        if ($target_pages > 1 && $habit_shift_variants < 1) {
            $warning_checks[] = 'social_habit_shift_thin';
        }
        if ($target_pages > 1 && $focused_variants < 1) {
            $warning_checks[] = 'social_focus_thin';
        }
        if ($target_pages > 1 && $promise_sync_variants < 1) {
            $warning_checks[] = 'social_promise_sync_thin';
        }
        if ($target_pages > 1 && $scannable_variants < 1) {
            $warning_checks[] = 'social_scannability_thin';
        }
        if ($target_pages > 1 && $two_step_variants < 1) {
            $warning_checks[] = 'social_two_step_thin';
        }
        if (!$image_ready) {
            $warning_checks[] = 'image_not_ready';
        }

        $score = 100;
        $penalties = [
            'missing_core_fields'    => 35,
            'missing_recipe'         => 25,
            'missing_manual_images'  => 20,
            'duplicate_conflict'     => 30,
            'missing_target_pages'   => 25,
            'thin_content'           => 15,
            'weak_title'             => 8,
            'weak_excerpt'           => 8,
            'weak_seo'               => 8,
            'weak_title_alignment'   => 7,
            'weak_pagination'        => 8,
            'weak_page_balance'      => 7,
            'weak_page_openings'     => 6,
            'weak_page_flow'         => 6,
            'weak_page_labels'       => 5,
            'repetitive_page_labels' => 5,
            'weak_page_summaries'    => 5,
            'weak_structure'         => 10,
            'missing_internal_links' => 9,
            'social_pack_incomplete' => 12,
            'social_pack_repetitive' => 10,
            'social_hooks_repetitive' => 8,
            'social_openings_repetitive' => 8,
            'social_angles_repetitive' => 8,
            'social_hook_forms_thin' => 5,
            'weak_social_copy'        => 10,
            'weak_social_lead'       => 8,
            'social_specificity_thin' => 8,
            'social_anchor_thin' => 7,
            'social_novelty_thin' => 7,
            'social_relatability_thin' => 6,
            'social_recognition_thin' => 6,
            'social_conversation_thin' => 6,
            'social_savvy_thin' => 6,
            'social_identity_shift_thin' => 6,
            'social_proof_thin' => 6,
            'social_actionability_thin' => 6,
            'social_immediacy_thin' => 6,
            'social_consequence_thin' => 6,
            'social_habit_shift_thin' => 6,
            'social_focus_thin' => 6,
            'social_promise_sync_thin' => 6,
            'social_scannability_thin' => 6,
            'social_two_step_thin' => 6,
            'social_front_load_thin' => 7,
            'social_curiosity_thin' => 6,
            'social_resolution_thin' => 6,
            'social_contrast_thin' => 6,
            'social_pain_points_thin' => 6,
            'social_payoffs_thin'   => 6,
            'image_not_ready'        => 8,
            'package_contract_drift' => 6,
            'facebook_adapter_contract_drift' => 5,
            'facebook_groups_adapter_contract_drift' => 3,
            'pinterest_adapter_contract_drift' => 3,
        ];
        foreach (array_merge($blocking_checks, $warning_checks) as $failed_check) {
            $score -= (int) ($penalties[$failed_check] ?? 0);
        }
        $score = max(0, $score);
        $blocking_checks = array_values(array_unique($blocking_checks));
        $warning_checks = array_values(array_unique($warning_checks));
        $failed_checks = array_values(array_unique(array_merge($blocking_checks, $warning_checks)));
        $quality_status = !empty($blocking_checks)
            ? 'block'
            : ((!empty($warning_checks) || $score < self::QUALITY_SCORE_THRESHOLD) ? 'warn' : 'pass');
        $editorial_summary = $this->build_editorial_readiness_summary([
            'quality_status' => $quality_status,
            'quality_score' => $score,
            'title_strong' => $title_strong,
            'opening_alignment_score' => $opening_alignment_score,
            'page_count' => $page_count,
            'strong_page_openings' => $strong_page_openings,
            'strong_page_summaries' => $strong_page_summaries,
            'target_pages' => $target_pages,
            'strong_social_variants' => $strong_social_variants,
            'lead_social_score' => $lead_social_score,
            'lead_social_specific' => $lead_social_specific,
            'lead_social_front_loaded' => $lead_social_front_loaded,
            'lead_social_promise_sync' => $lead_social_promise_sync,
            'blocking_checks' => $blocking_checks,
            'warning_checks' => $warning_checks,
        ]);

        return [
            'quality_score'   => $score,
            'quality_status'  => $quality_status,
            'blocking_checks' => $blocking_checks,
            'warning_checks'  => $warning_checks,
            'failed_checks'   => $failed_checks,
            'package_quality' => [
                'layer' => 'article',
                'contract_version' => sanitize_text_field((string) ($content_package['contract_version'] ?? ($this->generated_contract_versions($generated)['content_package'] ?? ''))),
                'contract_enforced' => !empty($contract_checks['package_contract_enforced']),
                'contract_warning' => in_array('package_contract_drift', $warning_checks, true),
                'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'package_contract_drift')),
                'stage_status' => sanitize_key((string) ($content_package['quality_summary']['stage_status'] ?? '')),
                'stage_checks' => is_array($content_package['quality_summary']['stage_checks'] ?? null) ? $content_package['quality_summary']['stage_checks'] : [],
                'editorial_readiness' => sanitize_key((string) ($content_package['quality_summary']['editorial_readiness'] ?? $editorial_summary['editorial_readiness'])),
            ],
            'channel_quality' => [
                'facebook' => [
                    'layer' => 'facebook',
                    'contract_version' => sanitize_text_field((string) ($facebook_channel['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? ''))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('facebook_adapter_contract_drift', $warning_checks, true),
                    'pool_quality_status' => sanitize_key((string) ($facebook_channel['quality_summary']['pool_quality_status'] ?? '')),
                    'distribution_source' => sanitize_key((string) ($facebook_channel['quality_summary']['distribution_source'] ?? '')),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'facebook_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => str_starts_with($check, 'social_') || $check === 'missing_target_pages' || $check === 'facebook_adapter_contract_drift')),
                ],
                'facebook_groups' => [
                    'layer' => 'facebook_groups',
                    'contract_version' => sanitize_text_field((string) (($channels['facebook_groups']['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? '')))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('facebook_groups_adapter_contract_drift', $warning_checks, true),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'facebook_groups_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => $check === 'facebook_groups_adapter_contract_drift')),
                ],
                'pinterest' => [
                    'layer' => 'pinterest',
                    'contract_version' => sanitize_text_field((string) (($channels['pinterest']['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? '')))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('pinterest_adapter_contract_drift', $warning_checks, true),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'pinterest_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => $check === 'pinterest_adapter_contract_drift')),
                ],
            ],
            'editorial_readiness' => $editorial_summary['editorial_readiness'],
            'editorial_highlights' => $editorial_summary['editorial_highlights'],
            'editorial_watchouts' => $editorial_summary['editorial_watchouts'],
            'quality_checks' => [
                'word_count'            => $word_count,
                'minimum_words'         => $minimum_words,
                'h2_count'              => $h2_count,
                'internal_links'        => $internal_links,
                'excerpt_words'         => $excerpt_words,
                'seo_words'             => $seo_words,
                'title_score'           => $title_score,
                'title_strong'          => $title_strong,
                'title_front_load_score'=> $title_front_load_score,
                'opening_alignment_score' => $opening_alignment_score,
                'excerpt_adds_value'    => $excerpt_adds_value,
                'opening_adds_value'    => $opening_adds_value,
                'opening_front_load_score' => $opening_front_load_score,
                'excerpt_signal_score'  => $excerpt_signal_score,
                'excerpt_front_load_score' => $excerpt_front_load_score,
                'seo_signal_score'      => $seo_signal_score,
                'seo_front_load_score'  => $seo_front_load_score,
                'page_count'            => $page_count,
                'shortest_page_words'   => $shortest_page_words,
                'strong_page_openings'  => $strong_page_openings,
                'unique_page_labels'    => $unique_page_labels,
                'strong_page_labels'    => $strong_page_labels,
                'strong_page_summaries' => $strong_page_summaries,
                'recipe_complete'       => $recipe_complete,
                'image_ready'           => $image_ready,
                'package_contract_enforced' => !empty($contract_checks['package_contract_enforced']),
                'channel_contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                'typed_contract_job'    => $typed_contract_job,
                'legacy_contract_job'   => !empty($contract_meta['legacy_job']),
                'strict_contract_mode'  => $strict_contract_mode,
                'target_pages'          => $target_pages,
                'social_variants'       => $social_variants,
                'unique_social_variants'=> $unique_variants,
                'unique_social_hooks'   => $unique_hooks,
                'unique_social_openings'=> $unique_openings,
                'unique_social_angles'  => $unique_angles,
                'unique_hook_form_candidates' => $unique_hook_form_candidates,
                'unique_social_hook_forms' => $unique_hook_forms,
                'social_pool_size'      => $social_pool_size,
                'strong_social_candidates' => $strong_social_candidates,
                'specific_social_candidates' => $specific_social_candidates,
                'anchored_social_candidates' => $anchored_social_candidates,
                'novelty_social_candidates' => $novelty_social_candidates,
                'relatable_social_candidates' => $relatable_social_candidates,
                'recognition_social_candidates' => $recognition_social_candidates,
                'conversation_social_candidates' => $conversation_social_candidates,
                'savvy_social_candidates' => $savvy_social_candidates,
                'identity_shift_social_candidates' => $identity_shift_social_candidates,
                'proof_social_candidates' => $proof_social_candidates,
                'actionable_social_candidates' => $actionable_social_candidates,
                'immediacy_social_candidates' => $immediacy_social_candidates,
                'consequence_social_candidates' => $consequence_social_candidates,
                'habit_shift_social_candidates' => $habit_shift_social_candidates,
                'focused_social_candidates' => $focused_social_candidates,
                'promise_sync_candidates' => $promise_sync_candidates,
                'scannable_social_candidates' => $scannable_social_candidates,
                'two_step_social_candidates' => $two_step_social_candidates,
                'front_loaded_social_candidates' => $front_loaded_social_candidates,
                'curiosity_social_candidates' => $curiosity_social_candidates,
                'resolution_social_candidates' => $resolution_social_candidates,
                'contrast_social_candidates' => $contrast_social_candidates,
                'pain_point_social_candidates' => $pain_point_social_candidates,
                'payoff_social_candidates' => $payoff_social_candidates,
                'high_scoring_social_candidates' => $high_scoring_social_candidates,
                'strong_social_variants'=> $strong_social_variants,
                'specific_social_variants' => $specific_social_variants,
                'anchored_variants' => $anchored_variants,
                'novelty_variants'    => $novelty_variants,
                'relatable_variants' => $relatable_variants,
                'recognition_variants' => $recognition_variants,
                'conversation_variants' => $conversation_variants,
                'savvy_variants' => $savvy_variants,
                'identity_shift_variants' => $identity_shift_variants,
                'proof_variants' => $proof_variants,
                'actionable_variants' => $actionable_variants,
                'immediacy_variants' => $immediacy_variants,
                'consequence_variants' => $consequence_variants,
                'habit_shift_variants' => $habit_shift_variants,
                'focused_variants' => $focused_variants,
                'promise_sync_variants' => $promise_sync_variants,
                'scannable_variants' => $scannable_variants,
                'two_step_variants' => $two_step_variants,
                'curiosity_variants'   => $curiosity_variants,
                'resolution_variants' => $resolution_variants,
                'contrast_variants'   => $contrast_variants,
                'front_loaded_social_variants' => $front_loaded_social_variants,
                'pain_point_variants'   => $pain_point_variants,
                'payoff_variants'       => $payoff_variants,
                'selected_social_average_score' => $selected_social_average_score,
                'lead_social_score'     => $lead_social_score,
                'lead_social_hook_form' => $lead_social_hook_form,
                'lead_social_specific'  => $lead_social_specific,
                'lead_social_anchored' => $lead_social_anchored,
                'lead_social_novelty' => $lead_social_novelty,
                'lead_social_relatable' => $lead_social_relatable,
                'lead_social_recognition' => $lead_social_recognition,
                'lead_social_conversation' => $lead_social_conversation,
                'lead_social_savvy' => $lead_social_savvy,
                'lead_social_identity_shift' => $lead_social_identity_shift,
                'lead_social_proof' => $lead_social_proof,
                'lead_social_actionable' => $lead_social_actionable,
                'lead_social_immediacy' => $lead_social_immediacy,
                'lead_social_consequence' => $lead_social_consequence,
                'lead_social_habit_shift' => $lead_social_habit_shift,
                'lead_social_focused' => $lead_social_focused,
                'lead_social_promise_sync' => $lead_social_promise_sync,
                'lead_social_scannable' => $lead_social_scannable,
                'lead_social_two_step' => $lead_social_two_step,
                'lead_social_curiosity' => $lead_social_curiosity,
                'lead_social_resolved' => $lead_social_resolved,
                'lead_social_contrast' => $lead_social_contrast,
                'lead_social_front_loaded' => $lead_social_front_loaded,
                'lead_social_pain_point' => $lead_social_pain_point,
                'lead_social_payoff'    => $lead_social_payoff,
                'duplicate_risk'        => $duplicate_risk,
            ],
        ];
    }
}
}
