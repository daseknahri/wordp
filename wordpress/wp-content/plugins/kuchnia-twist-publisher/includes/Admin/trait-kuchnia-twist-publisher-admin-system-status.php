<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_System_Status_Trait
{
    private function default_worker_status(): array
    {
        return [
            'worker_version'        => '',
            'enabled'               => false,
            'run_once'              => false,
            'poll_seconds'          => 0,
            'startup_delay_seconds' => 0,
            'config_ok'             => false,
            'last_seen_at'          => '',
            'last_loop_result'      => '',
            'last_job_id'           => 0,
            'last_job_status'       => '',
            'last_error'            => '',
        ];
    }

    private function get_worker_status(): array
    {
        return wp_parse_args(get_option(self::WORKER_STATUS_KEY, []), $this->default_worker_status());
    }

    private function system_status_snapshot(array $settings): array
    {
        $worker_status     = $this->get_worker_status();
        $worker_secret_set = $this->get_worker_secret() !== '';
        $openai_ready      = trim((string) (getenv('OPENAI_API_KEY') ?: $settings['openai_api_key'])) !== '';
        $facebook_pages    = $this->facebook_pages($settings, true, true);
        $facebook_ready    = !empty($facebook_pages);
        $poll_seconds      = max(0, (int) ($worker_status['poll_seconds'] ?? 0));
        $stale_after       = max(90, $poll_seconds * 3);
        $last_seen_at      = (string) ($worker_status['last_seen_at'] ?? '');
        $last_seen_unix    = $last_seen_at !== '' ? strtotime($last_seen_at . ' UTC') : 0;
        $now_unix          = current_time('timestamp', true);
        $heartbeat_age     = $last_seen_unix > 0 ? max(0, $now_unix - $last_seen_unix) : PHP_INT_MAX;
        $worker_stale      = $last_seen_unix <= 0 || $heartbeat_age > $stale_after;
        $worker_enabled    = !empty($worker_status['enabled']);
        $worker_config_ok  = $worker_secret_set && !empty($worker_status['config_ok']);
        $worker_version    = sanitize_text_field((string) ($worker_status['worker_version'] ?? ''));
        $last_error        = sanitize_text_field((string) ($worker_status['last_error'] ?? ''));
        $last_loop_result  = sanitize_key((string) ($worker_status['last_loop_result'] ?? ''));

        if ($last_seen_unix > 0) {
            $heartbeat_text = sprintf(
                __('Last seen %1$s ago.', 'kuchnia-twist'),
                human_time_diff($last_seen_unix, $now_unix)
            );
        } else {
            $heartbeat_text = __('No worker heartbeat received yet.', 'kuchnia-twist');
        }

        if ($worker_version !== '') {
            $heartbeat_text .= ' ' . sprintf(__('Version %s.', 'kuchnia-twist'), $worker_version);
        }

        if ($worker_enabled) {
            $worker_loop_text = !empty($worker_status['run_once'])
                ? __('Worker is in run-once mode and will idle after the current pass.', 'kuchnia-twist')
                : sprintf(
                    __('Polling every %1$ss after a %2$ss startup delay.', 'kuchnia-twist'),
                    max(1, $poll_seconds),
                    max(0, (int) ($worker_status['startup_delay_seconds'] ?? 0))
                );
        } else {
            $worker_loop_text = __('AUTOPOST_ENABLED is off in the worker container.', 'kuchnia-twist');
        }

        if ($last_loop_result !== '') {
            $worker_loop_text .= ' ' . sprintf(
                __('Last loop: %s.', 'kuchnia-twist'),
                $this->format_human_label($last_loop_result)
            );
        }

        if (!$worker_secret_set) {
            $worker_config_text = __('CONTENT_PIPELINE_SHARED_SECRET is missing in the WordPress container.', 'kuchnia-twist');
        } elseif ($worker_config_ok) {
            $worker_config_text = __('Worker reported a valid internal URL and shared secret configuration.', 'kuchnia-twist');
        } else {
            $worker_config_text = __('The worker has not confirmed valid internal callback configuration yet.', 'kuchnia-twist');
        }

        if ($last_error !== '') {
            $worker_config_text .= ' ' . sprintf(__('Last error: %s', 'kuchnia-twist'), $last_error);
        }

        $openai_text = $openai_ready
            ? __('An OpenAI API key is available from the environment or plugin settings.', 'kuchnia-twist')
            : __('Add OPENAI_API_KEY in Coolify or the plugin settings before background generation can run.', 'kuchnia-twist');

        $facebook_text = $facebook_ready
            ? sprintf(_n('%d active Facebook page is configured for distribution.', '%d active Facebook pages are configured for distribution.', count($facebook_pages), 'kuchnia-twist'), count($facebook_pages))
            : __('No active Facebook pages are configured. Blog publication can still succeed and stop at partial failure for Facebook.', 'kuchnia-twist');

        return [
            'worker_status'         => $worker_status,
            'worker_version'        => $worker_version,
            'worker_enabled'        => $worker_enabled,
            'worker_config_ok'      => $worker_config_ok,
            'worker_stale'          => $worker_stale,
            'worker_heartbeat_text' => $heartbeat_text,
            'last_seen_label'       => $last_seen_at !== '' ? $this->format_admin_datetime($last_seen_at) : __('Never', 'kuchnia-twist'),
            'worker_loop_text'      => $worker_loop_text,
            'worker_config_text'    => $worker_config_text,
            'worker_last_error'     => $last_error,
            'last_loop_label'       => $last_loop_result !== '' ? $this->format_human_label($last_loop_result) : __('Unknown', 'kuchnia-twist'),
            'last_job_id'           => max(0, (int) ($worker_status['last_job_id'] ?? 0)),
            'last_job_status'       => sanitize_key((string) ($worker_status['last_job_status'] ?? '')),
            'stale_after_seconds'   => $stale_after,
            'stale_after_label'     => sprintf(_n('%d second', '%d seconds', $stale_after, 'kuchnia-twist'), $stale_after),
            'openai_ready'          => $openai_ready,
            'openai_text'           => $openai_text,
            'facebook_ready'        => $facebook_ready,
            'facebook_text'         => $facebook_text,
            'facebook_pages_count'  => count($facebook_pages),
        ];
    }

    private function render_system_alerts(array $system_status): void
    {
        $alerts = [];

        if ($system_status['worker_stale']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Worker heartbeat is stale', 'kuchnia-twist'),
                'message' => __('The autopost container has not checked in recently, so queued jobs may sit idle until it comes back.', 'kuchnia-twist'),
                'detail'  => $system_status['worker_heartbeat_text'],
            ];
        }

        if (!$system_status['worker_config_ok']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Worker configuration needs attention', 'kuchnia-twist'),
                'message' => __('The worker has not confirmed a valid internal callback configuration yet.', 'kuchnia-twist'),
                'detail'  => $system_status['worker_config_text'],
            ];
        }

        if (!$system_status['openai_ready']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('OpenAI is missing', 'kuchnia-twist'),
                'message' => __('Queueing still works, but background generation will fail until an API key is available.', 'kuchnia-twist'),
                'detail'  => $system_status['openai_text'],
            ];
        }

        if (!$system_status['facebook_ready']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Facebook publishing is not fully configured', 'kuchnia-twist'),
                'message' => __('The blog article can still go live, but Facebook publishing may stop at partial failure.', 'kuchnia-twist'),
                'detail'  => $system_status['facebook_text'],
            ];
        }

        $legacy_queue = $this->dormant_content_queue_count();
        if ($legacy_queue > 0) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Dormant content jobs detected', 'kuchnia-twist'),
                'message' => __('Recipe and Food Fact are active. Older dormant content jobs still need manual review before you trust them in the typed content flow.', 'kuchnia-twist'),
                'detail'  => sprintf(_n('%d dormant queued job needs manual review.', '%d dormant queued jobs need manual review.', $legacy_queue, 'kuchnia-twist'), $legacy_queue),
            ];
        }

        if (!$alerts) {
            return;
        }
        ?>
        <div class="kt-alert-list" role="status" aria-live="polite">
            <?php foreach ($alerts as $alert) : ?>
                <article class="kt-alert <?php echo esc_attr($alert['class']); ?>">
                    <strong><?php echo esc_html($alert['title']); ?></strong>
                    <p><?php echo esc_html($alert['message']); ?></p>
                    <?php if (!empty($alert['detail'])) : ?>
                        <span><?php echo esc_html($alert['detail']); ?></span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function get_dashboard_counts(): array
    {
        global $wpdb;

        $counts = [
            'queued'          => 0,
            'scheduled'       => 0,
            'running'         => 0,
            'needs_attention' => 0,
            'completed'       => 0,
        ];

        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->table_name()} GROUP BY status", ARRAY_A);
        foreach ($rows ?: [] as $row) {
            $status = sanitize_key($row['status'] ?? '');
            $total  = (int) ($row['total'] ?? 0);

            if ($status === 'queued') {
                $counts['queued'] += $total;
                continue;
            }

            if ($status === 'scheduled') {
                $counts['scheduled'] += $total;
                continue;
            }

            if (in_array($status, ['generating', 'publishing_blog', 'publishing_facebook'], true)) {
                $counts['running'] += $total;
                continue;
            }

            if (in_array($status, ['failed', 'partial_failure'], true)) {
                $counts['needs_attention'] += $total;
                continue;
            }

            if ($status === 'completed') {
                $counts['completed'] += $total;
            }
        }

        return $counts;
    }

    private function dormant_content_queue_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()} WHERE status = 'queued' AND content_type NOT IN ('recipe', 'food_fact')");
    }
}
