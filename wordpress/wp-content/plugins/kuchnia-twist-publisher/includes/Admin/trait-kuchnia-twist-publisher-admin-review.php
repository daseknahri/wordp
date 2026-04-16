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

}
