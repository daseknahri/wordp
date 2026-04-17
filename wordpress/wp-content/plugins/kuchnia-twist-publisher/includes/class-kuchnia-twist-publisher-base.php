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
