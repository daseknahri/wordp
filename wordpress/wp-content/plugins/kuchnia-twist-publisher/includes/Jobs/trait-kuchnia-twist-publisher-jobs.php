<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Jobs_Trait
{
    private function get_jobs(int $limit = 10, array $filters = [], int $offset = 0): array
    {
        global $wpdb;

        [$where_sql, $params] = $this->job_query_parts($filters);
        $sql = "SELECT * FROM {$this->table_name()} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = max(0, $offset);

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        return array_map([$this, 'prepare_job_record'], $rows ?: []);
    }

    private function get_jobs_page(array $filters, int $page = 1, int $per_page = 24): array
    {
        $per_page    = $this->normalize_job_per_page($per_page);
        $total       = $this->count_jobs($filters);
        $total_pages = max(1, (int) ceil(($total ?: 1) / $per_page));
        $page        = min(max(1, $page), $total_pages);
        $offset      = ($page - 1) * $per_page;
        $items       = $total > 0 ? $this->get_jobs($per_page, $filters, $offset) : [];

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'from'        => $total > 0 ? $offset + 1 : 0,
            'to'          => $total > 0 ? $offset + count($items) : 0,
        ];
    }

    private function count_jobs(array $filters = []): int
    {
        global $wpdb;

        [$where_sql, $params] = $this->job_query_parts($filters);
        $sql = "SELECT COUNT(*) FROM {$this->table_name()} {$where_sql}";

        if ($params) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }

        return (int) $wpdb->get_var($sql);
    }

    private function get_jobs_for_export(array $filters = []): array
    {
        global $wpdb;

        [$where_sql, $params] = $this->job_query_parts($filters);
        $sql = "SELECT id, topic, content_type, status, stage, publish_on, created_at, last_attempt_at, updated_at, created_by, post_id, permalink, facebook_post_id, facebook_comment_id, retry_target, error_message FROM {$this->table_name()} {$where_sql} ORDER BY id DESC";

        if ($params) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }

        return is_array($rows) ? $rows : [];
    }

    private function job_query_parts(array $filters): array
    {
        global $wpdb;

        $where  = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(topic LIKE %s OR title_override LIKE %s OR error_message LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $content_type = $this->normalize_content_type_filter($filters['content_type'] ?? '');
        if ($content_type !== '') {
            $where[] = 'content_type = %s';
            $params[] = $content_type;
        }

        $statuses = $this->job_statuses_for_group((string) ($filters['status_group'] ?? 'all'));
        if ($statuses) {
            $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
            $where[] = "status IN ({$placeholders})";
            foreach ($statuses as $status) {
                $params[] = $status;
            }
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function job_filters_from_request(): array
    {
        return [
            'search'       => sanitize_text_field(wp_unslash($_GET['job_search'] ?? '')),
            'status_group' => $this->normalize_job_filter(wp_unslash($_GET['job_status'] ?? 'all')),
            'content_type' => $this->normalize_content_type_filter(wp_unslash($_GET['job_type'] ?? '')),
        ];
    }

    private function job_pagination_from_request(): array
    {
        return [
            'page'     => max(1, absint($_GET['job_page'] ?? 1)),
            'per_page' => $this->normalize_job_per_page(wp_unslash($_GET['job_per_page'] ?? 24)),
        ];
    }

    private function job_filter_options(): array
    {
        return [
            'all'        => __('All', 'kuchnia-twist'),
            'attention'  => __('Needs Attention', 'kuchnia-twist'),
            'active'     => __('Active', 'kuchnia-twist'),
            'completed'  => __('Completed', 'kuchnia-twist'),
        ];
    }

    private function normalize_job_filter($value): string
    {
        $value = sanitize_key((string) $value);
        return array_key_exists($value, $this->job_filter_options()) ? $value : 'all';
    }

    private function normalize_content_type_filter($value): string
    {
        $value = sanitize_key((string) $value);
        return array_key_exists($value, $this->active_content_types()) ? $value : '';
    }

    private function job_per_page_options(): array
    {
        return [12, 24, 50, 100];
    }

    private function normalize_job_per_page($value): int
    {
        $value = absint($value);
        return in_array($value, $this->job_per_page_options(), true) ? $value : 24;
    }

    private function job_statuses_for_group(string $group): array
    {
        return [
            'all'       => [],
            'attention' => ['failed', 'partial_failure'],
            'active'    => ['queued', 'generating', 'scheduled', 'publishing_blog', 'publishing_facebook'],
            'completed' => ['completed'],
        ][$group] ?? [];
    }

    private function publisher_page_url(array $args = []): string
    {
        $base = ['page' => 'kuchnia-twist-publisher'];
        $query = array_merge($base, $args);

        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            }
        }

        return add_query_arg($query, admin_url('admin.php'));
    }

    private function current_job_view_args(): array
    {
        return [
            'job_status'   => sanitize_key(wp_unslash($_GET['job_status'] ?? '')),
            'job_search'   => sanitize_text_field(wp_unslash($_GET['job_search'] ?? '')),
            'job_type'     => sanitize_key(wp_unslash($_GET['job_type'] ?? '')),
            'job_page'     => max(1, absint($_GET['job_page'] ?? 1)),
            'job_per_page' => $this->normalize_job_per_page($_GET['job_per_page'] ?? 24),
        ];
    }

    private function job_allows_schedule_actions(array $job): bool
    {
        return (string) ($job['content_type'] ?? '') === 'recipe'
            && (string) ($job['status'] ?? '') === 'scheduled';
    }

    private function posted_job_view_args(): array
    {
        return [
            'job_status'   => sanitize_key(wp_unslash($_POST['job_status'] ?? '')),
            'job_search'   => sanitize_text_field(wp_unslash($_POST['job_search'] ?? '')),
            'job_type'     => sanitize_key(wp_unslash($_POST['job_type'] ?? '')),
            'job_page'     => max(1, absint($_POST['job_page'] ?? 1)),
            'job_per_page' => $this->normalize_job_per_page($_POST['job_per_page'] ?? 24),
        ];
    }

    private function export_jobs_url(array $filters): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action'     => 'kuchnia_twist_export_jobs',
                    'job_search' => $filters['search'] !== '' ? $filters['search'] : null,
                    'job_status' => $filters['status_group'] !== 'all' ? $filters['status_group'] : null,
                    'job_type'   => $filters['content_type'] !== '' ? $filters['content_type'] : null,
                ],
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_export_jobs'
        );
    }

    private function job_filter_count(string $filter, array $counts): int
    {
        return [
            'all'        => (int) array_sum($counts),
            'attention'  => (int) ($counts['needs_attention'] ?? 0),
            'active'     => (int) (($counts['queued'] ?? 0) + ($counts['scheduled'] ?? 0) + ($counts['running'] ?? 0)),
            'completed'  => (int) ($counts['completed'] ?? 0),
        ][$filter] ?? 0;
    }

    private function job_results_summary(array $job_page): string
    {
        if ((int) $job_page['total'] <= 0) {
            return __('No jobs found.', 'kuchnia-twist');
        }

        return sprintf(
            __('Showing %1$d-%2$d of %3$d jobs', 'kuchnia-twist'),
            (int) $job_page['from'],
            (int) $job_page['to'],
            (int) $job_page['total']
        );
    }

    private function render_jobs_pagination(array $job_page, array $filters): void
    {
        if ((int) $job_page['total_pages'] <= 1) {
            return;
        }

        $current = (int) $job_page['page'];
        $total   = (int) $job_page['total_pages'];
        $base    = [
            'job_status'   => $filters['status_group'] !== 'all' ? $filters['status_group'] : null,
            'job_search'   => $filters['search'] !== '' ? $filters['search'] : null,
            'job_type'     => $filters['content_type'] !== '' ? $filters['content_type'] : null,
            'job_per_page' => $job_page['per_page'],
        ];
        ?>
        <nav class="kt-pagination" aria-label="<?php esc_attr_e('Job list pagination', 'kuchnia-twist'); ?>">
            <span class="kt-pagination__summary"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'kuchnia-twist'), $current, $total)); ?></span>
            <div class="kt-pagination__actions">
                <?php if ($current > 1) : ?>
                    <a class="button" href="<?php echo esc_url($this->publisher_page_url(array_merge($base, ['job_page' => $current - 1]))); ?>"><?php esc_html_e('Previous', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if ($current < $total) : ?>
                    <a class="button" href="<?php echo esc_url($this->publisher_page_url(array_merge($base, ['job_page' => $current + 1]))); ?>"><?php esc_html_e('Next', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
            </div>
        </nav>
        <?php
    }

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

    private function get_job(int $job_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name()} WHERE id = %d", $job_id), ARRAY_A);
        return $row ? $this->prepare_job_record($row) : null;
    }

    private function resolve_selected_job(array $jobs, int $job_id = 0): ?array
    {
        if ($job_id > 0) {
            $explicit = $this->get_job($job_id);
            if ($explicit) {
                return $explicit;
            }
        }

        $priority_groups = [
            ['failed', 'partial_failure'],
            ['scheduled', 'queued', 'generating', 'publishing_blog', 'publishing_facebook'],
        ];

        foreach ($priority_groups as $group) {
            $match = $this->find_first_job_by_status($jobs, $group);
            if ($match) {
                return $match;
            }
        }

        return $jobs[0] ?? null;
    }

    private function find_first_job_by_status(array $jobs, array $statuses): ?array
    {
        foreach ($jobs as $job) {
            if (in_array($job['status'], $statuses, true)) {
                return $job;
            }
        }

        return null;
    }

    private function prepare_job_record(array $row): array
    {
        $row['id']                       = (int) $row['id'];
        $row['created_by']               = !empty($row['created_by']) ? (int) $row['created_by'] : 0;
        $row['post_id']                  = !empty($row['post_id']) ? (int) $row['post_id'] : 0;
        $row['blog_image_id']            = !empty($row['blog_image_id']) ? (int) $row['blog_image_id'] : 0;
        $row['facebook_image_id']        = !empty($row['facebook_image_id']) ? (int) $row['facebook_image_id'] : 0;
        $row['featured_image_id']        = !empty($row['featured_image_id']) ? (int) $row['featured_image_id'] : 0;
        $row['facebook_image_result_id'] = !empty($row['facebook_image_result_id']) ? (int) $row['facebook_image_result_id'] : 0;
        $row['publish_on']               = (string) ($row['publish_on'] ?? '');
        $row['request_payload']          = $this->decode_json($row['request_payload']);
        $row['request_payload']          = $this->normalized_job_request_payload($row['request_payload'], $row);
        $row['generated_payload']        = $this->decode_json($row['generated_payload']);
        $row['blog_image']               = $this->attachment_payload($row['blog_image_id']);
        $row['facebook_image']           = $this->attachment_payload($row['facebook_image_id']);
        $row['featured_image']           = $this->attachment_payload($row['featured_image_id']);
        $row['facebook_image_result']    = $this->attachment_payload($row['facebook_image_result_id']);
        return $row;
    }

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
        $normalized_machine = [
            'prompt_version'      => sanitize_text_field((string) ($machine['prompt_version'] ?? self::CONTENT_MACHINE_VERSION)),
            'publication_profile' => sanitize_text_field((string) ($machine['publication_profile'] ?? '')),
            'content_preset'      => sanitize_key((string) ($machine['content_preset'] ?? $content_type)),
        ];
        if ($normalized_machine['publication_profile'] === '') {
            unset($normalized_machine['publication_profile']);
        }

        $payload['content_machine'] = $normalized_machine;
        $payload['channel_targets'] = $this->normalized_request_channel_targets($payload, $job);
        $payload['selected_facebook_pages'] = $payload['channel_targets']['facebook']['pages'];

        return $payload;
    }

    private function build_job_request_payload(array $args): array
    {
        $selected_pages = $this->normalize_selected_facebook_pages($args['selected_pages'] ?? []);
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
            'default_cta'                => sanitize_text_field((string) ($args['default_cta'] ?? '')),
            'content_machine'            => is_array($args['content_machine'] ?? null) ? $args['content_machine'] : [],
        ];

        return $this->normalized_job_request_payload($payload, [
            'content_type' => $payload['content_type'],
        ]);
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
