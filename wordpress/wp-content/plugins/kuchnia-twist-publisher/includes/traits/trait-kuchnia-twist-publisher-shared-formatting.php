<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Shared_Formatting_Trait
{
    private function format_admin_datetime(string $datetime): string
    {
        if ($datetime === '') {
            return __('Unknown', 'kuchnia-twist');
        }

        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
    }

    private function format_admin_datetime_input(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }

        try {
            $dt = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
            return $dt->setTimezone(wp_timezone())->format('Y-m-d\TH:i');
        } catch (Exception $exception) {
            unset($exception);
        }

        return '';
    }

    private function sanitize_publish_datetime_input(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
    }

    private function publish_datetime_input_to_utc(string $value): string
    {
        $value = $this->sanitize_publish_datetime_input($value);
        if ($value === '') {
            return '';
        }

        try {
            $datetime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, wp_timezone());
            if (!$datetime) {
                return '';
            }

            return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $exception) {
            unset($exception);
        }

        return '';
    }

    private function normalize_requested_publish_on_utc(string $local_value): string
    {
        $publish_on = $this->publish_datetime_input_to_utc($local_value);
        if ($publish_on === '') {
            return '';
        }

        return $this->publish_time_is_future($publish_on) ? $publish_on : current_time('mysql', true);
    }

    private function publish_time_is_future(string $utc_datetime): bool
    {
        if ($utc_datetime === '') {
            return false;
        }

        try {
            $publish_on = new DateTimeImmutable($utc_datetime, new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            return $publish_on > $now;
        } catch (Exception $exception) {
            unset($exception);
        }

        return false;
    }

    private function format_human_label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
