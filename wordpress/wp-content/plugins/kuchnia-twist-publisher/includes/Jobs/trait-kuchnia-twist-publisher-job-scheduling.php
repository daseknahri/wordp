<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Job_Scheduling_Trait
{
    private function sanitize_publish_time(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '09:00';
    }

    private function next_scheduled_job(): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT * FROM {$this->table_name()}
            WHERE status = 'scheduled'
              AND publish_on IS NOT NULL
            ORDER BY publish_on ASC, id ASC
            LIMIT 1",
            ARRAY_A
        );

        return $row ? $this->prepare_job_record($row) : null;
    }

    private function count_ready_waiting_jobs(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name()} WHERE status = 'scheduled' AND publish_on IS NOT NULL"
        );
    }

    private function next_publish_slot_utc(string $minimum_after_utc = '', int $exclude_job_id = 0): string
    {
        global $wpdb;

        $settings   = $this->get_settings();
        $time_value = $this->sanitize_publish_time((string) $settings['daily_publish_time']);
        [$hour, $minute] = array_map('intval', explode(':', $time_value));

        $timezone = wp_timezone();
        $reference = new DateTimeImmutable('now', $timezone);

        if ($minimum_after_utc !== '') {
            try {
                $minimum = new DateTimeImmutable($minimum_after_utc, new DateTimeZone('UTC'));
                $minimum = $minimum->setTimezone($timezone);
                if ($minimum > $reference) {
                    $reference = $minimum;
                }
            } catch (Exception $exception) {
                unset($exception);
            }
        }

        $where = "status IN ('scheduled', 'publishing_blog', 'publishing_facebook', 'completed', 'partial_failure') AND publish_on IS NOT NULL";
        if ($exclude_job_id > 0) {
            $where .= $wpdb->prepare(' AND id <> %d', $exclude_job_id);
        }

        $latest = $wpdb->get_var("SELECT publish_on FROM {$this->table_name()} WHERE {$where} ORDER BY publish_on DESC LIMIT 1");
        if (is_string($latest) && $latest !== '') {
            try {
                $latest_dt = new DateTimeImmutable($latest, new DateTimeZone('UTC'));
                $latest_dt = $latest_dt->setTimezone($timezone);
                if ($latest_dt > $reference) {
                    $reference = $latest_dt;
                }
            } catch (Exception $exception) {
                unset($exception);
            }
        }

        $candidate = $reference->setTime($hour, $minute, 0);
        if ($candidate <= $reference) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
