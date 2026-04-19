<?php

defined('ABSPATH') || exit;

abstract class Kuchnia_Twist_Publisher_Base
{
    public const VERSION = '1.7.6';
    public const CONTENT_MACHINE_VERSION = 'typed-content-v10';
    public const CONTENT_PACKAGE_CONTRACT_VERSION = 'content-package-v1';
    public const CHANNEL_ADAPTER_CONTRACT_VERSION = 'channel-adapters-v1';
    public const QUALITY_SCORE_THRESHOLD = 75;
    public const OPTION_KEY = 'kuchnia_twist_settings';
    public const VERSION_KEY = 'kuchnia_twist_publisher_version';
    public const THEME_BOOTSTRAP_KEY = 'kuchnia_twist_theme_bootstrapped';
    public const WORKER_STATUS_KEY = 'kuchnia_twist_worker_status';
    public const CORE_PAGE_SEED_HASH_META = '_kuchnia_twist_core_page_seed_hash';
    public const WORKER_REST_NAMESPACE = 'kuchnia-twist/v1';
    public const WORKER_SECRET_HEADER = 'x-kuchnia-worker-secret';
    public const WORKER_ROUTE_TEMPLATES = [
        'claim'        => 'jobs/claim',
        'media'        => 'jobs/(?P<id>\d+)/media',
        'publish_blog' => 'jobs/(?P<id>\d+)/publish-blog',
        'progress'     => 'jobs/(?P<id>\d+)/progress',
        'complete'     => 'jobs/(?P<id>\d+)/complete',
        'fail'         => 'jobs/(?P<id>\d+)/fail',
        'heartbeat'    => 'worker/heartbeat',
    ];
    public const RUNTIME_TABLE_SUFFIXES = [
        'jobs'         => 'kuchnia_twist_jobs',
        'job_events'   => 'kuchnia_twist_job_events',
        'recipe_ideas' => 'kuchnia_twist_recipe_ideas',
    ];

    protected function worker_rest_namespace(): string
    {
        return (string) apply_filters('kuchnia_twist_publisher_rest_namespace', self::WORKER_REST_NAMESPACE);
    }

    protected function worker_secret_header_name(): string
    {
        return (string) apply_filters('kuchnia_twist_publisher_worker_secret_header', self::WORKER_SECRET_HEADER);
    }

    protected function worker_route_templates(): array
    {
        $routes = apply_filters('kuchnia_twist_publisher_worker_route_templates', self::WORKER_ROUTE_TEMPLATES);
        return is_array($routes) ? $routes : self::WORKER_ROUTE_TEMPLATES;
    }

    protected function worker_platform_route_templates(): array
    {
        $templates = [];
        foreach ($this->worker_route_templates() as $key => $route) {
            $templates[$key] = preg_replace('/\(\?P<id>\\\\d\+\)/', '{id}', (string) $route);
        }

        return $templates;
    }

    protected function runtime_table_suffixes(): array
    {
        $suffixes = apply_filters('kuchnia_twist_publisher_runtime_table_suffixes', self::RUNTIME_TABLE_SUFFIXES);
        return is_array($suffixes) ? $suffixes : self::RUNTIME_TABLE_SUFFIXES;
    }

    protected function raise_media_processing_limits(): void
    {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }

        @ini_set('max_execution_time', '180');
        @ini_set('max_input_time', '180');

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
    }
}
