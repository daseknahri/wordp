<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Recipe_Ideas_Trait
{
    private function recipe_idea_statuses(): array
    {
        return [
            'idea'      => __('Idea', 'kuchnia-twist'),
            'queued'    => __('Queued', 'kuchnia-twist'),
            'scheduled' => __('Scheduled', 'kuchnia-twist'),
            'published' => __('Published', 'kuchnia-twist'),
            'archived'  => __('Archived', 'kuchnia-twist'),
        ];
    }

    private function get_recipe_ideas(array $statuses = [], int $limit = 50): array
    {
        global $wpdb;

        $statuses = array_values(array_filter(array_map(
            fn ($status): string => sanitize_key((string) $status),
            $statuses
        )));

        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->ideas_table_name()} WHERE status IN ({$placeholders}) ORDER BY updated_at DESC, id DESC LIMIT %d",
                [...$statuses, $limit]
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->ideas_table_name()} ORDER BY FIELD(status, 'queued', 'scheduled', 'idea', 'published', 'archived'), updated_at DESC, id DESC LIMIT %d",
                $limit
            );
        }

        $rows = $wpdb->get_results($query, ARRAY_A);
        return array_map([$this, 'prepare_recipe_idea_record'], $rows ?: []);
    }

    private function get_recipe_idea(int $idea_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->ideas_table_name()} WHERE id = %d", $idea_id), ARRAY_A);
        return $row ? $this->prepare_recipe_idea_record($row) : null;
    }

    private function prepare_recipe_idea_record(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['linked_job_id'] = !empty($row['linked_job_id']) ? (int) $row['linked_job_id'] : 0;
        $row['linked_post_id'] = !empty($row['linked_post_id']) ? (int) $row['linked_post_id'] : 0;
        $row['created_by'] = !empty($row['created_by']) ? (int) $row['created_by'] : 0;
        $row['dish_name'] = sanitize_text_field((string) ($row['dish_name'] ?? ''));
        $row['preferred_angle'] = $this->normalize_hook_angle_key((string) ($row['preferred_angle'] ?? ''));
        $row['operator_note'] = sanitize_textarea_field((string) ($row['operator_note'] ?? ''));
        $row['status'] = sanitize_key((string) ($row['status'] ?? 'idea'));
        $row['created_at'] = (string) ($row['created_at'] ?? '');
        $row['updated_at'] = (string) ($row['updated_at'] ?? '');
        return $row;
    }

    private function get_recipe_idea_counts(): array
    {
        global $wpdb;

        $counts = array_fill_keys(array_keys($this->recipe_idea_statuses()), 0);
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->ideas_table_name()} GROUP BY status", ARRAY_A);
        foreach ($rows ?: [] as $row) {
            $status = sanitize_key((string) ($row['status'] ?? ''));
            if (isset($counts[$status])) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    private function insert_recipe_idea(array $data): int
    {
        global $wpdb;

        $dish_name = sanitize_text_field((string) ($data['dish_name'] ?? ''));
        if ($dish_name === '') {
            return 0;
        }

        $existing = $this->find_recipe_idea_by_dish_name($dish_name);
        if ($existing) {
            $updates = ['updated_at' => current_time('mysql', true)];
            if (!empty($data['preferred_angle']) && empty($existing['preferred_angle'])) {
                $updates['preferred_angle'] = $this->normalize_hook_angle_key((string) $data['preferred_angle']);
            }
            if (!empty($data['operator_note']) && empty($existing['operator_note'])) {
                $updates['operator_note'] = sanitize_textarea_field((string) $data['operator_note']);
            }
            if (($existing['status'] ?? '') === 'archived') {
                $updates['status'] = 'idea';
            }
            $this->update_recipe_idea((int) $existing['id'], $updates);
            return (int) $existing['id'];
        }

        $now = current_time('mysql', true);
        $wpdb->insert($this->ideas_table_name(), [
            'dish_name'       => $dish_name,
            'preferred_angle' => $this->normalize_hook_angle_key((string) ($data['preferred_angle'] ?? '')),
            'operator_note'   => sanitize_textarea_field((string) ($data['operator_note'] ?? '')),
            'status'          => sanitize_key((string) ($data['status'] ?? 'idea')),
            'linked_job_id'   => !empty($data['linked_job_id']) ? (int) $data['linked_job_id'] : null,
            'linked_post_id'  => !empty($data['linked_post_id']) ? (int) $data['linked_post_id'] : null,
            'created_by'      => !empty($data['created_by']) ? (int) $data['created_by'] : get_current_user_id(),
            'created_at'      => $now,
            'updated_at'      => $now,
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    private function update_recipe_idea(int $idea_id, array $fields): void
    {
        global $wpdb;

        $updates = [];
        $formats = [];

        foreach ($fields as $key => $value) {
            switch ($key) {
                case 'dish_name':
                    $updates[$key] = sanitize_text_field((string) $value);
                    $formats[] = '%s';
                    break;
                case 'preferred_angle':
                    $updates[$key] = $this->normalize_hook_angle_key((string) $value);
                    $formats[] = '%s';
                    break;
                case 'operator_note':
                    $updates[$key] = sanitize_textarea_field((string) $value);
                    $formats[] = '%s';
                    break;
                case 'status':
                    $updates[$key] = sanitize_key((string) $value);
                    $formats[] = '%s';
                    break;
                case 'linked_job_id':
                case 'linked_post_id':
                case 'created_by':
                    $updates[$key] = $value ? (int) $value : null;
                    $formats[] = '%d';
                    break;
                case 'updated_at':
                case 'created_at':
                    $updates[$key] = (string) $value;
                    $formats[] = '%s';
                    break;
            }
        }

        if (!$updates) {
            return;
        }

        if (!isset($updates['updated_at'])) {
            $updates['updated_at'] = current_time('mysql', true);
            $formats[] = '%s';
        }

        $wpdb->update(
            $this->ideas_table_name(),
            $updates,
            ['id' => $idea_id],
            $formats,
            ['%d']
        );
    }

    private function find_recipe_idea_by_dish_name(string $dish_name): ?array
    {
        global $wpdb;

        $normalized = sanitize_title($dish_name);
        if ($normalized === '') {
            return null;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->ideas_table_name()} WHERE status <> %s ORDER BY id DESC",
                'archived'
            ),
            ARRAY_A
        );

        foreach ($rows ?: [] as $row) {
            if (sanitize_title((string) ($row['dish_name'] ?? '')) === $normalized) {
                return $this->prepare_recipe_idea_record($row);
            }
        }

        return null;
    }

    private function seed_recipe_ideas_from_topics_text(string $topics_text): void
    {
        global $wpdb;

        $existing_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->ideas_table_name()}");
        if ($existing_count > 0) {
            return;
        }

        foreach ($this->parse_topics($topics_text) as $topic) {
            $this->insert_recipe_idea([
                'dish_name' => $topic,
                'status'    => 'idea',
            ]);
        }
    }

    private function sync_recipe_idea_for_job_id(int $job_id): void
    {
        $job = $job_id > 0 ? $this->get_job($job_id) : null;
        if ($job) {
            $this->sync_recipe_idea_from_job($job);
        }
    }

    private function sync_recipe_idea_from_job(array $job): void
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $idea_id = absint($request['recipe_idea_id'] ?? 0);
        if ($idea_id <= 0) {
            return;
        }

        $idea = $this->get_recipe_idea($idea_id);
        if (!$idea) {
            return;
        }

        $status = 'idea';
        if (!empty($job['post_id']) || (in_array((string) ($job['status'] ?? ''), ['completed', 'partial_failure'], true) && !empty($job['permalink']))) {
            $status = 'published';
        } elseif ((string) ($job['status'] ?? '') === 'scheduled') {
            $status = 'scheduled';
        } elseif (in_array((string) ($job['status'] ?? ''), ['queued', 'generating', 'publishing_blog', 'publishing_facebook', 'failed', 'partial_failure'], true)) {
            $status = 'queued';
        }

        if (($idea['status'] ?? '') === 'archived' && $status === 'idea') {
            return;
        }

        $this->update_recipe_idea($idea_id, [
            'status'          => $status,
            'linked_job_id'   => (int) ($job['id'] ?? 0),
            'linked_post_id'  => !empty($job['post_id']) ? (int) $job['post_id'] : (int) ($idea['linked_post_id'] ?? 0),
            'preferred_angle' => $this->normalize_hook_angle_key((string) ($request['preferred_angle'] ?? $idea['preferred_angle'] ?? '')),
        ]);
    }

    private function archive_recipe_idea_link(array $idea): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action'  => 'kuchnia_twist_archive_recipe_idea',
                    'idea_id' => (int) $idea['id'],
                ],
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_archive_recipe_idea'
        );
    }

    private function parse_topics(string $topics_text): array
    {
        $topics = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $topics_text)));
        return $topics ?: kuchnia_twist_active_launch_topics();
    }

    private function ideas_table_name(): string
    {
        return $this->runtime_table_name('recipe_ideas');
    }
}
