<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/launch-content.php';
require_once __DIR__ . '/class-kuchnia-twist-publisher-base.php';
require_once __DIR__ . '/class-kuchnia-twist-publisher-module.php';
require_once __DIR__ . '/traits/trait-kuchnia-twist-publisher-shared-content.php';
require_once __DIR__ . '/traits/trait-kuchnia-twist-publisher-shared-formatting.php';
require_once __DIR__ . '/traits/trait-kuchnia-twist-publisher-shared-runtime.php';
require_once __DIR__ . '/Settings/class-kuchnia-twist-publisher-settings-module.php';
require_once __DIR__ . '/Contracts/class-kuchnia-twist-publisher-contracts-module.php';
require_once __DIR__ . '/Jobs/class-kuchnia-twist-publisher-jobs-module.php';
require_once __DIR__ . '/Site/class-kuchnia-twist-publisher-site-module.php';
require_once __DIR__ . '/Admin/class-kuchnia-twist-publisher-admin-module.php';
require_once __DIR__ . '/Publishing/class-kuchnia-twist-publisher-publishing-module.php';
require_once __DIR__ . '/Rest/class-kuchnia-twist-publisher-rest-worker-module.php';
require_once __DIR__ . '/Rest/class-kuchnia-twist-publisher-rest-publishing-module.php';
require_once __DIR__ . '/Quality/class-kuchnia-twist-publisher-quality-summary-module.php';
require_once __DIR__ . '/Quality/class-kuchnia-twist-publisher-quality-review-module.php';

final class Kuchnia_Twist_Publisher extends Kuchnia_Twist_Publisher_Base
{
    use Kuchnia_Twist_Publisher_Shared_Content_Trait;
    use Kuchnia_Twist_Publisher_Shared_Formatting_Trait;
    use Kuchnia_Twist_Publisher_Shared_Runtime_Trait;

    private Kuchnia_Twist_Publisher_Settings_Module $settings_module;
    private Kuchnia_Twist_Publisher_Contracts_Module $contracts_module;
    private Kuchnia_Twist_Publisher_Jobs_Module $jobs_module;
    private Kuchnia_Twist_Publisher_Site_Module $site_module;
    private Kuchnia_Twist_Publisher_Admin_Module $admin_module;
    private Kuchnia_Twist_Publisher_Publishing_Module $publishing_module;
    private Kuchnia_Twist_Publisher_Rest_Worker_Module $rest_worker_module;
    private Kuchnia_Twist_Publisher_Rest_Publishing_Module $rest_publishing_module;
    private Kuchnia_Twist_Publisher_Quality_Summary_Module $quality_summary_module;
    private Kuchnia_Twist_Publisher_Quality_Review_Module $quality_review_module;
    private array $modules = [];

    private static ?Kuchnia_Twist_Publisher $instance = null;

    public static function instance(): Kuchnia_Twist_Publisher
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        self::instance()->install();
    }

    private function __construct()
    {
        $this->settings_module = new Kuchnia_Twist_Publisher_Settings_Module($this);
        $this->contracts_module = new Kuchnia_Twist_Publisher_Contracts_Module($this);
        $this->jobs_module = new Kuchnia_Twist_Publisher_Jobs_Module($this);
        $this->site_module = new Kuchnia_Twist_Publisher_Site_Module($this);
        $this->admin_module = new Kuchnia_Twist_Publisher_Admin_Module($this);
        $this->publishing_module = new Kuchnia_Twist_Publisher_Publishing_Module($this);
        $this->rest_worker_module = new Kuchnia_Twist_Publisher_Rest_Worker_Module($this);
        $this->rest_publishing_module = new Kuchnia_Twist_Publisher_Rest_Publishing_Module($this);
        $this->quality_summary_module = new Kuchnia_Twist_Publisher_Quality_Summary_Module($this);
        $this->quality_review_module = new Kuchnia_Twist_Publisher_Quality_Review_Module($this);
        $this->modules = [
            $this->settings_module,
            $this->contracts_module,
            $this->jobs_module,
            $this->site_module,
            $this->admin_module,
            $this->publishing_module,
            $this->rest_worker_module,
            $this->rest_publishing_module,
            $this->quality_summary_module,
            $this->quality_review_module,
        ];

        add_action('init', [$this, 'maybe_bootstrap'], 1);
        add_action('init', [$this->site_module, 'register_shortcodes'], 2);
        add_action('admin_menu', [$this->admin_module, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this->admin_module, 'enqueue_admin_assets']);
        add_action('admin_post_kuchnia_twist_create_job', [$this->jobs_module, 'handle_create_job']);
        add_action('admin_post_kuchnia_twist_add_recipe_idea', [$this->jobs_module, 'handle_add_recipe_idea']);
        add_action('admin_post_kuchnia_twist_archive_recipe_idea', [$this->jobs_module, 'handle_archive_recipe_idea']);
        add_action('admin_post_kuchnia_twist_save_settings', [$this->settings_module, 'handle_save_settings']);
        add_action('admin_post_kuchnia_twist_retry_job', [$this->jobs_module, 'handle_retry_job']);
        add_action('admin_post_kuchnia_twist_publish_now', [$this->jobs_module, 'handle_publish_now']);
        add_action('admin_post_kuchnia_twist_set_job_schedule', [$this->jobs_module, 'handle_set_job_schedule']);
        add_action('admin_post_kuchnia_twist_cancel_scheduled_job', [$this->jobs_module, 'handle_cancel_scheduled_job']);
        add_action('admin_post_kuchnia_twist_export_jobs', [$this->jobs_module, 'handle_export_jobs']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->invoke_local_method($name, $arguments);
        }

        return $this->invoke_module_method($name, $arguments);
    }

    public function invoke_local_method(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            throw new BadMethodCallException(sprintf('Method %s not found on publisher.', $name));
        }

        return $this->{$name}(...$arguments);
    }

    public function invoke_module_method(string $name, array $arguments, ?Kuchnia_Twist_Publisher_Module $caller = null)
    {
        foreach ($this->modules as $module) {
            if ($caller !== null && $module === $caller) {
                continue;
            }

            if (method_exists($module, $name)) {
                return $module->invoke($name, $arguments);
            }
        }

        throw new BadMethodCallException(sprintf('Method %s not found on publisher or modules.', $name));
    }

    public function maybe_bootstrap(): void
    {
        $installed_version = get_option(self::VERSION_KEY, '');
        if ($installed_version !== self::VERSION) {
            $this->install();
        }

        if (!get_option(self::THEME_BOOTSTRAP_KEY) && wp_get_theme('kuchnia-twist')->exists()) {
            switch_theme('kuchnia-twist');
            update_option(self::THEME_BOOTSTRAP_KEY, 1, false);
        }

        $this->site_module->invoke('maybe_migrate_legacy_branding', []);
    }

    private function install(): void
    {
        $this->site_module->invoke('install', []);
    }

}



