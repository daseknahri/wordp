<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Job_Summary_Generated_Trait
{
    private function render_job_generated_snapshot(array $job, array $generated_snapshot, array $machine_meta, array $validator_summary_display): void
    {
        if (!$generated_snapshot) {
            return;
        }

        $validator_summary = is_array($machine_meta['validator_summary'] ?? null) ? $machine_meta['validator_summary'] : [];
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Generated Snapshot', 'kuchnia-twist'); ?></h4>
            <?php $this->render_job_generated_snapshot_metrics($generated_snapshot, $validator_summary, $validator_summary_display); ?>

            <?php if (!empty($generated_snapshot['excerpt'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-excerpt-' . (int) $job['id'], __('Excerpt', 'kuchnia-twist'), (string) $generated_snapshot['excerpt'], 3); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['editorial_highlights']) && is_array($generated_snapshot['editorial_highlights'])) : ?>
                <?php
                $status = [];
                if (!empty($generated_snapshot['editorial_readiness'])) {
                    $status = [
                        'label' => $this->editorial_readiness_label((string) $generated_snapshot['editorial_readiness']),
                        'class' => $this->editorial_readiness_class((string) $generated_snapshot['editorial_readiness']),
                    ];
                }
                $this->render_job_generated_chip_list(
                    __('Editorial highlights', 'kuchnia-twist'),
                    $generated_snapshot['editorial_highlights'],
                    $status
                );
                ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['editorial_watchouts']) && is_array($generated_snapshot['editorial_watchouts'])) : ?>
                <?php $this->render_job_generated_chip_list(__('Editorial watchouts', 'kuchnia-twist'), $generated_snapshot['editorial_watchouts']); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['seo_description'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-seo-' . (int) $job['id'], __('SEO Description', 'kuchnia-twist'), (string) $generated_snapshot['seo_description'], 3); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['opening_paragraph'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-opening-' . (int) $job['id'], __('Opening Paragraph', 'kuchnia-twist'), (string) $generated_snapshot['opening_paragraph'], 4, true); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['headings']) && is_array($generated_snapshot['headings'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-headings-' . (int) $job['id'], __('H2 Outline', 'kuchnia-twist'), implode("\n", $generated_snapshot['headings']), 5, true); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['page_labels']) && is_array($generated_snapshot['page_labels'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-pages-' . (int) $job['id'], __('Page Flow', 'kuchnia-twist'), implode("\n", $generated_snapshot['page_labels']), 4, true); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['image_alt'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-image-alt-' . (int) $job['id'], __('Image Alt Text', 'kuchnia-twist'), (string) $generated_snapshot['image_alt'], 2, true); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['image_prompt'])) : ?>
                <?php $this->render_job_generated_textarea('kt-generated-image-prompt-' . (int) $job['id'], __('Image Prompt', 'kuchnia-twist'), (string) $generated_snapshot['image_prompt'], 5, true); ?>
            <?php endif; ?>

            <?php if (($validator_summary['distribution_source'] ?? '') === 'partial_fallback') : ?>
                <p class="kt-system-note"><?php esc_html_e('The content engine returned some, but not all, Facebook variants. The worker filled the remaining page variants with local fallback copy.', 'kuchnia-twist'); ?></p>
            <?php elseif (($validator_summary['distribution_source'] ?? '') === 'local_fallback') : ?>
                <p class="kt-system-note kt-system-note--error"><?php esc_html_e('The Facebook social pack was fully rebuilt from local fallback copy because the content engine did not provide usable variants.', 'kuchnia-twist'); ?></p>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['blocking_checks']) && is_array($generated_snapshot['blocking_checks'])) : ?>
                <?php $this->render_job_generated_failed_checks(__('Blocking Checks', 'kuchnia-twist'), $generated_snapshot['blocking_checks']); ?>
            <?php endif; ?>

            <?php if (!empty($generated_snapshot['warning_checks']) && is_array($generated_snapshot['warning_checks'])) : ?>
                <?php $this->render_job_generated_failed_checks(__('Warning Checks', 'kuchnia-twist'), $generated_snapshot['warning_checks']); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_job_generated_snapshot_metrics(array $generated_snapshot, array $validator_summary, array $validator_summary_display): void
    {
        $rows = $this->job_generated_snapshot_metric_rows($generated_snapshot, $validator_summary, $validator_summary_display);
        if (!$rows) {
            return;
        }
        ?>
        <div class="kt-summary-list">
            <?php foreach ($rows as $row) : ?>
                <div>
                    <span><?php echo esc_html($row['label']); ?></span>
                    <strong><?php echo esc_html($row['value']); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function job_generated_snapshot_metric_rows(array $generated_snapshot, array $validator_summary, array $validator_summary_display): array
    {
        return array_merge(
            $this->job_generated_snapshot_core_rows($generated_snapshot, $validator_summary),
            $this->job_generated_snapshot_article_rows($validator_summary_display),
            $this->job_generated_snapshot_social_rows($validator_summary, $validator_summary_display)
        );
    }

    private function job_generated_snapshot_core_rows(array $generated_snapshot, array $validator_summary): array
    {
        $rows = [];

        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['title']), __('Generated title', 'kuchnia-twist'), (string) $generated_snapshot['title']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['slug']), __('Slug', 'kuchnia-twist'), (string) $generated_snapshot['slug']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['word_count']), __('Body words', 'kuchnia-twist'), (string) $generated_snapshot['word_count']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['page_count']), __('Article pages', 'kuchnia-twist'), (string) $generated_snapshot['page_count']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['shortest_page_words']), __('Shortest page', 'kuchnia-twist'), sprintf(__('%d words', 'kuchnia-twist'), (int) $generated_snapshot['shortest_page_words']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['strong_page_openings']) && !empty($generated_snapshot['page_count']), __('Strong page opens', 'kuchnia-twist'), sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_openings'], (int) $generated_snapshot['page_count']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['unique_page_labels']), __('Distinct page labels', 'kuchnia-twist'), (string) $generated_snapshot['unique_page_labels']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['strong_page_labels']) && !empty($generated_snapshot['page_count']), __('Strong page labels', 'kuchnia-twist'), sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_labels'], (int) $generated_snapshot['page_count']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['strong_page_summaries']) && !empty($generated_snapshot['page_count']), __('Strong page summaries', 'kuchnia-twist'), sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_summaries'], (int) $generated_snapshot['page_count']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['h2_count']), __('H2 sections', 'kuchnia-twist'), (string) $generated_snapshot['h2_count']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['internal_links']), __('Internal links', 'kuchnia-twist'), (string) $generated_snapshot['internal_links']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['social_variants']), __('Social variants', 'kuchnia-twist'), (string) $generated_snapshot['social_variants']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['unique_social_hooks']), __('Distinct hooks', 'kuchnia-twist'), (string) $generated_snapshot['unique_social_hooks']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['unique_social_openings']), __('Distinct openings', 'kuchnia-twist'), (string) $generated_snapshot['unique_social_openings']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['unique_social_angles']), __('Distinct angles', 'kuchnia-twist'), (string) $generated_snapshot['unique_social_angles']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['strong_social_variants']), __('Strong variants', 'kuchnia-twist'), (string) $generated_snapshot['strong_social_variants']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['target_pages']), __('Target pages', 'kuchnia-twist'), (string) $generated_snapshot['target_pages']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['quality_status']), __('Quality status', 'kuchnia-twist'), $this->quality_status_label((string) $generated_snapshot['quality_status']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['quality_score']), __('Quality score', 'kuchnia-twist'), (string) $generated_snapshot['quality_score']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['editorial_readiness']), __('Editorial readiness', 'kuchnia-twist'), $this->editorial_readiness_label((string) $generated_snapshot['editorial_readiness']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['package_layer']), __('Package layer', 'kuchnia-twist'), $this->quality_status_label((string) $generated_snapshot['package_layer']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['package_contract']), __('Package contract', 'kuchnia-twist'), (string) $generated_snapshot['package_contract']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['input_mode']), __('Input mode', 'kuchnia-twist'), $this->format_human_label((string) $generated_snapshot['input_mode']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['rendering_mode']), __('Rendering mode', 'kuchnia-twist'), $this->format_human_label((string) $generated_snapshot['rendering_mode']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_adapter']), __('Facebook adapter', 'kuchnia-twist'), $this->format_human_label((string) $generated_snapshot['facebook_adapter']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_contract']), __('Facebook contract', 'kuchnia-twist'), (string) $generated_snapshot['facebook_contract']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_layer']), __('Facebook layer', 'kuchnia-twist'), $this->quality_status_label((string) $generated_snapshot['facebook_layer']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_groups_adapter']), __('Facebook Groups adapter', 'kuchnia-twist'), $this->format_human_label((string) $generated_snapshot['facebook_groups_adapter']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_groups_contract']), __('Facebook Groups contract', 'kuchnia-twist'), (string) $generated_snapshot['facebook_groups_contract']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['facebook_groups_ready']), __('Facebook Groups draft', 'kuchnia-twist'), (string) $generated_snapshot['facebook_groups_ready']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['pinterest_adapter']), __('Pinterest adapter', 'kuchnia-twist'), $this->format_human_label((string) $generated_snapshot['pinterest_adapter']));
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['pinterest_contract']), __('Pinterest contract', 'kuchnia-twist'), (string) $generated_snapshot['pinterest_contract']);
        $this->append_job_generated_snapshot_row($rows, !empty($generated_snapshot['pinterest_ready']), __('Pinterest draft', 'kuchnia-twist'), (string) $generated_snapshot['pinterest_ready']);
        $this->append_job_generated_snapshot_row($rows, !empty($validator_summary['distribution_source']), __('Distribution copy', 'kuchnia-twist'), $this->format_human_label((string) $validator_summary['distribution_source']));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary['repair_attempts']), __('Repair attempts', 'kuchnia-twist'), (string) $validator_summary['repair_attempts']);
        $this->append_job_generated_snapshot_row($rows, !empty($validator_summary['article_stage_quality_status']), __('Article stage', 'kuchnia-twist'), $this->quality_status_label((string) $validator_summary['article_stage_quality_status']));
        $this->append_job_generated_snapshot_row($rows, !empty($validator_summary['article_stage_checks']) && is_array($validator_summary['article_stage_checks']), __('Article stage checks', 'kuchnia-twist'), (string) count($validator_summary['article_stage_checks']));

        return $rows;
    }

    private function job_generated_snapshot_article_rows(array $validator_summary_display): array
    {
        $rows = [];

        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_title_score']), __('Title score', 'kuchnia-twist'), (string) $validator_summary_display['article_title_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_title_strong']), __('Title strength', 'kuchnia-twist'), $this->job_generated_strength_label(!empty($validator_summary_display['article_title_strong'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_title_front_load_score']), __('Title lead', 'kuchnia-twist'), (string) $validator_summary_display['article_title_front_load_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_opening_alignment_score']), __('Opening alignment', 'kuchnia-twist'), (string) $validator_summary_display['article_opening_alignment_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_opening_front_load_score']), __('Opening lead', 'kuchnia-twist'), (string) $validator_summary_display['article_opening_front_load_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_excerpt_signal_score']), __('Excerpt score', 'kuchnia-twist'), (string) $validator_summary_display['article_excerpt_signal_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_excerpt_front_load_score']), __('Excerpt lead', 'kuchnia-twist'), (string) $validator_summary_display['article_excerpt_front_load_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_excerpt_adds_value']), __('Excerpt distinct', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['article_excerpt_adds_value'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_seo_signal_score']), __('SEO score', 'kuchnia-twist'), (string) $validator_summary_display['article_seo_signal_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_seo_front_load_score']), __('SEO lead', 'kuchnia-twist'), (string) $validator_summary_display['article_seo_front_load_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['article_opening_adds_value']), __('Opening distinct', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['article_opening_adds_value'])));

        return $rows;
    }

    private function job_generated_snapshot_social_rows(array $validator_summary, array $validator_summary_display): array
    {
        $rows = [];

        $this->append_job_generated_snapshot_row($rows, !empty($validator_summary['social_pool_quality_status']), __('Social pool', 'kuchnia-twist'), $this->quality_status_label((string) $validator_summary['social_pool_quality_status']));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary['social_repair_attempts']), __('Social repair attempts', 'kuchnia-twist'), (string) $validator_summary['social_repair_attempts']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['strong_social_candidates']), __('Strong candidates', 'kuchnia-twist'), (string) $validator_summary_display['strong_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['specific_social_candidates']), __('Specific candidates', 'kuchnia-twist'), (string) $validator_summary_display['specific_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['unique_hook_form_candidates']), __('Candidate hook forms', 'kuchnia-twist'), (string) $validator_summary_display['unique_hook_form_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['anchored_social_candidates']), __('Anchored candidates', 'kuchnia-twist'), (string) $validator_summary_display['anchored_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['novelty_social_candidates']), __('Novelty candidates', 'kuchnia-twist'), (string) $validator_summary_display['novelty_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['relatable_social_candidates']), __('Relatable candidates', 'kuchnia-twist'), (string) $validator_summary_display['relatable_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['recognition_social_candidates']), __('Self-recognition candidates', 'kuchnia-twist'), (string) $validator_summary_display['recognition_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['conversation_social_candidates']), __('Discussable candidates', 'kuchnia-twist'), (string) $validator_summary_display['conversation_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['savvy_social_candidates']), __('Savvy candidates', 'kuchnia-twist'), (string) $validator_summary_display['savvy_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['identity_shift_social_candidates']), __('Identity-shift candidates', 'kuchnia-twist'), (string) $validator_summary_display['identity_shift_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['proof_social_candidates']), __('Proof candidates', 'kuchnia-twist'), (string) $validator_summary_display['proof_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['actionable_social_candidates']), __('Actionable candidates', 'kuchnia-twist'), (string) $validator_summary_display['actionable_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['immediacy_social_candidates']), __('Immediate-use candidates', 'kuchnia-twist'), (string) $validator_summary_display['immediacy_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['consequence_social_candidates']), __('Stakes candidates', 'kuchnia-twist'), (string) $validator_summary_display['consequence_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['habit_shift_social_candidates']), __('Habit-shift candidates', 'kuchnia-twist'), (string) $validator_summary_display['habit_shift_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['focused_social_candidates']), __('Focused candidates', 'kuchnia-twist'), (string) $validator_summary_display['focused_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['promise_sync_candidates']), __('Promise-sync candidates', 'kuchnia-twist'), (string) $validator_summary_display['promise_sync_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['scannable_social_candidates']), __('Scannable candidates', 'kuchnia-twist'), (string) $validator_summary_display['scannable_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['two_step_social_candidates']), __('Two-step candidates', 'kuchnia-twist'), (string) $validator_summary_display['two_step_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['front_loaded_social_candidates']), __('Lead-ready candidates', 'kuchnia-twist'), (string) $validator_summary_display['front_loaded_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['curiosity_social_candidates']), __('Curiosity candidates', 'kuchnia-twist'), (string) $validator_summary_display['curiosity_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['resolution_social_candidates']), __('Resolved candidates', 'kuchnia-twist'), (string) $validator_summary_display['resolution_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['contrast_social_candidates']), __('Contrast candidates', 'kuchnia-twist'), (string) $validator_summary_display['contrast_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['pain_point_social_candidates']), __('Pain-point candidates', 'kuchnia-twist'), (string) $validator_summary_display['pain_point_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['payoff_social_candidates']), __('Payoff candidates', 'kuchnia-twist'), (string) $validator_summary_display['payoff_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['high_scoring_social_candidates']), __('High-scoring candidates', 'kuchnia-twist'), (string) $validator_summary_display['high_scoring_social_candidates']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['specific_social_variants']), __('Specific variants', 'kuchnia-twist'), (string) $validator_summary_display['specific_social_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['unique_social_hook_forms']), __('Hook forms', 'kuchnia-twist'), (string) $validator_summary_display['unique_social_hook_forms']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['anchored_variants']), __('Anchored variants', 'kuchnia-twist'), (string) $validator_summary_display['anchored_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['novelty_variants']), __('Novelty variants', 'kuchnia-twist'), (string) $validator_summary_display['novelty_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['relatable_variants']), __('Relatable variants', 'kuchnia-twist'), (string) $validator_summary_display['relatable_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['recognition_variants']), __('Self-recognition variants', 'kuchnia-twist'), (string) $validator_summary_display['recognition_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['conversation_variants']), __('Discussable variants', 'kuchnia-twist'), (string) $validator_summary_display['conversation_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['savvy_variants']), __('Savvy variants', 'kuchnia-twist'), (string) $validator_summary_display['savvy_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['identity_shift_variants']), __('Identity-shift variants', 'kuchnia-twist'), (string) $validator_summary_display['identity_shift_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['proof_variants']), __('Proof variants', 'kuchnia-twist'), (string) $validator_summary_display['proof_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['actionable_variants']), __('Actionable variants', 'kuchnia-twist'), (string) $validator_summary_display['actionable_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['immediacy_variants']), __('Immediate-use variants', 'kuchnia-twist'), (string) $validator_summary_display['immediacy_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['consequence_variants']), __('Stakes variants', 'kuchnia-twist'), (string) $validator_summary_display['consequence_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['habit_shift_variants']), __('Habit-shift variants', 'kuchnia-twist'), (string) $validator_summary_display['habit_shift_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['focused_variants']), __('Focused variants', 'kuchnia-twist'), (string) $validator_summary_display['focused_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['promise_sync_variants']), __('Promise-sync variants', 'kuchnia-twist'), (string) $validator_summary_display['promise_sync_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['scannable_variants']), __('Scannable variants', 'kuchnia-twist'), (string) $validator_summary_display['scannable_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['two_step_variants']), __('Two-step variants', 'kuchnia-twist'), (string) $validator_summary_display['two_step_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['front_loaded_social_variants']), __('Lead-ready variants', 'kuchnia-twist'), (string) $validator_summary_display['front_loaded_social_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['curiosity_variants']), __('Curiosity variants', 'kuchnia-twist'), (string) $validator_summary_display['curiosity_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['resolution_variants']), __('Resolved variants', 'kuchnia-twist'), (string) $validator_summary_display['resolution_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['contrast_variants']), __('Contrast variants', 'kuchnia-twist'), (string) $validator_summary_display['contrast_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['pain_point_variants']), __('Pain-point variants', 'kuchnia-twist'), (string) $validator_summary_display['pain_point_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['payoff_variants']), __('Payoff variants', 'kuchnia-twist'), (string) $validator_summary_display['payoff_variants']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['selected_social_average_score']), __('Selected score', 'kuchnia-twist'), (string) $validator_summary_display['selected_social_average_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_score']), __('Lead score', 'kuchnia-twist'), (string) $validator_summary_display['lead_social_score']);
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_specific']), __('Lead specific', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_specific'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_anchored']), __('Lead anchored', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_anchored'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_novelty']), __('Lead novelty', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_novelty'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_relatable']), __('Lead relatable', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_relatable'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_recognition']), __('Lead self-recognition', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_recognition'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_conversation']), __('Lead discussable', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_conversation'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_savvy']), __('Lead savvy', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_savvy'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_identity_shift']), __('Lead identity-shift', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_identity_shift'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_proof']), __('Lead proof', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_proof'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_actionable']), __('Lead actionable', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_actionable'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_immediacy']), __('Lead immediate-use', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_immediacy'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_consequence']), __('Lead stakes', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_consequence'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_habit_shift']), __('Lead habit-shift', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_habit_shift'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_focused']), __('Lead focused', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_focused'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_promise_sync']), __('Lead promise-sync', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_promise_sync'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_scannable']), __('Lead scannable', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_scannable'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_two_step']), __('Lead two-step', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_two_step'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_curiosity']), __('Lead curiosity', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_curiosity'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_resolved']), __('Lead resolved', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_resolved'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_contrast']), __('Lead contrast', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_contrast'])));
        $this->append_job_generated_snapshot_row($rows, !empty($validator_summary_display['lead_social_hook_form']), __('Lead hook form', 'kuchnia-twist'), $this->format_human_label((string) $validator_summary_display['lead_social_hook_form']));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_front_loaded']), __('Lead front-loaded', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_front_loaded'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_pain_point']), __('Lead pain-point', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_pain_point'])));
        $this->append_job_generated_snapshot_row($rows, isset($validator_summary_display['lead_social_payoff']), __('Lead payoff', 'kuchnia-twist'), $this->job_generated_yes_no(!empty($validator_summary_display['lead_social_payoff'])));

        return $rows;
    }

    private function append_job_generated_snapshot_row(array &$rows, bool $condition, string $label, string $value): void
    {
        if (!$condition || $value === '') {
            return;
        }

        $rows[] = [
            'label' => $label,
            'value' => $value,
        ];
    }

    private function render_job_generated_textarea(string $id, string $label, string $value, int $rows, bool $copy_button = false): void
    {
        ?>
        <div class="kt-generated-copy">
            <div class="kt-detail-block__head">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
                <?php if ($copy_button) : ?>
                    <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($id); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                <?php endif; ?>
            </div>
            <textarea id="<?php echo esc_attr($id); ?>" rows="<?php echo esc_attr((string) $rows); ?>" readonly><?php echo esc_textarea($value); ?></textarea>
        </div>
        <?php
    }

    private function render_job_generated_chip_list(string $label, array $items, array $status = []): void
    {
        if (!$items) {
            return;
        }
        ?>
        <div class="kt-generated-copy">
            <div class="kt-detail-block__head">
                <label><?php echo esc_html($label); ?></label>
                <?php if (!empty($status['label'])) : ?>
                    <span class="kt-status kt-status--<?php echo esc_attr($status['class'] ?? 'neutral'); ?>"><?php echo esc_html((string) $status['label']); ?></span>
                <?php endif; ?>
            </div>
            <div class="kt-context-chips">
                <?php foreach ($items as $item) : ?>
                    <span class="kt-context-chip"><?php echo esc_html((string) $item); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_job_generated_failed_checks(string $label, array $checks): void
    {
        if (!$checks) {
            return;
        }

        $messages = array_map(
            fn ($check): string => (string) ($this->quality_failed_check_messages()[(string) $check] ?? $this->format_human_label((string) $check)),
            $checks
        );

        $this->render_job_generated_chip_list($label, $messages);
    }

    private function job_generated_yes_no(bool $value): string
    {
        return $value ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist');
    }

    private function job_generated_strength_label(bool $value): string
    {
        return $value ? __('Strong', 'kuchnia-twist') : __('Weak', 'kuchnia-twist');
    }
}
