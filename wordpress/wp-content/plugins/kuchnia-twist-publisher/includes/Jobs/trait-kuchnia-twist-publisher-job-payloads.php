<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Job_Payloads_Trait
{
    private function normalize_selected_facebook_pages($pages): array
    {
        if (!is_array($pages)) {
            return [];
        }

        $normalized = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $page_id = sanitize_text_field((string) ($page['page_id'] ?? $page['pageId'] ?? ''));
            $label = sanitize_text_field((string) ($page['label'] ?? $page['name'] ?? ''));
            if ($page_id === '') {
                continue;
            }

            $normalized[$page_id] = array_filter([
                'page_id' => $page_id,
                'label'   => $label,
            ]);
        }

        return array_values($normalized);
    }

    private function available_facebook_page_map(): array
    {
        $map = [];
        foreach ($this->facebook_pages($this->get_settings(), false, true) as $page) {
            $page_id = sanitize_text_field((string) ($page['page_id'] ?? ''));
            if ($page_id === '') {
                continue;
            }

            $map[$page_id] = [
                'page_id' => $page_id,
                'label'   => sanitize_text_field((string) ($page['label'] ?? '')),
            ];
        }

        return $map;
    }

    private function selected_pages_from_ids(array $page_ids, array $settings): array
    {
        $selected_page_ids = array_values(array_filter(array_map(
            static fn ($value): string => sanitize_text_field((string) wp_unslash($value)),
            $page_ids
        )));
        $available_pages = $this->facebook_pages($settings, true, true);
        $available_page_map = [];
        foreach ($available_pages as $page) {
            $available_page_map[(string) $page['page_id']] = $page;
        }

        $selected_pages = [];
        foreach ($selected_page_ids as $page_id) {
            if (isset($available_page_map[$page_id])) {
                $selected_pages[] = $available_page_map[$page_id];
            }
        }

        return $this->normalize_selected_facebook_pages($selected_pages);
    }

    private function normalized_request_channel_targets(array $request_payload, array $job = []): array
    {
        $registry = $this->channel_registry();
        $raw_targets = is_array($request_payload['channel_targets'] ?? null) ? $request_payload['channel_targets'] : [];
        $legacy_pages = $this->normalize_selected_facebook_pages($request_payload['selected_facebook_pages'] ?? []);
        $page_map = $this->available_facebook_page_map();

        $facebook_raw = is_array($raw_targets['facebook'] ?? null) ? $raw_targets['facebook'] : [];
        $facebook_pages = $this->normalize_selected_facebook_pages($facebook_raw['pages'] ?? []);
        $facebook_page_ids = array_values(array_filter(array_map(
            static fn ($value): string => sanitize_text_field((string) $value),
            is_array($facebook_raw['page_ids'] ?? null) ? $facebook_raw['page_ids'] : []
        )));

        if (!$facebook_pages && $legacy_pages) {
            $facebook_pages = $legacy_pages;
        }

        if ($facebook_pages) {
            $ordered_pages = [];
            foreach ($facebook_pages as $page) {
                $page_id = (string) ($page['page_id'] ?? '');
                if ($page_id === '') {
                    continue;
                }

                $resolved = $page_map[$page_id] ?? [];
                $ordered_pages[$page_id] = array_filter([
                    'page_id' => $page_id,
                    'label'   => sanitize_text_field((string) ($page['label'] ?? ($resolved['label'] ?? ''))),
                ]);
            }
            $facebook_pages = array_values($ordered_pages);
        } elseif ($facebook_page_ids) {
            $facebook_pages = [];
            foreach ($facebook_page_ids as $page_id) {
                $resolved = $page_map[$page_id] ?? ['page_id' => $page_id, 'label' => ''];
                $facebook_pages[] = array_filter([
                    'page_id' => $page_id,
                    'label'   => sanitize_text_field((string) ($resolved['label'] ?? '')),
                ]);
            }
        }

        if (!$facebook_page_ids && $facebook_pages) {
            $facebook_page_ids = array_values(array_filter(array_unique(array_map(
                static fn (array $page): string => (string) ($page['page_id'] ?? ''),
                $facebook_pages
            ))));
        }

        $facebook_enabled = array_key_exists('enabled', $facebook_raw)
            ? !empty($facebook_raw['enabled'])
            : (!empty($facebook_pages) || !empty($facebook_page_ids));

        $facebook_groups_raw = is_array($raw_targets['facebook_groups'] ?? null) ? $raw_targets['facebook_groups'] : [];
        $pinterest_raw = is_array($raw_targets['pinterest'] ?? null) ? $raw_targets['pinterest'] : [];

        return [
            'facebook' => [
                'enabled' => $facebook_enabled,
                'page_ids'=> $facebook_page_ids,
                'pages'   => $facebook_pages,
            ],
            'facebook_groups' => [
                'enabled' => array_key_exists('enabled', $facebook_groups_raw) ? !empty($facebook_groups_raw['enabled']) : !empty($registry['facebook_groups']['request_target_shape']['enabled']),
                'mode'    => sanitize_key((string) ($facebook_groups_raw['mode'] ?? $registry['facebook_groups']['request_target_shape']['mode'] ?? 'manual_draft')),
            ],
            'pinterest' => [
                'enabled' => array_key_exists('enabled', $pinterest_raw) ? !empty($pinterest_raw['enabled']) : !empty($registry['pinterest']['request_target_shape']['enabled']),
                'mode'    => sanitize_key((string) ($pinterest_raw['mode'] ?? $registry['pinterest']['request_target_shape']['mode'] ?? 'draft')),
            ],
        ];
    }

    private function normalized_job_request_payload(array $request_payload, array $job = []): array
    {
        $payload = is_array($request_payload) ? $request_payload : [];
        $content_type = sanitize_key((string) ($payload['content_type'] ?? $job['content_type'] ?? 'recipe'));
        if ($content_type === '') {
            $content_type = 'recipe';
        }

        $machine = is_array($payload['content_machine'] ?? null) ? $payload['content_machine'] : [];
        $facebook_ctas = $this->normalized_request_facebook_ctas($payload, $machine);
        $facebook_post_teaser_cta = $facebook_ctas['facebook_post_teaser_cta'];
        $facebook_comment_link_cta = $facebook_ctas['facebook_comment_link_cta'];
        $normalized_machine = [
            'prompt_version'      => sanitize_text_field((string) ($machine['prompt_version'] ?? self::CONTENT_MACHINE_VERSION)),
            'publication_profile' => sanitize_text_field((string) ($machine['publication_profile'] ?? '')),
            'content_preset'      => sanitize_key((string) ($machine['content_preset'] ?? $content_type)),
            'default_ctas'        => [
                'facebook_post_teaser' => $facebook_post_teaser_cta,
                'facebook_comment_link' => $facebook_comment_link_cta,
            ],
            'default_cta'         => $facebook_comment_link_cta,
        ];
        if ($normalized_machine['publication_profile'] === '') {
            unset($normalized_machine['publication_profile']);
        }

        $payload['content_machine'] = $normalized_machine;
        $payload['facebook_post_teaser_cta'] = $facebook_post_teaser_cta;
        $payload['facebook_comment_link_cta'] = $facebook_comment_link_cta;
        $payload['default_cta'] = $facebook_comment_link_cta;
        $payload['channel_targets'] = $this->normalized_request_channel_targets($payload, $job);
        $payload['selected_facebook_pages'] = $payload['channel_targets']['facebook']['pages'];

        return $payload;
    }

    private function build_job_request_payload(array $args): array
    {
        $selected_pages = $this->normalize_selected_facebook_pages($args['selected_pages'] ?? []);
        $content_machine = is_array($args['content_machine'] ?? null) ? $args['content_machine'] : [];
        $facebook_ctas = $this->normalized_request_facebook_ctas([
            'facebook_post_teaser_cta'  => sanitize_text_field((string) ($args['facebook_post_teaser_cta'] ?? '')),
            'facebook_comment_link_cta' => sanitize_text_field((string) ($args['facebook_comment_link_cta'] ?? '')),
            'default_cta'               => sanitize_text_field((string) ($args['default_cta'] ?? '')),
        ], $content_machine);
        $payload = [
            'topic'                      => sanitize_text_field((string) ($args['topic'] ?? '')),
            'title_seed'                 => sanitize_text_field((string) ($args['title_seed'] ?? $args['topic'] ?? '')),
            'input_mode'                 => sanitize_key((string) ($args['input_mode'] ?? 'dish_name')),
            'content_type'               => sanitize_key((string) ($args['content_type'] ?? 'recipe')),
            'title_override'             => sanitize_text_field((string) ($args['title_override'] ?? '')),
            'schedule_mode'              => sanitize_key((string) ($args['schedule_mode'] ?? 'immediate')),
            'requested_publish_at'       => sanitize_text_field((string) ($args['requested_publish_at'] ?? '')),
            'requested_publish_timezone' => sanitize_text_field((string) ($args['requested_publish_timezone'] ?? 'UTC')),
            'blog_image_id'              => !empty($args['blog_image_id']) ? (int) $args['blog_image_id'] : 0,
            'facebook_image_id'          => !empty($args['facebook_image_id']) ? (int) $args['facebook_image_id'] : 0,
            'blog_image'                 => is_array($args['blog_image'] ?? null) ? $args['blog_image'] : [],
            'facebook_image'             => is_array($args['facebook_image'] ?? null) ? $args['facebook_image'] : [],
            'channel_targets'            => [
                'facebook' => [
                    'enabled' => !empty($selected_pages),
                    'page_ids'=> array_values(array_filter(array_unique(array_map(
                        static fn (array $page): string => (string) ($page['page_id'] ?? ''),
                        $selected_pages
                    )))),
                    'pages'   => $selected_pages,
                ],
                'facebook_groups' => [
                    'enabled' => false,
                    'mode'    => 'manual_draft',
                ],
                'pinterest' => [
                    'enabled' => false,
                    'mode'    => 'draft',
                ],
            ],
            'selected_facebook_pages'    => $selected_pages,
            'site_name'                  => sanitize_text_field((string) ($args['site_name'] ?? '')),
            'facebook_post_teaser_cta'   => $facebook_ctas['facebook_post_teaser_cta'],
            'facebook_comment_link_cta'  => $facebook_ctas['facebook_comment_link_cta'],
            'default_cta'                => $facebook_ctas['default_cta'],
            'content_machine'            => $content_machine,
        ];

        return $this->normalized_job_request_payload($payload, [
            'content_type' => $payload['content_type'],
        ]);
    }

    private function build_admin_job_request_payload(array $args, array $settings): array
    {
        $blog_image_id = !empty($args['blog_image_id']) ? (int) $args['blog_image_id'] : 0;
        $facebook_image_id = !empty($args['facebook_image_id']) ? (int) $args['facebook_image_id'] : 0;
        $content_type = sanitize_key((string) ($args['content_type'] ?? 'recipe'));

        return $this->build_job_request_payload([
            'topic'                      => sanitize_text_field((string) ($args['topic'] ?? '')),
            'title_seed'                 => sanitize_text_field((string) ($args['title_seed'] ?? ($args['topic'] ?? ''))),
            'input_mode'                 => sanitize_key((string) ($args['input_mode'] ?? 'dish_name')),
            'content_type'               => $content_type,
            'title_override'             => sanitize_text_field((string) ($args['title_override'] ?? '')),
            'schedule_mode'              => sanitize_key((string) ($args['schedule_mode'] ?? 'immediate')),
            'requested_publish_at'       => sanitize_text_field((string) ($args['requested_publish_at'] ?? '')),
            'requested_publish_timezone' => sanitize_text_field((string) ($args['requested_publish_timezone'] ?? 'UTC')),
            'blog_image_id'              => $blog_image_id,
            'facebook_image_id'          => $facebook_image_id,
            'blog_image'                 => $this->attachment_payload($blog_image_id),
            'facebook_image'             => $this->attachment_payload($facebook_image_id),
            'selected_pages'             => is_array($args['selected_pages'] ?? null) ? $args['selected_pages'] : [],
            'site_name'                  => sanitize_text_field((string) ($args['site_name'] ?? get_bloginfo('name'))),
            'facebook_post_teaser_cta'   => sanitize_text_field((string) ($args['facebook_post_teaser_cta'] ?? ($settings['facebook_post_teaser_cta'] ?? ''))),
            'facebook_comment_link_cta'  => sanitize_text_field((string) ($args['facebook_comment_link_cta'] ?? $this->facebook_comment_link_cta_setting($settings))),
            'content_machine'            => is_array($args['content_machine'] ?? null)
                ? $args['content_machine']
                : $this->job_content_machine_snapshot($settings, $content_type),
        ]);
    }

    private function normalized_request_facebook_ctas(array $payload, array $machine = []): array
    {
        $machine_default_ctas = is_array($machine['default_ctas'] ?? null) ? $machine['default_ctas'] : [];
        $legacy_default_cta = sanitize_text_field((string) ($payload['default_cta'] ?? ($machine['default_cta'] ?? '')));
        $facebook_post_teaser_cta = sanitize_text_field((string) ($payload['facebook_post_teaser_cta'] ?? ($machine_default_ctas['facebook_post_teaser'] ?? '')));
        $facebook_comment_link_cta = sanitize_text_field((string) ($payload['facebook_comment_link_cta'] ?? ($machine_default_ctas['facebook_comment_link'] ?? $legacy_default_cta)));

        if ($facebook_post_teaser_cta === '') {
            $facebook_post_teaser_cta = $this->default_facebook_post_teaser_cta();
        }

        if ($facebook_comment_link_cta === '') {
            $facebook_comment_link_cta = $legacy_default_cta !== ''
                ? $legacy_default_cta
                : $this->default_facebook_comment_link_cta();
        }

        return [
            'facebook_post_teaser_cta'  => $facebook_post_teaser_cta,
            'facebook_comment_link_cta' => $facebook_comment_link_cta,
            'default_cta'               => $facebook_comment_link_cta,
        ];
    }

    private function job_channel_targets(array $job): array
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        return $this->normalized_request_channel_targets($request, $job);
    }

    private function job_selected_pages(array $job): array
    {
        $targets = $this->job_channel_targets($job);
        return is_array($targets['facebook']['pages'] ?? null)
            ? $targets['facebook']['pages']
            : [];
    }

    private function attachment_payload(int $attachment_id): array
    {
        if (!$attachment_id) {
            return [];
        }

        return [
            'id'    => $attachment_id,
            'url'   => wp_get_attachment_url($attachment_id),
            'title' => get_the_title($attachment_id),
        ];
    }

    private function decode_json($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
