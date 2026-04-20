<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Rest_Publishing_Trait
{
    private function resolve_worker_callback_state(array $params, array $generated_payload, array $job = []): array
    {
        $publication = is_array($params['publication'] ?? null) ? $params['publication'] : [];
        $deliveries = is_array($params['deliveries'] ?? null) ? $params['deliveries'] : [];
        $facebook_delivery = is_array($deliveries['facebook'] ?? null) ? $deliveries['facebook'] : [];
        $facebook_groups_delivery = is_array($deliveries['facebook_groups'] ?? null) ? $deliveries['facebook_groups'] : [];

        $post_id = isset($publication['id']) ? (int) $publication['id'] : (int) ($params['post_id'] ?? ($job['post_id'] ?? 0));
        $permalink = isset($publication['permalink']) ? (string) $publication['permalink'] : (string) ($params['permalink'] ?? ($job['permalink'] ?? ''));
        $facebook_post_id = isset($facebook_delivery['postId'])
            ? sanitize_text_field((string) $facebook_delivery['postId'])
            : sanitize_text_field((string) ($params['facebook_post_id'] ?? ($job['facebook_post_id'] ?? '')));
        $facebook_comment_id = isset($facebook_delivery['commentId'])
            ? sanitize_text_field((string) $facebook_delivery['commentId'])
            : sanitize_text_field((string) ($params['facebook_comment_id'] ?? ($job['facebook_comment_id'] ?? '')));
        $facebook_caption = isset($facebook_delivery['caption'])
            ? (string) $facebook_delivery['caption']
            : (isset($params['facebook_caption']) ? (string) $params['facebook_caption'] : $this->derive_legacy_facebook_caption($generated_payload, $job));
        $group_share_kit = isset($facebook_groups_delivery['draft'])
            ? (string) $facebook_groups_delivery['draft']
            : (isset($facebook_groups_delivery['shareKit'])
                ? (string) $facebook_groups_delivery['shareKit']
                : (isset($params['group_share_kit']) ? (string) $params['group_share_kit'] : $this->derive_legacy_group_share_kit($generated_payload)));

        return [
            'post_id' => $post_id,
            'permalink' => $permalink,
            'facebook_post_id' => $facebook_post_id,
            'facebook_comment_id' => $facebook_comment_id,
            'facebook_caption' => $facebook_caption,
            'group_share_kit' => $group_share_kit,
        ];
    }

    public function rest_upload_media(WP_REST_Request $request)
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
        $slot   = sanitize_key($params['slot'] ?? 'blog');
        $b64    = (string) ($params['base64_data'] ?? '');
        $title  = sanitize_text_field($params['title'] ?? 'Generated image');
        $alt    = sanitize_text_field($params['alt'] ?? $title);
        $name   = sanitize_file_name($params['filename'] ?? 'generated-image.png');

        if ($b64 === '') {
            return new WP_Error('missing_image', __('Image payload is missing.', 'kuchnia-twist'), ['status' => 400]);
        }

        $binary = base64_decode($b64);
        if ($binary === false) {
            return new WP_Error('invalid_image', __('Image payload could not be decoded.', 'kuchnia-twist'), ['status' => 400]);
        }

        $this->raise_media_processing_limits();

        $upload = wp_upload_bits($name, null, $binary);
        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error'], ['status' => 500]);
        }

        $attachment_id = wp_insert_attachment([
            'post_title'     => $title,
            'post_mime_type' => wp_check_filetype($upload['file'])['type'] ?? 'image/png',
            'guid'           => $upload['url'],
        ], $upload['file']);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);

        global $wpdb;
        $column = $slot === 'facebook' ? 'facebook_image_id' : 'blog_image_id';
        $wpdb->update(
            $this->table_name(),
            [$column => $attachment_id, 'updated_at' => current_time('mysql', true)],
            ['id' => (int) $job['id']],
            ['%d', '%s'],
            ['%d']
        );

        $this->add_job_event(
            (int) $job['id'],
            'media_uploaded',
            (string) $job['status'],
            (string) $job['stage'],
            sprintf(__('Uploaded %s image asset.', 'kuchnia-twist'), $slot === 'facebook' ? __('Facebook', 'kuchnia-twist') : __('blog', 'kuchnia-twist')),
            [
                'slot'          => $slot,
                'attachment_id' => $attachment_id,
            ]
        );

        return rest_ensure_response($this->attachment_payload($attachment_id));
    }

    public function rest_publish_blog(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $job = $this->get_job((int) $request['id']);
        if (!$job) {
            return new WP_Error('job_not_found', __('Job not found.', 'kuchnia-twist'), ['status' => 404]);
        }

        $params          = $request->get_json_params();
        $featured_image  = (int) ($params['featured_image_id'] ?? 0);
        $facebook_image  = (int) ($params['facebook_image_id'] ?? 0);
        $request_payload = $this->normalized_job_request_payload(
            is_array($params['request_payload'] ?? null) ? $params['request_payload'] : ($job['request_payload'] ?? []),
            $job
        );
        $generated       = is_array($params['generated_payload'] ?? null) ? $params['generated_payload'] : [];
        $generated       = $this->sync_generated_contract_containers($generated, $job);
        $content_package = $this->normalized_generated_content_package($generated, $job);
        $publish_payload = $this->resolved_publish_payload($params, $generated, $job);
        $content_type    = $publish_payload['content_type'];
        $callback_state = $this->resolve_worker_callback_state($params, $generated, $job);
        $facebook_caption = $callback_state['facebook_caption'];
        $group_share_kit = $callback_state['group_share_kit'];

        $validation_error = $this->validate_generated_publish_payload($params, $generated, $job);
        if ($validation_error instanceof WP_Error) {
            return $validation_error;
        }

        $post_data = [
            'post_type'     => 'post',
            'post_status'   => 'publish',
            'post_title'    => $publish_payload['title'],
            'post_excerpt'  => $publish_payload['excerpt'],
            'post_content'  => $publish_payload['content_html'],
            'post_author'   => !empty($job['created_by']) ? (int) $job['created_by'] : get_current_user_id(),
            'post_name'     => $publish_payload['slug'],
            'post_category' => [$this->ensure_category($content_type)],
        ];

        $post_id = !empty($job['post_id'])
            ? wp_update_post(array_merge($post_data, ['ID' => (int) $job['post_id']]), true)
            : wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return new WP_Error('post_failed', $post_id->get_error_message(), ['status' => 500]);
        }

        if ($featured_image) {
            set_post_thumbnail($post_id, $featured_image);
        }

        update_post_meta($post_id, 'kuchnia_twist_content_type', $content_type);
        update_post_meta($post_id, 'kuchnia_twist_facebook_caption', $facebook_caption);
        update_post_meta($post_id, 'kuchnia_twist_group_share_kit', $group_share_kit);
        if ($content_type === 'recipe') {
            update_post_meta($post_id, 'kuchnia_twist_recipe_data', $content_package['recipe'] ?? []);
        } else {
            delete_post_meta($post_id, 'kuchnia_twist_recipe_data');
        }
        update_post_meta($post_id, 'kuchnia_twist_seo_description', $publish_payload['seo_description']);
        $page_flow = $this->normalize_generated_page_flow(
            is_array($content_package['page_flow'] ?? null) ? $content_package['page_flow'] : [],
            is_array($content_package['content_pages'] ?? null) ? $content_package['content_pages'] : []
        );
        if (!empty($page_flow)) {
            update_post_meta($post_id, 'kuchnia_twist_page_flow', $page_flow);
        } else {
            delete_post_meta($post_id, 'kuchnia_twist_page_flow');
        }

        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'                   => 'publishing_facebook',
                'stage'                    => 'publishing_facebook',
                'post_id'                  => $post_id,
                'featured_image_id'        => $featured_image ?: null,
                'facebook_image_result_id' => $facebook_image ?: null,
                'permalink'                => get_permalink($post_id),
                'request_payload'         => wp_json_encode($request_payload),
                'generated_payload'        => wp_json_encode($generated),
                'facebook_caption'         => $facebook_caption,
                'group_share_kit'          => $group_share_kit,
                'updated_at'               => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->add_job_event(
            (int) $job['id'],
            'blog_published',
            'publishing_facebook',
            'publishing_facebook',
            __('WordPress article published successfully.', 'kuchnia-twist'),
            [
                'post_id'             => $post_id,
                'featured_image_id'   => $featured_image ?: 0,
                'facebook_image_id'   => $facebook_image ?: 0,
                'permalink'           => get_permalink($post_id),
            ]
        );

        return rest_ensure_response([
            'post_id'    => $post_id,
            'permalink'  => get_permalink($post_id),
            'post_title' => get_the_title($post_id),
        ]);
    }

    public function rest_complete_job(WP_REST_Request $request)
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
        $request_payload = $this->normalized_job_request_payload(
            is_array($params['request_payload'] ?? null) ? $params['request_payload'] : ($job['request_payload'] ?? []),
            $job
        );
        $generated_payload = is_array($params['generated_payload'] ?? null) ? $params['generated_payload'] : [];
        $generated_payload = $this->sync_generated_contract_containers($generated_payload, $job);
        $channels = $this->generated_channels($generated_payload, $job);
        $distribution = is_array($channels['facebook']['distribution']['pages'] ?? null) ? $channels['facebook']['distribution']['pages'] : [];
        $callback_state = $this->resolve_worker_callback_state($params, $generated_payload, $job);
        $facebook_caption = $callback_state['facebook_caption'];
        $group_share_kit = $callback_state['group_share_kit'];
        $validator_summary = is_array($generated_payload['content_machine']['validator_summary'] ?? null)
            ? $generated_payload['content_machine']['validator_summary']
            : [];
        $distribution_total = count($distribution);
        $distribution_completed = count(array_filter(
            $distribution,
            static fn ($page): bool => is_array($page) && (($page['status'] ?? '') === 'completed')
        ));
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'              => sanitize_key($params['status'] ?? 'completed'),
                'stage'               => sanitize_key($params['status'] ?? 'completed'),
                'facebook_post_id'    => $callback_state['facebook_post_id'],
                'facebook_comment_id' => $callback_state['facebook_comment_id'],
                'request_payload'     => wp_json_encode($request_payload),
                'facebook_caption'    => $facebook_caption,
                'group_share_kit'     => $group_share_kit,
                'generated_payload'   => wp_json_encode($generated_payload),
                'error_message'       => !empty($params['error_message']) ? (string) $params['error_message'] : null,
                'updated_at'          => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $final_status = sanitize_key($params['status'] ?? 'completed');
        $complete_message = $distribution_total > 0
            ? sprintf(_n('Job completed successfully across %d Facebook page.', 'Job completed successfully across %d Facebook pages.', $distribution_total, 'kuchnia-twist'), $distribution_total)
            : __('Job completed successfully.', 'kuchnia-twist');
        $this->add_job_event(
            (int) $job['id'],
            'job_completed',
            $final_status,
            $final_status,
            $complete_message,
            [
                'facebook_post_id'    => $callback_state['facebook_post_id'],
                'facebook_comment_id' => $callback_state['facebook_comment_id'],
                'facebook_pages'      => $distribution_total > 0 ? (string) $distribution_total : '',
                'facebook_completed'  => $distribution_completed > 0 ? (string) $distribution_completed : '',
                'quality_status'      => sanitize_key((string) ($validator_summary['quality_status'] ?? '')),
                'quality_score'       => isset($validator_summary['quality_score']) ? (string) $validator_summary['quality_score'] : '',
            ]
        );

        return rest_ensure_response(['ok' => true]);
    }

    public function rest_fail_job(WP_REST_Request $request)
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
        $request_payload = $this->normalized_job_request_payload(
            is_array($params['request_payload'] ?? null) ? $params['request_payload'] : ($job['request_payload'] ?? []),
            $job
        );
        $generated_payload = is_array($params['generated_payload'] ?? null) ? $params['generated_payload'] : [];
        $generated_payload = $this->sync_generated_contract_containers($generated_payload, $job);
        $channels = $this->generated_channels($generated_payload, $job);
        $distribution = is_array($channels['facebook']['distribution']['pages'] ?? null) ? $channels['facebook']['distribution']['pages'] : [];
        $callback_state = $this->resolve_worker_callback_state($params, $generated_payload, $job);
        $facebook_caption = $callback_state['facebook_caption'];
        $group_share_kit = $callback_state['group_share_kit'];
        $validator_summary = is_array($generated_payload['content_machine']['validator_summary'] ?? null)
            ? $generated_payload['content_machine']['validator_summary']
            : [];
        $distribution_total = count($distribution);
        $distribution_completed = count(array_filter(
            $distribution,
            static fn ($page): bool => is_array($page) && (($page['status'] ?? '') === 'completed')
        ));
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'              => sanitize_key($params['status'] ?? 'failed'),
                'stage'               => sanitize_key($params['stage'] ?? 'failed'),
                'facebook_post_id'    => $callback_state['facebook_post_id'],
                'facebook_comment_id' => $callback_state['facebook_comment_id'],
                'request_payload'     => wp_json_encode($request_payload),
                'generated_payload'   => wp_json_encode($generated_payload),
                'facebook_caption'    => $facebook_caption,
                'group_share_kit'     => $group_share_kit,
                'error_message'       => (string) ($params['error_message'] ?? __('Unknown job failure.', 'kuchnia-twist')),
                'updated_at'          => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $failed_status = sanitize_key($params['status'] ?? 'failed');
        $failed_stage  = sanitize_key($params['stage'] ?? 'failed');
        $base_message  = (string) ($params['error_message'] ?? __('Unknown job failure.', 'kuchnia-twist'));
        $message       = $distribution_total > 0
            ? sprintf(__('%1$s (%2$d of %3$d Facebook pages completed)', 'kuchnia-twist'), $base_message, $distribution_completed, $distribution_total)
            : $base_message;

        $this->add_job_event(
            (int) $job['id'],
            'job_failed',
            $failed_status,
            $failed_stage,
            $message,
            [
                'facebook_post_id'    => $callback_state['facebook_post_id'],
                'facebook_comment_id' => $callback_state['facebook_comment_id'],
                'retry_target'        => (string) ($job['retry_target'] ?? ''),
                'facebook_pages'      => $distribution_total > 0 ? (string) $distribution_total : '',
                'facebook_completed'  => $distribution_completed > 0 ? (string) $distribution_completed : '',
                'quality_status'      => sanitize_key((string) ($validator_summary['quality_status'] ?? '')),
                'quality_score'       => isset($validator_summary['quality_score']) ? (string) $validator_summary['quality_score'] : '',
            ]
        );

        return rest_ensure_response(['ok' => true]);
    }
}
