<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/launch-content.php';
require_once __DIR__ . '/class-kuchnia-twist-publisher-base.php';
require_once __DIR__ . '/class-kuchnia-twist-publisher-module.php';
require_once __DIR__ . '/Settings/class-kuchnia-twist-publisher-settings-module.php';
require_once __DIR__ . '/Contracts/class-kuchnia-twist-publisher-contracts-module.php';
require_once __DIR__ . '/Jobs/class-kuchnia-twist-publisher-jobs-module.php';
require_once __DIR__ . '/Admin/class-kuchnia-twist-publisher-admin-module.php';
require_once __DIR__ . '/Publishing/class-kuchnia-twist-publisher-publishing-module.php';
require_once __DIR__ . '/Rest/class-kuchnia-twist-publisher-rest-worker-module.php';
require_once __DIR__ . '/Rest/class-kuchnia-twist-publisher-rest-publishing-module.php';
require_once __DIR__ . '/Quality/class-kuchnia-twist-publisher-quality-summary-module.php';
require_once __DIR__ . '/Quality/class-kuchnia-twist-publisher-quality-review-module.php';

final class Kuchnia_Twist_Publisher extends Kuchnia_Twist_Publisher_Base
{
    private Kuchnia_Twist_Publisher_Settings_Module $settings_module;
    private Kuchnia_Twist_Publisher_Contracts_Module $contracts_module;
    private Kuchnia_Twist_Publisher_Jobs_Module $jobs_module;
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
            $this->admin_module,
            $this->publishing_module,
            $this->rest_worker_module,
            $this->rest_publishing_module,
            $this->quality_summary_module,
            $this->quality_review_module,
        ];

        add_action('init', [$this, 'maybe_bootstrap'], 1);
        add_action('init', [$this, 'register_shortcodes'], 2);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_kuchnia_twist_create_job', [$this, 'handle_create_job']);
        add_action('admin_post_kuchnia_twist_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_kuchnia_twist_retry_job', [$this, 'handle_retry_job']);
        add_action('admin_post_kuchnia_twist_publish_now', [$this, 'handle_publish_now']);
        add_action('admin_post_kuchnia_twist_set_job_schedule', [$this, 'handle_set_job_schedule']);
        add_action('admin_post_kuchnia_twist_cancel_scheduled_job', [$this, 'handle_cancel_scheduled_job']);
        add_action('admin_post_kuchnia_twist_export_jobs', [$this, 'handle_export_jobs']);
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

        $this->maybe_migrate_legacy_branding();
    }

    public function register_shortcodes(): void
    {
        add_shortcode('kuchnia_twist_editor_name', [$this, 'shortcode_editor_name']);
        add_shortcode('kuchnia_twist_editor_role', [$this, 'shortcode_editor_role']);
        add_shortcode('kuchnia_twist_editor_public_email', [$this, 'shortcode_editor_public_email']);
        add_shortcode('kuchnia_twist_editor_business_email', [$this, 'shortcode_editor_business_email']);
        add_shortcode('kuchnia_twist_link', [$this, 'shortcode_internal_link']);
    }

    private function maybe_migrate_legacy_branding(): void
    {
        if (get_option('kuchnia_twist_branding_migrated')) {
            return;
        }

        $search = [
            'Dali Recipes',
            'Dali Recipies',
            'Dali Recipe',
            'Dali recipes',
            'Dali recipies',
            'Dali recipe',
            'Dali',
            'dali recipes',
            'dali recipies',
            'dali recipe',
            'dali',
        ];
        $replace = 'kuchniatwist';

        update_option('blogname', $replace);
        $description = (string) get_option('blogdescription', '');
        if ($description !== '') {
            update_option('blogdescription', str_ireplace($search, $replace, $description));
        }

        $settings = get_option(self::OPTION_KEY, []);
        if (is_array($settings) && !empty($settings)) {
            $settings = $this->replace_branding_recursive($settings, $search, $replace);
            update_option(self::OPTION_KEY, $settings);
        }

        $this->migrate_posts_branding($search, $replace);
        $this->migrate_terms_branding($search, $replace);

        update_option('kuchnia_twist_branding_migrated', 1, false);
    }

    private function replace_branding_recursive($value, array $search, string $replace)
    {
        if (is_string($value)) {
            return str_ireplace($search, $replace, $value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replace_branding_recursive($item, $search, $replace);
            }
        }

        return $value;
    }

    private function migrate_posts_branding(array $search, string $replace): void
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $like = '%dali%';
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s",
                $like,
                $like,
                $like
            )
        );

        if (!$post_ids) {
            return;
        }

        foreach ($post_ids as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post) {
                continue;
            }

            $updated = [
                'ID'           => $post->ID,
                'post_title'   => str_ireplace($search, $replace, (string) $post->post_title),
                'post_content' => str_ireplace($search, $replace, (string) $post->post_content),
                'post_excerpt' => str_ireplace($search, $replace, (string) $post->post_excerpt),
            ];

            wp_update_post($updated);
        }
    }

    private function migrate_terms_branding(array $search, string $replace): void
    {
        $terms = get_terms([
            'taxonomy'   => ['category', 'post_tag'],
            'hide_empty' => false,
            'name__like' => 'dali',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $new_name = str_ireplace($search, $replace, $term->name);
            $new_desc = str_ireplace($search, $replace, (string) $term->description);
            if ($new_name === $term->name && $new_desc === $term->description) {
                continue;
            }

            wp_update_term($term->term_id, $term->taxonomy, [
                'name'        => $new_name,
                'slug'        => sanitize_title($new_name),
                'description' => $new_desc,
            ]);
        }
    }

    public function register_admin_pages(): void
    {
        add_menu_page(
            __('kuchniatwist', 'kuchnia-twist'),
            __('kuchniatwist', 'kuchnia-twist'),
            'publish_posts',
            'kuchnia-twist-publisher',
            [$this, 'render_publisher_page'],
            'dashicons-carrot',
            26
        );

        add_submenu_page(
            'kuchnia-twist-publisher',
            __('Publishing Settings', 'kuchnia-twist'),
            __('Settings', 'kuchnia-twist'),
            'manage_options',
            'kuchnia-twist-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        $allowed_hooks = [
            'toplevel_page_kuchnia-twist-publisher',
            'kuchnia-twist-publisher_page_kuchnia-twist-settings',
            'kuchnia-twist_page_kuchnia-twist-settings',
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'kuchnia-twist-admin',
            plugins_url('admin.css', KUCHNIA_TWIST_PUBLISHER_FILE),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'kuchnia-twist-admin',
            plugins_url('admin.js', KUCHNIA_TWIST_PUBLISHER_FILE),
            ['jquery', 'media-editor'],
            self::VERSION,
            true
        );
    }

    public function render_publisher_page(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'kuchnia-twist'));
        }

        $settings             = $this->get_settings();
        $facebook_pages       = $this->facebook_pages($settings, true, true);
        $job_filters          = $this->job_filters_from_request();
        $pagination           = $this->job_pagination_from_request();
        $job_page             = $this->get_jobs_page($job_filters, $pagination['page'], $pagination['per_page']);
        $jobs                 = $job_page['items'];
        $selected_id          = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        $selected             = $this->resolve_selected_job($jobs, $selected_id);
        $counts               = $this->get_dashboard_counts();
        $notice_key           = isset($_GET['kt_notice']) ? sanitize_key(wp_unslash($_GET['kt_notice'])) : '';
        $manual_only          = $settings['image_generation_mode'] === 'manual_only';
        $system_status        = $this->system_status_snapshot($settings);
        $export_url           = $this->export_jobs_url($job_filters);
        $worker_last_job      = !empty($system_status['last_job_id']) ? $this->get_job((int) $system_status['last_job_id']) : null;
        $next_scheduled_job   = $this->next_scheduled_job();
        $scheduled_waiting    = $this->count_ready_waiting_jobs();
        $auto_refresh_seconds = ($selected_id === 0 && ($counts['queued'] + $counts['running']) > 0) ? 20 : 0;
        ?>
        <div class="wrap kt-admin"<?php echo $auto_refresh_seconds > 0 ? ' data-auto-refresh-seconds="' . esc_attr((string) $auto_refresh_seconds) . '"' : ''; ?>>
            <div class="kt-page-head">
                <div>
                    <h1><?php esc_html_e('Publisher', 'kuchnia-twist'); ?></h1>
                    <p><?php esc_html_e('Queue article jobs, watch the pipeline, and fan out social variants across your Facebook pages.', 'kuchnia-twist'); ?></p>
                </div>
                <div class="kt-head-actions">
                    <?php if ($auto_refresh_seconds > 0) : ?>
                        <button type="button" class="button kt-auto-refresh-toggle" data-seconds="<?php echo esc_attr((string) $auto_refresh_seconds); ?>" aria-pressed="false"><?php esc_html_e('Pause Auto Refresh', 'kuchnia-twist'); ?></button>
                        <span class="kt-head-status" data-auto-refresh-label role="status" aria-live="polite"><?php echo esc_html(sprintf(__('Refreshing every %ds while jobs are active.', 'kuchnia-twist'), $auto_refresh_seconds)); ?></span>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kuchnia-twist-settings')); ?>"><?php esc_html_e('Open Settings', 'kuchnia-twist'); ?></a>
                </div>
            </div>
            <div class="screen-reader-text" aria-live="polite" data-copy-live-region></div>
            <?php $this->render_notice($notice_key); ?>
            <section class="kt-summary-strip">
                <article class="kt-stat">
                    <span class="kt-stat__label"><?php esc_html_e('Queued', 'kuchnia-twist'); ?></span>
                    <strong class="kt-stat__value"><?php echo esc_html((string) $counts['queued']); ?></strong>
                </article>
                <article class="kt-stat">
                    <span class="kt-stat__label"><?php esc_html_e('Running', 'kuchnia-twist'); ?></span>
                    <strong class="kt-stat__value"><?php echo esc_html((string) $counts['running']); ?></strong>
                </article>
                <article class="kt-stat">
                    <span class="kt-stat__label"><?php esc_html_e('Needs Attention', 'kuchnia-twist'); ?></span>
                    <strong class="kt-stat__value"><?php echo esc_html((string) $counts['needs_attention']); ?></strong>
                </article>
                <article class="kt-stat">
                    <span class="kt-stat__label"><?php esc_html_e('Completed', 'kuchnia-twist'); ?></span>
                    <strong class="kt-stat__value"><?php echo esc_html((string) $counts['completed']); ?></strong>
                </article>
            </section>

            <section class="kt-system-strip">
                <article class="kt-system-card<?php echo $system_status['worker_stale'] ? ' is-warning' : ' is-ready'; ?>">
                    <span class="kt-system-card__label"><?php esc_html_e('Worker', 'kuchnia-twist'); ?></span>
                    <strong class="kt-system-card__value"><?php echo esc_html($system_status['worker_stale'] ? __('Stale', 'kuchnia-twist') : __('Seen recently', 'kuchnia-twist')); ?></strong>
                    <p><?php echo esc_html($system_status['worker_heartbeat_text']); ?></p>
                </article>
                <article class="kt-system-card<?php echo $system_status['worker_enabled'] ? ' is-ready' : ' is-warning'; ?>">
                    <span class="kt-system-card__label"><?php esc_html_e('Processing', 'kuchnia-twist'); ?></span>
                    <strong class="kt-system-card__value"><?php echo esc_html($system_status['worker_enabled'] ? __('Enabled', 'kuchnia-twist') : __('Disabled', 'kuchnia-twist')); ?></strong>
                    <p><?php echo esc_html($system_status['worker_loop_text']); ?></p>
                </article>
                <article class="kt-system-card<?php echo $system_status['openai_ready'] ? ' is-ready' : ' is-warning'; ?>">
                    <span class="kt-system-card__label"><?php esc_html_e('OpenAI', 'kuchnia-twist'); ?></span>
                    <strong class="kt-system-card__value"><?php echo esc_html($system_status['openai_ready'] ? __('Ready', 'kuchnia-twist') : __('Missing', 'kuchnia-twist')); ?></strong>
                    <p><?php echo esc_html($system_status['openai_text']); ?></p>
                </article>
                <article class="kt-system-card<?php echo $system_status['facebook_ready'] ? ' is-ready' : ' is-warning'; ?>">
                    <span class="kt-system-card__label"><?php esc_html_e('Facebook', 'kuchnia-twist'); ?></span>
                    <strong class="kt-system-card__value"><?php echo esc_html($system_status['facebook_ready'] ? __('Ready', 'kuchnia-twist') : __('Warning', 'kuchnia-twist')); ?></strong>
                    <p><?php echo esc_html($system_status['facebook_text']); ?></p>
                </article>
            </section>

            <?php $this->render_system_alerts($system_status); ?>

            <section class="kt-card kt-card--system-detail">
                <div class="kt-card-head">
                    <div>
                        <h2><?php esc_html_e('System Detail', 'kuchnia-twist'); ?></h2>
                        <p><?php esc_html_e('Worker freshness, last loop, and the latest operational touchpoint.', 'kuchnia-twist'); ?></p>
                    </div>
                </div>
                <dl class="kt-keyfacts">
                    <div>
                        <dt><?php esc_html_e('Last Seen', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($system_status['last_seen_label']); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Last Loop', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($system_status['last_loop_label']); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Stale After', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($system_status['stale_after_label']); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Last Job', 'kuchnia-twist'); ?></dt>
                        <dd>
                            <?php if ($worker_last_job) : ?>
                                <a href="<?php echo esc_url($this->publisher_page_url(['job_id' => (int) $worker_last_job['id']])); ?>"><?php echo esc_html(sprintf(__('Job #%1$d', 'kuchnia-twist'), (int) $worker_last_job['id'])); ?></a>
                            <?php else : ?>
                                <?php esc_html_e('No recent job', 'kuchnia-twist'); ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Next Release', 'kuchnia-twist'); ?></dt>
                        <dd>
                            <?php
                            echo $next_scheduled_job
                                ? esc_html($this->format_admin_datetime((string) $next_scheduled_job['publish_on']))
                                : esc_html__('No scheduled release', 'kuchnia-twist');
                            ?>
                        </dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Scheduled Waiting', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html((string) $scheduled_waiting); ?></dd>
                    </div>
                </dl>
                <?php if ($worker_last_job) : ?>
                    <p class="kt-system-note">
                        <?php
                        echo esc_html(
                            sprintf(
                                __('Last worker job: %1$s (%2$s).', 'kuchnia-twist'),
                                $worker_last_job['topic'],
                                $this->format_human_label((string) ($system_status['last_job_status'] ?: $worker_last_job['status']))
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($system_status['worker_last_error'])) : ?>
                    <p class="kt-system-note kt-system-note--error"><?php echo esc_html(sprintf(__('Last worker error: %s', 'kuchnia-twist'), $system_status['worker_last_error'])); ?></p>
                <?php endif; ?>
            </section>

            <div class="kt-admin-grid">
                <section class="kt-card kt-card--composer" id="kt-create-job">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Create Job', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Choose the content type, enter the dish name or working title, upload the images you already have, choose the Facebook pages, and optionally set an exact publish time.', 'kuchnia-twist'); ?></p>
                        </div>
                        <span class="kt-mode-pill <?php echo esc_attr($manual_only ? 'is-manual' : 'is-flex'); ?>">
                            <?php echo $manual_only ? esc_html__('Manual only', 'kuchnia-twist') : esc_html__('Uploaded first', 'kuchnia-twist'); ?>
                        </span>
                    </div>
                    <p class="kt-system-note">
                        <?php
                        echo $manual_only
                            ? esc_html__('Manual-only mode requires both images before queueing. Choose a content type, enter the dish name or working title, and leave publish time empty if the article should go live as soon as generation finishes.', 'kuchnia-twist')
                            : esc_html__('Uploaded-first mode keeps any images you provide and only generates the missing slot. Choose a content type, enter the dish name or working title, and leave publish time empty to publish as soon as generation finishes.', 'kuchnia-twist');
                        ?>
                    </p>
                    <div class="kt-requirements" aria-label="<?php esc_attr_e('Queue requirements', 'kuchnia-twist'); ?>">
                        <?php if ($manual_only) : ?>
                            <span class="kt-requirement-pill"><?php esc_html_e('Topic required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Blog image required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Facebook image required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Select at least one page', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Optional exact publish time', 'kuchnia-twist'); ?></span>
                        <?php else : ?>
                            <span class="kt-requirement-pill"><?php esc_html_e('Topic required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Generate only missing images', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Select at least one page', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Optional exact publish time', 'kuchnia-twist'); ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="kt-form">
                        <?php wp_nonce_field('kuchnia_twist_create_job'); ?>
                        <input type="hidden" name="action" value="kuchnia_twist_create_job">
                        <div class="kt-field-grid">
                            <label>
                                <span><?php esc_html_e('Content Type', 'kuchnia-twist'); ?></span>
                                <select name="content_type" data-content-type-select>
                                    <?php foreach ($this->queueable_content_types() as $content_value => $content_label) : ?>
                                        <option value="<?php echo esc_attr($content_value); ?>" <?php selected($content_value, 'recipe'); ?>><?php echo esc_html($content_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="kt-field-span-full">
                                <span data-topic-label data-label-recipe="<?php echo esc_attr__('Dish Name', 'kuchnia-twist'); ?>" data-label-food_fact="<?php echo esc_attr__('Working Title', 'kuchnia-twist'); ?>"><?php esc_html_e('Dish Name', 'kuchnia-twist'); ?></span>
                                <input type="text" name="topic_seed" required data-topic-input data-placeholder-recipe="<?php echo esc_attr__('For example: Creamy Tuscan Chicken Pasta', 'kuchnia-twist'); ?>" data-placeholder-food_fact="<?php echo esc_attr__('For example: 10 Things to Eat to Stay Young', 'kuchnia-twist'); ?>" placeholder="<?php esc_attr_e('For example: Creamy Tuscan Chicken Pasta', 'kuchnia-twist'); ?>">
                                <small data-topic-help data-help-recipe="<?php echo esc_attr__('Enter the dish name. The content engine will build the final title, recipe package, article pages, and social variants from it.', 'kuchnia-twist'); ?>" data-help-food_fact="<?php echo esc_attr__('Enter a working title or topic seed. The content engine can improve the final headline, infer the article angle, split it into pages, and build the social pack from it.', 'kuchnia-twist'); ?>"><?php esc_html_e('Enter the dish name. The content engine will build the final title, recipe package, article pages, and social variants from it.', 'kuchnia-twist'); ?></small>
                            </label>
                            <label class="kt-field-span-full">
                                <span><?php esc_html_e('Final Title Override', 'kuchnia-twist'); ?></span>
                                <input type="text" name="title_override" data-title-override-input data-placeholder-recipe="<?php echo esc_attr__('Optional. Leave empty to let the recipe engine decide the final title.', 'kuchnia-twist'); ?>" data-placeholder-food_fact="<?php echo esc_attr__('Optional. Leave empty to let the article engine optimize the final headline.', 'kuchnia-twist'); ?>" placeholder="<?php esc_attr_e('Optional. Leave empty to let the recipe engine decide the final title.', 'kuchnia-twist'); ?>">
                            </label>
                            <label class="kt-field-span-full">
                                <span><?php esc_html_e('Publish At', 'kuchnia-twist'); ?></span>
                                <input type="datetime-local" name="publish_at" step="60">
                                <small><?php echo esc_html(sprintf(__('Optional. Leave empty to publish as soon as the worker finishes generation. Times use the WordPress timezone: %s.', 'kuchnia-twist'), wp_timezone_string() ?: 'UTC')); ?></small>
                            </label>
                        </div>
                        <div class="kt-field-grid">
                            <label>
                                <span><?php esc_html_e('Blog Hero Image', 'kuchnia-twist'); ?></span>
                                <input type="file" name="blog_image" accept="image/*" <?php echo $manual_only ? 'required' : ''; ?>>
                            </label>
                            <label>
                                <span><?php esc_html_e('Facebook Image', 'kuchnia-twist'); ?></span>
                                <input type="file" name="facebook_image" accept="image/*" <?php echo $manual_only ? 'required' : ''; ?>>
                            </label>
                        </div>
                        <div class="kt-field-grid">
                            <fieldset class="kt-field-span-full kt-checkbox-card">
                                <legend><?php esc_html_e('Facebook Pages', 'kuchnia-twist'); ?></legend>
                                <?php if ($facebook_pages) : ?>
                                    <div class="kt-checkbox-actions">
                                        <div class="kt-inline-actions">
                                            <button type="button" class="button button-small" data-facebook-page-select-all><?php esc_html_e('Select All', 'kuchnia-twist'); ?></button>
                                            <button type="button" class="button button-small" data-facebook-page-clear><?php esc_html_e('Clear All', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <span class="kt-checkbox-count" data-facebook-selection-count>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    _n('%d page selected', '%d pages selected', count($facebook_pages), 'kuchnia-twist'),
                                                    count($facebook_pages)
                                                )
                                            );
                                            ?>
                                        </span>
                                    </div>
                                    <div class="kt-checkbox-list">
                                        <?php foreach ($facebook_pages as $page) : ?>
                                            <label class="kt-checkbox-item">
                                                <input type="checkbox" name="selected_facebook_pages[]" value="<?php echo esc_attr((string) $page['page_id']); ?>" data-facebook-page-checkbox checked>
                                                <span>
                                                    <strong><?php echo esc_html($page['label']); ?></strong>
                                                    <small><?php echo esc_html($page['page_id']); ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="kt-detail-note"><?php echo esc_html(sprintf(_n('%d active page is selected by default. One distinct Facebook variant will be generated for each selected page.', '%d active pages are selected by default. One distinct Facebook variant will be generated for each selected page.', count($facebook_pages), 'kuchnia-twist'), count($facebook_pages))); ?></p>
                                <?php else : ?>
                                    <p class="kt-system-note kt-system-note--error"><?php esc_html_e('Add at least one active Facebook page in Settings before queueing article jobs.', 'kuchnia-twist'); ?></p>
                                <?php endif; ?>
                            </fieldset>
                        </div>
                        <button type="submit" class="button button-primary button-hero" data-facebook-submit><?php esc_html_e('Queue Job', 'kuchnia-twist'); ?></button>
                    </form>
                </section>

                <section class="kt-card kt-card--selected">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Selected Job', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Live status, outputs, and the next useful action.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <?php if ($selected) : ?>
                        <?php $this->render_job_summary($selected, $system_status); ?>
                    <?php else : ?>
                        <div class="kt-empty-state">
                            <h3><?php esc_html_e('No jobs yet', 'kuchnia-twist'); ?></h3>
                            <p><?php esc_html_e('Your first queued article will appear here with status and output links.', 'kuchnia-twist'); ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="kt-card kt-card--jobs">
                <div class="kt-card-head">
                    <div>
                        <h2><?php esc_html_e('Recent Jobs', 'kuchnia-twist'); ?></h2>
                        <p><?php echo esc_html($this->job_results_summary($job_page)); ?></p>
                    </div>
                </div>
                <form method="get" class="kt-jobs-toolbar">
                    <input type="hidden" name="page" value="kuchnia-twist-publisher">
                    <input type="hidden" name="job_page" value="1">
                    <label class="kt-toolbar-search">
                        <span class="screen-reader-text"><?php esc_html_e('Search jobs', 'kuchnia-twist'); ?></span>
                        <input type="search" name="job_search" value="<?php echo esc_attr($job_filters['search']); ?>" placeholder="<?php esc_attr_e('Search topic, title, or error', 'kuchnia-twist'); ?>">
                    </label>
                    <label class="kt-toolbar-select">
                        <span class="screen-reader-text"><?php esc_html_e('Filter by content type', 'kuchnia-twist'); ?></span>
                        <select name="job_type">
                            <option value=""><?php esc_html_e('All types', 'kuchnia-twist'); ?></option>
                            <?php foreach ($this->active_content_types() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($job_filters['content_type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="kt-toolbar-select">
                        <span class="screen-reader-text"><?php esc_html_e('Jobs per page', 'kuchnia-twist'); ?></span>
                        <select name="job_per_page">
                            <?php foreach ($this->job_per_page_options() as $value) : ?>
                                <option value="<?php echo esc_attr((string) $value); ?>" <?php selected($job_page['per_page'], $value); ?>><?php echo esc_html(sprintf(_n('%d per page', '%d per page', $value, 'kuchnia-twist'), $value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <nav class="kt-filter-pills" aria-label="<?php esc_attr_e('Job filters', 'kuchnia-twist'); ?>">
                        <?php foreach ($this->job_filter_options() as $value => $label) : ?>
                            <a
                                class="kt-filter-pill<?php echo $job_filters['status_group'] === $value ? ' is-active' : ''; ?>"
                                aria-current="<?php echo $job_filters['status_group'] === $value ? 'page' : 'false'; ?>"
                                href="<?php echo esc_url($this->publisher_page_url([
                                    'job_status' => $value === 'all' ? null : $value,
                                    'job_search' => $job_filters['search'] !== '' ? $job_filters['search'] : null,
                                    'job_type'   => $job_filters['content_type'] !== '' ? $job_filters['content_type'] : null,
                                    'job_per_page' => $job_page['per_page'],
                                ])); ?>"
                            >
                                <span><?php echo esc_html($label); ?></span>
                                <strong><?php echo esc_html((string) $this->job_filter_count($value, $counts)); ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="kt-toolbar-actions">
                        <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'kuchnia-twist'); ?></a>
                        <button type="submit" class="button"><?php esc_html_e('Apply', 'kuchnia-twist'); ?></button>
                        <?php if ($job_filters['search'] !== '' || $job_filters['status_group'] !== 'all' || $job_filters['content_type'] !== '') : ?>
                            <a class="button button-link" href="<?php echo esc_url($this->publisher_page_url(['job_per_page' => $job_page['per_page']])); ?>"><?php esc_html_e('Clear', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($jobs) : ?>
                    <div class="kt-job-list">
                        <?php foreach ($jobs as $job) : ?>
                            <?php
                            $job_url = $this->publisher_page_url([
                                'job_id'     => (int) $job['id'],
                                'job_status' => $job_filters['status_group'] !== 'all' ? $job_filters['status_group'] : null,
                                'job_search' => $job_filters['search'] !== '' ? $job_filters['search'] : null,
                                'job_type'   => $job_filters['content_type'] !== '' ? $job_filters['content_type'] : null,
                                'job_page'   => $job_page['page'],
                                'job_per_page' => $job_page['per_page'],
                            ]);
                            $job_quality = $this->job_quality_summary($job);
                            ?>
                            <article
                                class="kt-job-row<?php echo ($selected && (int) $selected['id'] === (int) $job['id']) ? ' is-selected' : ''; ?><?php echo !empty($job_quality['quality_status']) ? ' is-' . esc_attr($this->quality_status_class((string) $job_quality['quality_status'])) : ''; ?>"
                                data-href="<?php echo esc_url($job_url); ?>"
                                tabindex="0"
                                role="link"
                                aria-label="<?php echo esc_attr(sprintf(__('Open job #%1$d for %2$s', 'kuchnia-twist'), (int) $job['id'], $job['topic'])); ?>"
                            >
                                <div class="kt-job-row__main">
                                    <div class="kt-job-row__topline">
                                        <h3><?php echo esc_html($job['topic']); ?></h3>
                                        <div class="kt-job-row__status-stack">
                                            <span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($this->format_human_label($job['status'])); ?></span>
                                            <?php if (!empty($job_quality['quality_status'])) : ?>
                                                <span class="kt-status kt-status--<?php echo esc_attr($this->quality_status_class((string) $job_quality['quality_status'])); ?>"><?php echo esc_html($this->quality_status_label((string) $job_quality['quality_status'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="kt-job-row__meta">
                                        <span><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Updated %s', 'kuchnia-twist'), $this->format_admin_datetime($job['updated_at']))); ?></span>
                                        <?php if (!empty($job['publish_on'])) : ?>
                                            <span><?php echo esc_html(sprintf(__('Publishes %s', 'kuchnia-twist'), $this->format_admin_datetime($job['publish_on']))); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($job['title_override'])) : ?>
                                            <span><?php echo esc_html(sprintf(__('Title: %s', 'kuchnia-twist'), $job['title_override'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="kt-chip-row">
                                        <?php $this->render_job_asset_badges($job); ?>
                                        <?php if (!empty($job['stage']) && $job['stage'] !== $job['status']) : ?>
                                            <span class="kt-stage-pill"><?php echo esc_html($this->format_human_label($job['stage'])); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($job['retry_target'])) : ?>
                                            <span class="kt-asset-pill"><?php echo esc_html(sprintf(__('Retry %s', 'kuchnia-twist'), $this->format_human_label($job['retry_target']))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php $distribution_stats = $this->job_distribution_stats($job); ?>
                                    <?php $recipe_snapshot = $this->job_recipe_snapshot($job); ?>
                                    <?php if ($distribution_stats['total'] > 0) : ?>
                                        <p class="kt-job-row__distribution">
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    __('Facebook pages: %1$d/%2$d complete', 'kuchnia-twist'),
                                                    $distribution_stats['completed'],
                                                    $distribution_stats['total']
                                                )
                                            );
                                            ?>
                                            <?php if ($distribution_stats['failed'] > 0) : ?>
                                                <span><?php echo esc_html(sprintf(_n('%d page failed', '%d pages failed', $distribution_stats['failed'], 'kuchnia-twist'), $distribution_stats['failed'])); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($recipe_snapshot['ingredients_count']) || !empty($recipe_snapshot['instructions_count'])) : ?>
                                        <p class="kt-job-row__distribution">
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    __('Recipe card: %1$d ingredients, %2$d steps', 'kuchnia-twist'),
                                                    (int) $recipe_snapshot['ingredients_count'],
                                                    (int) $recipe_snapshot['instructions_count']
                                                )
                                            );
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($job['error_message'])) : ?>
                                        <p class="kt-job-row__error"><?php echo esc_html(wp_html_excerpt((string) $job['error_message'], 140, '...')); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="kt-job-row__actions">
                                    <a class="button button-small" href="<?php echo esc_url($job_url); ?>"><?php esc_html_e('View', 'kuchnia-twist'); ?></a>
                                    <?php if (!empty($job['permalink'])) : ?>
                                        <a class="button button-small" href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Post', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                    <?php if (in_array($job['status'], ['failed', 'partial_failure'], true)) : ?>
                                        <a class="button button-small" href="<?php echo esc_url($this->retry_link($job)); ?>"><?php esc_html_e('Retry', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="kt-empty-state">
                        <h3><?php echo ($job_filters['search'] !== '' || $job_filters['status_group'] !== 'all' || $job_filters['content_type'] !== '') ? esc_html__('No matching jobs', 'kuchnia-twist') : esc_html__('No jobs queued', 'kuchnia-twist'); ?></h3>
                        <p>
                            <?php
                            echo ($job_filters['search'] !== '' || $job_filters['status_group'] !== 'all' || $job_filters['content_type'] !== '')
                                ? esc_html__('Try clearing the search or switching back to all jobs.', 'kuchnia-twist')
                                : esc_html__('Create the first item from the composer and it will show up here immediately.', 'kuchnia-twist');
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                <?php $this->render_jobs_pagination($job_page, $job_filters); ?>
            </section>
        </div>
        <?php
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'kuchnia-twist'));
        }

        $settings = $this->get_settings();
        $system_status = $this->system_status_snapshot($settings);
        $next_scheduled_job = $this->next_scheduled_job();
        $scheduled_waiting = $this->count_ready_waiting_jobs();
        $facebook_pages = $this->facebook_pages($settings, false, false);
        ?>
        <div class="wrap kt-admin">
            <div class="kt-page-head">
                <div>
                    <h1><?php esc_html_e('Publishing Settings', 'kuchnia-twist'); ?></h1>
                    <p><?php esc_html_e('Keep the typed content engine lean: one manual composer, short guidance sections, uploaded-first images, and multi-page Facebook distribution.', 'kuchnia-twist'); ?></p>
                </div>
            </div>
            <?php if (isset($_GET['kt_saved'])) : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'kuchnia-twist'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kt-settings">
                <?php wp_nonce_field('kuchnia_twist_save_settings'); ?>
                <input type="hidden" name="action" value="kuchnia_twist_save_settings">

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Content Machine Overview', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('The active AI lane is a typed manual engine: recipe jobs stay dish-name-first, food facts stay title-first, and every selected page receives one distinct Facebook variant.', 'kuchnia-twist'); ?></p>
                        </div>
                        <span class="kt-mode-pill"><?php echo esc_html(self::CONTENT_MACHINE_VERSION); ?></span>
                    </div>
                    <div class="kt-summary-list">
                        <div>
                            <span><?php esc_html_e('Next scheduled publish', 'kuchnia-twist'); ?></span>
                            <strong>
                                <?php
                                echo $next_scheduled_job
                                    ? esc_html($this->format_admin_datetime((string) $next_scheduled_job['publish_on']))
                                    : esc_html__('No release scheduled', 'kuchnia-twist');
                                ?>
                            </strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Scheduled waiting', 'kuchnia-twist'); ?></span>
                            <strong><?php echo esc_html((string) $scheduled_waiting); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Timezone', 'kuchnia-twist'); ?></span>
                            <strong><?php echo esc_html(wp_timezone_string() ?: 'UTC'); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Release rule', 'kuchnia-twist'); ?></span>
                            <strong><?php esc_html_e('Per job publish time', 'kuchnia-twist'); ?></strong>
                        </div>
                    </div>
                    <p class="kt-system-note"><?php esc_html_e('Articles now queue directly from the manual composer. Leave publish time empty for immediate publish after generation, or set an exact date and time per job.', 'kuchnia-twist'); ?></p>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Global Voice', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Publication identity and non-negotiables that every article and Facebook variant inherits, regardless of content type.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Publication Role', 'kuchnia-twist'); ?></span><textarea name="publication_role" rows="3"><?php echo esc_textarea($settings['publication_role']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Voice Brief', 'kuchnia-twist'); ?></span><textarea name="brand_voice" rows="4"><?php echo esc_textarea($settings['brand_voice']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Global Guardrails', 'kuchnia-twist'); ?></span><textarea name="global_guardrails" rows="5"><?php echo esc_textarea($settings['global_guardrails']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Default CTA Text', 'kuchnia-twist'); ?></span><input type="text" name="default_cta" value="<?php echo esc_attr($settings['default_cta']); ?>"></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Recipe Content Engine', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Guide the recipe article stage with short standards while the JSON contract, multi-page output, and recipe structure stay code-owned.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Recipe Article Stage Direction', 'kuchnia-twist'); ?></span><textarea name="recipe_master_prompt" rows="8"><?php echo esc_textarea($settings['recipe_master_prompt']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Recipe Article Standard', 'kuchnia-twist'); ?></span><textarea name="article_prompt" rows="5"><?php echo esc_textarea($settings['article_prompt']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Food Fact Content Engine', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Set the article standard for title-first explainers so the engine can infer the angle, improve the final headline, and stay out of recipe territory.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Food Fact Article Standard', 'kuchnia-twist'); ?></span><textarea name="food_fact_article_prompt" rows="5"><?php echo esc_textarea($settings['food_fact_article_prompt']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Social Rules', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Guide the Facebook hooks and captions by content type. The engine will generate extra candidates and keep the best distinct variants for the selected pages.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Recipe Social Rules', 'kuchnia-twist'); ?></span><textarea name="facebook_caption_guidance" rows="4"><?php echo esc_textarea($settings['facebook_caption_guidance']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Food Fact Social Rules', 'kuchnia-twist'); ?></span><textarea name="food_fact_facebook_caption_guidance" rows="4"><?php echo esc_textarea($settings['food_fact_facebook_caption_guidance']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Images', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Use uploaded assets first and only generate the missing slot when needed. Keep one shared food-photography style brief that works for recipes and explainers.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full">
                            <span><?php esc_html_e('Image Handling', 'kuchnia-twist'); ?></span>
                            <select name="image_generation_mode">
                                <option value="uploaded_first_generate_missing" <?php selected($settings['image_generation_mode'], 'uploaded_first_generate_missing'); ?>><?php esc_html_e('Uploaded First', 'kuchnia-twist'); ?></option>
                                <option value="manual_only" <?php selected($settings['image_generation_mode'], 'manual_only'); ?>><?php esc_html_e('Manual Only', 'kuchnia-twist'); ?></option>
                            </select>
                        </label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Image Style Brief', 'kuchnia-twist'); ?></span><textarea name="image_style" rows="4"><?php echo esc_textarea($settings['image_style']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Scheduling', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Each article can publish immediately after generation or wait for the exact date and time chosen in the manual composer.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label>
                            <span><?php esc_html_e('Timezone', 'kuchnia-twist'); ?></span>
                            <input type="text" value="<?php echo esc_attr(wp_timezone_string() ?: 'UTC'); ?>" readonly>
                        </label>
                        <label>
                            <span><?php esc_html_e('Scheduling Mode', 'kuchnia-twist'); ?></span>
                            <input type="text" value="<?php esc_attr_e('Per-job schedule or immediate publish', 'kuchnia-twist'); ?>" readonly>
                        </label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Models', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Keep the text and image models visible. Repair behavior stays on internally with one quiet retry pass.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label><span><?php esc_html_e('Text Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_model" value="<?php echo esc_attr($settings['openai_model']); ?>"></label>
                        <label><span><?php esc_html_e('Image Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_image_model" value="<?php echo esc_attr($settings['openai_image_model']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('API Key', 'kuchnia-twist'); ?></span><input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>"></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Identity', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Name, role, portrait, and contact details shown across the publication.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label><span><?php esc_html_e('Editor Name', 'kuchnia-twist'); ?></span><input type="text" name="editor_name" value="<?php echo esc_attr($settings['editor_name']); ?>"></label>
                        <label><span><?php esc_html_e('Editor Role', 'kuchnia-twist'); ?></span><input type="text" name="editor_role" value="<?php echo esc_attr($settings['editor_role']); ?>"></label>
                        <label><span><?php esc_html_e('Public Editorial Email', 'kuchnia-twist'); ?></span><input type="text" name="editor_public_email" value="<?php echo esc_attr($settings['editor_public_email']); ?>"></label>
                        <label><span><?php esc_html_e('Business Email', 'kuchnia-twist'); ?></span><input type="text" name="editor_business_email" value="<?php echo esc_attr($settings['editor_business_email']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Editor Bio', 'kuchnia-twist'); ?></span><textarea name="editor_bio" rows="5"><?php echo esc_textarea($settings['editor_bio']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Social', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Follow label and public profile links for the header, menu, and footer.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Follow Label', 'kuchnia-twist'); ?></span><input type="text" name="social_follow_label" value="<?php echo esc_attr($settings['social_follow_label']); ?>"></label>
                        <label><span><?php esc_html_e('Instagram URL', 'kuchnia-twist'); ?></span><input type="url" name="social_instagram_url" value="<?php echo esc_attr($settings['social_instagram_url']); ?>" placeholder="https://instagram.com/yourprofile"></label>
                        <label><span><?php esc_html_e('Facebook URL', 'kuchnia-twist'); ?></span><input type="url" name="social_facebook_url" value="<?php echo esc_attr($settings['social_facebook_url']); ?>" placeholder="https://facebook.com/yourpage"></label>
                        <label><span><?php esc_html_e('Pinterest URL', 'kuchnia-twist'); ?></span><input type="url" name="social_pinterest_url" value="<?php echo esc_attr($settings['social_pinterest_url']); ?>" placeholder="https://pinterest.com/yourprofile"></label>
                        <label><span><?php esc_html_e('TikTok URL', 'kuchnia-twist'); ?></span><input type="url" name="social_tiktok_url" value="<?php echo esc_attr($settings['social_tiktok_url']); ?>" placeholder="https://tiktok.com/@yourprofile"></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Distribution', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Manage the Facebook pages that can receive recipe social variants, plus shared link-tracking defaults.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label><span><?php esc_html_e('Graph API Version', 'kuchnia-twist'); ?></span><input type="text" name="facebook_graph_version" value="<?php echo esc_attr($settings['facebook_graph_version']); ?>"></label>
                        <label><span><?php esc_html_e('UTM Source', 'kuchnia-twist'); ?></span><input type="text" name="utm_source" value="<?php echo esc_attr($settings['utm_source']); ?>"></label>
                        <label><span><?php esc_html_e('UTM Campaign Prefix', 'kuchnia-twist'); ?></span><input type="text" name="utm_campaign_prefix" value="<?php echo esc_attr($settings['utm_campaign_prefix']); ?>"></label>
                    </div>
                    <div class="kt-page-library" data-facebook-pages>
                        <div class="kt-card-head kt-card-head--compact">
                            <div>
                                <h3><?php esc_html_e('Facebook Page Library', 'kuchnia-twist'); ?></h3>
                                <p><?php esc_html_e('Each queued article can target one or more active pages. One social variant will be generated for each selected page.', 'kuchnia-twist'); ?></p>
                            </div>
                            <button type="button" class="button" data-add-facebook-page><?php esc_html_e('Add Page', 'kuchnia-twist'); ?></button>
                        </div>
                        <div class="kt-page-library__rows" data-facebook-page-list>
                            <?php foreach ($facebook_pages as $index => $page) : ?>
                                <div class="kt-page-row" data-facebook-page-row>
                                    <div class="kt-field-grid">
                                        <label><span><?php esc_html_e('Label', 'kuchnia-twist'); ?></span><input type="text" name="facebook_pages[<?php echo (int) $index; ?>][label]" value="<?php echo esc_attr($page['label']); ?>" data-facebook-page-field="label"></label>
                                        <label><span><?php esc_html_e('Page ID', 'kuchnia-twist'); ?></span><input type="text" name="facebook_pages[<?php echo (int) $index; ?>][page_id]" value="<?php echo esc_attr($page['page_id']); ?>" data-facebook-page-field="page_id"></label>
                                        <label class="kt-field-span-full"><span><?php esc_html_e('Page Access Token', 'kuchnia-twist'); ?></span><textarea name="facebook_pages[<?php echo (int) $index; ?>][access_token]" rows="3" data-facebook-page-field="access_token"><?php echo esc_textarea($page['access_token']); ?></textarea></label>
                                        <label class="kt-toggle-field"><span><?php esc_html_e('Active', 'kuchnia-twist'); ?></span><input type="checkbox" name="facebook_pages[<?php echo (int) $index; ?>][active]" value="1" data-facebook-page-field="active" <?php checked(!empty($page['active'])); ?>></label>
                                    </div>
                                    <button type="button" class="button-link-delete" data-remove-facebook-page><?php esc_html_e('Remove page', 'kuchnia-twist'); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <template id="kt-facebook-page-template">
                            <div class="kt-page-row" data-facebook-page-row>
                                <div class="kt-field-grid">
                                    <label><span><?php esc_html_e('Label', 'kuchnia-twist'); ?></span><input type="text" data-facebook-page-field="label"></label>
                                    <label><span><?php esc_html_e('Page ID', 'kuchnia-twist'); ?></span><input type="text" data-facebook-page-field="page_id"></label>
                                    <label class="kt-field-span-full"><span><?php esc_html_e('Page Access Token', 'kuchnia-twist'); ?></span><textarea rows="3" data-facebook-page-field="access_token"></textarea></label>
                                    <label class="kt-toggle-field"><span><?php esc_html_e('Active', 'kuchnia-twist'); ?></span><input type="checkbox" value="1" data-facebook-page-field="active"></label>
                                </div>
                                <button type="button" class="button-link-delete" data-remove-facebook-page><?php esc_html_e('Remove page', 'kuchnia-twist'); ?></button>
                            </div>
                        </template>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Environment', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Quick runtime checks from the current WordPress container.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-environment-list">
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Worker heartbeat', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['worker_heartbeat_text']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['worker_stale'] ? 'is-missing' : 'is-ready'; ?>">
                                <?php echo $system_status['worker_stale'] ? esc_html__('Stale', 'kuchnia-twist') : esc_html__('Fresh', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Worker processing', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['worker_loop_text']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['worker_enabled'] ? 'is-ready' : 'is-missing'; ?>">
                                <?php echo $system_status['worker_enabled'] ? esc_html__('Enabled', 'kuchnia-twist') : esc_html__('Disabled', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Last loop result', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['last_seen_label']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['worker_stale'] ? 'is-missing' : 'is-ready'; ?>">
                                <?php echo esc_html($system_status['last_loop_label']); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Worker config', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['worker_config_text']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['worker_config_ok'] ? 'is-ready' : 'is-missing'; ?>">
                                <?php echo $system_status['worker_config_ok'] ? esc_html__('Valid', 'kuchnia-twist') : esc_html__('Invalid', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Worker secret', 'kuchnia-twist'); ?></strong>
                                <p><?php esc_html_e('Required for the autopost worker callback.', 'kuchnia-twist'); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $this->get_worker_secret() ? 'is-ready' : 'is-missing'; ?>">
                                <?php echo $this->get_worker_secret() ? esc_html__('Present', 'kuchnia-twist') : esc_html__('Missing', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('OpenAI availability', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['openai_text']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['openai_ready'] ? 'is-ready' : 'is-missing'; ?>">
                                <?php echo $system_status['openai_ready'] ? esc_html__('Ready', 'kuchnia-twist') : esc_html__('Missing', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Facebook availability', 'kuchnia-twist'); ?></strong>
                                <p><?php echo esc_html($system_status['facebook_text']); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['facebook_ready'] ? 'is-ready' : 'is-missing'; ?>">
                                <?php echo $system_status['facebook_ready'] ? esc_html__('Ready', 'kuchnia-twist') : esc_html__('Warning', 'kuchnia-twist'); ?>
                            </span>
                        </div>
                        <div class="kt-env-item">
                            <div>
                                <strong><?php esc_html_e('Stale threshold', 'kuchnia-twist'); ?></strong>
                                <p><?php esc_html_e('The worker is flagged stale after this heartbeat gap.', 'kuchnia-twist'); ?></p>
                            </div>
                            <span class="kt-env-pill <?php echo $system_status['worker_stale'] ? 'is-missing' : 'is-ready'; ?>">
                                <?php echo esc_html($system_status['stale_after_label']); ?>
                            </span>
                        </div>
                    </div>
                </section>

                <div class="kt-settings-save">
                    <p><?php esc_html_e('Saving keeps the same option keys and launch rules already used by the worker and theme.', 'kuchnia-twist'); ?></p>
                    <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Save Settings', 'kuchnia-twist'); ?></button>
                </div>
            </form>
        </div>
        <?php
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

        if ($settings['image_generation_mode'] === 'manual_only') {
            if (!$blog_image_id || !$facebook_image_id) {
                wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=launch_assets_required'));
                exit;
            }
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

        global $wpdb;
        $duplicate_job_id = 0;
        $recent_jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, topic, title_override
                FROM {$this->table_name()}
                WHERE content_type = %s
                  AND created_by = %d
                  AND status IN ('queued', 'generating', 'scheduled', 'publishing_blog', 'publishing_facebook')
                  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                ORDER BY id DESC
                LIMIT 25",
                $content_type,
                get_current_user_id()
            ),
            ARRAY_A
        );

        $normalized_topic = sanitize_title($topic);
        $normalized_title = sanitize_title($title_candidate);
        foreach ($recent_jobs ?: [] as $recent_job) {
            $recent_topic = sanitize_title((string) ($recent_job['topic'] ?? ''));
            $recent_title = sanitize_title((string) ($recent_job['title_override'] ?? $recent_job['topic'] ?? ''));

            if (
                ($normalized_topic !== '' && $recent_topic === $normalized_topic) ||
                ($normalized_title !== '' && $recent_title === $normalized_title)
            ) {
                $duplicate_job_id = (int) ($recent_job['id'] ?? 0);
                break;
            }
        }

        if ($duplicate_job_id > 0) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $duplicate_job_id . '&kt_notice=duplicate_job'));
            exit;
        }

        $publish_on = $this->normalize_requested_publish_on_utc($publish_at_local);
        $schedule_mode = $this->publish_time_is_future($publish_on) ? 'scheduled' : 'immediate';
        $payload = $this->build_job_request_payload([
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
            'blog_image'                 => $this->attachment_payload($blog_image_id),
            'facebook_image'             => $this->attachment_payload($facebook_image_id),
            'selected_pages'             => $selected_pages,
            'site_name'                  => get_bloginfo('name'),
            'default_cta'                => $settings['default_cta'],
            'content_machine'            => $this->job_content_machine_snapshot($settings, $content_type),
        ]);

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

        $dish_name = sanitize_text_field(wp_unslash($_POST['dish_name'] ?? ''));
        $preferred_angle = $this->normalize_hook_angle_key((string) wp_unslash($_POST['preferred_angle'] ?? ''));
        $operator_note = sanitize_textarea_field((string) wp_unslash($_POST['operator_note'] ?? ''));

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
        $idea = $idea_id > 0 ? $this->get_recipe_idea($idea_id) : null;

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

    public function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_save_settings');

        $current  = $this->get_settings();
        $pages = $this->sanitize_facebook_pages_input(wp_unslash($_POST['facebook_pages'] ?? []));
        $primary_page = null;
        foreach ($pages as $page) {
            if (!empty($page['active']) && $page['access_token'] !== '') {
                $primary_page = $page;
                break;
            }
        }
        if ($primary_page === null && !empty($pages)) {
            $primary_page = $pages[0];
        }

        $incoming = [
            'topics_text'                => trim((string) wp_unslash($_POST['topics_text'] ?? $current['topics_text'] ?? '')),
            'publication_role'           => trim((string) wp_unslash($_POST['publication_role'] ?? $current['publication_role'] ?? '')),
            'brand_voice'                => trim((string) wp_unslash($_POST['brand_voice'] ?? $current['brand_voice'] ?? '')),
            'global_guardrails'          => trim((string) wp_unslash($_POST['global_guardrails'] ?? $current['global_guardrails'] ?? '')),
            'recipe_master_prompt'       => trim((string) wp_unslash($_POST['recipe_master_prompt'] ?? $current['recipe_master_prompt'] ?? '')),
            'article_prompt'             => trim((string) wp_unslash($_POST['article_prompt'] ?? $current['article_prompt'] ?? '')),
            'facebook_caption_guidance'  => trim((string) wp_unslash($_POST['facebook_caption_guidance'] ?? $current['facebook_caption_guidance'] ?? '')),
            'food_fact_article_prompt'   => trim((string) wp_unslash($_POST['food_fact_article_prompt'] ?? $current['food_fact_article_prompt'] ?? '')),
            'food_fact_facebook_caption_guidance' => trim((string) wp_unslash($_POST['food_fact_facebook_caption_guidance'] ?? $current['food_fact_facebook_caption_guidance'] ?? '')),
            'default_cta'                => sanitize_text_field(wp_unslash($_POST['default_cta'] ?? $current['default_cta'] ?? '')),
            'editor_name'                => sanitize_text_field(wp_unslash($_POST['editor_name'] ?? $current['editor_name'] ?? '')),
            'editor_role'                => sanitize_text_field(wp_unslash($_POST['editor_role'] ?? $current['editor_role'] ?? '')),
            'editor_bio'                 => trim((string) wp_unslash($_POST['editor_bio'] ?? $current['editor_bio'] ?? '')),
            'editor_public_email'        => sanitize_email((string) wp_unslash($_POST['editor_public_email'] ?? $current['editor_public_email'] ?? '')),
            'editor_business_email'      => sanitize_email((string) wp_unslash($_POST['editor_business_email'] ?? $current['editor_business_email'] ?? '')),
            'editor_photo_id'            => absint($_POST['editor_photo_id'] ?? $current['editor_photo_id'] ?? 0),
            'social_instagram_url'       => esc_url_raw(trim((string) wp_unslash($_POST['social_instagram_url'] ?? $current['social_instagram_url'] ?? ''))),
            'social_facebook_url'        => esc_url_raw(trim((string) wp_unslash($_POST['social_facebook_url'] ?? $current['social_facebook_url'] ?? ''))),
            'social_pinterest_url'       => esc_url_raw(trim((string) wp_unslash($_POST['social_pinterest_url'] ?? $current['social_pinterest_url'] ?? ''))),
            'social_tiktok_url'          => esc_url_raw(trim((string) wp_unslash($_POST['social_tiktok_url'] ?? $current['social_tiktok_url'] ?? ''))),
            'social_follow_label'        => sanitize_text_field(wp_unslash($_POST['social_follow_label'] ?? $current['social_follow_label'] ?? '')),
            'openai_model'               => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? $current['openai_model'] ?? '')),
            'openai_image_model'         => sanitize_text_field(wp_unslash($_POST['openai_image_model'] ?? $current['openai_image_model'] ?? '')),
            'openai_api_key'             => trim((string) wp_unslash($_POST['openai_api_key'] ?? $current['openai_api_key'] ?? '')),
            'image_style'                => trim((string) wp_unslash($_POST['image_style'] ?? $current['image_style'] ?? '')),
            'image_generation_mode'      => $this->sanitize_image_generation_mode(wp_unslash($_POST['image_generation_mode'] ?? $current['image_generation_mode'] ?? 'uploaded_first_generate_missing')),
            'strict_contract_mode'       => isset($_POST['strict_contract_mode']) ? (!empty($_POST['strict_contract_mode']) ? '1' : '0') : (string) ($current['strict_contract_mode'] ?? '0'),
            'daily_publish_time'         => $this->sanitize_publish_time((string) wp_unslash($_POST['daily_publish_time'] ?? $current['daily_publish_time'] ?? '09:00')),
            'facebook_graph_version'     => sanitize_text_field(wp_unslash($_POST['facebook_graph_version'] ?? $current['facebook_graph_version'] ?? '')),
            'facebook_pages'             => $pages,
            'facebook_page_id'           => $primary_page['page_id'] ?? '',
            'facebook_page_access_token' => $primary_page['access_token'] ?? '',
            'utm_source'                 => sanitize_key(wp_unslash($_POST['utm_source'] ?? $current['utm_source'] ?? 'facebook')),
            'utm_campaign_prefix'        => sanitize_key(wp_unslash($_POST['utm_campaign_prefix'] ?? $current['utm_campaign_prefix'] ?? 'kuchnia-twist')),
        ];

        update_option(self::OPTION_KEY, wp_parse_args($incoming, $current));

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-settings&kt_saved=1'));
        exit;
    }

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

        wp_safe_redirect($this->publisher_page_url([
            'job_id'     => $job_id,
            'kt_notice'  => $notice,
            'job_status' => sanitize_key(wp_unslash($_GET['job_status'] ?? '')),
            'job_search' => sanitize_text_field(wp_unslash($_GET['job_search'] ?? '')),
            'job_type'   => sanitize_key(wp_unslash($_GET['job_type'] ?? '')),
            'job_page'   => max(1, absint($_GET['job_page'] ?? 1)),
            'job_per_page' => absint($_GET['job_per_page'] ?? 24),
        ]));
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

    private function install(): void
    {
        global $wpdb;
        $table             = $this->table_name();
        $events_table      = $this->events_table_name();
        $ideas_table       = $this->ideas_table_name();
        $charset_collate   = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_uuid char(36) NOT NULL,
                topic varchar(191) NOT NULL,
                content_type varchar(32) NOT NULL,
                title_override varchar(255) NOT NULL DEFAULT '',
                blog_image_id bigint(20) unsigned NULL,
                facebook_image_id bigint(20) unsigned NULL,
                status varchar(32) NOT NULL DEFAULT 'queued',
                stage varchar(32) NOT NULL DEFAULT 'queued',
                retry_target varchar(32) NOT NULL DEFAULT '',
                created_by bigint(20) unsigned NULL,
                post_id bigint(20) unsigned NULL,
                featured_image_id bigint(20) unsigned NULL,
                facebook_image_result_id bigint(20) unsigned NULL,
                facebook_post_id varchar(191) NOT NULL DEFAULT '',
                facebook_comment_id varchar(191) NOT NULL DEFAULT '',
                permalink text NULL,
                error_message longtext NULL,
                request_payload longtext NULL,
                generated_payload longtext NULL,
                facebook_caption longtext NULL,
                group_share_kit longtext NULL,
                publish_on datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                last_attempt_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_uuid (job_uuid),
                KEY status_created (status, created_at),
                KEY status_publish (status, publish_on)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$events_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id bigint(20) unsigned NOT NULL,
                event_type varchar(64) NOT NULL,
                status varchar(32) NOT NULL DEFAULT '',
                stage varchar(32) NOT NULL DEFAULT '',
                message text NULL,
                context_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY job_created (job_id, created_at),
                KEY event_type_created (event_type, created_at)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$ideas_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                dish_name varchar(191) NOT NULL,
                preferred_angle varchar(64) NOT NULL DEFAULT '',
                operator_note text NULL,
                status varchar(32) NOT NULL DEFAULT 'idea',
                linked_job_id bigint(20) unsigned NULL,
                linked_post_id bigint(20) unsigned NULL,
                created_by bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status_updated (status, updated_at),
                KEY linked_job (linked_job_id),
                KEY linked_post (linked_post_id)
            ) {$charset_collate};"
        );

        update_option(self::OPTION_KEY, wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings()));
        update_option(self::WORKER_STATUS_KEY, wp_parse_args(get_option(self::WORKER_STATUS_KEY, []), $this->default_worker_status()));
        update_option(self::VERSION_KEY, self::VERSION, false);

        $this->ensure_site_identity();
        $this->ensure_category('recipe');
        $this->ensure_category('food_fact');
        $this->ensure_category('food_story');
        $this->ensure_core_pages();
        $this->ensure_launch_posts();
    }

    private function ensure_site_identity(): void
    {
        $title = trim((string) get_option('blogname', ''));
        if ($title === '' || in_array(strtolower($title), ['my blog', 'site title', 'my wordpress blog', 'wordpress'], true)) {
            update_option('blogname', 'kuchniatwist');
        }

        $tagline = trim((string) get_option('blogdescription', ''));
        if ($tagline === '' || in_array(strtolower($tagline), ['just another wordpress site', 'another wordpress site'], true)) {
            update_option('blogdescription', 'Warm home cooking, useful food facts, and story-led kitchen essays.');
        }
    }

    private function ensure_core_pages(): void
    {
        $pages = $this->core_pages();

        foreach ($pages as $slug => $page) {
            $existing_page = get_page_by_path($slug, OBJECT, 'page');
            $page_id         = 0;
            $did_seed_update = false;
            $seed_hash       = $this->core_page_seed_hash($page);

            if (!$existing_page instanceof WP_Post) {
                $page_id = (int) wp_insert_post([
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $slug,
                    'post_excerpt' => (string) ($page['excerpt'] ?? ''),
                    'post_content' => $page['content'],
                ]);
                $did_seed_update = $page_id > 0;
            } elseif (
                $this->should_refresh_core_page($existing_page)
                || ($this->is_seed_managed_core_page($existing_page) && $this->current_core_page_hash($existing_page) !== $seed_hash)
            ) {
                $page_id = (int) wp_update_post([
                    'ID'           => $existing_page->ID,
                    'post_title'   => $page['title'],
                    'post_excerpt' => (string) ($page['excerpt'] ?? $existing_page->post_excerpt),
                    'post_content' => $page['content'],
                ]);
                $did_seed_update = $page_id > 0;
            } else {
                $page_id = (int) $existing_page->ID;
            }

            if ($did_seed_update && $page_id > 0) {
                update_post_meta($page_id, self::CORE_PAGE_SEED_HASH_META, $seed_hash);
            }

            if ($did_seed_update && $page_id > 0 && !empty($page['seo_description'])) {
                update_post_meta($page_id, 'kuchnia_twist_seo_description', (string) $page['seo_description']);
            }

            if ($did_seed_update && $page_id > 0 && !empty($page['featured_asset'])) {
                $this->maybe_assign_local_featured_image(
                    $page_id,
                    (string) $page['featured_asset'],
                    $page['title'],
                    (string) ($page['featured_alt'] ?? $page['title'])
                );
            }
        }
    }

    private function core_page_seed_hash(array $page): string
    {
        return md5(wp_json_encode([
            'title'           => (string) ($page['title'] ?? ''),
            'excerpt'         => (string) ($page['excerpt'] ?? ''),
            'content'         => (string) ($page['content'] ?? ''),
            'seo_description' => (string) ($page['seo_description'] ?? ''),
        ]));
    }

    private function current_core_page_hash(WP_Post $page): string
    {
        return md5(wp_json_encode([
            'title'           => (string) $page->post_title,
            'excerpt'         => (string) $page->post_excerpt,
            'content'         => (string) $page->post_content,
            'seo_description' => (string) get_post_meta($page->ID, 'kuchnia_twist_seo_description', true),
        ]));
    }

    private function is_seed_managed_core_page(WP_Post $page): bool
    {
        $stored_hash = (string) get_post_meta($page->ID, self::CORE_PAGE_SEED_HASH_META, true);

        if ($stored_hash === '') {
            return false;
        }

        return hash_equals($stored_hash, $this->current_core_page_hash($page));
    }

    private function core_pages(): array
    {
        return kuchnia_twist_launch_core_pages();
    }

    private function ensure_launch_posts(): void
    {
        $launch_posts = array_values(array_filter(
            kuchnia_twist_launch_posts(),
            fn (array $post): bool => $this->is_active_content_type((string) ($post['content_type'] ?? ''))
        ));
        $editor_id    = $this->default_editor_user_id();
        $total_posts  = count($launch_posts);

        foreach ($launch_posts as $index => $post) {
            $existing = get_page_by_path($post['slug'], OBJECT, 'post');
            $seeded   = $existing instanceof WP_Post ? (bool) get_post_meta($existing->ID, '_kuchnia_twist_launch_seed', true) : false;
            $date_gmt = gmdate('Y-m-d H:i:s', current_time('timestamp', true) - (($total_posts - $index) * DAY_IN_SECONDS));

            $post_data = [
                'post_type'     => 'post',
                'post_status'   => 'publish',
                'post_title'    => $post['title'],
                'post_name'     => $post['slug'],
                'post_excerpt'  => $post['excerpt'],
                'post_content'  => $post['content_html'],
                'post_author'   => $editor_id,
                'post_category' => [$this->ensure_category($post['content_type'])],
                'post_date_gmt' => $date_gmt,
                'post_date'     => get_date_from_gmt($date_gmt),
            ];

            if (!$existing instanceof WP_Post) {
                $post_id = wp_insert_post($post_data, true);
                if (is_wp_error($post_id)) {
                    continue;
                }
                add_post_meta($post_id, '_kuchnia_twist_launch_seed', 1, true);
            } elseif ($seeded) {
                $post_id = wp_update_post(array_merge($post_data, ['ID' => $existing->ID]), true);
                if (is_wp_error($post_id)) {
                    continue;
                }
            } else {
                $post_id = $existing->ID;
            }

            update_post_meta($post_id, 'kuchnia_twist_content_type', $post['content_type']);
            update_post_meta($post_id, 'kuchnia_twist_recipe_data', $post['recipe'] ?? []);
            update_post_meta($post_id, 'kuchnia_twist_seo_description', $post['seo_description']);

            if (!empty($post['featured_asset'])) {
                $this->maybe_assign_local_featured_image(
                    $post_id,
                    (string) $post['featured_asset'],
                    $post['title'],
                    (string) ($post['featured_alt'] ?? $post['title'])
                );
            }
        }
    }

    private function should_refresh_core_page(WP_Post $page): bool
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($page->post_content))));

        if ($text === '') {
            return true;
        }

        $starter_markers = [
            'replace this placeholder',
            'replace this starter copy',
            'use this page to add your editorial email',
            'use this page to explain what cookies',
            'use this page to describe how recipes are developed',
            'this starter page should be replaced',
            'kuchniatwist is a food journal built around three pillars',
            'not launching with display ads',
            'does not use newsletter tools, advertising tags, affiliate scripts, or third-party analytics platforms',
            'does not run a broader advertising or tracking stack at launch',
            'not launching with advertising cookies',
            'the launch version of kuchniatwist does not rely on ad-tech cookies',
            'not yet using the kinds of systems that require a more complex preference center',
            'the trust layer around the archive matters as much as the archive itself',
            'a useful site should feel maintained in public',
            'same public trust layer as this policy',
        ];

        foreach ($starter_markers as $marker) {
            if (strpos($text, $marker) !== false) {
                return true;
            }
        }

        return str_word_count($text) < 45;
    }

    private function default_editor_user_id(): int
    {
        $users = get_users([
            'role__in' => ['administrator', 'editor', 'author'],
            'number'   => 1,
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'fields'   => ['ID'],
        ]);

        if ($users && isset($users[0]->ID)) {
            return (int) $users[0]->ID;
        }

        return get_current_user_id() ?: 1;
    }

    private function maybe_assign_local_featured_image(int $post_id, string $relative_path, string $title, string $alt): void
    {
        $attachment_id = $this->import_local_asset($relative_path, $title, $alt, $post_id);
        if ($attachment_id > 0) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    private function import_local_asset(string $relative_path, string $title, string $alt, int $parent_id = 0): int
    {
        $existing = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'meta_key'       => '_kuchnia_twist_launch_asset',
            'meta_value'     => $relative_path,
            'fields'         => 'ids',
        ]);

        if ($existing) {
            $attachment_id = (int) $existing[0];
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
            if ($parent_id > 0) {
                wp_update_post(['ID' => $attachment_id, 'post_parent' => $parent_id]);
            }
            return $attachment_id;
        }

        $source_path = trailingslashit(KUCHNIA_TWIST_PUBLISHER_DIR) . 'assets/launch/' . ltrim($relative_path, '/');
        if (!file_exists($source_path)) {
            return 0;
        }

        $binary = file_get_contents($source_path);
        if ($binary === false) {
            return 0;
        }

        $upload = wp_upload_bits(basename($source_path), null, $binary);
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachment_id = wp_insert_attachment([
            'post_title'     => $title,
            'post_parent'    => $parent_id,
            'post_mime_type' => wp_check_filetype($upload['file'])['type'] ?? 'image/jpeg',
            'guid'           => $upload['url'],
        ], $upload['file'], $parent_id);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        update_post_meta($attachment_id, '_kuchnia_twist_launch_asset', $relative_path);

        return (int) $attachment_id;
    }

    private function ensure_category(string $content_type): int
    {
        $map = [
            'recipe'     => ['name' => __('Recipes', 'kuchnia-twist'), 'slug' => 'recipes'],
            'food_fact'  => ['name' => __('Food Facts', 'kuchnia-twist'), 'slug' => 'food-facts'],
            'food_story' => ['name' => __('Food Stories', 'kuchnia-twist'), 'slug' => 'food-stories'],
        ];

        $target = $map[$content_type] ?? $map['recipe'];
        $term   = get_category_by_slug($target['slug']);

        if ($term instanceof WP_Term) {
            return (int) $term->term_id;
        }

        return (int) wp_create_category($target['name']);
    }

    private function handle_media_upload(string $field): int
    {
        if (empty($_FILES[$field]['name'])) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload($field, 0);

        if (is_wp_error($attachment_id)) {
            wp_die(esc_html($attachment_id->get_error_message()));
        }

        return (int) $attachment_id;
    }

    private function default_worker_status(): array
    {
        return [
            'worker_version'        => '',
            'enabled'               => false,
            'run_once'              => false,
            'poll_seconds'          => 0,
            'startup_delay_seconds' => 0,
            'config_ok'             => false,
            'last_seen_at'          => '',
            'last_loop_result'      => '',
            'last_job_id'           => 0,
            'last_job_status'       => '',
            'last_error'            => '',
        ];
    }

    private function get_worker_status(): array
    {
        return wp_parse_args(get_option(self::WORKER_STATUS_KEY, []), $this->default_worker_status());
    }

    private function system_status_snapshot(array $settings): array
    {
        $worker_status     = $this->get_worker_status();
        $worker_secret_set = $this->get_worker_secret() !== '';
        $openai_ready      = trim((string) (getenv('OPENAI_API_KEY') ?: $settings['openai_api_key'])) !== '';
        $facebook_pages    = $this->facebook_pages($settings, true, true);
        $facebook_ready    = !empty($facebook_pages);
        $poll_seconds      = max(0, (int) ($worker_status['poll_seconds'] ?? 0));
        $stale_after       = max(90, $poll_seconds * 3);
        $last_seen_at      = (string) ($worker_status['last_seen_at'] ?? '');
        $last_seen_unix    = $last_seen_at !== '' ? strtotime($last_seen_at . ' UTC') : 0;
        $now_unix          = current_time('timestamp', true);
        $heartbeat_age     = $last_seen_unix > 0 ? max(0, $now_unix - $last_seen_unix) : PHP_INT_MAX;
        $worker_stale      = $last_seen_unix <= 0 || $heartbeat_age > $stale_after;
        $worker_enabled    = !empty($worker_status['enabled']);
        $worker_config_ok  = $worker_secret_set && !empty($worker_status['config_ok']);
        $worker_version    = sanitize_text_field((string) ($worker_status['worker_version'] ?? ''));
        $last_error        = sanitize_text_field((string) ($worker_status['last_error'] ?? ''));
        $last_loop_result  = sanitize_key((string) ($worker_status['last_loop_result'] ?? ''));

        if ($last_seen_unix > 0) {
            $heartbeat_text = sprintf(
                __('Last seen %1$s ago.', 'kuchnia-twist'),
                human_time_diff($last_seen_unix, $now_unix)
            );
        } else {
            $heartbeat_text = __('No worker heartbeat received yet.', 'kuchnia-twist');
        }

        if ($worker_version !== '') {
            $heartbeat_text .= ' ' . sprintf(__('Version %s.', 'kuchnia-twist'), $worker_version);
        }

        if ($worker_enabled) {
            $worker_loop_text = !empty($worker_status['run_once'])
                ? __('Worker is in run-once mode and will idle after the current pass.', 'kuchnia-twist')
                : sprintf(
                    __('Polling every %1$ss after a %2$ss startup delay.', 'kuchnia-twist'),
                    max(1, $poll_seconds),
                    max(0, (int) ($worker_status['startup_delay_seconds'] ?? 0))
                );
        } else {
            $worker_loop_text = __('AUTOPOST_ENABLED is off in the worker container.', 'kuchnia-twist');
        }

        if ($last_loop_result !== '') {
            $worker_loop_text .= ' ' . sprintf(
                __('Last loop: %s.', 'kuchnia-twist'),
                $this->format_human_label($last_loop_result)
            );
        }

        if (!$worker_secret_set) {
            $worker_config_text = __('CONTENT_PIPELINE_SHARED_SECRET is missing in the WordPress container.', 'kuchnia-twist');
        } elseif ($worker_config_ok) {
            $worker_config_text = __('Worker reported a valid internal URL and shared secret configuration.', 'kuchnia-twist');
        } else {
            $worker_config_text = __('The worker has not confirmed valid internal callback configuration yet.', 'kuchnia-twist');
        }

        if ($last_error !== '') {
            $worker_config_text .= ' ' . sprintf(__('Last error: %s', 'kuchnia-twist'), $last_error);
        }

        $openai_text = $openai_ready
            ? __('An OpenAI API key is available from the environment or plugin settings.', 'kuchnia-twist')
            : __('Add OPENAI_API_KEY in Coolify or the plugin settings before background generation can run.', 'kuchnia-twist');

        $facebook_text = $facebook_ready
            ? sprintf(_n('%d active Facebook page is configured for distribution.', '%d active Facebook pages are configured for distribution.', count($facebook_pages), 'kuchnia-twist'), count($facebook_pages))
            : __('No active Facebook pages are configured. Blog publication can still succeed and stop at partial failure for Facebook.', 'kuchnia-twist');

        return [
            'worker_status'         => $worker_status,
            'worker_version'        => $worker_version,
            'worker_enabled'        => $worker_enabled,
            'worker_config_ok'      => $worker_config_ok,
            'worker_stale'          => $worker_stale,
            'worker_heartbeat_text' => $heartbeat_text,
            'last_seen_label'       => $last_seen_at !== '' ? $this->format_admin_datetime($last_seen_at) : __('Never', 'kuchnia-twist'),
            'worker_loop_text'      => $worker_loop_text,
            'worker_config_text'    => $worker_config_text,
            'worker_last_error'     => $last_error,
            'last_loop_label'       => $last_loop_result !== '' ? $this->format_human_label($last_loop_result) : __('Unknown', 'kuchnia-twist'),
            'last_job_id'           => max(0, (int) ($worker_status['last_job_id'] ?? 0)),
            'last_job_status'       => sanitize_key((string) ($worker_status['last_job_status'] ?? '')),
            'stale_after_seconds'   => $stale_after,
            'stale_after_label'     => sprintf(_n('%d second', '%d seconds', $stale_after, 'kuchnia-twist'), $stale_after),
            'openai_ready'          => $openai_ready,
            'openai_text'           => $openai_text,
            'facebook_ready'        => $facebook_ready,
            'facebook_text'         => $facebook_text,
            'facebook_pages_count'  => count($facebook_pages),
        ];
    }

    private function render_system_alerts(array $system_status): void
    {
        $alerts = [];

        if ($system_status['worker_stale']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Worker heartbeat is stale', 'kuchnia-twist'),
                'message' => __('The autopost container has not checked in recently, so queued jobs may sit idle until it comes back.', 'kuchnia-twist'),
                'detail'  => $system_status['worker_heartbeat_text'],
            ];
        }

        if (!$system_status['worker_config_ok']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Worker configuration needs attention', 'kuchnia-twist'),
                'message' => __('The worker has not confirmed a valid internal callback configuration yet.', 'kuchnia-twist'),
                'detail'  => $system_status['worker_config_text'],
            ];
        }

        if (!$system_status['openai_ready']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('OpenAI is missing', 'kuchnia-twist'),
                'message' => __('Queueing still works, but background generation will fail until an API key is available.', 'kuchnia-twist'),
                'detail'  => $system_status['openai_text'],
            ];
        }

        if (!$system_status['facebook_ready']) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Facebook publishing is not fully configured', 'kuchnia-twist'),
                'message' => __('The blog article can still go live, but Facebook publishing may stop at partial failure.', 'kuchnia-twist'),
                'detail'  => $system_status['facebook_text'],
            ];
        }

        $legacy_queue = $this->dormant_content_queue_count();
        if ($legacy_queue > 0) {
            $alerts[] = [
                'class'   => 'is-warning',
                'title'   => __('Dormant content jobs detected', 'kuchnia-twist'),
                'message' => __('Recipe and Food Fact are active. Older dormant content jobs still need manual review before you trust them in the typed content flow.', 'kuchnia-twist'),
                'detail'  => sprintf(_n('%d dormant queued job needs manual review.', '%d dormant queued jobs need manual review.', $legacy_queue, 'kuchnia-twist'), $legacy_queue),
            ];
        }

        if (!$alerts) {
            return;
        }
        ?>
        <div class="kt-alert-list" role="status" aria-live="polite">
            <?php foreach ($alerts as $alert) : ?>
                <article class="kt-alert <?php echo esc_attr($alert['class']); ?>">
                    <strong><?php echo esc_html($alert['title']); ?></strong>
                    <p><?php echo esc_html($alert['message']); ?></p>
                    <?php if (!empty($alert['detail'])) : ?>
                        <span><?php echo esc_html($alert['detail']); ?></span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function get_dashboard_counts(): array
    {
        global $wpdb;

        $counts = [
            'queued'          => 0,
            'scheduled'       => 0,
            'running'         => 0,
            'needs_attention' => 0,
            'completed'       => 0,
        ];

        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->table_name()} GROUP BY status", ARRAY_A);
        foreach ($rows ?: [] as $row) {
            $status = sanitize_key($row['status'] ?? '');
            $total  = (int) ($row['total'] ?? 0);

            if ($status === 'queued') {
                $counts['queued'] += $total;
                continue;
            }

            if ($status === 'scheduled') {
                $counts['scheduled'] += $total;
                continue;
            }

            if (in_array($status, ['generating', 'publishing_blog', 'publishing_facebook'], true)) {
                $counts['running'] += $total;
                continue;
            }

            if (in_array($status, ['failed', 'partial_failure'], true)) {
                $counts['needs_attention'] += $total;
                continue;
            }

            if ($status === 'completed') {
                $counts['completed'] += $total;
            }
        }

        return $counts;
    }

    private function dormant_content_queue_count(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()} WHERE status = 'queued' AND content_type NOT IN ('recipe', 'food_fact')");
    }

    public function shortcode_editor_name(): string
    {
        $settings = $this->get_settings();
        if ($settings['editor_name'] !== '') {
            return esc_html($settings['editor_name']);
        }

        $user = get_userdata($this->default_editor_user_id());
        if ($user instanceof WP_User && $user->display_name !== '') {
            return esc_html($user->display_name);
        }

        return esc_html(get_bloginfo('name'));
    }

    public function shortcode_editor_role(): string
    {
        $settings = $this->get_settings();
        $role = $settings['editor_role'] !== '' ? $settings['editor_role'] : __('Founding editor', 'kuchnia-twist');
        return esc_html($role);
    }

    public function shortcode_editor_public_email(): string
    {
        $settings = $this->get_settings();
        $email = is_email($settings['editor_public_email']) ? $settings['editor_public_email'] : get_option('admin_email');
        return esc_html(antispambot((string) $email));
    }

    public function shortcode_editor_business_email(): string
    {
        $settings = $this->get_settings();
        $email = is_email($settings['editor_business_email']) ? $settings['editor_business_email'] : '';
        return esc_html(antispambot((string) $email));
    }

    public function shortcode_internal_link($atts, $content = ''): string
    {
        $atts = shortcode_atts([
            'slug' => '',
        ], $atts, 'kuchnia_twist_link');

        $slug = sanitize_title((string) $atts['slug']);
        $label = trim((string) $content);

        if ($slug === '' || $label === '') {
            return esc_html($label);
        }

        $url = '';
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
        }

        if ($url === '') {
            $post = get_page_by_path($slug, OBJECT, 'post');
            if ($post instanceof WP_Post) {
                $url = get_permalink($post);
            }
        }

        if ($url === '') {
            $term = get_category_by_slug($slug);
            if ($term instanceof WP_Term) {
                $url = get_category_link($term);
            }
        }

        if ($url === '' || is_wp_error($url)) {
            return esc_html($label);
        }

        return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
    }

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

    private function job_has_core_package(array $job): bool
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $package = $this->normalized_generated_content_package($generated, $job);
        $has_pages = !empty($package['content_pages']) && is_array($package['content_pages']);

        return !empty($package['title']) && !empty($package['slug']) && (!empty($package['content_html']) || $has_pages);
    }

    private function job_retry_target(array $job): string
    {
        $distribution = $this->job_facebook_distribution($job);
        if (!empty($job['post_id']) && !empty($distribution['pages']) && is_array($distribution['pages'])) {
            foreach ($distribution['pages'] as $page) {
                if (empty($page['post_id'])) {
                    return 'facebook';
                }
                if (empty($page['comment_id'])) {
                    return 'comment';
                }
            }

            return 'comment';
        }

        if (!empty($job['post_id'])) {
            return empty($job['facebook_post_id']) ? 'facebook' : 'comment';
        }

        return $this->job_has_core_package($job) ? 'publish' : 'full';
    }

    private function sanitize_image_generation_mode($value): string
    {
        $value = (string) $value;

        if ($value === 'ai_fallback') {
            return 'uploaded_first_generate_missing';
        }

        return in_array($value, ['manual_only', 'uploaded_first_generate_missing'], true) ? $value : 'uploaded_first_generate_missing';
    }

    private function find_conflicting_post_id(string $candidate, int $exclude_post_id = 0): int
    {
        global $wpdb;

        $normalized = sanitize_title($candidate);
        if ($normalized === '') {
            return 0;
        }

        $existing = get_page_by_path($normalized, OBJECT, 'post');
        if ($existing instanceof WP_Post && $existing->ID !== $exclude_post_id) {
            return (int) $existing->ID;
        }

        $title_match = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = 'post'
                  AND post_status IN ('publish', 'draft', 'future', 'pending', 'private')
                  AND LOWER(post_title) = LOWER(%s)
                  AND ID <> %d
                ORDER BY ID DESC
                LIMIT 1",
                $candidate,
                $exclude_post_id
            )
        );

        return $title_match;
    }

    private function normalized_social_variant_fingerprint(array $variant): string
    {
        return sanitize_title(wp_strip_all_tags($this->build_facebook_post_preview($variant)));
    }

    private function normalized_social_hook_fingerprint(array $variant): string
    {
        return sanitize_title(wp_strip_all_tags((string) ($variant['hook'] ?? '')));
    }

    private function normalized_social_opening_fingerprint(array $variant): string
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        return sanitize_title((string) ($lines[0] ?? ''));
    }

    private function social_variant_looks_weak(array $variant, string $article_title = '', string $content_type = 'recipe', string $article_excerpt = ''): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $hook_words = str_word_count($hook);
        $caption_words = str_word_count(wp_strip_all_tags($caption));
        $caption_lines = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $hook_front_load_score = $this->front_loaded_click_signal_score($hook, $content_type);
        $unanchored_pronoun_lead = preg_match('/^(it|this|that|these|they)\b/i', $hook) === 1
            && !$this->social_variant_anchor_signal($variant, $article_title, $article_excerpt);
        $superiority_bait = preg_match('/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you\'re doing|amateurs?|rookie move|lazy cooks)\b/i', $hook . ' ' . $caption) === 1;

        return $hook === ''
            || $hook_words < 4
            || $hook_words > 18
            || $caption === ''
            || $caption_words < 14
            || $caption_words > 85
            || $caption_lines < 2
            || $caption_lines > 5
            || $hook_front_load_score < 0
            || $unanchored_pronoun_lead
            || $superiority_bait
            || $this->contains_cheap_suspense_pattern($hook)
            || ($normalized_title !== '' && $normalized_hook === $normalized_title)
            || preg_match('/(https?:\/\/|www\.)/i', $caption) === 1
            || preg_match('/(^|\s)#[a-z0-9_]+/i', $caption) === 1;
    }

    private function social_variant_front_loaded_signal(array $variant, string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        return $this->front_loaded_click_signal_score($hook, $content_type) > 0;
    }

    private function contains_cheap_suspense_pattern(string $text): bool
    {
        return preg_match('/\b(what happens next|nobody tells you|no one tells you|what they don\'?t tell you|the secret(?: to)?|finally revealed|you(?:\'ll| will) never guess|hidden truth)\b/i', sanitize_text_field($text)) === 1;
    }

    private function social_variant_pain_point_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|get wrong|confusion|assumption)\b/i', $text) === 1;
    }

    private function social_variant_payoff_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(payoff|result|better|easier|faster|simpler|worth it|works|crisp|crispy|creamy|juicy|clearer|smarter|useful|difference)\b/i', $text) === 1;
    }

    private function social_variant_proof_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(\d+\s?(?:minute|min|minutes|mins|step|steps)|one[- ]pan|sheet pan|air fryer|skillet|oven|temperature|label|pantry|fridge|crispy|creamy|cheesy|garlicky|juicy|golden|without drying|without going soggy|that keeps|which keeps|because|so it stays|so you get)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.2 && $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2) {
            return true;
        }

        return false;
    }

    private function social_variant_curiosity_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        if ($hook === '' || $this->contains_cheap_suspense_pattern($hook) || $this->contains_cheap_suspense_pattern($caption)) {
            return false;
        }
        if (preg_match('/\b(why|how|turns out|actually|the difference|detail|changes|what most people|get wrong|mistake|truth|assumption)\b/i', $hook) !== 1) {
            return false;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($hook . ' ' . $caption, $article_context) >= 0.12) {
            return true;
        }

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
    }

    private function social_variant_contrast_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(instead of|rather than|not just|not the|more than|less about|without turning|but not|vs\.?|versus|the part that|what changes|what most people miss)\b/i', $text) === 1;
    }

    private function social_variant_resolves_early(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $needs_resolution = preg_match('/[?]|\b(why|how|turns out|actually|the difference|changes|truth|mistake|what most people|get wrong|instead of|rather than|not just|more than|less about|vs\.?|versus)\b/i', $hook) === 1;

        if ($early_caption === '') {
            return false;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $overlap_hit = $article_context !== '' && $this->shared_words_ratio($early_caption, $article_context) >= 0.16;
        $front_loaded_hit = $this->front_loaded_click_signal_score($early_caption, $content_type) > 0;
        $concrete_hit = preg_match('/\b(crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|faster|easier|save|problem|result|payoff|difference|detail|reason|because|instead|clearer|better)\b/i', $early_caption) === 1;

        if (!$needs_resolution) {
            return $overlap_hit || ($front_loaded_hit && $concrete_hit);
        }

        return $overlap_hit || ($front_loaded_hit && $concrete_hit);
    }

    private function social_variant_specificity_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        $text = trim($hook . ' ' . $caption);
        if ($text === '') {
            return 0;
        }

        $score = 0;
        if ($this->front_loaded_click_signal_score($hook, $content_type) > 0) {
            $score += 1;
        }
        if (preg_match('/\b(one-pan|sheet pan|air fryer|skillet|weeknight|budget|crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|myth|faster|easier|save|result|payoff|ingredient|texture|timing|answer)\b/i', $text) === 1) {
            $score += 1;
        }
        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '') {
            $overlap = $this->shared_words_ratio($text, $article_context);
            if ($overlap >= 0.12 && $overlap <= 0.7) {
                $score += 1;
            }
        }
        if (preg_match('/\b\d+\b/', $hook) === 1) {
            $score += 1;
        }

        return $score;
    }

    private function social_variant_novelty_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        $text = trim($hook . ' ' . $caption);
        if ($text === '') {
            return 0;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $title_overlap = $article_title !== '' ? $this->shared_words_ratio($text, $article_title) : 0.0;
        $context_overlap = $article_context !== '' ? $this->shared_words_ratio($text, $article_context) : 0.0;
        $score = 0;

        if ($context_overlap >= 0.16 && $title_overlap <= 0.58) {
            $score += 2;
        } elseif ($context_overlap >= 0.08 && $title_overlap <= 0.68) {
            $score += 1;
        }
        if ($this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2 && $title_overlap <= 0.58) {
            $score += 1;
        }
        if ($this->contains_cheap_suspense_pattern($hook) || $this->contains_cheap_suspense_pattern($caption)) {
            $score -= 1;
        }

        return max(0, $score);
    }

    private function build_social_anchor_phrases(string $article_title = '', string $article_excerpt = ''): array
    {
        $phrases = [];
        $seen = [];
        foreach ([$article_title, $article_excerpt] as $source) {
            foreach (preg_split('/\s*(?:,| and | with | without | or )\s*/i', sanitize_text_field($source)) ?: [] as $part) {
                $phrase = trim(sanitize_text_field($part));
                $fingerprint = sanitize_title($phrase);
                if ($phrase === '' || $fingerprint === '' || isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    private function social_variant_anchor_signal(array $variant, string $article_title = '', string $article_excerpt = ''): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        foreach ($this->build_social_anchor_phrases($article_title, $article_excerpt) as $target) {
            $overlap = $this->shared_words_ratio($text, $target);
            if ($overlap >= 0.18 || (str_word_count($target) <= 2 && $overlap >= 0.12)) {
                return true;
            }
        }

        return false;
    }

    private function social_variant_relatability_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(busy night|weeknight|after work|family dinner|home cook|at home|takeout night|budget dinner|feed everyone|tonight|make this tonight|fridge|pantry)\b/i';
        $fact_pattern = '/\b(in your kitchen|at home|home cook|next time you cook|next time you buy|next time you store|next time you shop|your pantry|your fridge|the label|grocery aisle|home kitchen)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if (preg_match('/\b(you|your|home|kitchen|dinner|cook)\b/i', $text) === 1 && $article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16) {
            return true;
        }

        return false;
    }

    private function social_variant_self_recognition_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(if your|when your|if you keep|if dinner keeps|if this keeps|the reason your|why your|you know that moment when|you know the night when)\b/i';
        $fact_pattern = '/\b(if your|when your|if you keep|if that label keeps|if this keeps|the reason your|why your|you know that moment when|you know the shopping moment when)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $repeated_outcome = preg_match('/\b(keeps getting|keeps turning|keeps ending up|still turns|still ends up|still feels|same mistake|same result|same flat|same soggy|same dry|same bland|same confusion|same waste)\b/i', $text) === 1;
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $article_pain_overlap = $article_context !== '' && $this->shared_words_ratio($text, $article_excerpt) >= 0.16;

        if (preg_match($pattern, $text) === 1 && ($repeated_outcome || $article_pain_overlap)) {
            return true;
        }

        return preg_match('/\b(your|you)\b/i', $text) === 1
            && $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            && (
                $repeated_outcome
                || $article_pain_overlap
                || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            )
            && $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt);
    }

    private function social_variant_conversation_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(comment|tag|share|send this|drop a|tell me in the comments|let me know)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(your house|your table|your family|in your family|the person who|the friend who|most home cooks|a lot of home cooks|everyone thinks|everyone assumes|if you always|the way you always|which one|debate|split)\b/i';
        $fact_pattern = '/\b(your kitchen|your pantry|your fridge|your grocery cart|at the store|on the label|the version you always buy|what most people buy|most people think|a lot of people assume|if you always|which one|debate|split)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        return $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            && $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            && (
                $this->social_variant_contrast_signal($variant)
                || $this->social_variant_pain_point_signal($variant)
                || preg_match('/\b(people|everyone|most|house|family|table|friend|buy|shop|order)\b/i', $text) === 1
            );
    }

    private function social_variant_actionability_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(next time you|before you|use this|skip the|start with|watch for|look for|keep it|swap in|swap out|do this|try this|store it|cook it|buy it|save this for|make this when)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.18 && preg_match('/\b(you|your|next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_immediacy_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(tonight|this week|this weekend|after work|before dinner|next grocery run|next shop|next time you cook|next time you shop|next time you make|weeknight|tomorrow night)\b/i';
        $fact_pattern = '/\b(this week|this weekend|next grocery run|next time you buy|next time you shop|next time you cook|next time you order|next time you store|before you buy|before you cook|before you order)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16 && preg_match('/\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_consequence_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(otherwise|or you keep|or it keeps|costs you|keeps costing|keeps wasting|wastes time|wastes money|ends up|turns dry|turns soggy|falls flat|miss the detail|miss that|without the detail|keep repeating|same mistake|less payoff|more effort|still paying for|still stuck with)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.18 && preg_match('/\b(miss|waste|cost|repeat|stuck|flat|dry|soggy|harder|payoff|effort)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_habit_shift_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(if you always|if you still|the way you always|usual move|usual dinner move|default dinner move|instead of|rather than|stop doing|stop treating|swap|trade|skip the|break the habit|usual habit|same dinner habit|keep doing)\b/i';
        $fact_pattern = '/\b(if you always|if you still|the way you always|usual move|default move|instead of|rather than|stop doing|swap|trade|skip the|break the habit|usual habit|same shopping habit|same kitchen habit|keep doing)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $shift_words = preg_match('/\b(always|still|instead of|rather than|swap|trade|usual|default|habit|keep doing|stop doing|break the habit|same mistake)\b/i', $text) === 1;
        $better_result =
            $this->social_variant_contrast_signal($variant)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type);
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $socially_recognizable =
            $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type);

        if (preg_match($pattern, $text) === 1 && $better_result && $grounded) {
            return true;
        }

        return $shift_words
            && $socially_recognizable
            && $better_result
            && $grounded;
    }

    private function social_variant_savvy_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(smart cooks|real cooks|good cooks know|bad cooks|lazy cooks|amateurs?|rookie move)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(smarter move|smarter dinner move|better move|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the method that works|the version worth making|the version worth repeating|worth using|worth making)\b/i';
        $fact_pattern = '/\b(smarter move|smarter buy|better buy|better pick|better choice|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the version worth buying|the version worth keeping|the detail worth knowing|worth checking|worth buying|worth using)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $smart_choice_words = preg_match('/\b(smarter|cleaner|better|worth|reliable|more reliable|better call|better bet|better pick|better choice|better move|good call)\b/i', $text) === 1;
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $useful_signal =
            $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_promise_sync_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $overlap_signal = $article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16;

        if (preg_match($pattern, $text) === 1 && $grounded && $useful_signal) {
            return true;
        }

        return $smart_choice_words
            && $grounded
            && ($useful_signal || $overlap_signal);
    }

    private function social_variant_identity_shift_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you\'re doing|amateurs?|rookie move|lazy cooks)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old weeknight move|past the usual dinner drag|no longer stuck with|graduate from)\b/i';
        $fact_pattern = '/\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old shopping move|past the usual confusion|no longer stuck with|graduate from)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $shift_words = preg_match('/\b(done with|leave behind|move past|past the usual|no longer stuck with|stop settling|old default|usual default|graduate from|break out of)\b/i', $text) === 1;
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $practical_lift =
            $this->social_variant_savvy_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant);
        $recognition =
            $this->social_variant_self_recognition_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type);

        if (preg_match($pattern, $text) === 1 && $grounded && $practical_lift && $recognition) {
            return true;
        }

        return $shift_words
            && $grounded
            && $practical_lift
            && $recognition;
    }

    private function social_variant_focus_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $lead_window = trim(sanitize_text_field($hook . ' ' . $early_caption));
        if ($lead_window === '') {
            return false;
        }

        $separator_count = preg_match_all('/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/i', $lead_window);
        $promise_hit_count = count(array_filter([
            $this->social_variant_pain_point_signal($variant),
            $this->social_variant_payoff_signal($variant),
            $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_curiosity_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_contrast_signal($variant),
        ]));

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $focused_overlap = $article_context !== '' && $this->shared_words_ratio($lead_window, $article_context) >= 0.18;

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $this->front_loaded_click_signal_score($hook, $content_type) >= 0
            && str_word_count($hook) <= 13
            && str_word_count($early_caption !== '' ? $early_caption : $hook) <= 24
            && (int) $separator_count <= 3
            && $promise_hit_count <= 4
            && $focused_overlap;
    }

    private function social_variant_promise_sync_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $lead_window = trim(sanitize_text_field($hook . ' ' . $early_caption));
        if ($lead_window === '') {
            return false;
        }

        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $title_overlap = $article_title !== '' ? $this->shared_words_ratio($lead_window, $article_title) : 0.0;
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $signal_overlap = $article_context !== '' ? $this->shared_words_ratio($lead_window, $article_context) : 0.0;
        $promise_hit =
            $this->social_variant_pain_point_signal($variant)
            || $this->social_variant_payoff_signal($variant)
            || $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type);

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $this->front_loaded_click_signal_score($hook !== '' ? $hook : $early_caption, $content_type) > 0
            && $normalized_hook !== ''
            && $normalized_hook !== $normalized_title
            && ($title_overlap >= 0.12 || $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt))
            && ($signal_overlap >= 0.14 || $promise_hit);
    }

    private function social_variant_two_step_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        if (count($lines) < 2) {
            return false;
        }

        $line1 = (string) ($lines[0] ?? '');
        $line2 = (string) ($lines[1] ?? '');
        $line1_words = str_word_count($line1);
        $line2_words = str_word_count($line2);
        $line_overlap = max(
            $this->shared_words_ratio($line1, $line2),
            $this->shared_words_ratio($line2, $line1)
        );
        $line1_start = sanitize_title(implode(' ', array_slice(preg_split('/\s+/', strtolower($line1)) ?: [], 0, 2)));
        $line2_start = sanitize_title(implode(' ', array_slice(preg_split('/\s+/', strtolower($line2)) ?: [], 0, 2)));
        $line1_variant = ['hook' => '', 'caption' => $line1];
        $line2_variant = ['hook' => '', 'caption' => $line2];
        $line1_problem_clue =
            $this->social_variant_pain_point_signal($line1_variant)
            || $this->social_variant_proof_signal($line1_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_curiosity_signal($line1_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_contrast_signal($line1_variant)
            || $this->front_loaded_click_signal_score($line1, $content_type) > 0;
        $line1_payoff = $this->social_variant_payoff_signal($line1_variant);
        $line2_use_or_result =
            $this->social_variant_payoff_signal($line2_variant)
            || $this->social_variant_actionability_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_proof_signal($line2_variant, $article_title, $article_excerpt, $content_type);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $line2_distinct_enough =
            $this->social_variant_specificity_score($line2_variant, $article_title, $article_excerpt, $content_type) >= 1
            || ($article_context !== '' && $this->shared_words_ratio($line2, $article_context) >= 0.16);
        $complementary_flow =
            ($line1_problem_clue && $line2_use_or_result)
            || ($line1_payoff && (
                $this->social_variant_proof_signal($line2_variant, $article_title, $article_excerpt, $content_type)
                || $this->social_variant_actionability_signal($line2_variant, $article_title, $article_excerpt, $content_type)
                || $this->social_variant_consequence_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            ));

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $line1_words >= 4
            && $line1_words <= 14
            && $line2_words >= 4
            && $line2_words <= 16
            && preg_match('/^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here\'?s why)\b/i', $line1) !== 1
            && preg_match('/^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here\'?s why)\b/i', $line2) !== 1
            && $line_overlap <= 0.72
            && $line1_start !== ''
            && $line1_start !== $line2_start
            && $line2_distinct_enough
            && $complementary_flow;
    }

    private function social_variant_scannability_signal(array $variant, string $content_type = 'recipe'): bool
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $lines = array_slice($lines, 0, 4);
        if (count($lines) < 3) {
            return false;
        }

        $line_word_counts = array_map(static fn (string $line): int => str_word_count($line), $lines);
        $short_lines = count(array_filter($line_word_counts, static fn (int $count): bool => $count >= 3 && $count <= 12));
        $line_starts = array_values(array_filter(array_map(static function (string $line): string {
            $parts = preg_split('/\s+/', strtolower($line)) ?: [];
            return sanitize_title(implode(' ', array_slice($parts, 0, 2)));
        }, $lines)));
        $unique_starts = array_unique($line_starts);
        $repeated_adjacent = false;
        for ($index = 1; $index < count($lines); $index++) {
            $previous = (string) ($lines[$index - 1] ?? '');
            $current = (string) ($lines[$index] ?? '');
            if (max($this->shared_words_ratio($current, $previous), $this->shared_words_ratio($previous, $current)) >= 0.72) {
                $repeated_adjacent = true;
                break;
            }
        }
        $overloaded_lines = count(array_filter($lines, static fn (string $line): bool => preg_match('/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/i', $line) === 1));
        $front_loaded_lines = count(array_filter($lines, fn (string $line): bool => $this->front_loaded_click_signal_score($line, $content_type) > 0));

        return $short_lines >= 2
            && count($unique_starts) >= min(count($lines), 3)
            && !$repeated_adjacent
            && $overloaded_lines <= 1
            && $front_loaded_lines >= 1;
    }

    private function social_variant_generic_penalty(array $variant): int
    {
        $hook = strtolower(sanitize_text_field((string) ($variant['hook'] ?? '')));
        $caption = strtolower(sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        $patterns = [
            '/\byou need to try\b/i',
            '/\byou should\b/i',
            '/\bmust try\b/i',
            '/\bthis is\b/i',
            '/\bthis one\b/i',
            '/\bthese are\b/i',
            '/\bhere\'?s why\b/i',
            '/\bso good\b/i',
            '/\bbest ever\b/i',
            '/\byou won\'?t believe\b/i',
            '/\bi\'m obsessed\b/i',
            '/\bgame changer\b/i',
            '/\breal cooks\b/i',
            '/\bgood cooks know\b/i',
            '/\bsmart cooks\b/i',
            '/\bserious cooks\b/i',
            '/\bpeople who know better\b/i',
            '/\bif you know what you\'re doing\b/i',
            '/\bamateurs?\b/i',
            '/\brookie move\b/i',
            '/\blazy cooks\b/i',
            '/\bthis one is everything\b/i',
            '/\btotal winner\b/i',
            '/\bwhat happens next\b/i',
            '/\bnobody tells you\b/i',
            '/\bno one tells you\b/i',
            '/\bwhat they don\'?t tell you\b/i',
            '/\bthe secret(?: to)?\b/i',
            '/\bfinally revealed\b/i',
            '/\byou(?:\'ll| will) never guess\b/i',
            '/\bhidden truth\b/i',
        ];

        $penalty = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $hook) === 1) {
                $penalty += 6;
            }
            if (preg_match($pattern, $caption) === 1) {
                $penalty += 4;
            }
        }

        return $penalty;
    }

    private function classify_social_hook_form(array $variant): string
    {
        $hook = strtolower(sanitize_text_field((string) ($variant['hook'] ?? '')));
        if ($hook === '') {
            return '';
        }
        if (preg_match('/^\d+\b/', $hook) === 1) {
            return 'numbered';
        }
        if (strpos($hook, '?') !== false || preg_match('/^(why|how|what|when|which)\b/', $hook) === 1) {
            return 'question';
        }
        if (preg_match('/\b(instead of|rather than|not just|not the|what most people|get wrong|vs\.?|versus)\b/', $hook) === 1) {
            return 'contrast';
        }
        if (preg_match('/^(stop|avoid|fix|skip|quit|never)\b/', $hook) === 1 || preg_match('/\b(mistake|wrong|avoid|fix)\b/', $hook) === 1) {
            return 'correction';
        }
        if (preg_match('/^(save|make|keep|use|try|cook|shop)\b/', $hook) === 1) {
            return 'directive';
        }
        if (preg_match('/\b(faster|easier|better|crispy|creamy|juicy|budget|weeknight|shortcut|payoff|result)\b/', $hook) === 1) {
            return 'payoff';
        }
        if (preg_match('/\b(problem|waste|stuck|mistake|harder|overpay|dry|soggy|flat)\b/', $hook) === 1) {
            return 'problem';
        }

        return 'statement';
    }

    private function social_variant_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $hook_words = str_word_count($hook);
        $caption_words = str_word_count(wp_strip_all_tags($caption));
        $caption_lines = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $angle_key = $this->normalize_hook_angle_key((string) ($variant['angle_key'] ?? $variant['angleKey'] ?? ''), $content_type);
        $overlap = $this->shared_words_ratio($hook, $article_title);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $context_overlap = $article_context !== '' ? $this->shared_words_ratio($hook . ' ' . wp_strip_all_tags($caption), $article_context) : 0.0;
        $specificity_score = $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type);
        $anchor_score = $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt) ? 2 : 0;
        $relatability_score = $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $recognition_score = $this->social_variant_self_recognition_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $conversation_score = $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $savvy_score = $this->social_variant_savvy_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $identity_shift_score = $this->social_variant_identity_shift_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $novelty_score = $this->social_variant_novelty_score($variant, $article_title, $article_excerpt, $content_type);
        $pain_point_score = $this->social_variant_pain_point_signal($variant) ? 2 : 0;
        $payoff_score = $this->social_variant_payoff_signal($variant) ? 2 : 0;
        $proof_score = $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $actionability_score = $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $immediacy_score = $this->social_variant_immediacy_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $consequence_score = $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $habit_shift_score = $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $focus_score = $this->social_variant_focus_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $promise_sync_score = $this->social_variant_promise_sync_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $scannability_score = $this->social_variant_scannability_signal($variant, $content_type) ? 1 : 0;
        $two_step_score = $this->social_variant_two_step_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $curiosity_score = $this->social_variant_curiosity_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $contrast_score = $this->social_variant_contrast_signal($variant) ? 1 : 0;
        $resolution_score = $this->social_variant_resolves_early($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $hook_front_load_score = $this->front_loaded_click_signal_score($hook, $content_type);
        $score = 0;

        if ($angle_key !== '') {
            $score += 4;
        }
        $score += ($hook_words >= 6 && $hook_words <= 11) ? 6 : 3;
        $score += ($caption_words >= 22 && $caption_words <= 55) ? 5 : 2;
        $score += ($caption_lines >= 3 && $caption_lines <= 4) ? 4 : 2;
        if ($normalized_title !== '' && $normalized_hook !== $normalized_title) {
            $score += 4;
        }
        if ($overlap <= 0.45) {
            $score += 3;
        } elseif ($overlap >= 0.8) {
            $score -= 5;
        }
        if ($article_context !== '') {
            if ($context_overlap >= 0.16 && $context_overlap <= 0.7) {
                $score += 4;
            } elseif ($context_overlap >= 0.08) {
                $score += 2;
            } elseif ($context_overlap === 0.0) {
                $score -= 2;
            }
        }

        $score += $specificity_score;
        $score += $anchor_score;
        $score += $relatability_score;
        $score += $recognition_score;
        $score += $conversation_score;
        $score += $savvy_score;
        $score += $identity_shift_score;
        $score += $novelty_score;
        $score += $pain_point_score;
        $score += $payoff_score;
        $score += $proof_score;
        $score += $actionability_score;
        $score += $immediacy_score;
        $score += $consequence_score;
        $score += $habit_shift_score;
        $score += $focus_score;
        $score += $promise_sync_score;
        $score += $scannability_score;
        $score += $two_step_score;
        $score += $curiosity_score;
        $score += $contrast_score;
        $score += $resolution_score;
        $score += $hook_front_load_score;

        return $score - $this->social_variant_generic_penalty($variant);
    }

    private function extract_opening_paragraph_text(string $content_html): string
    {
        if ($content_html !== '' && preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content_html, $matches)) {
            return sanitize_text_field(wp_strip_all_tags((string) ($matches[1] ?? '')));
        }

        return '';
    }

    private function shared_words_ratio(string $left, string $right): float
    {
        $stop_words = [
            'the', 'a', 'an', 'and', 'or', 'for', 'with', 'your', 'this', 'that', 'from', 'into',
            'about', 'what', 'when', 'why', 'how', 'most', 'more', 'than',
        ];

        $tokenize = static function (string $value) use ($stop_words): array {
            $text = strtolower(remove_accents(sanitize_text_field(wp_strip_all_tags($value))));
            $parts = preg_split('/[^a-z0-9]+/', $text) ?: [];

            return array_values(array_unique(array_filter(array_map(
                static fn ($token): string => trim((string) $token),
                $parts
            ), static fn ($token): bool => $token !== '' && strlen($token) > 2 && !in_array($token, $stop_words, true))));
        };

        $left_tokens = $tokenize($left);
        $right_tokens = $tokenize($right);
        if (empty($left_tokens) || empty($right_tokens)) {
            return 0.0;
        }

        $right_lookup = array_fill_keys($right_tokens, true);
        $shared = 0;
        foreach ($left_tokens as $token) {
            if (isset($right_lookup[$token])) {
                $shared++;
            }
        }

        return $shared / max(1, count($left_tokens));
    }

    private function title_looks_strong(string $title, string $topic = '', string $content_type = 'recipe'): bool
    {
        $text = sanitize_text_field($title);
        $word_count = str_word_count($text);
        if ($text === '' || $word_count < 4 || $word_count > 14) {
            return false;
        }
        if (preg_match('/\b(you won\'?t believe|best ever|game changer|what you need to know|everything you need to know|why everyone is talking about)\b/i', $text) === 1) {
            return false;
        }
        if ($topic !== '' && $this->shared_words_ratio($text, $topic) < 0.15) {
            return false;
        }
        if ($this->front_loaded_click_signal_score($text, $content_type) < 0) {
            return false;
        }

        return true;
    }

    private function excerpt_adds_new_value(string $title, string $excerpt): bool
    {
        $text = sanitize_text_field($excerpt);
        if (str_word_count($text) < 12) {
            return false;
        }

        return $this->shared_words_ratio($text, $title) < 0.82;
    }

    private function opening_paragraph_adds_new_value(string $content_html, string $title, string $excerpt = ''): bool
    {
        $opening = $this->extract_opening_paragraph_text($content_html);
        if (str_word_count($opening) < 16) {
            return false;
        }
        if ($this->shared_words_ratio($opening, $title) >= 0.85) {
            return false;
        }
        if ($excerpt !== '' && $this->shared_words_ratio($opening, $excerpt) >= 0.9) {
            return false;
        }

        return true;
    }

    private function front_loaded_click_signal_score(string $text, string $content_type = 'recipe'): int
    {
        $lead = strtolower(trim(wp_trim_words(sanitize_text_field($text), 5, '')));
        if ($lead === '') {
            return 0;
        }

        $score = 0;
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|truth|myth|actually|really|better|crispy|creamy|budget|weeknight|juicy|quick|simple|get wrong|most people)\b/i', $lead) === 1) {
            $score += 2;
        }
        if ($content_type === 'recipe' && preg_match('/\b(one-pan|sheet pan|air fryer|skillet|cheesy|garlicky|comfort|dinner|takeout)\b/i', $lead) === 1) {
            $score += 1;
        }
        if ($content_type === 'food_fact' && preg_match('/\b(why|how|what|truth|myth|mistake|actually)\b/i', $lead) === 1) {
            $score += 1;
        }
        if (preg_match('/\b\d+\b/', $lead) === 1) {
            $score += 1;
        }
        if (preg_match('/^(you need to|you should|this is|this one|these are|here\'?s why|the best)\b/i', $lead) === 1) {
            $score -= 2;
        }

        return $score;
    }

    private function contrast_click_signal_score(string $text): int
    {
        $normalized = strtolower(sanitize_text_field($text));
        if ($normalized === '') {
            return 0;
        }

        return preg_match('/\b(instead of|rather than|not just|not the|more than|less about|what most people miss|what changes|vs\.?|versus)\b/i', $normalized) === 1 ? 1 : 0;
    }

    private function headline_specificity_score(string $title, string $content_type = 'recipe', string $topic = ''): int
    {
        $text = sanitize_text_field($title);
        $normalized_title = sanitize_title($text);
        $normalized_topic = sanitize_title($topic);
        $words = str_word_count($text);
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 5 && $words <= 13) {
            $score += 3;
        } elseif ($words >= 4 && $words <= 16) {
            $score += 1;
        } else {
            $score -= 2;
        }

        if ($normalized_topic !== '' && $normalized_title !== '' && $normalized_title !== $normalized_topic) {
            $score += 2;
        }

        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|why|how|actually|really|most people|get wrong)\b/i', $text) === 1) {
            $score += 3;
        }
        if (preg_match('/\b(one-pan|weeknight|crispy|creamy|cheesy|garlicky|juicy|budget|air fryer|oven|skillet|better than takeout)\b/i', $text) === 1) {
            $score += 2;
        }
        if (preg_match('/\b\d+\b/', $text) === 1) {
            $score += 1;
        }
        $score += $this->front_loaded_click_signal_score($text, $content_type);
        $score += $this->contrast_click_signal_score($text);
        if (strpos($text, '?') !== false) {
            $score -= 1;
        }
        if (preg_match('/\b(recipe|guide|tips|ideas|facts|article)\b/i', $text) === 1 && $words <= 6) {
            $score -= 2;
        }
        if ($content_type === 'food_fact' && $normalized_topic !== '' && $normalized_title === $normalized_topic) {
            $score -= 2;
        }

        return $score;
    }

    private function opening_promise_alignment_score(string $title, string $opening_paragraph): int
    {
        $title_text = sanitize_text_field($title);
        $opening_text = sanitize_text_field($opening_paragraph);
        if ($title_text === '' || $opening_text === '') {
            return 0;
        }

        $overlap = $this->shared_words_ratio($title_text, $opening_text);
        $score = 0;
        if ($overlap >= 0.24) {
            $score += 3;
        } elseif ($overlap >= 0.14) {
            $score += 2;
        } elseif ($overlap >= 0.08) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|payoff|problem|why|how)\b/i', $opening_text) === 1) {
            $score += 1;
        }
        if ($this->front_loaded_click_signal_score($opening_text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($opening_text);

        return $score;
    }

    private function excerpt_click_signal_score(string $excerpt, string $title = '', string $opening_paragraph = ''): int
    {
        $text = sanitize_text_field($excerpt);
        $words = str_word_count($text);
        $title_overlap = $this->shared_words_ratio($text, $title);
        $opening_overlap = $opening_paragraph !== '' ? $this->shared_words_ratio($text, $opening_paragraph) : 0;
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 12 && $words <= 30) {
            $score += 2;
        } elseif ($words >= 10 && $words <= 36) {
            $score += 1;
        }
        if ($title_overlap <= 0.72) {
            $score += 2;
        } elseif ($title_overlap >= 0.9) {
            $score -= 2;
        }
        if ($opening_paragraph !== '' && $opening_overlap >= 0.08 && $opening_overlap <= 0.7) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i', $text) === 1) {
            $score += 2;
        }
        if ($this->front_loaded_click_signal_score($text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($text);

        return $score;
    }

    private function seo_description_signal_score(string $seo_description, string $title = '', string $excerpt = ''): int
    {
        $text = sanitize_text_field($seo_description);
        $words = str_word_count($text);
        $title_overlap = $this->shared_words_ratio($text, $title);
        $excerpt_overlap = $excerpt !== '' ? $this->shared_words_ratio($text, $excerpt) : 0;
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 12 && $words <= 28) {
            $score += 2;
        } elseif ($words >= 10 && $words <= 32) {
            $score += 1;
        }
        if ($title_overlap <= 0.72) {
            $score += 2;
        } elseif ($title_overlap >= 0.9) {
            $score -= 2;
        }
        if ($excerpt !== '' && $excerpt_overlap >= 0.08 && $excerpt_overlap <= 0.8) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i', $text) === 1) {
            $score += 2;
        }
        if ($this->front_loaded_click_signal_score($text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($text);

        return $score;
    }

    private function quality_failed_check_messages(): array
    {
        return [
            'missing_core_fields'        => __('Missing title, slug, or article body.', 'kuchnia-twist'),
            'missing_recipe'             => __('Missing recipe ingredients or instructions.', 'kuchnia-twist'),
            'missing_manual_images'      => __('Manual-only mode requires both blog and Facebook images.', 'kuchnia-twist'),
            'duplicate_conflict'         => __('A duplicate title or slug conflict was detected.', 'kuchnia-twist'),
            'missing_target_pages'       => __('At least one target Facebook page is required.', 'kuchnia-twist'),
            'thin_content'               => __('The article body is too thin for launch quality.', 'kuchnia-twist'),
            'weak_title'                 => __('The title is too generic to carry a strong click promise.', 'kuchnia-twist'),
            'weak_excerpt'               => __('The excerpt is too weak, repetitive, or slow to surface a concrete reason to click.', 'kuchnia-twist'),
            'weak_seo'                   => __('The SEO description is too weak, repetitive, or buries the concrete click reason too late.', 'kuchnia-twist'),
            'weak_title_alignment'       => __('Page 1 does not cash the title promise quickly enough with a concrete answer, problem, or payoff.', 'kuchnia-twist'),
            'weak_pagination'            => __('The article should be split into 2 or 3 strong pages.', 'kuchnia-twist'),
            'weak_page_balance'          => __('One article page is too thin to feel intentional.', 'kuchnia-twist'),
            'weak_page_openings'         => __('One article page opens weakly instead of feeling like a deliberate new page.', 'kuchnia-twist'),
            'weak_page_flow'             => __('The generated page flow is missing a clear label or summary for one of the article pages.', 'kuchnia-twist'),
            'weak_page_labels'           => __('The page labels are too generic to feel like real chapter navigation.', 'kuchnia-twist'),
            'repetitive_page_labels'     => __('The page labels are too repetitive.', 'kuchnia-twist'),
            'weak_page_summaries'        => __('The page summaries are too thin to make the next click feel worthwhile.', 'kuchnia-twist'),
            'weak_structure'             => __('The article needs more H2 structure.', 'kuchnia-twist'),
            'missing_internal_links'     => __('The article needs more internal links.', 'kuchnia-twist'),
            'package_contract_drift'     => __('This new typed job is relying on legacy article fields because the canonical content package is incomplete or malformed.', 'kuchnia-twist'),
            'facebook_adapter_contract_drift' => __('This new typed job is relying on legacy Facebook fields because the Facebook adapter payload is incomplete or malformed.', 'kuchnia-twist'),
            'facebook_groups_adapter_contract_drift' => __('This new typed job is missing a clean Facebook group manual-share adapter payload.', 'kuchnia-twist'),
            'pinterest_adapter_contract_drift' => __('This new typed job is missing a clean Pinterest draft adapter payload.', 'kuchnia-twist'),
            'social_pack_incomplete'     => __('The Facebook social pack does not cover all selected pages.', 'kuchnia-twist'),
            'social_pack_repetitive'     => __('The Facebook social pack is too repetitive.', 'kuchnia-twist'),
            'social_hooks_repetitive'    => __('The Facebook hooks are too repetitive across selected pages.', 'kuchnia-twist'),
            'social_openings_repetitive' => __('The Facebook caption openings are too repetitive across selected pages.', 'kuchnia-twist'),
            'social_angles_repetitive'   => __('The Facebook angle mix is too repetitive across selected pages.', 'kuchnia-twist'),
            'social_hook_forms_thin'     => __('The selected Facebook pack reuses too many of the same hook shapes instead of varying the sentence pattern.', 'kuchnia-twist'),
            'weak_social_copy'           => __('The Facebook hooks or captions are too weak for publish.', 'kuchnia-twist'),
            'weak_social_lead'           => __('The lead Facebook variant is not strong, specific, concrete, or front-loaded enough to carry the first click opportunity.', 'kuchnia-twist'),
            'social_specificity_thin'    => __('Too few selected Facebook variants feel concrete and article-specific.', 'kuchnia-twist'),
            'social_anchor_thin'         => __('Too few selected Facebook variants name a concrete dish, ingredient, mistake, method, or topic.', 'kuchnia-twist'),
            'social_novelty_thin'        => __('Too few selected Facebook variants add a concrete new detail beyond the article title.', 'kuchnia-twist'),
            'social_relatability_thin'   => __('Too few selected Facebook variants frame a recognizable real-life kitchen moment.', 'kuchnia-twist'),
            'social_recognition_thin'    => __('Too few selected Facebook variants create a direct self-recognition moment around a repeated kitchen result or mistake.', 'kuchnia-twist'),
            'social_conversation_thin'   => __('Too few selected Facebook variants feel naturally discussable through a real household habit, shopping split, or recognizable choice.', 'kuchnia-twist'),
            'social_savvy_thin'          => __('Too few selected Facebook variants make the reader feel they are about to make a smarter kitchen or shopping move.', 'kuchnia-twist'),
            'social_identity_shift_thin' => __('Too few selected Facebook variants make the reader feel they are leaving behind the old default move for a better one.', 'kuchnia-twist'),
            'social_proof_thin'          => __('Too few selected Facebook variants carry a believable concrete clue or proof early.', 'kuchnia-twist'),
            'social_actionability_thin'  => __('Too few selected Facebook variants make the next move or practical use feel obvious.', 'kuchnia-twist'),
            'social_immediacy_thin'      => __('Too few selected Facebook variants make the article feel relevant to the reader\'s next cook, shop, order, or weeknight decision.', 'kuchnia-twist'),
            'social_front_load_thin'     => __('Too few selected Facebook variants surface the concrete problem or payoff in the first words.', 'kuchnia-twist'),
            'social_curiosity_thin'      => __('Too few selected Facebook variants create honest curiosity with a concrete clue.', 'kuchnia-twist'),
            'social_resolution_thin'     => __('Too few selected Facebook variants resolve the hook with a concrete clue in the first caption lines.', 'kuchnia-twist'),
            'social_contrast_thin'       => __('Too few selected Facebook variants use a clean expectation-vs-reality or mistake-vs-fix contrast.', 'kuchnia-twist'),
            'social_pain_points_thin'    => __('Too few selected Facebook variants frame a clear problem, mistake, or shortcut.', 'kuchnia-twist'),
            'social_payoffs_thin'        => __('Too few selected Facebook variants frame a clear payoff or result.', 'kuchnia-twist'),
            'social_consequence_thin'    => __('Too few selected Facebook variants make the cost, waste, or repeated mistake feel concrete.', 'kuchnia-twist'),
            'social_habit_shift_thin'    => __('Too few selected Facebook variants create a clear old-habit-versus-better-result shift.', 'kuchnia-twist'),
            'social_focus_thin'          => __('Too few selected Facebook variants stay centered on one clean dominant promise.', 'kuchnia-twist'),
            'social_promise_sync_thin'   => __('Too few selected Facebook variants line up cleanly with the article title and page-one promise without echoing the headline.', 'kuchnia-twist'),
            'social_scannability_thin'   => __('Too few selected Facebook variants stay easy to scan in short distinct caption lines.', 'kuchnia-twist'),
            'social_two_step_thin'       => __('Too few selected Facebook variants make caption line 1 and line 2 do distinct useful jobs instead of repeating the same idea.', 'kuchnia-twist'),
            'image_not_ready'            => __('The required image slots are not ready yet.', 'kuchnia-twist'),
        ];
    }

    private function build_job_quality_summary(array $job, array $generated, array $overrides = []): array
    {
        $settings        = $this->get_settings();
        $content_package = $this->normalized_generated_content_package($generated, $job);
        $channels        = $this->generated_channels($generated, $job);
        $facebook_channel = is_array($channels['facebook'] ?? null) ? $channels['facebook'] : [];
        $content_type    = sanitize_key((string) ($overrides['content_type'] ?? $content_package['content_type'] ?? $generated['content_type'] ?? $job['content_type'] ?? 'recipe'));
        $title           = sanitize_text_field((string) ($overrides['title'] ?? $content_package['title'] ?? $generated['title'] ?? ''));
        $slug            = sanitize_title((string) ($overrides['slug'] ?? $content_package['slug'] ?? $generated['slug'] ?? ''));
        $excerpt         = sanitize_text_field((string) ($overrides['excerpt'] ?? $content_package['excerpt'] ?? $generated['excerpt'] ?? ''));
        $seo_description = sanitize_text_field((string) ($overrides['seo_description'] ?? $content_package['seo_description'] ?? $generated['seo_description'] ?? ''));
        $content_html    = (string) ($overrides['content_html'] ?? $content_package['content_html'] ?? $generated['content_html'] ?? '');
        $content_pages   = is_array($content_package['content_pages'] ?? null) ? $content_package['content_pages'] : [];
        $selected_pages  = $this->job_selected_pages($job);
        $social_candidates = is_array($facebook_channel['candidates'] ?? null) ? $facebook_channel['candidates'] : [];
        $social_pack     = is_array($facebook_channel['selected'] ?? null) ? $facebook_channel['selected'] : [];
        $recipe          = is_array($content_package['recipe'] ?? null) ? $content_package['recipe'] : [];
        $featured_image  = isset($overrides['featured_image_id']) ? (int) $overrides['featured_image_id'] : (int) ($job['featured_image_id'] ?: $job['blog_image_id']);
        $facebook_image  = isset($overrides['facebook_image_id']) ? (int) $overrides['facebook_image_id'] : (int) ($job['facebook_image_result_id'] ?: $job['facebook_image_id'] ?: $featured_image);
        $minimum_words   = [
            'recipe'     => 1200,
            'food_fact'  => 1100,
            'food_story' => 1100,
        ][$content_type] ?? 1100;
        $contract_meta = $this->resolve_contract_job_flags($generated);
        $typed_contract_job = !empty($contract_meta['typed_contract_job']);
        $contract_checks = $typed_contract_job
            ? $this->canonical_contract_checks($generated, $job, count($selected_pages))
            : [
                'package_contract_enforced' => false,
                'channel_contract_enforced' => false,
                'warning_checks' => [],
            ];
        $strict_contract_mode = ($settings['strict_contract_mode'] ?? '0') === '1';
        $contract_blocking_checks = ($strict_contract_mode && $typed_contract_job)
            ? array_values(array_filter($contract_checks['warning_checks'], static fn (string $check): bool => str_ends_with($check, '_contract_drift')))
            : [];
        if (empty($content_pages) && $content_html !== '') {
            $content_pages = array_values(array_filter(preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: []));
        }
        $page_flow = $this->normalize_generated_page_flow(
            is_array($content_package['page_flow'] ?? null) ? $content_package['page_flow'] : [],
            $content_pages
        );
        $page_word_counts = array_values(array_filter(array_map(
            static fn ($page): int => str_word_count(wp_strip_all_tags((string) $page)),
            $content_pages
        )));
        $page_count      = !empty($content_pages) ? count($content_pages) : 1;
        $shortest_page_words = !empty($page_word_counts) ? min($page_word_counts) : 0;
        $strong_page_openings = 0;
        foreach ($content_pages as $page_index => $page_html) {
            if ($this->page_starts_with_expected_lead((string) $page_html, (int) $page_index)) {
                $strong_page_openings++;
            }
        }
        $page_label_fingerprints = array_values(array_filter(array_map(
            fn ($page): string => $this->normalize_page_flow_label_fingerprint((string) ((is_array($page) ? ($page['label'] ?? '') : ''))),
            $page_flow
        )));
        $unique_page_labels = count(array_unique($page_label_fingerprints));
        $strong_page_labels = count(array_filter($page_flow, fn ($page): bool => is_array($page) && $this->page_flow_label_looks_strong((string) ($page['label'] ?? ''), (int) ($page['index'] ?? 0))));
        $strong_page_summaries = count(array_filter($page_flow, fn ($page): bool => is_array($page) && $this->page_flow_summary_looks_strong((string) ($page['summary'] ?? ''), (string) ($page['label'] ?? ''))));
        $word_count      = str_word_count(wp_strip_all_tags($content_html));
        $h2_count        = substr_count(strtolower($content_html), '<h2');
        $internal_links  = $this->count_internal_links($content_html);
        $excerpt_words   = str_word_count($excerpt);
        $seo_words       = str_word_count($seo_description);
        $opening_paragraph = $this->extract_opening_paragraph_text($content_html);
        $title_score = $this->headline_specificity_score($title, $content_type, (string) ($job['topic'] ?? ''));
        $title_strong = $this->title_looks_strong($title, (string) ($job['topic'] ?? ''), $content_type);
        $title_front_load_score = $this->front_loaded_click_signal_score($title, $content_type);
        $excerpt_front_load_score = $this->front_loaded_click_signal_score($excerpt, $content_type);
        $seo_front_load_score = $this->front_loaded_click_signal_score($seo_description, $content_type);
        $opening_front_load_score = $this->front_loaded_click_signal_score($opening_paragraph, $content_type);
        $opening_alignment_score = $this->opening_promise_alignment_score($title, $opening_paragraph);
        $excerpt_adds_value = $this->excerpt_adds_new_value($title, $excerpt);
        $opening_adds_value = $this->opening_paragraph_adds_new_value($content_html, $title, $excerpt);
        $excerpt_signal_score = $this->excerpt_click_signal_score($excerpt, $title, $opening_paragraph);
        $seo_signal_score = $this->seo_description_signal_score($seo_description, $title, $excerpt);
        $recipe_complete = $content_type !== 'recipe' || (!empty($recipe['ingredients']) && !empty($recipe['instructions']));
        $image_ready     = $settings['image_generation_mode'] === 'manual_only'
            ? ($featured_image > 0 && $facebook_image > 0)
            : ($featured_image > 0 && $facebook_image > 0);
        $target_pages    = count($selected_pages);
        $social_variants = count($social_pack);
        $unique_variants = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_variant_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_hooks = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_hook_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_openings = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalized_social_opening_fingerprint(is_array($variant) ? $variant : []),
            $social_pack
        ))));
        $unique_angles = count(array_unique(array_filter(array_map(
            fn ($variant): string => $this->normalize_hook_angle_key((string) ((is_array($variant) ? ($variant['angle_key'] ?? $variant['angleKey'] ?? '') : '')), $content_type),
            $social_pack
        ))));
        $unique_hook_form_candidates = count(array_unique(array_filter(array_map(
            fn ($variant): string => is_array($variant) ? $this->classify_social_hook_form($variant) : '',
            $social_candidates
        ))));
        $unique_hook_forms = count(array_unique(array_filter(array_map(
            fn ($variant): string => is_array($variant) ? $this->classify_social_hook_form($variant) : '',
            $social_pack
        ))));
        $social_pool_size = count(array_filter($social_candidates, 'is_array'));
        $strong_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && !$this->social_variant_looks_weak($variant, $title, $content_type, $excerpt)));
        $specific_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_specificity_score($variant, $title, $excerpt, $content_type) >= 2));
        $anchored_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_anchor_signal($variant, $title, $excerpt)));
        $novelty_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_novelty_score($variant, $title, $excerpt, $content_type) >= 2));
        $relatable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_relatability_signal($variant, $title, $excerpt, $content_type)));
        $recognition_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_self_recognition_signal($variant, $title, $excerpt, $content_type)));
        $conversation_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_conversation_signal($variant, $title, $excerpt, $content_type)));
        $savvy_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_savvy_signal($variant, $title, $excerpt, $content_type)));
        $identity_shift_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_identity_shift_signal($variant, $title, $excerpt, $content_type)));
        $proof_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_proof_signal($variant, $title, $excerpt, $content_type)));
        $actionable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_actionability_signal($variant, $title, $excerpt, $content_type)));
        $immediacy_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_immediacy_signal($variant, $title, $excerpt, $content_type)));
        $consequence_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_consequence_signal($variant, $title, $excerpt, $content_type)));
        $habit_shift_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_habit_shift_signal($variant, $title, $excerpt, $content_type)));
        $focused_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_focus_signal($variant, $title, $excerpt, $content_type)));
        $promise_sync_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_promise_sync_signal($variant, $title, $excerpt, $content_type)));
        $scannable_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_scannability_signal($variant, $content_type)));
        $two_step_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_two_step_signal($variant, $title, $excerpt, $content_type)));
        $front_loaded_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_front_loaded_signal($variant, $content_type)));
        $curiosity_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_curiosity_signal($variant, $title, $excerpt, $content_type)));
        $resolution_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_resolves_early($variant, $title, $excerpt, $content_type)));
        $contrast_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_contrast_signal($variant)));
        $pain_point_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_pain_point_signal($variant)));
        $payoff_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_payoff_signal($variant)));
        $high_scoring_social_candidates = count(array_filter($social_candidates, fn ($variant): bool => is_array($variant) && $this->social_variant_score($variant, $title, $excerpt, $content_type) >= 18));
        $strong_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && !$this->social_variant_looks_weak($variant, $title, $content_type, $excerpt)));
        $specific_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_specificity_score($variant, $title, $excerpt, $content_type) >= 2));
        $anchored_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_anchor_signal($variant, $title, $excerpt)));
        $novelty_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_novelty_score($variant, $title, $excerpt, $content_type) >= 2));
        $relatable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_relatability_signal($variant, $title, $excerpt, $content_type)));
        $recognition_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_self_recognition_signal($variant, $title, $excerpt, $content_type)));
        $conversation_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_conversation_signal($variant, $title, $excerpt, $content_type)));
        $savvy_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_savvy_signal($variant, $title, $excerpt, $content_type)));
        $identity_shift_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_identity_shift_signal($variant, $title, $excerpt, $content_type)));
        $proof_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_proof_signal($variant, $title, $excerpt, $content_type)));
        $actionable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_actionability_signal($variant, $title, $excerpt, $content_type)));
        $immediacy_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_immediacy_signal($variant, $title, $excerpt, $content_type)));
        $consequence_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_consequence_signal($variant, $title, $excerpt, $content_type)));
        $habit_shift_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_habit_shift_signal($variant, $title, $excerpt, $content_type)));
        $focused_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_focus_signal($variant, $title, $excerpt, $content_type)));
        $promise_sync_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_promise_sync_signal($variant, $title, $excerpt, $content_type)));
        $scannable_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_scannability_signal($variant, $content_type)));
        $two_step_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_two_step_signal($variant, $title, $excerpt, $content_type)));
        $curiosity_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_curiosity_signal($variant, $title, $excerpt, $content_type)));
        $resolution_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_resolves_early($variant, $title, $excerpt, $content_type)));
        $contrast_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_contrast_signal($variant)));
        $front_loaded_social_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_front_loaded_signal($variant, $content_type)));
        $pain_point_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_pain_point_signal($variant)));
        $payoff_variants = count(array_filter($social_pack, fn ($variant): bool => is_array($variant) && $this->social_variant_payoff_signal($variant)));
        $selected_social_scores = array_values(array_filter(array_map(
            fn ($variant): ?int => is_array($variant) ? $this->social_variant_score($variant, $title, $excerpt, $content_type) : null,
            $social_pack
        ), static fn ($score): bool => is_numeric($score)));
        $selected_social_average_score = !empty($selected_social_scores)
            ? round(array_sum($selected_social_scores) / max(1, count($selected_social_scores)), 1)
            : 0;
        $lead_variant = !empty($social_pack[0]) && is_array($social_pack[0]) ? $social_pack[0] : [];
        $lead_social_score = !empty($lead_variant) ? $this->social_variant_score($lead_variant, $title, $excerpt, $content_type) : 0;
        $lead_social_hook_form = !empty($lead_variant) ? $this->classify_social_hook_form($lead_variant) : '';
        $lead_social_specific = !empty($lead_variant) && $this->social_variant_specificity_score($lead_variant, $title, $excerpt, $content_type) >= 2;
        $lead_social_anchored = !empty($lead_variant) && $this->social_variant_anchor_signal($lead_variant, $title, $excerpt);
        $lead_social_novelty = !empty($lead_variant) && $this->social_variant_novelty_score($lead_variant, $title, $excerpt, $content_type) >= 2;
        $lead_social_relatable = !empty($lead_variant) && $this->social_variant_relatability_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_recognition = !empty($lead_variant) && $this->social_variant_self_recognition_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_conversation = !empty($lead_variant) && $this->social_variant_conversation_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_savvy = !empty($lead_variant) && $this->social_variant_savvy_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_identity_shift = !empty($lead_variant) && $this->social_variant_identity_shift_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_proof = !empty($lead_variant) && $this->social_variant_proof_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_actionable = !empty($lead_variant) && $this->social_variant_actionability_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_immediacy = !empty($lead_variant) && $this->social_variant_immediacy_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_consequence = !empty($lead_variant) && $this->social_variant_consequence_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_habit_shift = !empty($lead_variant) && $this->social_variant_habit_shift_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_focused = !empty($lead_variant) && $this->social_variant_focus_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_promise_sync = !empty($lead_variant) && $this->social_variant_promise_sync_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_scannable = !empty($lead_variant) && $this->social_variant_scannability_signal($lead_variant, $content_type);
        $lead_social_two_step = !empty($lead_variant) && $this->social_variant_two_step_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_curiosity = !empty($lead_variant) && $this->social_variant_curiosity_signal($lead_variant, $title, $excerpt, $content_type);
        $lead_social_resolved = !empty($lead_variant) && $this->social_variant_resolves_early($lead_variant, $title, $excerpt, $content_type);
        $lead_social_contrast = !empty($lead_variant) && $this->social_variant_contrast_signal($lead_variant);
        $lead_social_front_loaded = !empty($lead_variant) && $this->social_variant_front_loaded_signal($lead_variant, $content_type);
        $lead_social_pain_point = !empty($lead_variant) && $this->social_variant_pain_point_signal($lead_variant);
        $lead_social_payoff = !empty($lead_variant) && $this->social_variant_payoff_signal($lead_variant);
        $duplicate_risk = $title === '' || $slug === ''
            ? false
            : ($this->find_conflicting_post_id($slug, (int) ($job['post_id'] ?? 0)) > 0 || $this->find_conflicting_post_id($title, (int) ($job['post_id'] ?? 0)) > 0);

        $blocking_checks = $contract_blocking_checks;
        $warning_checks = array_values(array_filter($contract_checks['warning_checks'], static fn (string $check): bool => !in_array($check, $contract_blocking_checks, true)));
        if ($title === '' || $slug === '' || trim(wp_strip_all_tags($content_html)) === '') {
            $blocking_checks[] = 'missing_core_fields';
        }
        if ($content_type === 'recipe' && !$recipe_complete) {
            $blocking_checks[] = 'missing_recipe';
        }
        if ($settings['image_generation_mode'] === 'manual_only' && (!$featured_image || !$facebook_image)) {
            $blocking_checks[] = 'missing_manual_images';
        }
        if ($duplicate_risk) {
            $blocking_checks[] = 'duplicate_conflict';
        }
        if ($target_pages < 1) {
            $blocking_checks[] = 'missing_target_pages';
        }
        if ($word_count < $minimum_words) {
            $warning_checks[] = 'thin_content';
        }
        if (!$title_strong || $title_score < 3) {
            $warning_checks[] = 'weak_title';
        }
        if ($excerpt_words < 12 || !$excerpt_adds_value || $excerpt_signal_score < 3) {
            $warning_checks[] = 'weak_excerpt';
        }
        if ($seo_words < 12 || $seo_signal_score < 3) {
            $warning_checks[] = 'weak_seo';
        }
        if ($opening_alignment_score < 2 || !$opening_adds_value) {
            $warning_checks[] = 'weak_title_alignment';
        }
        if ($page_count < 2 || $page_count > 3) {
            $warning_checks[] = 'weak_pagination';
        }
        if ($page_count > 1 && $shortest_page_words > 0 && $shortest_page_words < 140) {
            $warning_checks[] = 'weak_page_balance';
        }
        if ($page_count > 1 && $strong_page_openings < $page_count) {
            $warning_checks[] = 'weak_page_openings';
        }
        if ($page_count > 1 && count($page_flow) < $page_count) {
            $warning_checks[] = 'weak_page_flow';
        }
        if ($page_count > 1 && $strong_page_labels < $page_count) {
            $warning_checks[] = 'weak_page_labels';
        }
        if ($page_count > 1 && $unique_page_labels < $page_count) {
            $warning_checks[] = 'repetitive_page_labels';
        }
        if ($page_count > 1 && $strong_page_summaries < $page_count) {
            $warning_checks[] = 'weak_page_summaries';
        }
        if ($h2_count < 2) {
            $warning_checks[] = 'weak_structure';
        }
        if ($internal_links < 3) {
            $warning_checks[] = 'missing_internal_links';
        }
        if ($social_variants < max(1, $target_pages)) {
            $warning_checks[] = 'social_pack_incomplete';
        }
        if ($social_variants > 0 && $unique_variants < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_pack_repetitive';
        }
        if ($social_variants > 1 && $unique_hooks < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_hooks_repetitive';
        }
        if ($social_variants > 1 && $unique_openings < min($social_variants, max(1, $target_pages))) {
            $warning_checks[] = 'social_openings_repetitive';
        }
        if ($target_pages > 1 && $unique_angles < min($target_pages, count($this->social_angle_presets($content_type)))) {
            $warning_checks[] = 'social_angles_repetitive';
        }
        if ($target_pages > 1 && $unique_hook_forms < max(2, min(3, $target_pages))) {
            $warning_checks[] = 'social_hook_forms_thin';
        }
        if ($strong_social_variants < max(1, $target_pages)) {
            $warning_checks[] = 'weak_social_copy';
        }
        if ($target_pages > 0 && ($lead_social_score < 16 || !$lead_social_specific || !$lead_social_anchored || !$lead_social_novelty || !$lead_social_relatable || !$lead_social_recognition || !$lead_social_focused || !$lead_social_promise_sync || !$lead_social_scannable || !$lead_social_two_step || (($lead_social_curiosity || $lead_social_contrast) && !$lead_social_resolved) || (!$lead_social_pain_point && !$lead_social_payoff && !$lead_social_consequence && !$lead_social_habit_shift && !$lead_social_savvy && !$lead_social_identity_shift) || !$lead_social_front_loaded)) {
            $warning_checks[] = 'weak_social_lead';
        }
        if ($specific_social_variants < max(1, min(max(1, $target_pages), 2))) {
            $warning_checks[] = 'social_specificity_thin';
        }
        if ($target_pages > 0 && $anchored_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_anchor_thin';
        }
        if ($target_pages > 0 && $novelty_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_novelty_thin';
        }
        if ($target_pages > 1 && $relatable_variants < 1) {
            $warning_checks[] = 'social_relatability_thin';
        }
        if ($target_pages > 1 && $recognition_variants < 1) {
            $warning_checks[] = 'social_recognition_thin';
        }
        if ($target_pages > 1 && $conversation_variants < 1) {
            $warning_checks[] = 'social_conversation_thin';
        }
        if ($target_pages > 1 && $savvy_variants < 1) {
            $warning_checks[] = 'social_savvy_thin';
        }
        if ($target_pages > 1 && $identity_shift_variants < 1) {
            $warning_checks[] = 'social_identity_shift_thin';
        }
        if ($target_pages > 0 && $front_loaded_social_variants < max(1, min($target_pages, 2))) {
            $warning_checks[] = 'social_front_load_thin';
        }
        if ($target_pages > 1 && $curiosity_variants < 1) {
            $warning_checks[] = 'social_curiosity_thin';
        }
        if ($target_pages > 1 && $resolution_variants < 1) {
            $warning_checks[] = 'social_resolution_thin';
        }
        if ($target_pages > 1 && $contrast_variants < 1) {
            $warning_checks[] = 'social_contrast_thin';
        }
        if ($target_pages > 1 && $pain_point_variants < 1) {
            $warning_checks[] = 'social_pain_points_thin';
        }
        if ($target_pages > 1 && $payoff_variants < 1) {
            $warning_checks[] = 'social_payoffs_thin';
        }
        if ($target_pages > 1 && $proof_variants < 1) {
            $warning_checks[] = 'social_proof_thin';
        }
        if ($target_pages > 1 && $actionable_variants < 1) {
            $warning_checks[] = 'social_actionability_thin';
        }
        if ($target_pages > 1 && $immediacy_variants < 1) {
            $warning_checks[] = 'social_immediacy_thin';
        }
        if ($target_pages > 1 && $consequence_variants < 1) {
            $warning_checks[] = 'social_consequence_thin';
        }
        if ($target_pages > 1 && $habit_shift_variants < 1) {
            $warning_checks[] = 'social_habit_shift_thin';
        }
        if ($target_pages > 1 && $focused_variants < 1) {
            $warning_checks[] = 'social_focus_thin';
        }
        if ($target_pages > 1 && $promise_sync_variants < 1) {
            $warning_checks[] = 'social_promise_sync_thin';
        }
        if ($target_pages > 1 && $scannable_variants < 1) {
            $warning_checks[] = 'social_scannability_thin';
        }
        if ($target_pages > 1 && $two_step_variants < 1) {
            $warning_checks[] = 'social_two_step_thin';
        }
        if (!$image_ready) {
            $warning_checks[] = 'image_not_ready';
        }

        $score = 100;
        $penalties = [
            'missing_core_fields'    => 35,
            'missing_recipe'         => 25,
            'missing_manual_images'  => 20,
            'duplicate_conflict'     => 30,
            'missing_target_pages'   => 25,
            'thin_content'           => 15,
            'weak_title'             => 8,
            'weak_excerpt'           => 8,
            'weak_seo'               => 8,
            'weak_title_alignment'   => 7,
            'weak_pagination'        => 8,
            'weak_page_balance'      => 7,
            'weak_page_openings'     => 6,
            'weak_page_flow'         => 6,
            'weak_page_labels'       => 5,
            'repetitive_page_labels' => 5,
            'weak_page_summaries'    => 5,
            'weak_structure'         => 10,
            'missing_internal_links' => 9,
            'social_pack_incomplete' => 12,
            'social_pack_repetitive' => 10,
            'social_hooks_repetitive' => 8,
            'social_openings_repetitive' => 8,
            'social_angles_repetitive' => 8,
            'social_hook_forms_thin' => 5,
            'weak_social_copy'        => 10,
            'weak_social_lead'       => 8,
            'social_specificity_thin' => 8,
            'social_anchor_thin' => 7,
            'social_novelty_thin' => 7,
            'social_relatability_thin' => 6,
            'social_recognition_thin' => 6,
            'social_conversation_thin' => 6,
            'social_savvy_thin' => 6,
            'social_identity_shift_thin' => 6,
            'social_proof_thin' => 6,
            'social_actionability_thin' => 6,
            'social_immediacy_thin' => 6,
            'social_consequence_thin' => 6,
            'social_habit_shift_thin' => 6,
            'social_focus_thin' => 6,
            'social_promise_sync_thin' => 6,
            'social_scannability_thin' => 6,
            'social_two_step_thin' => 6,
            'social_front_load_thin' => 7,
            'social_curiosity_thin' => 6,
            'social_resolution_thin' => 6,
            'social_contrast_thin' => 6,
            'social_pain_points_thin' => 6,
            'social_payoffs_thin'   => 6,
            'image_not_ready'        => 8,
            'package_contract_drift' => 6,
            'facebook_adapter_contract_drift' => 5,
            'facebook_groups_adapter_contract_drift' => 3,
            'pinterest_adapter_contract_drift' => 3,
        ];
        foreach (array_merge($blocking_checks, $warning_checks) as $failed_check) {
            $score -= (int) ($penalties[$failed_check] ?? 0);
        }
        $score = max(0, $score);
        $blocking_checks = array_values(array_unique($blocking_checks));
        $warning_checks = array_values(array_unique($warning_checks));
        $failed_checks = array_values(array_unique(array_merge($blocking_checks, $warning_checks)));
        $quality_status = !empty($blocking_checks)
            ? 'block'
            : ((!empty($warning_checks) || $score < self::QUALITY_SCORE_THRESHOLD) ? 'warn' : 'pass');
        $editorial_summary = $this->build_editorial_readiness_summary([
            'quality_status' => $quality_status,
            'quality_score' => $score,
            'title_strong' => $title_strong,
            'opening_alignment_score' => $opening_alignment_score,
            'page_count' => $page_count,
            'strong_page_openings' => $strong_page_openings,
            'strong_page_summaries' => $strong_page_summaries,
            'target_pages' => $target_pages,
            'strong_social_variants' => $strong_social_variants,
            'lead_social_score' => $lead_social_score,
            'lead_social_specific' => $lead_social_specific,
            'lead_social_front_loaded' => $lead_social_front_loaded,
            'lead_social_promise_sync' => $lead_social_promise_sync,
            'blocking_checks' => $blocking_checks,
            'warning_checks' => $warning_checks,
        ]);

        return [
            'quality_score'   => $score,
            'quality_status'  => $quality_status,
            'blocking_checks' => $blocking_checks,
            'warning_checks'  => $warning_checks,
            'failed_checks'   => $failed_checks,
            'package_quality' => [
                'layer' => 'article',
                'contract_version' => sanitize_text_field((string) ($content_package['contract_version'] ?? ($this->generated_contract_versions($generated)['content_package'] ?? ''))),
                'contract_enforced' => !empty($contract_checks['package_contract_enforced']),
                'contract_warning' => in_array('package_contract_drift', $warning_checks, true),
                'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'package_contract_drift')),
                'stage_status' => sanitize_key((string) ($content_package['quality_summary']['stage_status'] ?? '')),
                'stage_checks' => is_array($content_package['quality_summary']['stage_checks'] ?? null) ? $content_package['quality_summary']['stage_checks'] : [],
                'editorial_readiness' => sanitize_key((string) ($content_package['quality_summary']['editorial_readiness'] ?? $editorial_summary['editorial_readiness'])),
            ],
            'channel_quality' => [
                'facebook' => [
                    'layer' => 'facebook',
                    'contract_version' => sanitize_text_field((string) ($facebook_channel['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? ''))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('facebook_adapter_contract_drift', $warning_checks, true),
                    'pool_quality_status' => sanitize_key((string) ($facebook_channel['quality_summary']['pool_quality_status'] ?? '')),
                    'distribution_source' => sanitize_key((string) ($facebook_channel['quality_summary']['distribution_source'] ?? '')),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'facebook_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => str_starts_with($check, 'social_') || $check === 'missing_target_pages' || $check === 'facebook_adapter_contract_drift')),
                ],
                'facebook_groups' => [
                    'layer' => 'facebook_groups',
                    'contract_version' => sanitize_text_field((string) (($channels['facebook_groups']['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? '')))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('facebook_groups_adapter_contract_drift', $warning_checks, true),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'facebook_groups_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => $check === 'facebook_groups_adapter_contract_drift')),
                ],
                'pinterest' => [
                    'layer' => 'pinterest',
                    'contract_version' => sanitize_text_field((string) (($channels['pinterest']['contract_version'] ?? ($this->generated_contract_versions($generated)['channel_adapters'] ?? '')))),
                    'contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                    'contract_warning' => in_array('pinterest_adapter_contract_drift', $warning_checks, true),
                    'blocking_checks' => array_values(array_filter($blocking_checks, static fn (string $check): bool => $check === 'pinterest_adapter_contract_drift')),
                    'warning_checks' => array_values(array_filter($warning_checks, static fn (string $check): bool => $check === 'pinterest_adapter_contract_drift')),
                ],
            ],
            'editorial_readiness' => $editorial_summary['editorial_readiness'],
            'editorial_highlights' => $editorial_summary['editorial_highlights'],
            'editorial_watchouts' => $editorial_summary['editorial_watchouts'],
            'quality_checks' => [
                'word_count'            => $word_count,
                'minimum_words'         => $minimum_words,
                'h2_count'              => $h2_count,
                'internal_links'        => $internal_links,
                'excerpt_words'         => $excerpt_words,
                'seo_words'             => $seo_words,
                'title_score'           => $title_score,
                'title_strong'          => $title_strong,
                'title_front_load_score'=> $title_front_load_score,
                'opening_alignment_score' => $opening_alignment_score,
                'excerpt_adds_value'    => $excerpt_adds_value,
                'opening_adds_value'    => $opening_adds_value,
                'opening_front_load_score' => $opening_front_load_score,
                'excerpt_signal_score'  => $excerpt_signal_score,
                'excerpt_front_load_score' => $excerpt_front_load_score,
                'seo_signal_score'      => $seo_signal_score,
                'seo_front_load_score'  => $seo_front_load_score,
                'page_count'            => $page_count,
                'shortest_page_words'   => $shortest_page_words,
                'strong_page_openings'  => $strong_page_openings,
                'unique_page_labels'    => $unique_page_labels,
                'strong_page_labels'    => $strong_page_labels,
                'strong_page_summaries' => $strong_page_summaries,
                'recipe_complete'       => $recipe_complete,
                'image_ready'           => $image_ready,
                'package_contract_enforced' => !empty($contract_checks['package_contract_enforced']),
                'channel_contract_enforced' => !empty($contract_checks['channel_contract_enforced']),
                'typed_contract_job'    => $typed_contract_job,
                'legacy_contract_job'   => !empty($contract_meta['legacy_job']),
                'strict_contract_mode'  => $strict_contract_mode,
                'target_pages'          => $target_pages,
                'social_variants'       => $social_variants,
                'unique_social_variants'=> $unique_variants,
                'unique_social_hooks'   => $unique_hooks,
                'unique_social_openings'=> $unique_openings,
                'unique_social_angles'  => $unique_angles,
                'unique_hook_form_candidates' => $unique_hook_form_candidates,
                'unique_social_hook_forms' => $unique_hook_forms,
                'social_pool_size'      => $social_pool_size,
                'strong_social_candidates' => $strong_social_candidates,
                'specific_social_candidates' => $specific_social_candidates,
                'anchored_social_candidates' => $anchored_social_candidates,
                'novelty_social_candidates' => $novelty_social_candidates,
                'relatable_social_candidates' => $relatable_social_candidates,
                'recognition_social_candidates' => $recognition_social_candidates,
                'conversation_social_candidates' => $conversation_social_candidates,
                'savvy_social_candidates' => $savvy_social_candidates,
                'identity_shift_social_candidates' => $identity_shift_social_candidates,
                'proof_social_candidates' => $proof_social_candidates,
                'actionable_social_candidates' => $actionable_social_candidates,
                'immediacy_social_candidates' => $immediacy_social_candidates,
                'consequence_social_candidates' => $consequence_social_candidates,
                'habit_shift_social_candidates' => $habit_shift_social_candidates,
                'focused_social_candidates' => $focused_social_candidates,
                'promise_sync_candidates' => $promise_sync_candidates,
                'scannable_social_candidates' => $scannable_social_candidates,
                'two_step_social_candidates' => $two_step_social_candidates,
                'front_loaded_social_candidates' => $front_loaded_social_candidates,
                'curiosity_social_candidates' => $curiosity_social_candidates,
                'resolution_social_candidates' => $resolution_social_candidates,
                'contrast_social_candidates' => $contrast_social_candidates,
                'pain_point_social_candidates' => $pain_point_social_candidates,
                'payoff_social_candidates' => $payoff_social_candidates,
                'high_scoring_social_candidates' => $high_scoring_social_candidates,
                'strong_social_variants'=> $strong_social_variants,
                'specific_social_variants' => $specific_social_variants,
                'anchored_variants' => $anchored_variants,
                'novelty_variants'    => $novelty_variants,
                'relatable_variants' => $relatable_variants,
                'recognition_variants' => $recognition_variants,
                'conversation_variants' => $conversation_variants,
                'savvy_variants' => $savvy_variants,
                'identity_shift_variants' => $identity_shift_variants,
                'proof_variants' => $proof_variants,
                'actionable_variants' => $actionable_variants,
                'immediacy_variants' => $immediacy_variants,
                'consequence_variants' => $consequence_variants,
                'habit_shift_variants' => $habit_shift_variants,
                'focused_variants' => $focused_variants,
                'promise_sync_variants' => $promise_sync_variants,
                'scannable_variants' => $scannable_variants,
                'two_step_variants' => $two_step_variants,
                'curiosity_variants'   => $curiosity_variants,
                'resolution_variants' => $resolution_variants,
                'contrast_variants'   => $contrast_variants,
                'front_loaded_social_variants' => $front_loaded_social_variants,
                'pain_point_variants'   => $pain_point_variants,
                'payoff_variants'       => $payoff_variants,
                'selected_social_average_score' => $selected_social_average_score,
                'lead_social_score'     => $lead_social_score,
                'lead_social_hook_form' => $lead_social_hook_form,
                'lead_social_specific'  => $lead_social_specific,
                'lead_social_anchored' => $lead_social_anchored,
                'lead_social_novelty' => $lead_social_novelty,
                'lead_social_relatable' => $lead_social_relatable,
                'lead_social_recognition' => $lead_social_recognition,
                'lead_social_conversation' => $lead_social_conversation,
                'lead_social_savvy' => $lead_social_savvy,
                'lead_social_identity_shift' => $lead_social_identity_shift,
                'lead_social_proof' => $lead_social_proof,
                'lead_social_actionable' => $lead_social_actionable,
                'lead_social_immediacy' => $lead_social_immediacy,
                'lead_social_consequence' => $lead_social_consequence,
                'lead_social_habit_shift' => $lead_social_habit_shift,
                'lead_social_focused' => $lead_social_focused,
                'lead_social_promise_sync' => $lead_social_promise_sync,
                'lead_social_scannable' => $lead_social_scannable,
                'lead_social_two_step' => $lead_social_two_step,
                'lead_social_curiosity' => $lead_social_curiosity,
                'lead_social_resolved' => $lead_social_resolved,
                'lead_social_contrast' => $lead_social_contrast,
                'lead_social_front_loaded' => $lead_social_front_loaded,
                'lead_social_pain_point' => $lead_social_pain_point,
                'lead_social_payoff'    => $lead_social_payoff,
                'duplicate_risk'        => $duplicate_risk,
            ],
        ];
    }

    private function render_job_summary(array $job, array $system_status = []): void
    {
        $events = $this->get_job_events((int) $job['id'], 12);
        $event_stats = $this->get_job_event_stats((int) $job['id']);
        $quality_summary = $this->job_quality_summary($job);
        $quality_status = (string) ($quality_summary['quality_status'] ?? '');
        ?>
        <div class="kt-summary">
            <div class="kt-summary__hero">
                <div>
                    <p class="kt-summary__eyebrow"><?php echo esc_html(sprintf(__('Job #%d', 'kuchnia-twist'), (int) $job['id'])); ?></p>
                    <h3><?php echo esc_html($job['topic']); ?></h3>
                    <div class="kt-summary__meta">
                        <span><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></span>
                        <span><?php echo esc_html(sprintf(__('Updated %s', 'kuchnia-twist'), $this->format_admin_datetime($job['updated_at']))); ?></span>
                        <?php if (!empty($job['publish_on'])) : ?>
                            <span><?php echo esc_html(sprintf(__('Scheduled %s', 'kuchnia-twist'), $this->format_admin_datetime($job['publish_on']))); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="kt-summary__status">
                    <span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($this->format_human_label($job['status'])); ?></span>
                    <?php if (!empty($quality_summary['quality_status'])) : ?>
                        <span class="kt-status kt-status--<?php echo esc_attr($this->quality_status_class((string) $quality_summary['quality_status'])); ?>"><?php echo esc_html($this->quality_status_label((string) $quality_summary['quality_status'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($job['stage'])) : ?>
                        <span class="kt-stage-pill"><?php echo esc_html($this->format_human_label($job['stage'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($system_status['worker_stale'])) : ?>
                <section class="kt-detail-block kt-detail-block--warning">
                    <h4><?php esc_html_e('Worker Status', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('The autopost worker heartbeat is stale, so background progress may pause until the container checks in again.', 'kuchnia-twist'); ?></p>
                    <span class="kt-detail-note"><?php echo esc_html($system_status['worker_heartbeat_text'] ?? ''); ?></span>
                </section>
            <?php endif; ?>

            <?php if ($quality_status === 'warn') : ?>
                <section class="kt-detail-block kt-detail-block--warning">
                    <h4><?php esc_html_e('Quality Warnings', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('This recipe can still publish, but the content machine flagged softer quality issues that deserve a quick operator review before you rely on it heavily.', 'kuchnia-twist'); ?></p>
                </section>
            <?php elseif ($quality_status === 'block') : ?>
                <section class="kt-detail-block kt-detail-block--error">
                    <h4><?php esc_html_e('Quality Block', 'kuchnia-twist'); ?></h4>
                    <p><?php esc_html_e('This recipe is blocked from blog publish until the hard integrity issues are resolved.', 'kuchnia-twist'); ?></p>
                </section>
            <?php endif; ?>

            <?php $this->render_job_stage_rail($job); ?>

            <div class="kt-chip-row">
                <?php $this->render_job_asset_badges($job); ?>
            </div>

            <dl class="kt-keyfacts">
                <div>
                    <dt><?php esc_html_e('Content Type', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Created', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_admin_datetime($job['created_at'] ?? '')); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Updated', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->format_admin_datetime($job['updated_at'])); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Queued By', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html($this->job_author_label($job)); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Attempts', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html((string) ($event_stats['attempts'] ?: 0)); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Retries', 'kuchnia-twist'); ?></dt>
                    <dd><?php echo esc_html((string) ($event_stats['retries'] ?: 0)); ?></dd>
                </div>
                <?php if (!empty($job['last_attempt_at'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Last Attempt', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->format_admin_datetime((string) $job['last_attempt_at'])); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($job['publish_on'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Scheduled For', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->format_admin_datetime((string) $job['publish_on'])); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($event_stats['latest'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Latest Event', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->format_admin_datetime((string) $event_stats['latest'])); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($job['title_override'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Title Override', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($job['title_override']); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($job['retry_target'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Retry Target', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->format_human_label($job['retry_target'])); ?></dd>
                    </div>
                <?php endif; ?>
                <?php $machine_meta = $this->job_content_machine_meta($job); ?>
                <?php
                $validator_summary_display = is_array($machine_meta['validator_summary'] ?? null) ? $machine_meta['validator_summary'] : [];
                foreach ([
                    'article_title_score',
                    'article_title_strong',
                    'article_title_front_load_score',
                    'article_opening_alignment_score',
                    'article_opening_front_load_score',
                    'article_excerpt_signal_score',
                    'article_excerpt_front_load_score',
                    'article_seo_signal_score',
                    'article_seo_front_load_score',
                    'article_excerpt_adds_value',
                    'article_opening_adds_value',
                    'social_pool_size',
                    'strong_social_candidates',
                    'specific_social_candidates',
                    'unique_hook_form_candidates',
                    'anchored_social_candidates',
                    'novelty_social_candidates',
                    'relatable_social_candidates',
                    'recognition_social_candidates',
                    'conversation_social_candidates',
                    'proof_social_candidates',
                    'actionable_social_candidates',
                    'immediacy_social_candidates',
                    'consequence_social_candidates',
                    'habit_shift_social_candidates',
                    'focused_social_candidates',
                    'promise_sync_candidates',
                    'scannable_social_candidates',
                    'two_step_social_candidates',
                    'front_loaded_social_candidates',
                    'curiosity_social_candidates',
                    'resolution_social_candidates',
                    'contrast_social_candidates',
                    'pain_point_social_candidates',
                    'payoff_social_candidates',
                    'high_scoring_social_candidates',
                    'specific_social_variants',
                    'unique_social_hook_forms',
                    'anchored_variants',
                    'novelty_variants',
                    'relatable_variants',
                    'recognition_variants',
                    'conversation_variants',
                    'proof_variants',
                    'actionable_variants',
                    'immediacy_variants',
                    'consequence_variants',
                    'habit_shift_variants',
                    'focused_variants',
                    'promise_sync_variants',
                    'scannable_variants',
                    'two_step_variants',
                    'front_loaded_social_variants',
                    'curiosity_variants',
                    'resolution_variants',
                    'contrast_variants',
                    'pain_point_variants',
                    'payoff_variants',
                    'selected_social_average_score',
                    'lead_social_score',
                    'lead_social_specific',
                    'lead_social_anchored',
                    'lead_social_novelty',
                    'lead_social_relatable',
                    'lead_social_recognition',
                    'lead_social_conversation',
                    'lead_social_proof',
                    'lead_social_actionable',
                    'lead_social_immediacy',
                    'lead_social_consequence',
                    'lead_social_habit_shift',
                    'lead_social_focused',
                    'lead_social_promise_sync',
                    'lead_social_scannable',
                    'lead_social_two_step',
                    'lead_social_curiosity',
                    'lead_social_resolved',
                    'lead_social_contrast',
                    'lead_social_hook_form',
                    'lead_social_front_loaded',
                    'lead_social_pain_point',
                    'lead_social_payoff',
                ] as $validator_key) {
                    if (!array_key_exists($validator_key, $validator_summary_display) && array_key_exists($validator_key, $quality_summary)) {
                        $validator_summary_display[$validator_key] = $quality_summary[$validator_key];
                    }
                }
                ?>
                <?php if (!empty($machine_meta['publication_profile'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Profile', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($machine_meta['publication_profile']); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($machine_meta['content_preset'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Preset', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->format_human_label($machine_meta['content_preset'])); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($machine_meta['prompt_version'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Prompt Version', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($machine_meta['prompt_version']); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($quality_summary['quality_status'])) : ?>
                    <div>
                        <dt><?php esc_html_e('Quality Status', 'kuchnia-twist'); ?></dt>
                        <dd><?php echo esc_html($this->quality_status_label((string) $quality_summary['quality_status'])); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <section class="kt-detail-block">
                <h4><?php esc_html_e('Request Snapshot', 'kuchnia-twist'); ?></h4>
                <?php $selected_pages = $this->job_selected_pages($job); ?>
                <?php $channel_targets = $this->job_channel_targets($job); ?>
                <?php $request_payload = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : []; ?>
                <?php
                $current_page_map = [];
                foreach ($this->facebook_pages($this->get_settings(), false, true) as $page) {
                    $current_page_map[(string) ($page['page_id'] ?? '')] = !empty($page['active']);
                }
                $inactive_selected_pages = array_values(array_filter(
                    $selected_pages,
                    static function (array $page) use ($current_page_map): bool {
                        $page_id = (string) ($page['page_id'] ?? '');
                        return $page_id === '' || empty($current_page_map[$page_id]);
                    }
                ));
                ?>
                <div class="kt-summary-list">
                    <div>
                        <span><?php esc_html_e('Requested title', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($this->job_requested_title($job)); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Schedule mode', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($this->format_human_label((string) ($request_payload['schedule_mode'] ?? 'immediate'))); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Publish timing', 'kuchnia-twist'); ?></span>
                        <strong>
                            <?php
                            echo !empty($job['publish_on'])
                                ? esc_html($this->format_admin_datetime((string) $job['publish_on']))
                                : esc_html__('Publish as soon as ready', 'kuchnia-twist');
                            ?>
                        </strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Hero image supplied', 'kuchnia-twist'); ?></span>
                        <strong><?php echo !empty($job['blog_image_id']) ? esc_html__('Yes', 'kuchnia-twist') : esc_html__('No', 'kuchnia-twist'); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Facebook image supplied', 'kuchnia-twist'); ?></span>
                        <strong><?php echo !empty($job['facebook_image_id']) ? esc_html__('Yes', 'kuchnia-twist') : esc_html__('No', 'kuchnia-twist'); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Site label', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($this->job_site_label($job)); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Facebook targets', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($selected_pages ? implode(', ', wp_list_pluck($selected_pages, 'label')) : __('None selected', 'kuchnia-twist')); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Facebook Groups', 'kuchnia-twist'); ?></span>
                        <strong><?php echo !empty($channel_targets['facebook_groups']['enabled']) ? esc_html($this->format_human_label((string) ($channel_targets['facebook_groups']['mode'] ?? 'manual_draft'))) : esc_html__('Dormant manual draft', 'kuchnia-twist'); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Pinterest', 'kuchnia-twist'); ?></span>
                        <strong><?php echo !empty($channel_targets['pinterest']['enabled']) ? esc_html($this->format_human_label((string) ($channel_targets['pinterest']['mode'] ?? 'draft'))) : esc_html__('Dormant draft', 'kuchnia-twist'); ?></strong>
                    </div>
                </div>
                <?php if ($inactive_selected_pages) : ?>
                    <p class="kt-system-note kt-system-note--error">
                        <?php
                        echo esc_html(
                            sprintf(
                                __('These selected pages are no longer active in Settings: %s', 'kuchnia-twist'),
                                implode(', ', wp_list_pluck($inactive_selected_pages, 'label'))
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </section>

            <?php $generated_snapshot = $this->job_generated_snapshot($job); ?>
            <?php if ($generated_snapshot) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Generated Snapshot', 'kuchnia-twist'); ?></h4>
                    <div class="kt-summary-list">
                        <?php if (!empty($generated_snapshot['title'])) : ?>
                            <div>
                                <span><?php esc_html_e('Generated title', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($generated_snapshot['title']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['slug'])) : ?>
                            <div>
                                <span><?php esc_html_e('Slug', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($generated_snapshot['slug']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['word_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('Body words', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['word_count']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['page_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('Article pages', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['page_count']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['shortest_page_words'])) : ?>
                            <div>
                                <span><?php esc_html_e('Shortest page', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('%d words', 'kuchnia-twist'), (int) $generated_snapshot['shortest_page_words'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['strong_page_openings']) && !empty($generated_snapshot['page_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('Strong page opens', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_openings'], (int) $generated_snapshot['page_count'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['unique_page_labels'])) : ?>
                            <div>
                                <span><?php esc_html_e('Distinct page labels', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['unique_page_labels']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['strong_page_labels']) && !empty($generated_snapshot['page_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('Strong page labels', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_labels'], (int) $generated_snapshot['page_count'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['strong_page_summaries']) && !empty($generated_snapshot['page_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('Strong page summaries', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(sprintf(__('%1$d of %2$d', 'kuchnia-twist'), (int) $generated_snapshot['strong_page_summaries'], (int) $generated_snapshot['page_count'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['h2_count'])) : ?>
                            <div>
                                <span><?php esc_html_e('H2 sections', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['h2_count']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['internal_links'])) : ?>
                            <div>
                                <span><?php esc_html_e('Internal links', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['internal_links']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['social_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Social variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['social_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['unique_social_hooks'])) : ?>
                            <div>
                                <span><?php esc_html_e('Distinct hooks', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['unique_social_hooks']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['unique_social_openings'])) : ?>
                            <div>
                                <span><?php esc_html_e('Distinct openings', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['unique_social_openings']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['unique_social_angles'])) : ?>
                            <div>
                                <span><?php esc_html_e('Distinct angles', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['unique_social_angles']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['strong_social_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Strong variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['strong_social_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['target_pages'])) : ?>
                            <div>
                                <span><?php esc_html_e('Target pages', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['target_pages']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['quality_status'])) : ?>
                            <div>
                                <span><?php esc_html_e('Quality status', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->quality_status_label((string) $generated_snapshot['quality_status'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['quality_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Quality score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['quality_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['editorial_readiness'])) : ?>
                            <div>
                                <span><?php esc_html_e('Editorial readiness', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->editorial_readiness_label((string) $generated_snapshot['editorial_readiness'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['package_layer'])) : ?>
                            <div>
                                <span><?php esc_html_e('Package layer', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->quality_status_label((string) $generated_snapshot['package_layer'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['package_contract'])) : ?>
                            <div>
                                <span><?php esc_html_e('Package contract', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['package_contract']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['input_mode'])) : ?>
                            <div>
                                <span><?php esc_html_e('Input mode', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $generated_snapshot['input_mode'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['rendering_mode'])) : ?>
                            <div>
                                <span><?php esc_html_e('Rendering mode', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $generated_snapshot['rendering_mode'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_adapter'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook adapter', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $generated_snapshot['facebook_adapter'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_contract'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook contract', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['facebook_contract']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_layer'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook layer', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->quality_status_label((string) $generated_snapshot['facebook_layer'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_groups_adapter'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook Groups adapter', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $generated_snapshot['facebook_groups_adapter'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_groups_contract'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook Groups contract', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['facebook_groups_contract']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['facebook_groups_ready'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook Groups draft', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['facebook_groups_ready']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['pinterest_adapter'])) : ?>
                            <div>
                                <span><?php esc_html_e('Pinterest adapter', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $generated_snapshot['pinterest_adapter'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['pinterest_contract'])) : ?>
                            <div>
                                <span><?php esc_html_e('Pinterest contract', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['pinterest_contract']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($generated_snapshot['pinterest_ready'])) : ?>
                            <div>
                                <span><?php esc_html_e('Pinterest draft', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $generated_snapshot['pinterest_ready']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($machine_meta['validator_summary']['distribution_source'])) : ?>
                            <div>
                                <span><?php esc_html_e('Distribution copy', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $machine_meta['validator_summary']['distribution_source'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($machine_meta['validator_summary']['repair_attempts'])) : ?>
                            <div>
                                <span><?php esc_html_e('Repair attempts', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $machine_meta['validator_summary']['repair_attempts']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($machine_meta['validator_summary']['article_stage_quality_status'])) : ?>
                            <div>
                                <span><?php esc_html_e('Article stage', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->quality_status_label((string) $machine_meta['validator_summary']['article_stage_quality_status'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($machine_meta['validator_summary']['article_stage_checks']) && is_array($machine_meta['validator_summary']['article_stage_checks'])) : ?>
                            <div>
                                <span><?php esc_html_e('Article stage checks', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) count($machine_meta['validator_summary']['article_stage_checks'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_title_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Title score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_title_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_title_strong'])) : ?>
                            <div>
                                <span><?php esc_html_e('Title strength', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['article_title_strong']) ? __('Strong', 'kuchnia-twist') : __('Weak', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_title_front_load_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Title lead', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_title_front_load_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_opening_alignment_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Opening alignment', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_opening_alignment_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_opening_front_load_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Opening lead', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_opening_front_load_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_excerpt_signal_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Excerpt score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_excerpt_signal_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_excerpt_front_load_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Excerpt lead', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_excerpt_front_load_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_excerpt_adds_value'])) : ?>
                            <div>
                                <span><?php esc_html_e('Excerpt distinct', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['article_excerpt_adds_value']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_seo_signal_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('SEO score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_seo_signal_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_seo_front_load_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('SEO lead', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['article_seo_front_load_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['article_opening_adds_value'])) : ?>
                            <div>
                                <span><?php esc_html_e('Opening distinct', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['article_opening_adds_value']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($machine_meta['validator_summary']['social_pool_quality_status'])) : ?>
                            <div>
                                <span><?php esc_html_e('Social pool', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->quality_status_label((string) $machine_meta['validator_summary']['social_pool_quality_status'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($machine_meta['validator_summary']['social_repair_attempts'])) : ?>
                            <div>
                                <span><?php esc_html_e('Social repair attempts', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $machine_meta['validator_summary']['social_repair_attempts']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['strong_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Strong candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['strong_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['specific_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Specific candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['specific_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['unique_hook_form_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Candidate hook forms', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['unique_hook_form_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['anchored_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Anchored candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['anchored_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['novelty_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Novelty candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['novelty_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['relatable_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Relatable candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['relatable_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['recognition_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Self-recognition candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['recognition_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['conversation_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Discussable candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['conversation_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['savvy_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Savvy candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['savvy_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['identity_shift_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Identity-shift candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['identity_shift_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['proof_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Proof candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['proof_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['actionable_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Actionable candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['actionable_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['immediacy_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Immediate-use candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['immediacy_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['consequence_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Stakes candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['consequence_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['habit_shift_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Habit-shift candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['habit_shift_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['focused_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Focused candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['focused_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['promise_sync_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Promise-sync candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['promise_sync_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['scannable_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Scannable candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['scannable_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['two_step_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Two-step candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['two_step_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['front_loaded_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead-ready candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['front_loaded_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['curiosity_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Curiosity candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['curiosity_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['resolution_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Resolved candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['resolution_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['contrast_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Contrast candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['contrast_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['pain_point_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Pain-point candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['pain_point_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['payoff_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('Payoff candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['payoff_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['high_scoring_social_candidates'])) : ?>
                            <div>
                                <span><?php esc_html_e('High-scoring candidates', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['high_scoring_social_candidates']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['specific_social_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Specific variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['specific_social_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['unique_social_hook_forms'])) : ?>
                            <div>
                                <span><?php esc_html_e('Hook forms', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['unique_social_hook_forms']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['anchored_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Anchored variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['anchored_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['novelty_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Novelty variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['novelty_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['relatable_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Relatable variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['relatable_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['recognition_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Self-recognition variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['recognition_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['conversation_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Discussable variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['conversation_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['savvy_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Savvy variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['savvy_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['identity_shift_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Identity-shift variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['identity_shift_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['proof_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Proof variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['proof_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['actionable_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Actionable variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['actionable_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['immediacy_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Immediate-use variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['immediacy_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['consequence_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Stakes variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['consequence_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['habit_shift_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Habit-shift variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['habit_shift_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['focused_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Focused variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['focused_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['promise_sync_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Promise-sync variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['promise_sync_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['scannable_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Scannable variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['scannable_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['two_step_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Two-step variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['two_step_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['front_loaded_social_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead-ready variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['front_loaded_social_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['curiosity_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Curiosity variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['curiosity_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['resolution_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Resolved variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['resolution_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['contrast_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Contrast variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['contrast_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['pain_point_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Pain-point variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['pain_point_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['payoff_variants'])) : ?>
                            <div>
                                <span><?php esc_html_e('Payoff variants', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['payoff_variants']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['selected_social_average_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Selected score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['selected_social_average_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_score'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead score', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html((string) $validator_summary_display['lead_social_score']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_specific'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead specific', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_specific']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_anchored'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead anchored', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_anchored']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_novelty'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead novelty', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_novelty']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_relatable'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead relatable', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_relatable']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_recognition'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead self-recognition', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_recognition']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_conversation'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead discussable', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_conversation']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_savvy'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead savvy', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_savvy']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_identity_shift'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead identity-shift', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_identity_shift']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_proof'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead proof', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_proof']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_actionable'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead actionable', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_actionable']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_immediacy'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead immediate-use', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_immediacy']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_consequence'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead stakes', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_consequence']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_habit_shift'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead habit-shift', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_habit_shift']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_focused'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead focused', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_focused']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_promise_sync'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead promise-sync', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_promise_sync']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_scannable'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead scannable', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_scannable']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_two_step'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead two-step', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_two_step']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_curiosity'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead curiosity', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_curiosity']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_resolved'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead resolved', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_resolved']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_contrast'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead contrast', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_contrast']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($validator_summary_display['lead_social_hook_form'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead hook form', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($this->format_human_label((string) $validator_summary_display['lead_social_hook_form'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_front_loaded'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead front-loaded', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_front_loaded']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_pain_point'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead pain-point', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_pain_point']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($validator_summary_display['lead_social_payoff'])) : ?>
                            <div>
                                <span><?php esc_html_e('Lead payoff', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(!empty($validator_summary_display['lead_social_payoff']) ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($generated_snapshot['excerpt'])) : ?>
                        <div class="kt-generated-copy">
                            <label for="kt-generated-excerpt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Excerpt', 'kuchnia-twist'); ?></label>
                            <textarea id="kt-generated-excerpt-<?php echo (int) $job['id']; ?>" rows="3" readonly><?php echo esc_textarea($generated_snapshot['excerpt']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['editorial_highlights']) && is_array($generated_snapshot['editorial_highlights'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label><?php esc_html_e('Editorial highlights', 'kuchnia-twist'); ?></label>
                                <?php if (!empty($generated_snapshot['editorial_readiness'])) : ?>
                                    <span class="kt-status kt-status--<?php echo esc_attr($this->editorial_readiness_class((string) $generated_snapshot['editorial_readiness'])); ?>"><?php echo esc_html($this->editorial_readiness_label((string) $generated_snapshot['editorial_readiness'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="kt-context-chips">
                                <?php foreach ($generated_snapshot['editorial_highlights'] as $highlight) : ?>
                                    <span class="kt-context-chip"><?php echo esc_html((string) $highlight); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['editorial_watchouts']) && is_array($generated_snapshot['editorial_watchouts'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label><?php esc_html_e('Editorial watchouts', 'kuchnia-twist'); ?></label>
                            </div>
                            <div class="kt-context-chips">
                                <?php foreach ($generated_snapshot['editorial_watchouts'] as $watchout) : ?>
                                    <span class="kt-context-chip"><?php echo esc_html((string) $watchout); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['seo_description'])) : ?>
                        <div class="kt-generated-copy">
                            <label for="kt-generated-seo-<?php echo (int) $job['id']; ?>"><?php esc_html_e('SEO Description', 'kuchnia-twist'); ?></label>
                            <textarea id="kt-generated-seo-<?php echo (int) $job['id']; ?>" rows="3" readonly><?php echo esc_textarea($generated_snapshot['seo_description']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['opening_paragraph'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-generated-opening-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Opening Paragraph', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-generated-opening-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-generated-opening-<?php echo (int) $job['id']; ?>" rows="4" readonly><?php echo esc_textarea($generated_snapshot['opening_paragraph']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['headings']) && is_array($generated_snapshot['headings'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-generated-headings-<?php echo (int) $job['id']; ?>"><?php esc_html_e('H2 Outline', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-generated-headings-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-generated-headings-<?php echo (int) $job['id']; ?>" rows="5" readonly><?php echo esc_textarea(implode("\n", $generated_snapshot['headings'])); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['page_labels']) && is_array($generated_snapshot['page_labels'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-generated-pages-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Page Flow', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-generated-pages-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-generated-pages-<?php echo (int) $job['id']; ?>" rows="4" readonly><?php echo esc_textarea(implode("\n", $generated_snapshot['page_labels'])); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['image_alt'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-generated-image-alt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Image Alt Text', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-generated-image-alt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-generated-image-alt-<?php echo (int) $job['id']; ?>" rows="2" readonly><?php echo esc_textarea($generated_snapshot['image_alt']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['image_prompt'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-generated-image-prompt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Image Prompt', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-generated-image-prompt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-generated-image-prompt-<?php echo (int) $job['id']; ?>" rows="5" readonly><?php echo esc_textarea($generated_snapshot['image_prompt']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (($machine_meta['validator_summary']['distribution_source'] ?? '') === 'partial_fallback') : ?>
                        <p class="kt-system-note"><?php esc_html_e('The content engine returned some, but not all, Facebook variants. The worker filled the remaining page variants with local fallback copy.', 'kuchnia-twist'); ?></p>
                    <?php elseif (($machine_meta['validator_summary']['distribution_source'] ?? '') === 'local_fallback') : ?>
                        <p class="kt-system-note kt-system-note--error"><?php esc_html_e('The Facebook social pack was fully rebuilt from local fallback copy because the content engine did not provide usable variants.', 'kuchnia-twist'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['blocking_checks']) && is_array($generated_snapshot['blocking_checks'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label><?php esc_html_e('Blocking Checks', 'kuchnia-twist'); ?></label>
                            </div>
                            <div class="kt-context-chips">
                                <?php foreach ($generated_snapshot['blocking_checks'] as $failed_check) : ?>
                                    <span class="kt-context-chip"><?php echo esc_html($this->quality_failed_check_messages()[(string) $failed_check] ?? $this->format_human_label((string) $failed_check)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['warning_checks']) && is_array($generated_snapshot['warning_checks'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label><?php esc_html_e('Warning Checks', 'kuchnia-twist'); ?></label>
                            </div>
                            <div class="kt-context-chips">
                                <?php foreach ($generated_snapshot['warning_checks'] as $failed_check) : ?>
                                    <span class="kt-context-chip"><?php echo esc_html($this->quality_failed_check_messages()[(string) $failed_check] ?? $this->format_human_label((string) $failed_check)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php $recipe_snapshot = $this->job_recipe_snapshot($job); ?>
            <?php if ($recipe_snapshot) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Recipe Snapshot', 'kuchnia-twist'); ?></h4>
                    <div class="kt-summary-list">
                        <?php if (!empty($recipe_snapshot['prep_time'])) : ?>
                            <div>
                                <span><?php esc_html_e('Prep time', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($recipe_snapshot['prep_time']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($recipe_snapshot['cook_time'])) : ?>
                            <div>
                                <span><?php esc_html_e('Cook time', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($recipe_snapshot['cook_time']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($recipe_snapshot['total_time'])) : ?>
                            <div>
                                <span><?php esc_html_e('Total time', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($recipe_snapshot['total_time']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($recipe_snapshot['yield'])) : ?>
                            <div>
                                <span><?php esc_html_e('Yield', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($recipe_snapshot['yield']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></span>
                            <strong><?php echo esc_html((string) $recipe_snapshot['ingredients_count']); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('Instructions', 'kuchnia-twist'); ?></span>
                            <strong><?php echo esc_html((string) $recipe_snapshot['instructions_count']); ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($recipe_snapshot['ingredients'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-recipe-ingredients-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-recipe-ingredients-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-recipe-ingredients-<?php echo (int) $job['id']; ?>" rows="6" readonly><?php echo esc_textarea(implode("\n", $recipe_snapshot['ingredients'])); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($recipe_snapshot['instructions'])) : ?>
                        <div class="kt-generated-copy">
                            <div class="kt-detail-block__head">
                                <label for="kt-recipe-instructions-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Instructions', 'kuchnia-twist'); ?></label>
                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-recipe-instructions-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                            </div>
                            <textarea id="kt-recipe-instructions-<?php echo (int) $job['id']; ?>" rows="8" readonly><?php echo esc_textarea(implode("\n", $recipe_snapshot['instructions'])); ?></textarea>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php $distribution = $this->job_facebook_distribution($job); ?>
            <?php if (!empty($job['permalink']) || !empty($job['facebook_post_id']) || !empty($job['facebook_comment_id']) || !empty($distribution['pages'])) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Outputs', 'kuchnia-twist'); ?></h4>
                    <div class="kt-summary-list">
                        <?php if (!empty($job['permalink'])) : ?>
                            <div>
                                <span><?php esc_html_e('Article', 'kuchnia-twist'); ?></span>
                                <a href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open article', 'kuchnia-twist'); ?></a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job['facebook_post_id'])) : ?>
                            <div>
                                <span><?php esc_html_e('Facebook post ID', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($job['facebook_post_id']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job['facebook_comment_id'])) : ?>
                            <div>
                                <span><?php esc_html_e('First comment ID', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html($job['facebook_comment_id']); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($distribution['pages'])) : ?>
                            <div>
                                <span><?php esc_html_e('Page distribution', 'kuchnia-twist'); ?></span>
                                <strong><?php echo esc_html(sprintf(_n('%d page targeted', '%d pages targeted', count($distribution['pages']), 'kuchnia-twist'), count($distribution['pages']))); ?></strong>
                            </div>
                            <div>
                                <span><?php esc_html_e('Pages completed', 'kuchnia-twist'); ?></span>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            __('%1$d of %2$d', 'kuchnia-twist'),
                                            count(array_filter($distribution['pages'], static fn (array $page): bool => ($page['status'] ?? '') === 'completed')),
                                            count($distribution['pages'])
                                        )
                                    );
                                    ?>
                                </strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($this->job_has_media($job)) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Media', 'kuchnia-twist'); ?></h4>
                    <div class="kt-media-grid">
                        <?php $this->render_job_media_cards($job); ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($job['error_message'])) : ?>
                <section class="kt-detail-block kt-detail-block--error">
                    <h4><?php esc_html_e('Error', 'kuchnia-twist'); ?></h4>
                    <p class="kt-error"><?php echo esc_html($job['error_message']); ?></p>
                </section>
            <?php endif; ?>

            <?php if (!empty($job['permalink']) || in_array($job['status'], ['failed', 'partial_failure', 'scheduled'], true)) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Actions', 'kuchnia-twist'); ?></h4>
                    <div class="kt-inline-actions">
                        <?php if ($job['status'] === 'scheduled') : ?>
                            <a class="button button-primary" href="<?php echo esc_url($this->publish_now_link($job)); ?>"><?php esc_html_e('Publish Now', 'kuchnia-twist'); ?></a>
                            <a class="button button-secondary" href="<?php echo esc_url($this->cancel_scheduled_job_link($job)); ?>"><?php esc_html_e('Cancel Release', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($job['post_id'])) : ?>
                            <a class="button" href="<?php echo esc_url(get_edit_post_link((int) $job['post_id'])); ?>"><?php esc_html_e('Edit Post', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                        <?php if (!empty($job['permalink'])) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open Article', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array($job['status'], ['failed', 'partial_failure'], true)) : ?>
                            <a class="button button-primary" href="<?php echo esc_url($this->retry_link($job)); ?>"><?php esc_html_e('Retry Job', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($job['status'] === 'scheduled') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kt-form kt-inline-form">
                            <?php wp_nonce_field('kuchnia_twist_set_job_schedule'); ?>
                            <input type="hidden" name="action" value="kuchnia_twist_set_job_schedule">
                            <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                            <?php foreach ($this->current_job_view_args() as $arg_key => $arg_value) : ?>
                                <input type="hidden" name="<?php echo esc_attr($arg_key); ?>" value="<?php echo esc_attr((string) $arg_value); ?>">
                            <?php endforeach; ?>
                            <div class="kt-field-grid">
                                <label>
                                    <span><?php esc_html_e('Set Schedule', 'kuchnia-twist'); ?></span>
                                    <input type="datetime-local" name="publish_at" step="60" value="<?php echo esc_attr($this->format_admin_datetime_input((string) ($job['publish_on'] ?? ''))); ?>" required>
                                </label>
                                <label>
                                    <span><?php esc_html_e('Timezone', 'kuchnia-twist'); ?></span>
                                    <input type="text" value="<?php echo esc_attr(wp_timezone_string() ?: 'UTC'); ?>" readonly>
                                </label>
                            </div>
                            <div class="kt-inline-actions">
                                <button type="submit" class="button"><?php esc_html_e('Reschedule', 'kuchnia-twist'); ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php $social_pack = $this->job_social_pack($job); ?>
            <?php if ($social_pack) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <h4><?php esc_html_e('Facebook Social Pack', 'kuchnia-twist'); ?></h4>
                        <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('%d variant', '%d variants', count($social_pack), 'kuchnia-twist'), count($social_pack))); ?></span>
                    </div>
                    <div class="kt-variant-list">
                        <?php foreach ($social_pack as $index => $variant) : ?>
                            <?php
                            $target_page = $selected_pages[$index]['label'] ?? '';
                            $variant_id = 'kt-social-variant-' . (int) $job['id'] . '-' . (int) $index;
                            $post_preview = $this->build_facebook_post_preview($variant);
                            ?>
                            <article class="kt-variant-card">
                                <div class="kt-variant-card__head">
                                    <div>
                                        <strong><?php echo esc_html(sprintf(__('Variant %d', 'kuchnia-twist'), $index + 1)); ?></strong>
                                        <?php if ($target_page !== '') : ?>
                                            <span><?php echo esc_html($target_page); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($variant['angle_key'])) : ?>
                                        <span class="kt-stage-pill"><?php echo esc_html($this->hook_angle_label((string) $variant['angle_key'], (string) ($job['content_type'] ?? 'recipe'))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="kt-variant-fields">
                                    <?php if (!empty($variant['hook'])) : ?>
                                        <div class="kt-variant-field">
                                            <div class="kt-detail-block__head">
                                                <label for="<?php echo esc_attr($variant_id . '-hook'); ?>"><?php esc_html_e('Hook', 'kuchnia-twist'); ?></label>
                                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id . '-hook'); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                            </div>
                                            <textarea id="<?php echo esc_attr($variant_id . '-hook'); ?>" rows="2" readonly><?php echo esc_textarea((string) ($variant['hook'] ?? '')); ?></textarea>
                                        </div>
                                    <?php endif; ?>
                                    <div class="kt-variant-field">
                                        <div class="kt-detail-block__head">
                                            <label for="<?php echo esc_attr($variant_id); ?>"><?php esc_html_e('Caption', 'kuchnia-twist'); ?></label>
                                            <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <textarea id="<?php echo esc_attr($variant_id); ?>" rows="5" readonly><?php echo esc_textarea((string) ($variant['caption'] ?? '')); ?></textarea>
                                    </div>
                                    <?php if ($post_preview !== '') : ?>
                                        <div class="kt-variant-field">
                                            <div class="kt-detail-block__head">
                                                <label for="<?php echo esc_attr($variant_id . '-message'); ?>"><?php esc_html_e('Final post message', 'kuchnia-twist'); ?></label>
                                                <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr($variant_id . '-message'); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                            </div>
                                            <textarea id="<?php echo esc_attr($variant_id . '-message'); ?>" rows="6" readonly><?php echo esc_textarea($post_preview); ?></textarea>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($variant['cta_hint'])) : ?>
                                    <p class="kt-detail-note"><?php echo esc_html($variant['cta_hint']); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php elseif (!empty($this->derive_legacy_facebook_caption(is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [], $job))) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <label for="kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Facebook Caption', 'kuchnia-twist'); ?></label>
                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                    </div>
                    <textarea id="kt-facebook-caption-<?php echo (int) $job['id']; ?>" rows="5" readonly><?php echo esc_textarea($this->derive_legacy_facebook_caption(is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [], $job)); ?></textarea>
                </section>
            <?php endif; ?>

            <?php if (!empty($distribution['pages'])) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <h4><?php esc_html_e('Facebook Distribution', 'kuchnia-twist'); ?></h4>
                        <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('%d page', '%d pages', count($distribution['pages']), 'kuchnia-twist'), count($distribution['pages']))); ?></span>
                    </div>
                    <div class="kt-distribution-list">
                        <?php foreach ($distribution['pages'] as $page) : ?>
                            <?php
                            $distribution_preview = $this->build_facebook_post_preview(is_array($page) ? $page : []);
                            $comment_preview = $this->build_facebook_comment_preview($job, is_array($page) ? $page : []);
                            ?>
                            <article class="kt-distribution-card">
                                <div class="kt-distribution-card__head">
                                    <div>
                                        <strong><?php echo esc_html($page['label'] ?: $page['page_id']); ?></strong>
                                        <span><?php echo esc_html($page['page_id']); ?></span>
                                    </div>
                                    <span class="kt-status kt-status--<?php echo esc_attr($page['status'] ?: 'queued'); ?>"><?php echo esc_html($this->format_human_label($page['status'] ?: 'queued')); ?></span>
                                </div>
                                <div class="kt-context-chips">
                                    <?php if (!empty($page['angle_key'])) : ?>
                                        <span class="kt-context-chip"><?php echo esc_html($this->hook_angle_label((string) $page['angle_key'], (string) ($job['content_type'] ?? 'recipe'))); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($page['post_id'])) : ?>
                                        <span class="kt-context-chip"><?php echo esc_html(sprintf(__('Post ID: %s', 'kuchnia-twist'), $page['post_id'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($page['comment_id'])) : ?>
                                        <span class="kt-context-chip"><?php echo esc_html(sprintf(__('Comment ID: %s', 'kuchnia-twist'), $page['comment_id'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($distribution_preview !== '') : ?>
                                    <div class="kt-variant-field">
                                        <div class="kt-detail-block__head">
                                            <label for="<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Posted message', 'kuchnia-twist'); ?></label>
                                            <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <textarea id="<?php echo esc_attr('kt-distribution-message-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>" rows="6" readonly><?php echo esc_textarea($distribution_preview); ?></textarea>
                                    </div>
                                <?php endif; ?>
                                <?php if ($comment_preview !== '') : ?>
                                    <div class="kt-variant-field">
                                        <div class="kt-detail-block__head">
                                            <label for="<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('First comment message', 'kuchnia-twist'); ?></label>
                                            <button type="button" class="button button-small kt-copy-button" data-copy-target="#<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                                        </div>
                                        <textarea id="<?php echo esc_attr('kt-distribution-comment-' . (int) $job['id'] . '-' . sanitize_html_class((string) ($page['page_id'] ?? 'page'))); ?>" rows="4" readonly><?php echo esc_textarea($comment_preview); ?></textarea>
                                    </div>
                                <?php endif; ?>
                                <div class="kt-inline-actions">
                                    <?php if (!empty($page['post_url'])) : ?>
                                        <a class="button button-small" href="<?php echo esc_url($page['post_url']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open Facebook Post', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($page['comment_url'])) : ?>
                                        <a class="button button-small" href="<?php echo esc_url($page['comment_url']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open First Comment', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($page['error'])) : ?>
                                    <p class="kt-error"><?php echo esc_html($page['error']); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($events) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <h4><?php esc_html_e('Ops Timeline', 'kuchnia-twist'); ?></h4>
                        <span class="kt-stage-pill"><?php echo esc_html(sprintf(_n('Last %d event', 'Last %d events', count($events), 'kuchnia-twist'), count($events))); ?></span>
                    </div>
                    <div class="kt-event-list">
                        <?php foreach ($events as $event) : ?>
                            <article class="kt-event">
                                <div class="kt-event__top">
                                    <strong><?php echo esc_html($this->format_human_label($event['event_type'])); ?></strong>
                                    <span><?php echo esc_html($this->format_admin_datetime($event['created_at'])); ?></span>
                                </div>
                                <div class="kt-chip-row">
                                    <?php if (!empty($event['status'])) : ?>
                                        <span class="kt-status kt-status--<?php echo esc_attr($event['status']); ?>"><?php echo esc_html($this->format_human_label($event['status'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['stage']) && $event['stage'] !== $event['status']) : ?>
                                        <span class="kt-stage-pill"><?php echo esc_html($this->format_human_label($event['stage'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($event['message'])) : ?>
                                    <p><?php echo esc_html($event['message']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($event['context']) && is_array($event['context'])) : ?>
                                    <div class="kt-context-chips">
                                        <?php foreach ($event['context'] as $key => $value) : ?>
                                            <?php if ($value === '' || $value === null) { continue; } ?>
                                            <span class="kt-context-chip">
                                                <?php
                                                echo esc_html(
                                                    sprintf(
                                                        '%s: %s',
                                                        $this->format_human_label((string) $key),
                                                        is_bool($value) ? ($value ? __('Yes', 'kuchnia-twist') : __('No', 'kuchnia-twist')) : (string) $value
                                                    )
                                                );
                                                ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    private function extract_article_page_labels(array $pages): array
    {
        return $this->format_article_page_flow_labels($this->extract_article_page_flow($pages));
    }

    private function extract_article_page_flow(array $pages): array
    {
        $flow = [];

        foreach ($pages as $index => $page) {
            $page = trim((string) $page);
            if ($page === '') {
                continue;
            }

            $label = $this->extract_article_page_label_text($page, sprintf(__('Page %d', 'kuchnia-twist'), $index + 1));
            $summary = $this->extract_article_page_summary($page);

            $flow[] = [
                'index' => $index + 1,
                'label' => $label !== '' ? $label : sprintf(__('Page %d', 'kuchnia-twist'), $index + 1),
                'summary' => $summary,
            ];
        }

        return $flow;
    }

    private function normalize_generated_page_flow(array $flow, array $pages): array
    {
        $fallback = $this->extract_article_page_flow($pages);
        if (empty($flow)) {
            return $fallback;
        }

        $normalized = [];
        $used_labels = [];
        foreach ($fallback as $index => $page) {
            $raw = is_array($flow[$index] ?? null) ? $flow[$index] : [];
            $fallback_label = sanitize_text_field((string) ($page['label'] ?? sprintf(__('Page %d', 'kuchnia-twist'), $index + 1)));
            $fallback_summary = sanitize_text_field((string) ($page['summary'] ?? ''));
            $label = sanitize_text_field((string) ($raw['label'] ?? $raw['title'] ?? $raw['page_label'] ?? ''));
            $summary = sanitize_text_field((string) ($raw['summary'] ?? $raw['page_summary'] ?? $raw['description'] ?? ''));

            if (!$this->page_flow_label_looks_strong($label, $index + 1)) {
                $label = $fallback_label;
            }
            if (!$this->page_flow_summary_looks_strong($summary, $label)) {
                $summary = $fallback_summary;
            }

            $fingerprint = $this->normalize_page_flow_label_fingerprint($label);
            $fallback_fingerprint = $this->normalize_page_flow_label_fingerprint($fallback_label);
            if (($fingerprint === '' || in_array($fingerprint, $used_labels, true)) && $fallback_fingerprint !== '' && !in_array($fallback_fingerprint, $used_labels, true)) {
                $label = $fallback_label;
                $fingerprint = $fallback_fingerprint;
            }

            if ($fingerprint === '' || in_array($fingerprint, $used_labels, true)) {
                $derived_label = $this->derive_page_flow_label_from_summary($summary !== '' ? $summary : $fallback_summary, $fallback_label);
                $derived_fingerprint = $this->normalize_page_flow_label_fingerprint($derived_label);
                if ($derived_fingerprint !== '' && !in_array($derived_fingerprint, $used_labels, true) && $this->page_flow_label_looks_strong($derived_label, $index + 1)) {
                    $label = $derived_label;
                    $fingerprint = $derived_fingerprint;
                }
            }

            if (!$this->page_flow_summary_looks_strong($summary, $label)) {
                $summary = $fallback_summary;
            }
            if ($summary === '') {
                $summary = $fallback_summary;
            }
            if ($fingerprint !== '') {
                $used_labels[] = $fingerprint;
            }

            $normalized[] = [
                'index'   => (int) ($page['index'] ?? ($index + 1)),
                'label'   => $label !== '' ? wp_trim_words($label, 8, '...') : $fallback_label,
                'summary' => $summary !== '' ? wp_trim_words($summary, 18, '...') : $fallback_summary,
            ];
        }

        return $normalized;
    }

    private function format_article_page_flow_labels(array $flow): array
    {
        $labels = [];

        foreach ($flow as $page) {
            $label = (string) ($page['label'] ?? '');
            $summary = (string) ($page['summary'] ?? '');
            $index = (int) ($page['index'] ?? 0);

            if ($index < 1 || $label === '') {
                continue;
            }

            $labels[] = $summary !== '' && $summary !== $label
                ? sprintf(__('Page %1$d: %2$s - %3$s', 'kuchnia-twist'), $index, $label, $summary)
                : sprintf(__('Page %1$d: %2$s', 'kuchnia-twist'), $index, $label);
        }

        return $labels;
    }

    private function extract_article_page_label_text(string $page, string $fallback = ''): string
    {
        if (preg_match('/<h2\b[^>]*>(.*?)<\/h2>/is', $page, $matches)) {
            $label = sanitize_text_field(wp_strip_all_tags((string) ($matches[1] ?? '')));
            $label = preg_replace('/^[0-9]+\s*[:.)-]?\s*/', '', (string) $label);
            if ($label !== '') {
                return wp_trim_words($label, 8, '...');
            }
        }

        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $page, $matches)) {
            $paragraph = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
            if ($paragraph !== '') {
                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph];
                $lead = trim((string) ($sentences[0] ?? $paragraph));
                if ($lead !== '') {
                    return wp_trim_words($lead, 8, '...');
                }
            }
        }

        $plaintext = preg_replace('/\s+/', ' ', wp_strip_all_tags($page));
        if ($plaintext !== '') {
            return sanitize_text_field(wp_trim_words((string) $plaintext, 8, '...'));
        }

        return sanitize_text_field($fallback);
    }

    private function extract_article_page_summary(string $page): string
    {
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $page, $matches)) {
            $paragraph = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
            if ($paragraph !== '') {
                $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph])));
                $summary = (string) ($sentences[1] ?? $sentences[0] ?? $paragraph);
                if ($summary !== '') {
                    return sanitize_text_field(wp_trim_words($summary, 18, '...'));
                }
            }
        }

        $plaintext = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($page)));
        if ($plaintext !== '') {
            return sanitize_text_field(wp_trim_words($plaintext, 18, '...'));
        }

        return '';
    }

    private function normalize_page_flow_label_fingerprint(string $label): string
    {
        $label = strtolower(remove_accents(sanitize_text_field($label)));
        $label = preg_replace('/^(page|part|section|step)\s+\d+\s*[:.)-]?\s*/i', '', $label);
        $label = preg_replace('/[^a-z0-9\s]/', ' ', (string) $label);

        return trim(preg_replace('/\s+/', ' ', (string) $label));
    }

    private function page_flow_label_looks_strong(string $label, int $index = 0): bool
    {
        $text = sanitize_text_field($label);
        $fallback = sprintf('Page %d', $index > 0 ? $index : 1);
        $fingerprint = $this->normalize_page_flow_label_fingerprint($text !== '' ? $text : $fallback);
        if ($fingerprint === '') {
            return false;
        }

        if (str_word_count($fingerprint) < 2 || strlen($fingerprint) < 8) {
            return false;
        }

        return !preg_match('/^(page|part|section|continue|next page|keep reading|read more)\b/i', $text);
    }

    private function page_flow_summary_looks_strong(string $summary, string $label = ''): bool
    {
        $text = sanitize_text_field($summary);
        if ($text === '') {
            return false;
        }

        $summary_fingerprint = $this->normalize_page_flow_label_fingerprint($text);
        $label_fingerprint = $this->normalize_page_flow_label_fingerprint($label);
        if (str_word_count($summary_fingerprint) < 6) {
            return false;
        }
        if ($label_fingerprint !== '' && $summary_fingerprint === $label_fingerprint) {
            return false;
        }

        return !preg_match('/^(page|part)\s+\d+\b|^(keep reading|continue reading|read more|next up)\b/i', $text);
    }

    private function derive_page_flow_label_from_summary(string $summary, string $fallback = ''): string
    {
        $source = sanitize_text_field($summary !== '' ? $summary : $fallback);
        if ($source === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $source) ?: [$source];
        $lead = trim((string) ($sentences[0] ?? $source));
        if ($lead === '') {
            $lead = $source;
        }

        return sanitize_text_field(wp_trim_words($lead, 8, '...'));
    }

    private function page_starts_with_expected_lead(string $page_html, int $index): bool
    {
        $page_html = trim($page_html);
        if ($page_html === '') {
            return false;
        }

        if ($index === 0) {
            return preg_match('/^<p\b/i', $page_html) === 1;
        }

        return preg_match('/^<(h2|blockquote|ul|ol)\b/i', $page_html) === 1;
    }

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

    private function retry_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_retry_job',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_retry_job'
        );
    }

    private function publish_now_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_publish_now',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_publish_now'
        );
    }

    private function cancel_scheduled_job_link(array $job): string
    {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    [
                        'action' => 'kuchnia_twist_cancel_scheduled_job',
                        'job_id' => (int) $job['id'],
                    ],
                    $this->current_job_view_args()
                ),
                admin_url('admin-post.php')
            ),
            'kuchnia_twist_cancel_scheduled_job'
        );
    }

    private function authorize_worker(WP_REST_Request $request)
    {
        $secret = $this->get_worker_secret();
        $sent   = (string) $request->get_header('x-kuchnia-worker-secret');

        if ($secret === '') {
            return new WP_Error('worker_secret_missing', __('Worker secret is not configured.', 'kuchnia-twist'), ['status' => 500]);
        }

        if ($sent === '' || !hash_equals($secret, $sent)) {
            return new WP_Error('forbidden', __('Invalid worker credentials.', 'kuchnia-twist'), ['status' => 403]);
        }

        return true;
    }

    private function get_worker_secret(): string
    {
        return trim((string) getenv('CONTENT_PIPELINE_SHARED_SECRET'));
    }

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

    private function social_angle_presets(string $content_type = 'recipe'): array
    {
        $presets = [
            'recipe' => [
                'quick_dinner' => [
                    'label'       => __('Quick Dinner', 'kuchnia-twist'),
                    'instruction' => __('Lead with speed, ease, and a real weeknight payoff.', 'kuchnia-twist'),
                ],
                'comfort_food' => [
                    'label'       => __('Comfort Food', 'kuchnia-twist'),
                    'instruction' => __('Lean into warmth, cozy payoff, texture, and repeat-cook appeal.', 'kuchnia-twist'),
                ],
                'budget_friendly' => [
                    'label'       => __('Budget Friendly', 'kuchnia-twist'),
                    'instruction' => __('Emphasize value, pantry practicality, and generous payoff without sounding cheap.', 'kuchnia-twist'),
                ],
                'beginner_friendly' => [
                    'label'       => __('Beginner Friendly', 'kuchnia-twist'),
                    'instruction' => __('Make the hook feel approachable, low-stress, and confidence-building.', 'kuchnia-twist'),
                ],
                'crowd_pleaser' => [
                    'label'       => __('Crowd Pleaser', 'kuchnia-twist'),
                    'instruction' => __('Frame the recipe as dependable, family-friendly, and easy to serve again.', 'kuchnia-twist'),
                ],
                'better_than_takeout' => [
                    'label'       => __('Better Than Takeout', 'kuchnia-twist'),
                    'instruction' => __('Focus on restaurant-style payoff with simpler home-kitchen control.', 'kuchnia-twist'),
                ],
            ],
            'food_fact' => [
                'myth_busting' => [
                    'label'       => __('Myth Busting', 'kuchnia-twist'),
                    'instruction' => __('Lead with a correction to something many cooks casually believe.', 'kuchnia-twist'),
                ],
                'surprising_truth' => [
                    'label'       => __('Surprising Truth', 'kuchnia-twist'),
                    'instruction' => __('Frame the post around a specific surprise that changes how the reader sees the topic.', 'kuchnia-twist'),
                ],
                'kitchen_mistake' => [
                    'label'       => __('Kitchen Mistake', 'kuchnia-twist'),
                    'instruction' => __('Focus on a common mistake, why it happens, and what to do instead.', 'kuchnia-twist'),
                ],
                'smarter_shortcut' => [
                    'label'       => __('Smarter Shortcut', 'kuchnia-twist'),
                    'instruction' => __('Offer a clearer, simpler, or smarter way to handle the topic in a home kitchen.', 'kuchnia-twist'),
                ],
                'what_most_people_get_wrong' => [
                    'label'       => __('What Most People Get Wrong', 'kuchnia-twist'),
                    'instruction' => __('Make the angle about the exact misunderstanding most readers carry into the kitchen.', 'kuchnia-twist'),
                ],
                'ingredient_truth' => [
                    'label'       => __('Ingredient Truth', 'kuchnia-twist'),
                    'instruction' => __('Explain what an ingredient really does and why that matters in practice.', 'kuchnia-twist'),
                ],
                'changes_how_you_cook_it' => [
                    'label'       => __('Changes How You Cook It', 'kuchnia-twist'),
                    'instruction' => __('Make the payoff feel like a concrete shift in how the reader will cook after learning this.', 'kuchnia-twist'),
                ],
                'restaurant_vs_home' => [
                    'label'       => __('Restaurant vs Home', 'kuchnia-twist'),
                    'instruction' => __('Contrast restaurant assumptions with what really works in a normal home kitchen.', 'kuchnia-twist'),
                ],
            ],
        ];

        return $presets[$content_type] ?? $presets['recipe'];
    }

    private function all_social_angle_presets(): array
    {
        return array_merge(
            $this->social_angle_presets('recipe'),
            $this->social_angle_presets('food_fact')
        );
    }

    private function normalize_hook_angle_key(string $value, string $content_type = ''): string
    {
        $value = sanitize_key($value);
        $presets = $content_type !== '' ? $this->social_angle_presets($content_type) : $this->all_social_angle_presets();
        return isset($presets[$value]) ? $value : '';
    }

    private function hook_angle_label(string $angle_key, string $content_type = 'recipe'): string
    {
        $angle_key = $this->normalize_hook_angle_key($angle_key, $content_type);
        $presets = $content_type !== '' ? $this->social_angle_presets($content_type) : $this->all_social_angle_presets();
        return $angle_key !== '' && !empty($presets[$angle_key]['label'])
            ? (string) $presets[$angle_key]['label']
            : __('Auto rotate', 'kuchnia-twist');
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


    private function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'kuchnia_twist_jobs';
    }

    private function events_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'kuchnia_twist_job_events';
    }

    private function ideas_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'kuchnia_twist_recipe_ideas';
    }
}
