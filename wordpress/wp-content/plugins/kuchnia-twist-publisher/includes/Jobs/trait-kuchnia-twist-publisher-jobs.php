<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Jobs_Trait
{
    public function handle_retry_job(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_retry_job');

        $job_id = (int) ($_GET['job_id'] ?? 0);
        $job    = $this->get_job($job_id);

        if (!$job) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=job_missing'));
            exit;
        }

        if (($job['content_type'] ?? '') !== 'recipe' && !$this->job_has_core_package($job)) {
            wp_safe_redirect($this->publisher_page_url([
                'job_id'    => $job_id,
                'kt_notice' => 'recipe_only_lane',
            ]));
            exit;
        }

        $retry_target = $this->job_retry_target($job);
        $status       = $retry_target === 'full' ? 'queued' : 'scheduled';
        $publish_on   = $retry_target === 'full' ? null : current_time('mysql', true);

        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'        => $status,
                'stage'         => $status,
                'retry_target'  => $retry_target,
                'publish_on'    => $publish_on,
                'error_message' => null,
                'updated_at'    => current_time('mysql', true),
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $machine_meta = $this->job_content_machine_meta($job);

        $this->add_job_event(
            $job_id,
            'retry_queued',
            $status,
            $status,
            __('Job queued for retry.', 'kuchnia-twist'),
            [
                'retry_target'        => $retry_target,
                'publish_on'          => $publish_on ?: '',
                'prompt_version'      => (string) ($machine_meta['prompt_version'] ?? ''),
                'publication_profile' => (string) ($machine_meta['publication_profile'] ?? ''),
                'content_preset'      => (string) ($machine_meta['content_preset'] ?? ''),
            ]
        );

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=kuchnia-twist-publisher');
        $redirect = remove_query_arg(['_wpnonce', 'action'], $redirect);
        $redirect = add_query_arg([
            'job_id'    => $job_id,
            'kt_notice' => 'job_queued',
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_create_job(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_create_job');

        $settings         = $this->get_settings();
        $queueable_types  = $this->queueable_content_types();
        $content_type     = sanitize_key((string) wp_unslash($_POST['content_type'] ?? 'recipe'));
        if (!isset($queueable_types[$content_type])) {
            $content_type = 'recipe';
        }

        $topic            = sanitize_text_field(wp_unslash($_POST['topic_seed'] ?? $_POST['dish_name'] ?? $_POST['working_title'] ?? ''));
        $title            = sanitize_text_field(wp_unslash($_POST['title_override'] ?? ''));
        $publish_at_input = (string) wp_unslash($_POST['publish_at'] ?? '');
        $publish_at_local = $this->sanitize_publish_datetime_input($publish_at_input);
        $input_mode       = $content_type === 'recipe' ? 'dish_name' : 'working_title';
        $selected_pages   = $this->selected_pages_from_ids((array) ($_POST['selected_facebook_pages'] ?? []), $settings);

        if ($topic === '') {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=invalid_job'));
            exit;
        }

        if ($publish_at_input !== '' && $publish_at_local === '') {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=invalid_schedule'));
            exit;
        }

        $blog_image_id     = $this->handle_media_upload('blog_image');
        $facebook_image_id = $this->handle_media_upload('facebook_image');

        if ($settings['image_generation_mode'] === 'manual_only' && (!$blog_image_id || !$facebook_image_id)) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=launch_assets_required'));
            exit;
        }

        if (empty($selected_pages)) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=facebook_pages_required'));
            exit;
        }

        $title_candidate = $title !== '' ? $title : $topic;
        if ($this->find_conflicting_post_id($title_candidate) > 0) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=existing_post_conflict'));
            exit;
        }

        $duplicate_job_id = $this->recent_duplicate_job_id($content_type, $topic, $title_candidate);
        if ($duplicate_job_id > 0) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $duplicate_job_id . '&kt_notice=duplicate_job'));
            exit;
        }

        $publish_on    = $this->normalize_requested_publish_on_utc($publish_at_local);
        $schedule_mode = $this->publish_time_is_future($publish_on) ? 'scheduled' : 'immediate';
        $payload       = $this->build_admin_job_request_payload([
            'topic'                      => $topic,
            'title_seed'                 => $topic,
            'input_mode'                 => $input_mode,
            'content_type'               => $content_type,
            'title_override'             => $title,
            'schedule_mode'              => $schedule_mode,
            'requested_publish_at'       => $publish_at_local,
            'requested_publish_timezone' => wp_timezone_string() ?: 'UTC',
            'blog_image_id'              => $blog_image_id,
            'facebook_image_id'          => $facebook_image_id,
            'selected_pages'             => $selected_pages,
            'site_name'                  => get_bloginfo('name'),
        ], $settings);

        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($this->table_name(), [
            'job_uuid'          => wp_generate_uuid4(),
            'topic'             => $topic,
            'content_type'      => $content_type,
            'title_override'    => $title,
            'blog_image_id'     => $blog_image_id ?: null,
            'facebook_image_id' => $facebook_image_id ?: null,
            'status'            => 'queued',
            'stage'             => 'queued',
            'retry_target'      => '',
            'publish_on'        => $publish_on !== '' ? $publish_on : null,
            'created_by'        => get_current_user_id(),
            'request_payload'   => wp_json_encode($payload),
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $job_id = (int) $wpdb->insert_id;
        $this->add_job_event(
            $job_id,
            'job_created',
            'queued',
            'queued',
            __('Job queued from wp-admin.', 'kuchnia-twist'),
            [
                'content_type'        => $content_type,
                'created_by'          => get_current_user_id(),
                'blog_image_id'       => $blog_image_id ?: 0,
                'facebook_image_id'   => $facebook_image_id ?: 0,
                'selected_pages'      => count($selected_pages),
                'schedule_mode'       => $schedule_mode,
                'publish_on'          => $publish_on,
                'prompt_version'      => self::CONTENT_MACHINE_VERSION,
                'publication_profile' => (string) ($payload['content_machine']['publication_profile'] ?? ''),
                'content_preset'      => (string) ($payload['content_machine']['content_preset'] ?? $content_type),
            ]
        );

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $job_id . '&kt_notice=job_created'));
        exit;
    }

    public function handle_add_recipe_idea(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_add_recipe_idea');

        $dish_name       = sanitize_text_field(wp_unslash($_POST['dish_name'] ?? ''));
        $preferred_angle = $this->normalize_hook_angle_key((string) wp_unslash($_POST['preferred_angle'] ?? ''));
        $operator_note   = sanitize_textarea_field((string) wp_unslash($_POST['operator_note'] ?? ''));

        if ($dish_name === '') {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=invalid_job'));
            exit;
        }

        $this->insert_recipe_idea([
            'dish_name'        => $dish_name,
            'preferred_angle'  => $preferred_angle,
            'operator_note'    => $operator_note,
            'status'           => 'idea',
            'created_by'       => get_current_user_id(),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=idea_created'));
        exit;
    }

    public function handle_archive_recipe_idea(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_archive_recipe_idea');

        $idea_id = absint($_GET['idea_id'] ?? 0);
        $idea    = $idea_id > 0 ? $this->get_recipe_idea($idea_id) : null;

        if (!$idea) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=idea_missing'));
            exit;
        }

        $this->update_recipe_idea($idea_id, [
            'status'     => 'archived',
            'updated_at' => current_time('mysql', true),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=idea_archived'));
        exit;
    }

    public function handle_publish_now(): void
    {
        $this->handle_scheduled_job_action('kuchnia_twist_publish_now', 'publish_now');
    }

    public function handle_set_job_schedule(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_set_job_schedule');

        $job_id = (int) ($_POST['job_id'] ?? 0);
        $job    = $this->get_job($job_id);

        if (!$job) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=job_missing'));
            exit;
        }

        if (!$this->job_allows_schedule_actions($job)) {
            wp_safe_redirect($this->publisher_page_url(array_merge(
                ['job_id' => $job_id, 'kt_notice' => 'job_action_blocked'],
                $this->posted_job_view_args()
            )));
            exit;
        }

        $publish_at_input = (string) wp_unslash($_POST['publish_at'] ?? '');
        $publish_at_local = $this->sanitize_publish_datetime_input($publish_at_input);
        if ($publish_at_local === '') {
            wp_safe_redirect($this->publisher_page_url(array_merge(
                ['job_id' => $job_id, 'kt_notice' => 'invalid_schedule'],
                $this->posted_job_view_args()
            )));
            exit;
        }

        $publish_on = $this->normalize_requested_publish_on_utc($publish_at_local);
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'     => 'scheduled',
                'stage'      => 'scheduled',
                'publish_on' => $publish_on,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->add_job_event(
            $job_id,
            'schedule_updated',
            'scheduled',
            'scheduled',
            $this->publish_time_is_future($publish_on)
                ? __('Scheduled publish time updated.', 'kuchnia-twist')
                : __('Scheduled publish time updated to publish immediately.', 'kuchnia-twist'),
            ['publish_on' => $publish_on]
        );

        wp_safe_redirect($this->publisher_page_url(array_merge(
            ['job_id' => $job_id, 'kt_notice' => 'job_schedule_updated'],
            $this->posted_job_view_args()
        )));
        exit;
    }

    public function handle_cancel_scheduled_job(): void
    {
        $this->handle_scheduled_job_action('kuchnia_twist_cancel_scheduled_job', 'cancel');
    }

    private function handle_scheduled_job_action(string $nonce_action, string $action): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer($nonce_action);

        $job_id = (int) ($_GET['job_id'] ?? 0);
        $job    = $this->get_job($job_id);

        if (!$job) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=job_missing'));
            exit;
        }

        if (!$this->job_allows_schedule_actions($job)) {
            wp_safe_redirect($this->publisher_page_url(array_merge(
                ['job_id' => $job_id, 'kt_notice' => 'job_action_blocked'],
                $this->current_job_view_args()
            )));
            exit;
        }

        global $wpdb;
        $notice = 'job_missing';

        if ($action === 'publish_now') {
            $retry_target = $this->job_retry_target($job);
            $wpdb->update(
                $this->table_name(),
                [
                    'status'        => 'scheduled',
                    'stage'         => 'scheduled',
                    'retry_target'  => $retry_target === 'full' ? 'publish' : $retry_target,
                    'publish_on'    => current_time('mysql', true),
                    'error_message' => null,
                    'updated_at'    => current_time('mysql', true),
                ],
                ['id' => $job_id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            $this->add_job_event(
                $job_id,
                'publish_now_requested',
                'scheduled',
                'scheduled',
                __('Scheduled job moved to publish immediately.', 'kuchnia-twist'),
                ['publish_on' => current_time('mysql', true)]
            );
            $notice = 'job_publish_now';
        } elseif ($action === 'cancel') {
            $wpdb->update(
                $this->table_name(),
                [
                    'status'        => 'failed',
                    'stage'         => 'scheduled',
                    'retry_target'  => $this->job_has_core_package($job) ? 'publish' : 'full',
                    'publish_on'    => null,
                    'error_message' => __('Scheduled release canceled by operator.', 'kuchnia-twist'),
                    'updated_at'    => current_time('mysql', true),
                ],
                ['id' => $job_id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            $this->add_job_event(
                $job_id,
                'schedule_canceled',
                'failed',
                'scheduled',
                __('Scheduled release canceled by operator.', 'kuchnia-twist'),
                []
            );
            $notice = 'job_schedule_canceled';
        }

        wp_safe_redirect($this->publisher_page_url(array_merge(
            [
                'job_id'        => $job_id,
                'kt_notice'     => $notice,
                'job_per_page'  => absint($_GET['job_per_page'] ?? 24),
            ],
            $this->current_job_view_args()
        )));
        exit;
    }

    public function handle_export_jobs(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_export_jobs');

        $filters = $this->job_filters_from_request();
        $rows    = $this->get_jobs_for_export($filters);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kuchnia-twist-jobs-' . gmdate('Y-m-d-H-i-s') . '.csv');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'id',
            'topic',
            'content_type',
            'status',
            'stage',
            'publish_on',
            'created_at',
            'last_attempt_at',
            'updated_at',
            'created_by',
            'post_id',
            'permalink',
            'facebook_post_id',
            'facebook_comment_id',
            'retry_target',
            'error_message',
        ]);

        foreach ($rows as $job) {
            fputcsv($output, [
                (int) $job['id'],
                (string) $job['topic'],
                (string) $job['content_type'],
                (string) $job['status'],
                (string) $job['stage'],
                (string) ($job['publish_on'] ?? ''),
                (string) ($job['created_at'] ?? ''),
                (string) ($job['last_attempt_at'] ?? ''),
                (string) ($job['updated_at'] ?? ''),
                (string) ($job['created_by'] ?? ''),
                (string) ($job['post_id'] ?? ''),
                (string) ($job['permalink'] ?? ''),
                (string) ($job['facebook_post_id'] ?? ''),
                (string) ($job['facebook_comment_id'] ?? ''),
                (string) ($job['retry_target'] ?? ''),
                (string) ($job['error_message'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }

}
