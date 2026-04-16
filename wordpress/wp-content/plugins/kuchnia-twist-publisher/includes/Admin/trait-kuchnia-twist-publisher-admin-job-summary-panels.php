<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Job_Summary_Panels_Trait
{
    private function job_summary_validator_summary_display(array $machine_meta, array $quality_summary): array
    {
        $validator_summary_display = is_array($machine_meta['validator_summary'] ?? null) ? $machine_meta['validator_summary'] : [];

        foreach ([
            'article_title_score',
            'article_title_strong',
            'article_title_front_load_score',
            'article_opening_alignment_score',
            'article_opening_front_load_score',
            'article_excerpt_signal_score',
            'article_excerpt_front_load_score',
            'article_seo_signal_score',
            'article_seo_front_load_score',
            'article_excerpt_adds_value',
            'article_opening_adds_value',
            'social_pool_size',
            'strong_social_candidates',
            'specific_social_candidates',
            'unique_hook_form_candidates',
            'anchored_social_candidates',
            'novelty_social_candidates',
            'relatable_social_candidates',
            'recognition_social_candidates',
            'conversation_social_candidates',
            'savvy_social_candidates',
            'identity_shift_social_candidates',
            'proof_social_candidates',
            'actionable_social_candidates',
            'immediacy_social_candidates',
            'consequence_social_candidates',
            'habit_shift_social_candidates',
            'focused_social_candidates',
            'promise_sync_candidates',
            'scannable_social_candidates',
            'two_step_social_candidates',
            'front_loaded_social_candidates',
            'curiosity_social_candidates',
            'resolution_social_candidates',
            'contrast_social_candidates',
            'pain_point_social_candidates',
            'payoff_social_candidates',
            'high_scoring_social_candidates',
            'specific_social_variants',
            'unique_social_hook_forms',
            'anchored_variants',
            'novelty_variants',
            'relatable_variants',
            'recognition_variants',
            'conversation_variants',
            'savvy_variants',
            'identity_shift_variants',
            'proof_variants',
            'actionable_variants',
            'immediacy_variants',
            'consequence_variants',
            'habit_shift_variants',
            'focused_variants',
            'promise_sync_variants',
            'scannable_variants',
            'two_step_variants',
            'front_loaded_social_variants',
            'curiosity_variants',
            'resolution_variants',
            'contrast_variants',
            'pain_point_variants',
            'payoff_variants',
            'selected_social_average_score',
            'lead_social_score',
            'lead_social_specific',
            'lead_social_anchored',
            'lead_social_novelty',
            'lead_social_relatable',
            'lead_social_recognition',
            'lead_social_conversation',
            'lead_social_savvy',
            'lead_social_identity_shift',
            'lead_social_proof',
            'lead_social_actionable',
            'lead_social_immediacy',
            'lead_social_consequence',
            'lead_social_habit_shift',
            'lead_social_focused',
            'lead_social_promise_sync',
            'lead_social_scannable',
            'lead_social_two_step',
            'lead_social_curiosity',
            'lead_social_resolved',
            'lead_social_contrast',
            'lead_social_hook_form',
            'lead_social_front_loaded',
            'lead_social_pain_point',
            'lead_social_payoff',
        ] as $validator_key) {
            if (!array_key_exists($validator_key, $validator_summary_display) && array_key_exists($validator_key, $quality_summary)) {
                $validator_summary_display[$validator_key] = $quality_summary[$validator_key];
            }
        }

        return $validator_summary_display;
    }

    private function render_job_keyfacts(array $job, array $event_stats, array $quality_summary, array $machine_meta): void
    {
        ?>
        <dl class="kt-keyfacts">
            <div>
                <dt><?php esc_html_e('Content Type', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Created', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html($this->format_admin_datetime($job['created_at'] ?? '')); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Updated', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html($this->format_admin_datetime($job['updated_at'])); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Queued By', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html($this->job_author_label($job)); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Attempts', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html((string) ($event_stats['attempts'] ?: 0)); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Retries', 'kuchnia-twist'); ?></dt>
                <dd><?php echo esc_html((string) ($event_stats['retries'] ?: 0)); ?></dd>
            </div>
            <?php if (!empty($job['last_attempt_at'])) : ?>
                <div>
                    <dt><?php esc_html_e('Last Attempt', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_admin_datetime((string) $job['last_attempt_at'])); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($job['publish_on'])) : ?>
                <div>
                    <dt><?php esc_html_e('Scheduled For', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_admin_datetime((string) $job['publish_on'])); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($event_stats['latest'])) : ?>
                <div>
                    <dt><?php esc_html_e('Latest Event', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_admin_datetime((string) $event_stats['latest'])); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($job['title_override'])) : ?>
                <div>
                    <dt><?php esc_html_e('Title Override', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($job['title_override']); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($job['retry_target'])) : ?>
                <div>
                    <dt><?php esc_html_e('Retry Target', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_human_label($job['retry_target'])); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($machine_meta['publication_profile'])) : ?>
                <div>
                    <dt><?php esc_html_e('Profile', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($machine_meta['publication_profile']); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($machine_meta['content_preset'])) : ?>
                <div>
                    <dt><?php esc_html_e('Preset', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_human_label($machine_meta['content_preset'])); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($machine_meta['prompt_version'])) : ?>
                <div>
                    <dt><?php esc_html_e('Prompt Version', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($machine_meta['prompt_version']); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($quality_summary['quality_status'])) : ?>
                <div>
                    <dt><?php esc_html_e('Quality Status', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->quality_status_label((string) $quality_summary['quality_status'])); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <?php
    }

    private function render_job_request_snapshot(array $job): void
    {
        $selected_pages = $this->job_selected_pages($job);
        $channel_targets = $this->job_channel_targets($job);
        $request_payload = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $current_page_map = [];

        foreach ($this->facebook_pages($this->get_settings(), false, true) as $page) {
            $current_page_map[(string) ($page['page_id'] ?? '')] = !empty($page['active']);
        }

        $inactive_selected_pages = array_values(array_filter(
            $selected_pages,
            static function (array $page) use ($current_page_map): bool {
                $page_id = (string) ($page['page_id'] ?? '');
                return $page_id === '' || empty($current_page_map[$page_id]);
            }
        ));
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Request Snapshot', 'kuchnia-twist'); ?></h4>
            <div class="kt-summary-list">
                <div>
                    <span><?php esc_html_e('Requested title', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html($this->job_requested_title($job)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Schedule mode', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html($this->format_human_label((string) ($request_payload['schedule_mode'] ?? 'immediate'))); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Publish timing', 'kuchnia-twist'); ?></span>
                    <strong>
                        <?php
                        echo !empty($job['publish_on'])
                            ? esc_html($this->format_admin_datetime((string) $job['publish_on']))
                            : esc_html__('Publish as soon as ready', 'kuchnia-twist');
                        ?>
                    </strong>
                </div>
                <div>
                    <span><?php esc_html_e('Hero image supplied', 'kuchnia-twist'); ?></span>
                    <strong><?php echo !empty($job['blog_image_id']) ? esc_html__('Yes', 'kuchnia-twist') : esc_html__('No', 'kuchnia-twist'); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Facebook image supplied', 'kuchnia-twist'); ?></span>
                    <strong><?php echo !empty($job['facebook_image_id']) ? esc_html__('Yes', 'kuchnia-twist') : esc_html__('No', 'kuchnia-twist'); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Site label', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html($this->job_site_label($job)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Facebook targets', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html($selected_pages ? implode(', ', wp_list_pluck($selected_pages, 'label')) : __('None selected', 'kuchnia-twist')); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Facebook Groups', 'kuchnia-twist'); ?></span>
                    <strong><?php echo !empty($channel_targets['facebook_groups']['enabled']) ? esc_html($this->format_human_label((string) ($channel_targets['facebook_groups']['mode'] ?? 'manual_draft'))) : esc_html__('Dormant manual draft', 'kuchnia-twist'); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Pinterest', 'kuchnia-twist'); ?></span>
                    <strong><?php echo !empty($channel_targets['pinterest']['enabled']) ? esc_html($this->format_human_label((string) ($channel_targets['pinterest']['mode'] ?? 'draft'))) : esc_html__('Dormant draft', 'kuchnia-twist'); ?></strong>
                </div>
            </div>
            <?php if ($inactive_selected_pages) : ?>
                <p class="kt-system-note kt-system-note--error">
                    <?php
                    echo esc_html(
                        sprintf(
                            __('These selected pages are no longer active in Settings: %s', 'kuchnia-twist'),
                            implode(', ', wp_list_pluck($inactive_selected_pages, 'label'))
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_job_recipe_snapshot(array $job): void
    {
        $recipe_snapshot = $this->job_recipe_snapshot($job);
        if (!$recipe_snapshot) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Recipe Snapshot', 'kuchnia-twist'); ?></h4>
            <div class="kt-summary-list">
                <?php if (!empty($recipe_snapshot['prep_time'])) : ?>
                    <div>
                        <span><?php esc_html_e('Prep time', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($recipe_snapshot['prep_time']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($recipe_snapshot['cook_time'])) : ?>
                    <div>
                        <span><?php esc_html_e('Cook time', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($recipe_snapshot['cook_time']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($recipe_snapshot['total_time'])) : ?>
                    <div>
                        <span><?php esc_html_e('Total time', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($recipe_snapshot['total_time']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($recipe_snapshot['yield'])) : ?>
                    <div>
                        <span><?php esc_html_e('Yield', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($recipe_snapshot['yield']); ?></strong>
                    </div>
                <?php endif; ?>
                <div>
                    <span><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html((string) $recipe_snapshot['ingredients_count']); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Instructions', 'kuchnia-twist'); ?></span>
                    <strong><?php echo esc_html((string) $recipe_snapshot['instructions_count']); ?></strong>
                </div>
            </div>
            <?php if (!empty($recipe_snapshot['ingredients'])) : ?>
                <div class="kt-generated-copy">
                    <div class="kt-detail-block__head">
                        <label for="kt-recipe-ingredients-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></label>
                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-recipe-ingredients-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                    </div>
                    <textarea id="kt-recipe-ingredients-<?php echo (int) $job['id']; ?>" rows="6" readonly><?php echo esc_textarea(implode("\n", $recipe_snapshot['ingredients'])); ?></textarea>
                </div>
            <?php endif; ?>
            <?php if (!empty($recipe_snapshot['instructions'])) : ?>
                <div class="kt-generated-copy">
                    <div class="kt-detail-block__head">
                        <label for="kt-recipe-instructions-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Instructions', 'kuchnia-twist'); ?></label>
                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-recipe-instructions-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                    </div>
                    <textarea id="kt-recipe-instructions-<?php echo (int) $job['id']; ?>" rows="8" readonly><?php echo esc_textarea(implode("\n", $recipe_snapshot['instructions'])); ?></textarea>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_job_outputs(array $job, array $distribution): void
    {
        if (empty($job['permalink']) && empty($job['facebook_post_id']) && empty($job['facebook_comment_id']) && empty($distribution['pages'])) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Outputs', 'kuchnia-twist'); ?></h4>
            <div class="kt-summary-list">
                <?php if (!empty($job['permalink'])) : ?>
                    <div>
                        <span><?php esc_html_e('Article', 'kuchnia-twist'); ?></span>
                        <a href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open article', 'kuchnia-twist'); ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($job['facebook_post_id'])) : ?>
                    <div>
                        <span><?php esc_html_e('Facebook post ID', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($job['facebook_post_id']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($job['facebook_comment_id'])) : ?>
                    <div>
                        <span><?php esc_html_e('First comment ID', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($job['facebook_comment_id']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($distribution['pages'])) : ?>
                    <div>
                        <span><?php esc_html_e('Page distribution', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html(sprintf(_n('%d page targeted', '%d pages targeted', count($distribution['pages']), 'kuchnia-twist'), count($distribution['pages']))); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Pages completed', 'kuchnia-twist'); ?></span>
                        <strong>
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('%1$d of %2$d', 'kuchnia-twist'),
                                    count(array_filter($distribution['pages'], static fn (array $page): bool => ($page['status'] ?? '') === 'completed')),
                                    count($distribution['pages'])
                                )
                            );
                            ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    private function render_job_media_section(array $job): void
    {
        if (!$this->job_has_media($job)) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Media', 'kuchnia-twist'); ?></h4>
            <div class="kt-media-grid">
                <?php $this->render_job_media_cards($job); ?>
            </div>
        </section>
        <?php
    }

    private function render_job_error_section(array $job): void
    {
        if (empty($job['error_message'])) {
            return;
        }
        ?>
        <section class="kt-detail-block kt-detail-block--error">
            <h4><?php esc_html_e('Error', 'kuchnia-twist'); ?></h4>
            <p class="kt-error"><?php echo esc_html($job['error_message']); ?></p>
        </section>
        <?php
    }

    private function render_job_actions(array $job): void
    {
        if (empty($job['permalink']) && !in_array($job['status'], ['failed', 'partial_failure', 'scheduled'], true)) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <h4><?php esc_html_e('Actions', 'kuchnia-twist'); ?></h4>
            <div class="kt-inline-actions">
                <?php if ($job['status'] === 'scheduled') : ?>
                    <a class="button button-primary" href="<?php echo esc_url($this->publish_now_link($job)); ?>"><?php esc_html_e('Publish Now', 'kuchnia-twist'); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url($this->cancel_scheduled_job_link($job)); ?>"><?php esc_html_e('Cancel Release', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if (!empty($job['post_id'])) : ?>
                    <a class="button" href="<?php echo esc_url(get_edit_post_link((int) $job['post_id'])); ?>"><?php esc_html_e('Edit Post', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if (!empty($job['permalink'])) : ?>
                    <a class="button button-secondary" href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open Article', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if (in_array($job['status'], ['failed', 'partial_failure'], true)) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($this->retry_link($job)); ?>"><?php esc_html_e('Retry Job', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
            </div>
            <?php if ($job['status'] === 'scheduled') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kt-form kt-inline-form">
                    <?php wp_nonce_field('kuchnia_twist_set_job_schedule'); ?>
                    <input type="hidden" name="action" value="kuchnia_twist_set_job_schedule">
                    <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                    <?php foreach ($this->current_job_view_args() as $arg_key => $arg_value) : ?>
                        <input type="hidden" name="<?php echo esc_attr($arg_key); ?>" value="<?php echo esc_attr((string) $arg_value); ?>">
                    <?php endforeach; ?>
                    <div class="kt-field-grid">
                        <label>
                            <span><?php esc_html_e('Set Schedule', 'kuchnia-twist'); ?></span>
                            <input type="datetime-local" name="publish_at" step="60" value="<?php echo esc_attr($this->format_admin_datetime_input((string) ($job['publish_on'] ?? ''))); ?>" required>
                        </label>
                        <label>
                            <span><?php esc_html_e('Timezone', 'kuchnia-twist'); ?></span>
                            <input type="text" value="<?php echo esc_attr(wp_timezone_string() ?: 'UTC'); ?>" readonly>
                        </label>
                    </div>
                    <div class="kt-inline-actions">
                        <button type="submit" class="button"><?php esc_html_e('Reschedule', 'kuchnia-twist'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_job_social_pack_section(array $job): void
    {
        $social_pack = $this->job_social_pack($job);

        if ($social_pack) {
            $selected_pages = $this->job_selected_pages($job);
            ?>
            <section class="kt-detail-block">
                <div class="kt-detail-block__head">
                    <h4><?php esc_html_e('Facebook Social Pack', 'kuchnia-twist'); ?></h4>
                    <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('%d variant', '%d variants', count($social_pack), 'kuchnia-twist'), count($social_pack))); ?></span>
                </div>
                <div class="kt-variant-list">
                    <?php foreach ($social_pack as $index => $variant) : ?>
                        <?php
                        $target_page = $selected_pages[$index]['label'] ?? '';
                        $variant_id = 'kt-social-variant-' . (int) $job['id'] . '-' . (int) $index;
                        $post_preview = $this->build_facebook_post_preview($variant);
                        ?>
                        <article class="kt-variant-card">
                            <div class="kt-variant-card__head">
                                <div>
                                    <strong><?php echo esc_html(sprintf(__('Variant %d', 'kuchnia-twist'), $index + 1)); ?></strong>
                                    <?php if ($target_page !== '') : ?>
                                        <span><?php echo esc_html($target_page); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($variant['angle_key'])) : ?>
                                    <span class="kt-stage-pill"><?php echo esc_html($this->hook_angle_label((string) $variant['angle_key'], (string) ($job['content_type'] ?? 'recipe'))); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="kt-variant-fields">
                                <?php if (!empty($variant['hook'])) : ?>
                                    <div class="kt-variant-field">
                                        <div class="kt-detail-block__head">
                                            <label for="<?php echo esc_attr($variant_id . '-hook'); ?>"><?php esc_html_e('Hook', 'kuchnia-twist'); ?></label>
                                            <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id . '-hook'); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <textarea id="<?php echo esc_attr($variant_id . '-hook'); ?>" rows="2" readonly><?php echo esc_textarea((string) ($variant['hook'] ?? '')); ?></textarea>
                                    </div>
                                <?php endif; ?>
                                <div class="kt-variant-field">
                                    <div class="kt-detail-block__head">
                                        <label for="<?php echo esc_attr($variant_id); ?>"><?php esc_html_e('Caption', 'kuchnia-twist'); ?></label>
                                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                    </div>
                                    <textarea id="<?php echo esc_attr($variant_id); ?>" rows="5" readonly><?php echo esc_textarea((string) ($variant['caption'] ?? '')); ?></textarea>
                                </div>
                                <?php if ($post_preview !== '') : ?>
                                    <div class="kt-variant-field">
                                        <div class="kt-detail-block__head">
                                            <label for="<?php echo esc_attr($variant_id . '-message'); ?>"><?php esc_html_e('Final post message', 'kuchnia-twist'); ?></label>
                                            <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id . '-message'); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <textarea id="<?php echo esc_attr($variant_id . '-message'); ?>" rows="6" readonly><?php echo esc_textarea($post_preview); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($variant['cta_hint'])) : ?>
                                <p class="kt-detail-note"><?php echo esc_html($variant['cta_hint']); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php

            return;
        }

        $legacy_caption = $this->derive_legacy_facebook_caption(is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [], $job);
        if ($legacy_caption === '') {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <div class="kt-detail-block__head">
                <label for="kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Facebook Caption', 'kuchnia-twist'); ?></label>
                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
            </div>
            <textarea id="kt-facebook-caption-<?php echo (int) $job['id']; ?>" rows="5" readonly><?php echo esc_textarea($legacy_caption); ?></textarea>
        </section>
        <?php
    }

    private function render_job_distribution_section(array $job, array $distribution): void
    {
        if (empty($distribution['pages'])) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <div class="kt-detail-block__head">
                <h4><?php esc_html_e('Facebook Distribution', 'kuchnia-twist'); ?></h4>
                <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('%d page', '%d pages', count($distribution['pages']), 'kuchnia-twist'), count($distribution['pages']))); ?></span>
            </div>
            <div class="kt-distribution-list">
                <?php foreach ($distribution['pages'] as $page) : ?>
                    <?php
                    $distribution_preview = $this->build_facebook_post_preview(is_array($page) ? $page : []);
                    $comment_preview = $this->build_facebook_comment_preview($job, is_array($page) ? $page : []);
                    ?>
                    <article class="kt-distribution-card">
                        <div class="kt-distribution-card__head">
                            <div>
                                <strong><?php echo esc_html($page['label'] ?: $page['page_id']); ?></strong>
                                <span><?php echo esc_html($page['page_id']); ?></span>
                            </div>
                            <span class="kt-status kt-status--<?php echo esc_attr($page['status'] ?: 'queued'); ?>"><?php echo esc_html($this->format_human_label($page['status'] ?: 'queued')); ?></span>
                        </div>
                        <div class="kt-context-chips">
                            <?php if (!empty($page['angle_key'])) : ?>
                                <span class="kt-context-chip"><?php echo esc_html($this->hook_angle_label((string) $page['angle_key'], (string) ($job['content_type'] ?? 'recipe'))); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($page['post_id'])) : ?>
                                <span class="kt-context-chip"><?php echo esc_html(sprintf(__('Post ID: %s', 'kuchnia-twist'), $page['post_id'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($page['comment_id'])) : ?>
                                <span class="kt-context-chip"><?php echo esc_html(sprintf(__('Comment ID: %s', 'kuchnia-twist'), $page['comment_id'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($distribution_preview !== '') : ?>
                            <div class="kt-variant-field">
                                <div class="kt-detail-block__head">
                                    <label for="<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Posted message', 'kuchnia-twist'); ?></label>
                                    <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                </div>
                                <textarea id="<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>" rows="6" readonly><?php echo esc_textarea($distribution_preview); ?></textarea>
                            </div>
                        <?php endif; ?>
                        <?php if ($comment_preview !== '') : ?>
                            <div class="kt-variant-field">
                                <div class="kt-detail-block__head">
                                    <label for="<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('First comment message', 'kuchnia-twist'); ?></label>
                                    <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                </div>
                                <textarea id="<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>" rows="4" readonly><?php echo esc_textarea($comment_preview); ?></textarea>
                            </div>
                        <?php endif; ?>
                        <div class="kt-inline-actions">
                            <?php if (!empty($page['post_url'])) : ?>
                                <a class="button button-small" href="<?php echo esc_url($page['post_url']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open Facebook Post', 'kuchnia-twist'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($page['comment_url'])) : ?>
                                <a class="button button-small" href="<?php echo esc_url($page['comment_url']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open First Comment', 'kuchnia-twist'); ?></a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($page['error'])) : ?>
                            <p class="kt-error"><?php echo esc_html($page['error']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function render_job_ops_timeline(array $events): void
    {
        if (!$events) {
            return;
        }
        ?>
        <section class="kt-detail-block">
            <div class="kt-detail-block__head">
                <h4><?php esc_html_e('Ops Timeline', 'kuchnia-twist'); ?></h4>
                <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('Last %d event', 'Last %d events', count($events), 'kuchnia-twist'), count($events))); ?></span>
            </div>
            <div class="kt-event-list">
                <?php foreach ($events as $event) : ?>
                    <article class="kt-event">
                        <div class="kt-event__top">
                            <strong><?php echo esc_html($this->format_human_label($event['event_type'])); ?></strong>
                            <span><?php echo esc_html($this->format_admin_datetime($event['created_at'])); ?></span>
                        </div>
                        <div class="kt-chip-row">
                            <?php if (!empty($event['status'])) : ?>
                                <span class="kt-status kt-status--<?php echo esc_attr($event['status']); ?>"><?php echo esc_html($this->format_human_label($event['status'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($event['stage']) && $event['stage'] !== $event['status']) : ?>
                                <span class="kt-stage-pill"><?php echo esc_html($this->format_human_label($event['stage'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($event['message'])) : ?>
                            <p><?php echo esc_html($event['message']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($event['context']) && is_array($event['context'])) : ?>
                            <div class="kt-context-chips">
                                <?php foreach ($event['context'] as $key => $value) : ?>
                                    <?php if ($value === '' || $value === null) { continue; } ?>
                                    <span class="kt-context-chip">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                '%s: %s',
                                                $this->format_human_label((string) $key),
                                                is_bool($value) ? ($value ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')) : (string) $value
                                            )
                                        );
                                        ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
