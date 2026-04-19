<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Rest_Worker_Trait
{
    public function register_rest_routes(): void
    {
        $namespace = $this->worker_rest_namespace();
        $routes = $this->worker_route_templates();
        $callbacks = [
            'claim'        => 'rest_claim_job',
            'media'        => 'rest_upload_media',
            'publish_blog' => 'rest_publish_blog',
            'progress'     => 'rest_progress_job',
            'complete'     => 'rest_complete_job',
            'fail'         => 'rest_fail_job',
            'heartbeat'    => 'rest_worker_heartbeat',
        ];

        foreach ($callbacks as $key => $method) {
            $route = isset($routes[$key]) ? '/' . ltrim((string) $routes[$key], '/') : '';
            if ($route === '/') {
                continue;
            }

            register_rest_route($namespace, $route, [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, $method],
                'permission_callback' => '__return_true',
            ]);
        }
    }

    public function rest_claim_job(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        global $wpdb;
        $table = $this->table_name();
        $claim_mode = '';
        $job = $wpdb->get_row(
            "SELECT * FROM {$table}
            WHERE status = 'scheduled'
              AND publish_on IS NOT NULL
              AND publish_on <= UTC_TIMESTAMP()
            ORDER BY publish_on ASC, id ASC
            LIMIT 1",
            ARRAY_A
        );

        if ($job) {
            $claim_mode = 'publish';
        } else {
            $queueable_types = array_keys($this->queueable_content_types());
            $placeholders = implode(', ', array_fill(0, count($queueable_types), '%s'));
            $job = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = 'queued' AND content_type IN ({$placeholders}) ORDER BY id ASC LIMIT 1",
                    ...$queueable_types
                ),
                ARRAY_A
            );
            if ($job) {
                $claim_mode = 'generate';
            }
        }

        if (!$job) {
            return rest_ensure_response(['job' => null]);
        }

        if ($claim_mode === 'publish') {
            $publish_stage = in_array((string) ($job['retry_target'] ?? ''), ['facebook', 'comment'], true) ? 'publishing_facebook' : 'publishing_blog';
            $wpdb->update(
                $table,
                [
                    'status'          => $publish_stage,
                    'stage'           => $publish_stage,
                    'last_attempt_at' => current_time('mysql', true),
                    'updated_at'      => current_time('mysql', true),
                ],
                ['id' => (int) $job['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            $this->add_job_event(
                (int) $job['id'],
                'job_claimed',
                $publish_stage,
                $publish_stage,
                __('Worker claimed a due scheduled job.', 'kuchnia-twist'),
                [
                    'last_attempt_at' => current_time('mysql', true),
                    'publish_on'      => (string) ($job['publish_on'] ?? ''),
                ]
            );
        } else {
            $wpdb->update(
                $table,
                [
                    'status'          => 'generating',
                    'stage'           => 'generating',
                    'last_attempt_at' => current_time('mysql', true),
                    'updated_at'      => current_time('mysql', true),
                ],
                ['id' => (int) $job['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            $this->add_job_event(
                (int) $job['id'],
                'job_claimed',
                'generating',
                'generating',
                __('Worker claimed queued job.', 'kuchnia-twist'),
                ['last_attempt_at' => current_time('mysql', true)]
            );
        }

        $settings = $this->get_settings();

        return rest_ensure_response([
            'claim_mode' => $claim_mode,
            'job'      => $this->get_job((int) $job['id']),
            'settings' => $this->worker_runtime_settings_payload($settings),
        ]);
    }

    public function rest_progress_job(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $job = $this->get_job((int) $request['id']);
        if (!$job) {
            return new WP_Error('job_not_found', __('Job not found.', 'kuchnia-twist'), ['status' => 404]);
        }

        $params = $request->get_json_params();
        global $wpdb;

        $status = sanitize_key($params['status'] ?? $job['status']);
        $stage  = sanitize_key($params['stage'] ?? $status);
        $request_payload = $this->normalized_job_request_payload(
            is_array($params['request_payload'] ?? null) ? $params['request_payload'] : ($job['request_payload'] ?? []),
            $job
        );
        $generated_payload = is_array($params['generated_payload'] ?? null) ? $params['generated_payload'] : ($job['generated_payload'] ?? []);
        $generated_payload = $this->sync_generated_contract_containers($generated_payload, $job);
        $featured_image_id = !empty($params['featured_image_id']) ? (int) $params['featured_image_id'] : (int) ($job['featured_image_id'] ?? 0);
        $facebook_image_id = !empty($params['facebook_image_result_id']) ? (int) $params['facebook_image_result_id'] : (int) ($job['facebook_image_result_id'] ?? 0);
        $publish_on        = !empty($params['publish_on']) ? (string) $params['publish_on'] : (string) ($job['publish_on'] ?? '');
        $facebook_caption  = isset($params['facebook_caption']) ? (string) $params['facebook_caption'] : $this->derive_legacy_facebook_caption($generated_payload, $job);
        $group_share_kit   = isset($params['group_share_kit']) ? (string) $params['group_share_kit'] : $this->derive_legacy_group_share_kit($generated_payload);
        $validator_summary = is_array($generated_payload['content_machine']['validator_summary'] ?? null)
            ? $generated_payload['content_machine']['validator_summary']
            : [];
        $quality_status = sanitize_key((string) ($validator_summary['quality_status'] ?? ''));
        $blocking_checks = !empty($validator_summary['blocking_checks']) && is_array($validator_summary['blocking_checks'])
            ? $validator_summary['blocking_checks']
            : [];
        $warning_checks = !empty($validator_summary['warning_checks']) && is_array($validator_summary['warning_checks'])
            ? $validator_summary['warning_checks']
            : [];

        if ($status === 'scheduled' && $publish_on === '') {
            $publish_on = current_time('mysql', true);
        }

        $wpdb->update(
            $this->table_name(),
            [
                'status'        => $status,
                'stage'         => $stage,
                'publish_on'    => $publish_on !== '' ? $publish_on : null,
                'request_payload' => wp_json_encode($request_payload),
                'generated_payload' => wp_json_encode($generated_payload),
                'facebook_caption'  => $facebook_caption,
                'group_share_kit'   => $group_share_kit,
                'featured_image_id' => $featured_image_id ?: null,
                'facebook_image_result_id' => $facebook_image_id ?: null,
                'error_message' => !empty($params['error_message']) ? (string) $params['error_message'] : null,
                'updated_at'    => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        $message = !empty($params['error_message'])
            ? (string) $params['error_message']
            : ($status === 'scheduled'
                ? ($quality_status === 'warn'
                    ? ($this->publish_time_is_future($publish_on)
                        ? sprintf(__('Job generated with quality warnings and scheduled for %s.', 'kuchnia-twist'), $this->format_admin_datetime($publish_on))
                        : __('Job generated with quality warnings and is ready to publish immediately.', 'kuchnia-twist'))
                    : ($this->publish_time_is_future($publish_on)
                        ? sprintf(__('Job generated and scheduled for %s.', 'kuchnia-twist'), $this->format_admin_datetime($publish_on))
                        : __('Job generated and ready to publish immediately.', 'kuchnia-twist')))
                : sprintf(__('Job moved to %s.', 'kuchnia-twist'), $this->format_human_label($stage)));

        $this->add_job_event(
            (int) $job['id'],
            $status === 'scheduled' ? 'job_scheduled' : 'progress_update',
            $status,
            $stage,
            $message,
            array_filter([
                'publish_on'     => $publish_on,
                'prompt_version' => is_array($generated_payload['content_machine'] ?? null) ? (string) ($generated_payload['content_machine']['prompt_version'] ?? '') : '',
                'content_preset' => is_array($generated_payload['content_machine'] ?? null) ? (string) ($generated_payload['content_machine']['content_preset'] ?? '') : '',
                'profile'        => is_array($generated_payload['content_machine'] ?? null) ? (string) ($generated_payload['content_machine']['publication_profile'] ?? '') : '',
                'repair_attempts'=> (string) ($validator_summary['repair_attempts'] ?? ''),
                'distribution'   => (string) ($validator_summary['distribution_source'] ?? ''),
                'target_pages'   => (string) ($validator_summary['target_pages'] ?? ''),
                'social_variants'=> (string) ($validator_summary['social_variants'] ?? ''),
                'quality_score'  => (string) ($validator_summary['quality_score'] ?? ''),
                'quality_status' => $quality_status,
                'blocking_checks'=> !empty($blocking_checks) ? (string) count($blocking_checks) : '',
                'warning_checks' => !empty($warning_checks) ? (string) count($warning_checks) : '',
            ])
        );

        return rest_ensure_response([
            'ok'  => true,
            'job' => $this->get_job((int) $job['id']),
        ]);
    }

    public function rest_worker_heartbeat(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $payload = $request->get_json_params();
        $status = [
            'worker_version'         => sanitize_text_field($payload['worker_version'] ?? ''),
            'enabled'                => !empty($payload['enabled']),
            'run_once'               => !empty($payload['run_once']),
            'poll_seconds'           => max(0, (int) ($payload['poll_seconds'] ?? 0)),
            'startup_delay_seconds'  => max(0, (int) ($payload['startup_delay_seconds'] ?? 0)),
            'config_ok'              => !empty($payload['config_ok']),
            'last_seen_at'           => current_time('mysql', true),
            'last_loop_result'       => sanitize_key($payload['last_loop_result'] ?? ''),
            'last_job_id'            => absint($payload['last_job_id'] ?? 0),
            'last_job_status'        => sanitize_key($payload['last_job_status'] ?? ''),
            'last_error'             => sanitize_text_field($payload['last_error'] ?? ''),
        ];

        update_option(self::WORKER_STATUS_KEY, wp_parse_args($status, $this->default_worker_status()), false);

        if ($status['last_job_id'] > 0 && ($status['last_error'] !== '' || !$status['config_ok'])) {
            $this->add_job_event(
                $status['last_job_id'],
                !$status['config_ok'] ? 'worker_config_warning' : 'worker_warning',
                $status['last_job_status'],
                $status['last_loop_result'],
                $status['last_error'] !== '' ? $status['last_error'] : __('Worker configuration warning received.', 'kuchnia-twist'),
                [
                    'worker_version' => $status['worker_version'],
                    'loop_result'    => $status['last_loop_result'],
                ]
            );
        }

        return rest_ensure_response(['ok' => true]);
    }
}
