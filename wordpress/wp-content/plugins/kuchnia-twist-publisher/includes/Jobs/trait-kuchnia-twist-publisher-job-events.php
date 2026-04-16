<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Job_Events_Trait
{
    private function get_job_events(int $job_id, int $limit = 12): array
    {
        global $wpdb;

        if ($job_id <= 0) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->events_table_name()} WHERE job_id = %d ORDER BY id DESC LIMIT %d",
                $job_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(function (array $row): array {
            return [
                'id'         => (int) $row['id'],
                'job_id'     => (int) $row['job_id'],
                'event_type' => sanitize_key((string) ($row['event_type'] ?? '')),
                'status'     => sanitize_key((string) ($row['status'] ?? '')),
                'stage'      => sanitize_key((string) ($row['stage'] ?? '')),
                'message'    => sanitize_text_field((string) ($row['message'] ?? '')),
                'context'    => $this->decode_json($row['context_json'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows ?: []);
    }

    private function get_job_event_stats(int $job_id): array
    {
        global $wpdb;

        if ($job_id <= 0) {
            return [
                'attempts' => 0,
                'retries'  => 0,
                'latest'   => '',
            ];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) AS total, MAX(created_at) AS latest_at
                FROM {$this->events_table_name()}
                WHERE job_id = %d
                GROUP BY event_type",
                $job_id
            ),
            ARRAY_A
        );

        $stats = [
            'attempts' => 0,
            'retries'  => 0,
            'latest'   => '',
        ];

        foreach ($rows ?: [] as $row) {
            $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
            $total      = (int) ($row['total'] ?? 0);

            if ($event_type === 'job_claimed') {
                $stats['attempts'] = $total;
            } elseif ($event_type === 'retry_queued') {
                $stats['retries'] = $total;
            }

            $latest_at = (string) ($row['latest_at'] ?? '');
            if ($latest_at !== '' && ($stats['latest'] === '' || strtotime($latest_at . ' UTC') > strtotime($stats['latest'] . ' UTC'))) {
                $stats['latest'] = $latest_at;
            }
        }

        return $stats;
    }

    private function add_job_event(int $job_id, string $event_type, string $status, string $stage, string $message, array $context = []): void
    {
        if ($job_id <= 0) {
            return;
        }

        global $wpdb;
        $clean_context = $this->compact_event_context($context);

        $wpdb->insert($this->events_table_name(), [
            'job_id'       => $job_id,
            'event_type'   => sanitize_key($event_type),
            'status'       => sanitize_key($status),
            'stage'        => sanitize_key($stage),
            'message'      => sanitize_textarea_field($message),
            'context_json' => $clean_context ? wp_json_encode($clean_context) : null,
            'created_at'   => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
    }

    private function compact_event_context(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $normalized_key = sanitize_key((string) $key);
            if ($normalized_key === '') {
                continue;
            }

            if (is_bool($value)) {
                $clean[$normalized_key] = $value;
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $clean[$normalized_key] = $value;
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $clean[$normalized_key] = sanitize_text_field(wp_html_excerpt($value, 180, '...'));
            }
        }

        return $clean;
    }
}
