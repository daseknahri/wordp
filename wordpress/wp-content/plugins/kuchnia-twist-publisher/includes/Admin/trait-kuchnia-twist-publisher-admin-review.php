<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Review_Trait
{
    private function render_notice(string $notice_key): void
    {
        $messages = [
            'job_created'   => __('Publishing job queued. The background worker will pick it up shortly.', 'kuchnia-twist'),
            'job_queued'    => __('Job queued again for processing.', 'kuchnia-twist'),
            'job_missing'   => __('The selected job could not be found.', 'kuchnia-twist'),
            'invalid_job'   => __('Please enter a valid dish name or working title before queueing the job.', 'kuchnia-twist'),
            'invalid_schedule' => __('Enter a valid publish date and time in the WordPress timezone.', 'kuchnia-twist'),
            'duplicate_job' => __('A matching job is already in progress, so the existing one was kept instead of creating a duplicate.', 'kuchnia-twist'),
            'existing_post_conflict' => __('A published or queued post with the same topic/title already exists, so the duplicate launch article was blocked.', 'kuchnia-twist'),
            'launch_title_required' => __('Launch mode requires a final title before a job can be queued.', 'kuchnia-twist'),
            'launch_assets_required' => __('Manual-only image handling requires both a real blog hero image and a real Facebook image.', 'kuchnia-twist'),
            'facebook_pages_required' => __('Select at least one active Facebook page before queueing an article job.', 'kuchnia-twist'),
            'recipe_only_lane' => __('This job type is not active in the current typed content engine. Only recipe and food fact jobs can generate new content right now.', 'kuchnia-twist'),
            'job_action_blocked' => __('That scheduling action is only available for scheduled article jobs.', 'kuchnia-twist'),
            'job_publish_now' => __('The scheduled job will publish on the next worker pass.', 'kuchnia-twist'),
            'job_schedule_updated' => __('The scheduled publish time was updated.', 'kuchnia-twist'),
            'job_schedule_canceled' => __('The scheduled release was canceled and moved into needs attention.', 'kuchnia-twist'),
        ];

        if (!isset($messages[$notice_key])) {
            return;
        }

        $class = in_array($notice_key, ['launch_title_required', 'launch_assets_required', 'facebook_pages_required', 'invalid_job', 'invalid_schedule', 'existing_post_conflict', 'job_schedule_canceled', 'recipe_only_lane', 'job_action_blocked'], true) ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($messages[$notice_key]) . '</p></div>';
    }

    private function render_job_stage_rail(array $job): void
    {
        $steps = $this->job_stage_items($job);
        if (!$steps) {
            return;
        }
        ?>
        <section class="kt-stage-rail" aria-label="<?php esc_attr_e('Job progress', 'kuchnia-twist'); ?>">
            <?php foreach ($steps as $step) : ?>
                <div class="kt-stage-step is-<?php echo esc_attr($step['state']); ?>">
                    <span class="kt-stage-step__dot" aria-hidden="true"></span>
                    <strong><?php echo esc_html($step['label']); ?></strong>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private function job_stage_items(array $job): array
    {
        $sequence = [
            'queued'             => __('Queued', 'kuchnia-twist'),
            'generating'         => __('Generating', 'kuchnia-twist'),
            'scheduled'          => __('Scheduled', 'kuchnia-twist'),
            'publishing_blog'    => __('WordPress', 'kuchnia-twist'),
            'publishing_facebook'=> __('Facebook', 'kuchnia-twist'),
        ];

        $stage         = sanitize_key((string) ($job['stage'] ?? $job['status'] ?? 'queued'));
        $status        = sanitize_key((string) ($job['status'] ?? 'queued'));
        $current_index = array_search($stage, array_keys($sequence), true);
        $current_index = $current_index === false ? 0 : (int) $current_index;
        $items         = [];
        $keys          = array_keys($sequence);

        foreach ($sequence as $key => $label) {
            $index = array_search($key, $keys, true);
            $index = $index === false ? 0 : (int) $index;
            $state = 'pending';

            if ($status === 'completed') {
                $state = 'complete';
            } elseif (in_array($status, ['failed', 'partial_failure'], true)) {
                if ($index < $current_index) {
                    $state = 'complete';
                } elseif ($index === $current_index) {
                    $state = 'problem';
                }
            } else {
                if ($index < $current_index) {
                    $state = 'complete';
                } elseif ($index === $current_index) {
                    $state = 'current';
                }
            }

            $items[] = [
                'key'   => $key,
                'label' => $label,
                'state' => $state,
            ];
        }

        $items[] = [
            'key'   => 'outcome',
            'label' => $status === 'completed'
                ? __('Completed', 'kuchnia-twist')
                : (in_array($status, ['failed', 'partial_failure'], true) ? __('Needs attention', 'kuchnia-twist') : __('In progress', 'kuchnia-twist')),
            'state' => $status === 'completed' ? 'complete' : (in_array($status, ['failed', 'partial_failure'], true) ? 'problem' : 'current'),
        ];

        return $items;
    }

    private function render_job_asset_badges(array $job): void
    {
        $assets = [
            'blog_image_id'         => __('Hero attached', 'kuchnia-twist'),
            'facebook_image_id'     => __('Facebook image attached', 'kuchnia-twist'),
            'featured_image_id'     => __('Featured image ready', 'kuchnia-twist'),
            'facebook_image_result_id' => __('Facebook result ready', 'kuchnia-twist'),
        ];

        foreach ($assets as $key => $label) {
            if (!empty($job[$key])) {
                echo '<span class="kt-asset-pill">' . esc_html($label) . '</span>';
            }
        }

        $selected_pages = $this->job_selected_pages($job);
        if ($selected_pages) {
            echo '<span class="kt-asset-pill">' . esc_html(sprintf(_n('%d page selected', '%d pages selected', count($selected_pages), 'kuchnia-twist'), count($selected_pages))) . '</span>';
        }

        $distribution = $this->job_facebook_distribution($job);
        if (!empty($distribution['pages']) && is_array($distribution['pages'])) {
            $completed = count(array_filter(
                $distribution['pages'],
                static fn (array $page): bool => ($page['status'] ?? '') === 'completed'
            ));
            echo '<span class="kt-asset-pill">' . esc_html(sprintf(__('Facebook %1$d/%2$d', 'kuchnia-twist'), $completed, count($distribution['pages']))) . '</span>';
        }

        $machine_meta = $this->job_content_machine_meta($job);
        $distribution_source = (string) ($machine_meta['validator_summary']['distribution_source'] ?? '');
        if ($distribution_source !== '') {
            echo '<span class="kt-asset-pill">' . esc_html(sprintf(__('Copy %s', 'kuchnia-twist'), $this->format_human_label($distribution_source))) . '</span>';
        }

        $quality_status = (string) ($this->job_quality_summary($job)['quality_status'] ?? '');
        if ($quality_status !== '') {
            echo '<span class="kt-asset-pill kt-asset-pill--' . esc_attr($this->quality_status_class($quality_status)) . '">' . esc_html($this->quality_status_label($quality_status)) . '</span>';
        }
    }

    private function job_has_media(array $job): bool
    {
        foreach (['blog_image', 'facebook_image', 'featured_image', 'facebook_image_result'] as $key) {
            if (!empty($job[$key]['url'])) {
                return true;
            }
        }

        return false;
    }

    private function render_job_media_cards(array $job): void
    {
        $items = [
            'blog_image'           => __('Queued Hero', 'kuchnia-twist'),
            'facebook_image'       => __('Queued Facebook Image', 'kuchnia-twist'),
            'featured_image'       => __('Published Featured Image', 'kuchnia-twist'),
            'facebook_image_result'=> __('Published Facebook Result', 'kuchnia-twist'),
        ];

        foreach ($items as $key => $label) {
            $media = $job[$key] ?? [];
            if (empty($media['url'])) {
                continue;
            }
            ?>
            <article class="kt-media-card">
                <img src="<?php echo esc_url($media['url']); ?>" alt="">
                <div class="kt-media-card__body">
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if (!empty($media['title'])) : ?>
                        <span><?php echo esc_html($media['title']); ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php
        }
    }

    private function job_author_label(array $job): string
    {
        if (!empty($job['created_by'])) {
            $user = get_userdata((int) $job['created_by']);
            if ($user instanceof WP_User && $user->display_name !== '') {
                return $user->display_name;
            }
        }

        return __('Unknown', 'kuchnia-twist');
    }

    private function job_requested_title(array $job): string
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $title = sanitize_text_field((string) ($request['title_override'] ?? $job['title_override'] ?? ''));

        return $title !== '' ? $title : __('AI generated title', 'kuchnia-twist');
    }

    private function job_site_label(array $job): string
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $site = sanitize_text_field((string) ($request['site_name'] ?? ''));

        return $site !== '' ? $site : get_bloginfo('name');
    }

}
