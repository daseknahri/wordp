<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Job_Summary_Trait
{
    private function render_job_summary(array $job, array $system_status = []): void
    {
        $events = $this->get_job_events((int) $job['id'], 12);
        $event_stats = $this->get_job_event_stats((int) $job['id']);
        $quality_summary = $this->job_quality_summary($job);
        $quality_status = (string) ($quality_summary['quality_status'] ?? '');
        ?>
        <div class="kt-summary">
            <div class="kt-summary__hero">
                <div>
                    <p class="kt-summary__eyebrow"><?php echo esc_html(sprintf(__('Job #%d', 'kuchnia-twist'), (int) $job['id'])); ?></p>
                    <h3><?php echo esc_html($job['topic']); ?></h3>
                    <div class="kt-summary__meta">
                        <span><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></span>
                        <span><?php echo esc_html(sprintf(__('Updated %s', 'kuchnia-twist'), $this->format_admin_datetime($job['updated_at']))); ?></span>
                        <?php if (!empty($job['publish_on'])) : ?>
                            <span><?php echo esc_html(sprintf(__('Scheduled %s', 'kuchnia-twist'), $this->format_admin_datetime($job['publish_on']))); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kt-summary__status">
                    <span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($this->format_human_label($job['status'])); ?></span>
                    <?php if (!empty($quality_summary['quality_status'])) : ?>
                        <span class="kt-status kt-status--<?php echo esc_attr($this->quality_status_class((string) $quality_summary['quality_status'])); ?>"><?php echo esc_html($this->quality_status_label((string) $quality_summary['quality_status'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($job['stage'])) : ?>
                        <span class="kt-stage-pill"><?php echo esc_html($this->format_human_label($job['stage'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($system_status['worker_stale'])) : ?>
                <section class="kt-detail-block kt-detail-block--warning">
                    <h4><?php esc_html_e('Worker Status', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('The autopost worker heartbeat is stale, so background progress may pause until the container checks in again.', 'kuchnia-twist'); ?></p>
                    <span class="kt-detail-note"><?php echo esc_html($system_status['worker_heartbeat_text'] ?? ''); ?></span>
                </section>
            <?php endif; ?>

            <?php if ($quality_status === 'warn') : ?>
                <section class="kt-detail-block kt-detail-block--warning">
                    <h4><?php esc_html_e('Quality Warnings', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('This recipe can still publish, but the content machine flagged softer quality issues that deserve a quick operator review before you rely on it heavily.', 'kuchnia-twist'); ?></p>
                </section>
            <?php elseif ($quality_status === 'block') : ?>
                <section class="kt-detail-block kt-detail-block--error">
                    <h4><?php esc_html_e('Quality Block', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('This recipe is blocked from blog publish until the hard integrity issues are resolved.', 'kuchnia-twist'); ?></p>
                </section>
            <?php endif; ?>

            <?php $this->render_job_stage_rail($job); ?>

            <div class="kt-chip-row">
                <?php $this->render_job_asset_badges($job); ?>
            </div>

            <?php $machine_meta = $this->job_content_machine_meta($job); ?>
            <?php $validator_summary_display = $this->job_summary_validator_summary_display($machine_meta, $quality_summary); ?>
            <?php $this->render_job_keyfacts($job, $event_stats, $quality_summary, $machine_meta); ?>
            <?php $this->render_job_request_snapshot($job); ?>

            <?php $generated_snapshot = $this->job_generated_snapshot($job); ?>
            <?php $this->render_job_generated_snapshot($job, $generated_snapshot, $machine_meta, $validator_summary_display); ?>

            <?php $this->render_job_recipe_snapshot($job); ?>

            <?php $distribution = $this->job_facebook_distribution($job); ?>
            <?php $this->render_job_outputs($job, $distribution); ?>

            <?php $this->render_job_media_section($job); ?>

            <?php $this->render_job_error_section($job); ?>

            <?php $this->render_job_actions($job); ?>

            <?php $this->render_job_social_pack_section($job); ?>
            <?php $this->render_job_distribution_section($job, $distribution); ?>
            <?php $this->render_job_ops_timeline($events); ?>
        </div>
        <?php
    }
}
