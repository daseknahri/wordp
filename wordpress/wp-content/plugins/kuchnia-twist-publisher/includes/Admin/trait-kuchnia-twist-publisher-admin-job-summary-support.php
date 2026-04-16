<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Job_Summary_Support_Trait
{
    private function retry_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_retry_job',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_retry_job'
        );
    }

    private function publish_now_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_publish_now',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_publish_now'
        );
    }

    private function cancel_scheduled_job_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_cancel_scheduled_job',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_cancel_scheduled_job'
        );
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
            'queued'              => __('Queued', 'kuchnia-twist'),
            'generating'          => __('Generating', 'kuchnia-twist'),
            'scheduled'           => __('Scheduled', 'kuchnia-twist'),
            'publishing_blog'     => __('WordPress', 'kuchnia-twist'),
            'publishing_facebook' => __('Facebook', 'kuchnia-twist'),
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
            'blog_image_id'            => __('Hero attached', 'kuchnia-twist'),
            'facebook_image_id'        => __('Facebook image attached', 'kuchnia-twist'),
            'featured_image_id'        => __('Featured image ready', 'kuchnia-twist'),
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
            'blog_image'            => __('Queued Hero', 'kuchnia-twist'),
            'facebook_image'        => __('Queued Facebook Image', 'kuchnia-twist'),
            'featured_image'        => __('Published Featured Image', 'kuchnia-twist'),
            'facebook_image_result' => __('Published Facebook Result', 'kuchnia-twist'),
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
