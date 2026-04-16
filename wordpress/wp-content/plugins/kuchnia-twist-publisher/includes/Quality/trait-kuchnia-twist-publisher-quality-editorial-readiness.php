<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Editorial_Readiness_Trait
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

    private function quality_failed_check_messages(): array
    {
        return [
            'missing_core_fields'        => __('Missing title, slug, or article body.', 'kuchnia-twist'),
            'missing_recipe'             => __('Missing recipe ingredients or instructions.', 'kuchnia-twist'),
            'missing_manual_images'      => __('Manual-only mode requires both blog and Facebook images.', 'kuchnia-twist'),
            'duplicate_conflict'         => __('A duplicate title or slug conflict was detected.', 'kuchnia-twist'),
            'missing_target_pages'       => __('At least one target Facebook page is required.', 'kuchnia-twist'),
            'thin_content'               => __('The article body is too thin for launch quality.', 'kuchnia-twist'),
            'weak_title'                 => __('The title is too generic to carry a strong click promise.', 'kuchnia-twist'),
            'weak_excerpt'               => __('The excerpt is too weak, repetitive, or slow to surface a concrete reason to click.', 'kuchnia-twist'),
            'weak_seo'                   => __('The SEO description is too weak, repetitive, or buries the concrete click reason too late.', 'kuchnia-twist'),
            'weak_title_alignment'       => __('Page 1 does not cash the title promise quickly enough with a concrete answer, problem, or payoff.', 'kuchnia-twist'),
            'weak_pagination'            => __('The article should be split into 2 or 3 strong pages.', 'kuchnia-twist'),
            'weak_page_balance'          => __('One article page is too thin to feel intentional.', 'kuchnia-twist'),
            'weak_page_openings'         => __('One article page opens weakly instead of feeling like a deliberate new page.', 'kuchnia-twist'),
            'weak_page_flow'             => __('The generated page flow is missing a clear label or summary for one of the article pages.', 'kuchnia-twist'),
            'weak_page_labels'           => __('The page labels are too generic to feel like real chapter navigation.', 'kuchnia-twist'),
            'repetitive_page_labels'     => __('The page labels are too repetitive.', 'kuchnia-twist'),
            'weak_page_summaries'        => __('The page summaries are too thin to make the next click feel worthwhile.', 'kuchnia-twist'),
            'weak_structure'             => __('The article needs more H2 structure.', 'kuchnia-twist'),
            'missing_internal_links'     => __('The article needs more internal links.', 'kuchnia-twist'),
            'package_contract_drift'     => __('This new typed job is relying on legacy article fields because the canonical content package is incomplete or malformed.', 'kuchnia-twist'),
            'facebook_adapter_contract_drift' => __('This new typed job is relying on legacy Facebook fields because the Facebook adapter payload is incomplete or malformed.', 'kuchnia-twist'),
            'facebook_groups_adapter_contract_drift' => __('This new typed job is missing a clean Facebook group manual-share adapter payload.', 'kuchnia-twist'),
            'pinterest_adapter_contract_drift' => __('This new typed job is missing a clean Pinterest draft adapter payload.', 'kuchnia-twist'),
            'social_pack_incomplete'     => __('The Facebook social pack does not cover all selected pages.', 'kuchnia-twist'),
            'social_pack_repetitive'     => __('The Facebook social pack is too repetitive.', 'kuchnia-twist'),
            'social_hooks_repetitive'    => __('The Facebook hooks are too repetitive across selected pages.', 'kuchnia-twist'),
            'social_openings_repetitive' => __('The Facebook caption openings are too repetitive across selected pages.', 'kuchnia-twist'),
            'social_angles_repetitive'   => __('The Facebook angle mix is too repetitive across selected pages.', 'kuchnia-twist'),
            'social_hook_forms_thin'     => __('The selected Facebook pack reuses too many of the same hook shapes instead of varying the sentence pattern.', 'kuchnia-twist'),
            'weak_social_copy'           => __('The Facebook hooks or captions are too weak for publish.', 'kuchnia-twist'),
            'weak_social_lead'           => __('The lead Facebook variant is not strong, specific, concrete, or front-loaded enough to carry the first click opportunity.', 'kuchnia-twist'),
            'social_specificity_thin'    => __('Too few selected Facebook variants feel concrete and article-specific.', 'kuchnia-twist'),
            'social_anchor_thin'         => __('Too few selected Facebook variants name a concrete dish, ingredient, mistake, method, or topic.', 'kuchnia-twist'),
            'social_novelty_thin'        => __('Too few selected Facebook variants add a concrete new detail beyond the article title.', 'kuchnia-twist'),
            'social_relatability_thin'   => __('Too few selected Facebook variants frame a recognizable real-life kitchen moment.', 'kuchnia-twist'),
            'social_recognition_thin'    => __('Too few selected Facebook variants create a direct self-recognition moment around a repeated kitchen result or mistake.', 'kuchnia-twist'),
            'social_conversation_thin'   => __('Too few selected Facebook variants feel naturally discussable through a real household habit, shopping split, or recognizable choice.', 'kuchnia-twist'),
            'social_savvy_thin'          => __('Too few selected Facebook variants make the reader feel they are about to make a smarter kitchen or shopping move.', 'kuchnia-twist'),
            'social_identity_shift_thin' => __('Too few selected Facebook variants make the reader feel they are leaving behind the old default move for a better one.', 'kuchnia-twist'),
            'social_proof_thin'          => __('Too few selected Facebook variants carry a believable concrete clue or proof early.', 'kuchnia-twist'),
            'social_actionability_thin'  => __('Too few selected Facebook variants make the next move or practical use feel obvious.', 'kuchnia-twist'),
            'social_immediacy_thin'      => __('Too few selected Facebook variants make the article feel relevant to the reader\'s next cook, shop, order, or weeknight decision.', 'kuchnia-twist'),
            'social_front_load_thin'     => __('Too few selected Facebook variants surface the concrete problem or payoff in the first words.', 'kuchnia-twist'),
            'social_curiosity_thin'      => __('Too few selected Facebook variants create honest curiosity with a concrete clue.', 'kuchnia-twist'),
            'social_resolution_thin'     => __('Too few selected Facebook variants resolve the hook with a concrete clue in the first caption lines.', 'kuchnia-twist'),
            'social_contrast_thin'       => __('Too few selected Facebook variants use a clean expectation-vs-reality or mistake-vs-fix contrast.', 'kuchnia-twist'),
            'social_pain_points_thin'    => __('Too few selected Facebook variants frame a clear problem, mistake, or shortcut.', 'kuchnia-twist'),
            'social_payoffs_thin'        => __('Too few selected Facebook variants frame a clear payoff or result.', 'kuchnia-twist'),
            'social_consequence_thin'    => __('Too few selected Facebook variants make the cost, waste, or repeated mistake feel concrete.', 'kuchnia-twist'),
            'social_habit_shift_thin'    => __('Too few selected Facebook variants create a clear old-habit-versus-better-result shift.', 'kuchnia-twist'),
            'social_focus_thin'          => __('Too few selected Facebook variants stay centered on one clean dominant promise.', 'kuchnia-twist'),
            'social_promise_sync_thin'   => __('Too few selected Facebook variants line up cleanly with the article title and page-one promise without echoing the headline.', 'kuchnia-twist'),
            'social_scannability_thin'   => __('Too few selected Facebook variants stay easy to scan in short distinct caption lines.', 'kuchnia-twist'),
            'social_two_step_thin'       => __('Too few selected Facebook variants make caption line 1 and line 2 do distinct useful jobs instead of repeating the same idea.', 'kuchnia-twist'),
            'image_not_ready'            => __('The required image slots are not ready yet.', 'kuchnia-twist'),
        ];
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
}
