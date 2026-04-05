<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/launch-content.php';

final class Kuchnia_Twist_Publisher
{
    private const VERSION = '1.3.3';
    private const OPTION_KEY = 'kuchnia_twist_settings';
    private const VERSION_KEY = 'kuchnia_twist_publisher_version';
    private const THEME_BOOTSTRAP_KEY = 'kuchnia_twist_theme_bootstrapped';
    private const WORKER_STATUS_KEY = 'kuchnia_twist_worker_status';
    private const CORE_PAGE_SEED_HASH_META = '_kuchnia_twist_core_page_seed_hash';

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
        add_action('init', [$this, 'maybe_bootstrap'], 1);
        add_action('init', [$this, 'register_shortcodes'], 2);
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_kuchnia_twist_create_job', [$this, 'handle_create_job']);
        add_action('admin_post_kuchnia_twist_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_kuchnia_twist_retry_job', [$this, 'handle_retry_job']);
        add_action('admin_post_kuchnia_twist_export_jobs', [$this, 'handle_export_jobs']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
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
    }

    public function register_shortcodes(): void
    {
        add_shortcode('kuchnia_twist_editor_name', [$this, 'shortcode_editor_name']);
        add_shortcode('kuchnia_twist_editor_role', [$this, 'shortcode_editor_role']);
        add_shortcode('kuchnia_twist_editor_public_email', [$this, 'shortcode_editor_public_email']);
        add_shortcode('kuchnia_twist_editor_business_email', [$this, 'shortcode_editor_business_email']);
        add_shortcode('kuchnia_twist_link', [$this, 'shortcode_internal_link']);
    }

    public function register_admin_pages(): void
    {
        add_menu_page(
            __('Kuchnia Twist', 'kuchnia-twist'),
            __('Kuchnia Twist', 'kuchnia-twist'),
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
        if (!in_array($hook, ['toplevel_page_kuchnia-twist-publisher', 'kuchnia-twist_page_kuchnia-twist-settings'], true)) {
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

    public function register_rest_routes(): void
    {
        register_rest_route('kuchnia-twist/v1', '/jobs/claim', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_claim_job'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/jobs/(?P<id>\d+)/media', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_upload_media'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/jobs/(?P<id>\d+)/publish-blog', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_publish_blog'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/jobs/(?P<id>\d+)/progress', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_progress_job'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/jobs/(?P<id>\d+)/complete', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_complete_job'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/jobs/(?P<id>\d+)/fail', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_fail_job'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('kuchnia-twist/v1', '/worker/heartbeat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_worker_heartbeat'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function render_publisher_page(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'kuchnia-twist'));
        }

        $settings      = $this->get_settings();
        $topics        = $this->parse_topics($settings['topics_text']);
        $job_filters   = $this->job_filters_from_request();
        $pagination    = $this->job_pagination_from_request();
        $job_page      = $this->get_jobs_page($job_filters, $pagination['page'], $pagination['per_page']);
        $jobs          = $job_page['items'];
        $selected_id   = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
        $selected      = $this->resolve_selected_job($jobs, $selected_id);
        $counts        = $this->get_dashboard_counts();
        $notice_key    = isset($_GET['kt_notice']) ? sanitize_key(wp_unslash($_GET['kt_notice'])) : '';
        $manual_only   = $settings['image_generation_mode'] === 'manual_only';
        $system_status = $this->system_status_snapshot($settings);
        $export_url    = $this->export_jobs_url($job_filters);
        $worker_last_job = !empty($system_status['last_job_id']) ? $this->get_job((int) $system_status['last_job_id']) : null;
        $auto_refresh_seconds = ($counts['queued'] + $counts['running']) > 0 ? 20 : 0;
        ?>
        <div class="wrap kt-admin"<?php echo $auto_refresh_seconds > 0 ? ' data-auto-refresh-seconds="' . esc_attr((string) $auto_refresh_seconds) . '"' : ''; ?>>
            <div class="kt-page-head">
                <div>
                    <h1><?php esc_html_e('Publisher', 'kuchnia-twist'); ?></h1>
                    <p><?php esc_html_e('Queue articles, watch the pipeline, and jump back into anything that needs attention.', 'kuchnia-twist'); ?></p>
                </div>
                <div class="kt-head-actions">
                    <?php if ($auto_refresh_seconds > 0) : ?>
                        <button type="button" class="button kt-auto-refresh-toggle" data-seconds="<?php echo esc_attr((string) $auto_refresh_seconds); ?>"><?php esc_html_e('Pause Auto Refresh', 'kuchnia-twist'); ?></button>
                        <span class="kt-head-status" data-auto-refresh-label><?php echo esc_html(sprintf(__('Refreshing every %ds while jobs are active.', 'kuchnia-twist'), $auto_refresh_seconds)); ?></span>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kuchnia-twist-settings')); ?>"><?php esc_html_e('Open Settings', 'kuchnia-twist'); ?></a>
                </div>
            </div>
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
                <section class="kt-card kt-card--composer">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Create Job', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Build one publish-ready queue item.', 'kuchnia-twist'); ?></p>
                        </div>
                        <span class="kt-mode-pill <?php echo esc_attr($manual_only ? 'is-manual' : 'is-flex'); ?>">
                            <?php echo $manual_only ? esc_html__('Manual only', 'kuchnia-twist') : esc_html__('AI fallback', 'kuchnia-twist'); ?>
                        </span>
                    </div>
                    <div class="kt-requirements" aria-label="<?php esc_attr_e('Queue requirements', 'kuchnia-twist'); ?>">
                        <?php if ($manual_only) : ?>
                            <span class="kt-requirement-pill"><?php esc_html_e('Title required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Blog image required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Facebook image required', 'kuchnia-twist'); ?></span>
                        <?php else : ?>
                            <span class="kt-requirement-pill"><?php esc_html_e('Topic required', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Images optional', 'kuchnia-twist'); ?></span>
                            <span class="kt-requirement-pill"><?php esc_html_e('Queue publishes both channels', 'kuchnia-twist'); ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="kt-form">
                        <?php wp_nonce_field('kuchnia_twist_create_job'); ?>
                        <input type="hidden" name="action" value="kuchnia_twist_create_job">
                        <div class="kt-field-grid">
                            <label>
                                <span><?php esc_html_e('Topic', 'kuchnia-twist'); ?></span>
                                <select name="topic" required>
                                    <?php foreach ($topics as $topic) : ?>
                                        <option value="<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('Content Type', 'kuchnia-twist'); ?></span>
                                <select name="content_type" required>
                                    <?php foreach ($this->content_types() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="kt-field-span-full">
                                <span><?php echo $manual_only ? esc_html__('Final Title', 'kuchnia-twist') : esc_html__('Title Override', 'kuchnia-twist'); ?></span>
                                <input type="text" name="title_override" <?php echo $manual_only ? 'required' : ''; ?> placeholder="<?php echo esc_attr($manual_only ? __('Required during manual-only launch mode', 'kuchnia-twist') : __('Leave empty to let AI generate it', 'kuchnia-twist')); ?>">
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
                        <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Queue Job', 'kuchnia-twist'); ?></button>
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
                            <?php foreach ($this->content_types() as $value => $label) : ?>
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
                    <div class="kt-filter-pills" role="tablist" aria-label="<?php esc_attr_e('Job filters', 'kuchnia-twist'); ?>">
                        <?php foreach ($this->job_filter_options() as $value => $label) : ?>
                            <a
                                class="kt-filter-pill<?php echo $job_filters['status_group'] === $value ? ' is-active' : ''; ?>"
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
                    </div>
                    <div class="kt-toolbar-actions">
                        <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'kuchnia-twist'); ?></a>
                        <button type="submit" class="button"><?php esc_html_e('Apply', 'kuchnia-twist'); ?></button>
                        <?php if ($job_filters['search'] !== '' || $job_filters['status_group'] !== 'all' || $job_filters['content_type'] !== '') : ?>
                            <a class="button button-link" href="<?php echo esc_url($this->publisher_page_url(['job_per_page' => $job_page['per_page']])); ?>"><?php esc_html_e('Clear', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($jobs) : ?>
                    <div class="kt-job-list" role="list">
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
                            ?>
                            <article
                                class="kt-job-row<?php echo ($selected && (int) $selected['id'] === (int) $job['id']) ? ' is-selected' : ''; ?>"
                                data-href="<?php echo esc_url($job_url); ?>"
                                tabindex="0"
                                role="listitem"
                            >
                                <div class="kt-job-row__main">
                                    <div class="kt-job-row__topline">
                                        <h3><?php echo esc_html($job['topic']); ?></h3>
                                        <span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($this->format_human_label($job['status'])); ?></span>
                                    </div>
                                    <div class="kt-job-row__meta">
                                        <span><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></span>
                                        <span><?php echo esc_html(sprintf(__('Updated %s', 'kuchnia-twist'), $this->format_admin_datetime($job['updated_at']))); ?></span>
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
        ?>
        <div class="wrap kt-admin">
            <div class="kt-page-head">
                <div>
                    <h1><?php esc_html_e('Publishing Settings', 'kuchnia-twist'); ?></h1>
                    <p><?php esc_html_e('Editorial defaults, identity, distribution, and launch policy in one place.', 'kuchnia-twist'); ?></p>
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
                            <h2><?php esc_html_e('Editorial', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Topics, voice, prompt guidance, and CTA defaults.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label class="kt-field-span-full"><span><?php esc_html_e('Topics (one per line)', 'kuchnia-twist'); ?></span><textarea name="topics_text" rows="8"><?php echo esc_textarea($settings['topics_text']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Brand Voice', 'kuchnia-twist'); ?></span><textarea name="brand_voice" rows="4"><?php echo esc_textarea($settings['brand_voice']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Article Prompt Guidance', 'kuchnia-twist'); ?></span><textarea name="article_prompt" rows="6"><?php echo esc_textarea($settings['article_prompt']); ?></textarea></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Default CTA Text', 'kuchnia-twist'); ?></span><input type="text" name="default_cta" value="<?php echo esc_attr($settings['default_cta']); ?>"></label>
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
                    <div class="kt-media-field">
                        <span><?php esc_html_e('Editor Portrait', 'kuchnia-twist'); ?></span>
                        <input type="hidden" name="editor_photo_id" value="<?php echo (int) $settings['editor_photo_id']; ?>">
                        <div class="kt-media-preview">
                            <?php if (!empty($settings['editor_photo_id'])) : ?>
                                <?php echo wp_get_attachment_image((int) $settings['editor_photo_id'], 'thumbnail'); ?>
                            <?php else : ?>
                                <p><?php esc_html_e('No portrait selected.', 'kuchnia-twist'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="kt-media-actions">
                            <button type="button" class="button kt-media-select" data-target='{"input":"[name=\"editor_photo_id\"]","preview":".kt-media-preview"}'><?php esc_html_e('Choose Portrait', 'kuchnia-twist'); ?></button>
                            <button type="button" class="button-link-delete kt-media-clear"><?php esc_html_e('Remove portrait', 'kuchnia-twist'); ?></button>
                        </div>
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
                            <h2><?php esc_html_e('Generation', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Text, image, and prompt controls used by the pipeline.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label><span><?php esc_html_e('Text Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_model" value="<?php echo esc_attr($settings['openai_model']); ?>"></label>
                        <label><span><?php esc_html_e('Image Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_image_model" value="<?php echo esc_attr($settings['openai_image_model']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('API Key', 'kuchnia-twist'); ?></span><input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Image Style Guidance', 'kuchnia-twist'); ?></span><textarea name="image_style" rows="4"><?php echo esc_textarea($settings['image_style']); ?></textarea></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Distribution', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Facebook publishing credentials and link tracking defaults.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <div class="kt-field-grid">
                        <label><span><?php esc_html_e('Graph API Version', 'kuchnia-twist'); ?></span><input type="text" name="facebook_graph_version" value="<?php echo esc_attr($settings['facebook_graph_version']); ?>"></label>
                        <label><span><?php esc_html_e('Facebook Page ID', 'kuchnia-twist'); ?></span><input type="text" name="facebook_page_id" value="<?php echo esc_attr($settings['facebook_page_id']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Facebook Page Access Token', 'kuchnia-twist'); ?></span><textarea name="facebook_page_access_token" rows="4"><?php echo esc_textarea($settings['facebook_page_access_token']); ?></textarea></label>
                        <label><span><?php esc_html_e('UTM Source', 'kuchnia-twist'); ?></span><input type="text" name="utm_source" value="<?php echo esc_attr($settings['utm_source']); ?>"></label>
                        <label><span><?php esc_html_e('UTM Campaign Prefix', 'kuchnia-twist'); ?></span><input type="text" name="utm_campaign_prefix" value="<?php echo esc_attr($settings['utm_campaign_prefix']); ?>"></label>
                    </div>
                </section>

                <section class="kt-card">
                    <div class="kt-card-head">
                        <div>
                            <h2><?php esc_html_e('Launch Policy', 'kuchnia-twist'); ?></h2>
                            <p><?php esc_html_e('Control whether publish jobs require real uploaded media.', 'kuchnia-twist'); ?></p>
                        </div>
                    </div>
                    <label>
                        <span><?php esc_html_e('Image Generation Mode', 'kuchnia-twist'); ?></span>
                        <select name="image_generation_mode">
                            <option value="manual_only" <?php selected($settings['image_generation_mode'], 'manual_only'); ?>><?php esc_html_e('Manual only', 'kuchnia-twist'); ?></option>
                            <option value="ai_fallback" <?php selected($settings['image_generation_mode'], 'ai_fallback'); ?>><?php esc_html_e('AI fallback', 'kuchnia-twist'); ?></option>
                        </select>
                    </label>
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

        $settings      = $this->get_settings();
        $allowed_types = array_keys($this->content_types());
        $topic         = sanitize_text_field(wp_unslash($_POST['topic'] ?? ''));
        $content_type  = sanitize_key(wp_unslash($_POST['content_type'] ?? 'recipe'));
        $title         = sanitize_text_field(wp_unslash($_POST['title_override'] ?? ''));

        if ($topic === '' || !in_array($content_type, $allowed_types, true)) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=invalid_job'));
            exit;
        }

        $blog_image_id     = $this->handle_media_upload('blog_image');
        $facebook_image_id = $this->handle_media_upload('facebook_image');

        if ($settings['image_generation_mode'] === 'manual_only') {
            if ($title === '') {
                wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=launch_title_required'));
                exit;
            }

            if (!$blog_image_id || !$facebook_image_id) {
                wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=launch_assets_required'));
                exit;
            }
        }

        $title_candidate = $title !== '' ? $title : $topic;
        if ($this->find_conflicting_post_id($title_candidate) > 0) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&kt_notice=existing_post_conflict'));
            exit;
        }

        global $wpdb;
        $duplicate_job_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                FROM {$this->table_name()}
                WHERE topic = %s
                  AND content_type = %s
                  AND title_override = %s
                  AND created_by = %d
                  AND status IN ('queued', 'generating', 'publishing_blog', 'publishing_facebook')
                  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
                ORDER BY id DESC
                LIMIT 1",
                $topic,
                $content_type,
                $title,
                get_current_user_id()
            )
        );

        if ($duplicate_job_id > 0) {
            wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $duplicate_job_id . '&kt_notice=duplicate_job'));
            exit;
        }

        $payload = [
            'topic'             => $topic,
            'content_type'      => $content_type,
            'title_override'    => $title,
            'blog_image_id'     => $blog_image_id,
            'facebook_image_id' => $facebook_image_id,
            'blog_image'        => $this->attachment_payload($blog_image_id),
            'facebook_image'    => $this->attachment_payload($facebook_image_id),
            'site_name'         => get_bloginfo('name'),
            'default_cta'       => $settings['default_cta'],
        ];

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
                'content_type'      => $content_type,
                'created_by'        => get_current_user_id(),
                'blog_image_id'     => $blog_image_id ?: 0,
                'facebook_image_id' => $facebook_image_id ?: 0,
            ]
        );

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $job_id . '&kt_notice=job_created'));
        exit;
    }

    public function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_save_settings');

        $current  = $this->get_settings();
        $incoming = [
            'topics_text'                => trim((string) wp_unslash($_POST['topics_text'] ?? '')),
            'brand_voice'                => trim((string) wp_unslash($_POST['brand_voice'] ?? '')),
            'article_prompt'             => trim((string) wp_unslash($_POST['article_prompt'] ?? '')),
            'default_cta'                => sanitize_text_field(wp_unslash($_POST['default_cta'] ?? '')),
            'editor_name'                => sanitize_text_field(wp_unslash($_POST['editor_name'] ?? '')),
            'editor_role'                => sanitize_text_field(wp_unslash($_POST['editor_role'] ?? '')),
            'editor_bio'                 => trim((string) wp_unslash($_POST['editor_bio'] ?? '')),
            'editor_public_email'        => sanitize_email((string) wp_unslash($_POST['editor_public_email'] ?? '')),
            'editor_business_email'      => sanitize_email((string) wp_unslash($_POST['editor_business_email'] ?? '')),
            'editor_photo_id'            => absint($_POST['editor_photo_id'] ?? 0),
            'social_instagram_url'       => esc_url_raw(trim((string) wp_unslash($_POST['social_instagram_url'] ?? ''))),
            'social_facebook_url'        => esc_url_raw(trim((string) wp_unslash($_POST['social_facebook_url'] ?? ''))),
            'social_pinterest_url'       => esc_url_raw(trim((string) wp_unslash($_POST['social_pinterest_url'] ?? ''))),
            'social_tiktok_url'          => esc_url_raw(trim((string) wp_unslash($_POST['social_tiktok_url'] ?? ''))),
            'social_follow_label'        => sanitize_text_field(wp_unslash($_POST['social_follow_label'] ?? '')),
            'openai_model'               => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? '')),
            'openai_image_model'         => sanitize_text_field(wp_unslash($_POST['openai_image_model'] ?? '')),
            'openai_api_key'             => trim((string) wp_unslash($_POST['openai_api_key'] ?? '')),
            'image_style'                => trim((string) wp_unslash($_POST['image_style'] ?? '')),
            'image_generation_mode'      => $this->sanitize_image_generation_mode(wp_unslash($_POST['image_generation_mode'] ?? 'manual_only')),
            'facebook_graph_version'     => sanitize_text_field(wp_unslash($_POST['facebook_graph_version'] ?? '')),
            'facebook_page_id'           => sanitize_text_field(wp_unslash($_POST['facebook_page_id'] ?? '')),
            'facebook_page_access_token' => trim((string) wp_unslash($_POST['facebook_page_access_token'] ?? '')),
            'utm_source'                 => sanitize_key(wp_unslash($_POST['utm_source'] ?? 'facebook')),
            'utm_campaign_prefix'        => sanitize_key(wp_unslash($_POST['utm_campaign_prefix'] ?? 'kuchnia-twist')),
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

        $retry_target = 'full';
        if (!empty($job['post_id'])) {
            $retry_target = empty($job['facebook_post_id']) ? 'facebook' : 'comment';
        }

        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'        => 'queued',
                'stage'         => 'queued',
                'retry_target'  => $retry_target,
                'error_message' => null,
                'updated_at'    => current_time('mysql', true),
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->add_job_event(
            $job_id,
            'retry_queued',
            'queued',
            'queued',
            __('Job queued for retry.', 'kuchnia-twist'),
            ['retry_target' => $retry_target]
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

    public function rest_claim_job(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        global $wpdb;
        $table = $this->table_name();
        $job   = $wpdb->get_row("SELECT * FROM {$table} WHERE status = 'queued' ORDER BY id ASC LIMIT 1", ARRAY_A);

        if (!$job) {
            return rest_ensure_response(['job' => null]);
        }

        $wpdb->update(
            $table,
            [
                'status'          => 'generating',
                'stage'           => 'generating',
                'last_attempt_at' => current_time('mysql', true),
                'updated_at'      => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->add_job_event(
            (int) $job['id'],
            'job_claimed',
            'generating',
            'generating',
            __('Worker claimed queued job.', 'kuchnia-twist'),
            ['last_attempt_at' => current_time('mysql', true)]
        );

        $settings = $this->get_settings();

        return rest_ensure_response([
            'job'      => $this->get_job((int) $job['id']),
            'settings' => [
                'site_name'                  => get_bloginfo('name'),
                'site_url'                   => home_url('/'),
                'brand_voice'                => $settings['brand_voice'],
                'article_prompt'             => $settings['article_prompt'],
                'default_cta'                => $settings['default_cta'],
                'image_style'                => $settings['image_style'],
                'image_generation_mode'      => $settings['image_generation_mode'],
                'facebook_graph_version'     => $settings['facebook_graph_version'],
                'facebook_page_id'           => $settings['facebook_page_id'],
                'facebook_page_access_token' => $settings['facebook_page_access_token'],
                'openai_model'               => $settings['openai_model'],
                'openai_image_model'         => $settings['openai_image_model'],
                'openai_api_key'             => getenv('OPENAI_API_KEY') ?: $settings['openai_api_key'],
                'openai_base_url'            => getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
                'utm_source'                 => $settings['utm_source'],
                'utm_campaign_prefix'        => $settings['utm_campaign_prefix'],
            ],
        ]);
    }

    public function rest_progress_job(WP_REST_Request $request)
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
        global $wpdb;

        $status = sanitize_key($params['status'] ?? $job['status']);
        $stage  = sanitize_key($params['stage'] ?? $status);

        $wpdb->update(
            $this->table_name(),
            [
                'status'        => $status,
                'stage'         => $stage,
                'error_message' => !empty($params['error_message']) ? (string) $params['error_message'] : null,
                'updated_at'    => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $message = !empty($params['error_message'])
            ? (string) $params['error_message']
            : sprintf(__('Job moved to %s.', 'kuchnia-twist'), $this->format_human_label($stage));

        $this->add_job_event(
            (int) $job['id'],
            'progress_update',
            $status,
            $stage,
            $message,
            []
        );

        return rest_ensure_response(['ok' => true]);
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

        $params         = $request->get_json_params();
        $content_type   = sanitize_key($params['content_type'] ?? $job['content_type']);
        $featured_image = (int) ($params['featured_image_id'] ?? 0);
        $facebook_image = (int) ($params['facebook_image_id'] ?? 0);
        $generated      = is_array($params['generated_payload'] ?? null) ? $params['generated_payload'] : [];

        $validation_error = $this->validate_generated_publish_payload($params, $generated, $job);
        if ($validation_error instanceof WP_Error) {
            return $validation_error;
        }

        $post_data = [
            'post_type'     => 'post',
            'post_status'   => 'publish',
            'post_title'    => sanitize_text_field($params['title'] ?? ''),
            'post_excerpt'  => sanitize_textarea_field($params['excerpt'] ?? ''),
            'post_content'  => wp_kses_post($params['content_html'] ?? ''),
            'post_author'   => !empty($job['created_by']) ? (int) $job['created_by'] : get_current_user_id(),
            'post_name'     => sanitize_title($params['slug'] ?? ''),
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
        update_post_meta($post_id, 'kuchnia_twist_facebook_caption', (string) ($params['facebook_caption'] ?? ''));
        update_post_meta($post_id, 'kuchnia_twist_group_share_kit', (string) ($params['group_share_kit'] ?? ''));
        update_post_meta($post_id, 'kuchnia_twist_recipe_data', $generated['recipe'] ?? []);
        update_post_meta($post_id, 'kuchnia_twist_seo_description', (string) ($params['seo_description'] ?? ''));

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
                'generated_payload'        => wp_json_encode($generated),
                'facebook_caption'         => (string) ($params['facebook_caption'] ?? ''),
                'group_share_kit'          => (string) ($params['group_share_kit'] ?? ''),
                'updated_at'               => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'],
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
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'              => sanitize_key($params['status'] ?? 'completed'),
                'stage'               => sanitize_key($params['status'] ?? 'completed'),
                'facebook_post_id'    => sanitize_text_field($params['facebook_post_id'] ?? ''),
                'facebook_comment_id' => sanitize_text_field($params['facebook_comment_id'] ?? ''),
                'facebook_caption'    => (string) ($params['facebook_caption'] ?? $job['facebook_caption']),
                'group_share_kit'     => (string) ($params['group_share_kit'] ?? $job['group_share_kit']),
                'generated_payload'   => wp_json_encode($params['generated_payload'] ?? $job['generated_payload']),
                'error_message'       => !empty($params['error_message']) ? (string) $params['error_message'] : null,
                'updated_at'          => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $final_status = sanitize_key($params['status'] ?? 'completed');
        $this->add_job_event(
            (int) $job['id'],
            'job_completed',
            $final_status,
            $final_status,
            __('Job completed successfully.', 'kuchnia-twist'),
            [
                'facebook_post_id'    => sanitize_text_field($params['facebook_post_id'] ?? ''),
                'facebook_comment_id' => sanitize_text_field($params['facebook_comment_id'] ?? ''),
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
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            [
                'status'              => sanitize_key($params['status'] ?? 'failed'),
                'stage'               => sanitize_key($params['stage'] ?? 'failed'),
                'facebook_post_id'    => sanitize_text_field($params['facebook_post_id'] ?? $job['facebook_post_id']),
                'facebook_comment_id' => sanitize_text_field($params['facebook_comment_id'] ?? $job['facebook_comment_id']),
                'generated_payload'   => wp_json_encode($params['generated_payload'] ?? $job['generated_payload']),
                'facebook_caption'    => (string) ($params['facebook_caption'] ?? $job['facebook_caption']),
                'group_share_kit'     => (string) ($params['group_share_kit'] ?? $job['group_share_kit']),
                'error_message'       => (string) ($params['error_message'] ?? __('Unknown job failure.', 'kuchnia-twist')),
                'updated_at'          => current_time('mysql', true),
            ],
            ['id' => (int) $job['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        $failed_status = sanitize_key($params['status'] ?? 'failed');
        $failed_stage  = sanitize_key($params['stage'] ?? 'failed');
        $message       = (string) ($params['error_message'] ?? __('Unknown job failure.', 'kuchnia-twist'));

        $this->add_job_event(
            (int) $job['id'],
            'job_failed',
            $failed_status,
            $failed_stage,
            $message,
            [
                'facebook_post_id'    => sanitize_text_field($params['facebook_post_id'] ?? $job['facebook_post_id']),
                'facebook_comment_id' => sanitize_text_field($params['facebook_comment_id'] ?? $job['facebook_comment_id']),
                'retry_target'        => (string) ($job['retry_target'] ?? ''),
            ]
        );

        return rest_ensure_response(['ok' => true]);
    }

    public function rest_worker_heartbeat(WP_REST_Request $request)
    {
        $auth = $this->authorize_worker($request);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $payload = $request->get_json_params();
        $status = [
            'worker_version'         => sanitize_text_field($payload['worker_version'] ?? ''),
            'enabled'                => !empty($payload['enabled']),
            'run_once'               => !empty($payload['run_once']),
            'poll_seconds'           => max(0, (int) ($payload['poll_seconds'] ?? 0)),
            'startup_delay_seconds'  => max(0, (int) ($payload['startup_delay_seconds'] ?? 0)),
            'config_ok'              => !empty($payload['config_ok']),
            'last_seen_at'           => current_time('mysql', true),
            'last_loop_result'       => sanitize_key($payload['last_loop_result'] ?? ''),
            'last_job_id'            => absint($payload['last_job_id'] ?? 0),
            'last_job_status'        => sanitize_key($payload['last_job_status'] ?? ''),
            'last_error'             => sanitize_text_field($payload['last_error'] ?? ''),
        ];

        update_option(self::WORKER_STATUS_KEY, wp_parse_args($status, $this->default_worker_status()), false);

        if ($status['last_job_id'] > 0 && ($status['last_error'] !== '' || !$status['config_ok'])) {
            $this->add_job_event(
                $status['last_job_id'],
                !$status['config_ok'] ? 'worker_config_warning' : 'worker_warning',
                $status['last_job_status'],
                $status['last_loop_result'],
                $status['last_error'] !== '' ? $status['last_error'] : __('Worker configuration warning received.', 'kuchnia-twist'),
                [
                    'worker_version' => $status['worker_version'],
                    'loop_result'    => $status['last_loop_result'],
                ]
            );
        }

        return rest_ensure_response(['ok' => true]);
    }

    private function install(): void
    {
        global $wpdb;
        $table             = $this->table_name();
        $events_table      = $this->events_table_name();
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
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                last_attempt_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_uuid (job_uuid),
                KEY status_created (status, created_at)
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
            update_option('blogname', 'Kuchnia Twist');
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
        $launch_posts = kuchnia_twist_launch_posts();
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
            'kuchnia twist is a food journal built around three pillars',
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
        $sql = "SELECT id, topic, content_type, status, stage, created_at, last_attempt_at, updated_at, created_by, post_id, permalink, facebook_post_id, facebook_comment_id, retry_target, error_message FROM {$this->table_name()} {$where_sql} ORDER BY id DESC";

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
        return array_key_exists($value, $this->content_types()) ? $value : '';
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
            'active'    => ['queued', 'generating', 'publishing_blog', 'publishing_facebook'],
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
            'active'     => (int) (($counts['queued'] ?? 0) + ($counts['running'] ?? 0)),
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
        $facebook_ready    = trim((string) $settings['facebook_page_id']) !== '' && trim((string) $settings['facebook_page_access_token']) !== '';
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
            ? __('Facebook Page ID and access token are configured.', 'kuchnia-twist')
            : __('Facebook Page ID or access token is missing. Blog publication can still succeed and stop at partial failure for Facebook.', 'kuchnia-twist');

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
            ['queued', 'generating', 'publishing_blog', 'publishing_facebook'],
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
        $row['request_payload']          = $this->decode_json($row['request_payload']);
        $row['generated_payload']        = $this->decode_json($row['generated_payload']);
        $row['blog_image']               = $this->attachment_payload($row['blog_image_id']);
        $row['facebook_image']           = $this->attachment_payload($row['facebook_image_id']);
        $row['featured_image']           = $this->attachment_payload($row['featured_image_id']);
        $row['facebook_image_result']    = $this->attachment_payload($row['facebook_image_result_id']);
        return $row;
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

    private function sanitize_image_generation_mode($value): string
    {
        return in_array((string) $value, ['manual_only', 'ai_fallback'], true) ? (string) $value : 'manual_only';
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

    private function validate_generated_publish_payload(array $params, array $generated, array $job): ?WP_Error
    {
        $settings       = $this->get_settings();
        $title          = sanitize_text_field($params['title'] ?? '');
        $slug           = sanitize_title($params['slug'] ?? '');
        $excerpt        = sanitize_text_field($params['excerpt'] ?? '');
        $seo_description = sanitize_text_field($params['seo_description'] ?? '');
        $content_html   = (string) ($params['content_html'] ?? '');
        $content_type   = sanitize_key($params['content_type'] ?? $job['content_type']);
        $featured_image = (int) ($params['featured_image_id'] ?? 0);
        $facebook_image = (int) ($params['facebook_image_id'] ?? 0);
        $word_count     = str_word_count(wp_strip_all_tags($content_html));
        $minimum_words  = [
            'recipe'     => 1200,
            'food_fact'  => 1100,
            'food_story' => 1100,
        ][$content_type] ?? 1100;

        if ($title === '' || $slug === '' || trim(wp_strip_all_tags($content_html)) === '') {
            return new WP_Error('invalid_generated_post', __('The worker payload was missing a title, slug, or article body.', 'kuchnia-twist'), ['status' => 400]);
        }

        if ($settings['image_generation_mode'] === 'manual_only' && (!$featured_image || !$facebook_image)) {
            return new WP_Error('launch_media_required', __('Manual-only launch mode requires both real uploaded blog and Facebook images before publish.', 'kuchnia-twist'), ['status' => 400]);
        }

        if ($this->find_conflicting_post_id($slug, (int) ($job['post_id'] ?? 0)) > 0 || $this->find_conflicting_post_id($title, (int) ($job['post_id'] ?? 0)) > 0) {
            return new WP_Error('duplicate_post', __('A post with the same title or slug already exists, so this generated article was blocked.', 'kuchnia-twist'), ['status' => 409]);
        }

        if ($word_count < $minimum_words) {
            return new WP_Error('thin_content', sprintf(__('The generated article body was too short for launch quality standards. Minimum words for %s is %d.', 'kuchnia-twist'), $content_type, $minimum_words), ['status' => 400]);
        }

        if (substr_count(strtolower($content_html), '<h2') < 2) {
            return new WP_Error('weak_structure', __('The generated article needs at least two H2 sections before it can publish.', 'kuchnia-twist'), ['status' => 400]);
        }

        if (str_word_count($excerpt) < 12) {
            return new WP_Error('weak_excerpt', __('The generated excerpt was too thin for a launch-quality archive card.', 'kuchnia-twist'), ['status' => 400]);
        }

        if (str_word_count($seo_description) < 12) {
            return new WP_Error('weak_seo', __('The generated SEO description was too thin for launch quality standards.', 'kuchnia-twist'), ['status' => 400]);
        }

        $opening = strtolower(trim(wp_strip_all_tags((string) preg_split('/<\/p>/i', $content_html, 2)[0])));
        $blocked_phrases = [
            'lorem ipsum',
            'in today',
            'when it comes to',
            'this article explores',
            'whether you are',
            'few things are as',
            'as an ai',
            'generated by ai',
        ];

        foreach ($blocked_phrases as $phrase) {
            if ($phrase !== '' && strpos($opening, $phrase) !== false) {
                return new WP_Error('generic_opening', __('The generated article opened with placeholder or generic phrasing and was blocked before publish.', 'kuchnia-twist'), ['status' => 400]);
            }
        }

        if ($this->count_internal_links($content_html) < 3) {
            return new WP_Error('missing_internal_links', __('The generated article did not include enough internal Kuchnia Twist links.', 'kuchnia-twist'), ['status' => 400]);
        }

        if ($content_type === 'recipe') {
            $recipe = is_array($generated['recipe'] ?? null) ? $generated['recipe'] : [];
            if (empty($recipe['ingredients']) || empty($recipe['instructions'])) {
                return new WP_Error('missing_recipe', __('Recipe posts must include complete ingredients and instructions.', 'kuchnia-twist'), ['status' => 400]);
            }
        }

        return null;
    }

    private function count_internal_links(string $content_html): int
    {
        $shortcodes = preg_match_all('/\[kuchnia_twist_link\s+slug=/i', $content_html);
        $anchors    = preg_match_all('/<a\s+[^>]*href=/i', $content_html);

        return (int) $shortcodes + (int) $anchors;
    }

    private function render_notice(string $notice_key): void
    {
        $messages = [
            'job_created'   => __('Publishing job queued. The background worker will pick it up shortly.', 'kuchnia-twist'),
            'job_queued'    => __('Job queued again for processing.', 'kuchnia-twist'),
            'job_missing'   => __('The selected job could not be found.', 'kuchnia-twist'),
            'invalid_job'   => __('Please select a valid topic and content type.', 'kuchnia-twist'),
            'duplicate_job' => __('A matching job is already in progress, so the existing one was kept instead of creating a duplicate.', 'kuchnia-twist'),
            'existing_post_conflict' => __('A published or queued post with the same topic/title already exists, so the duplicate launch article was blocked.', 'kuchnia-twist'),
            'launch_title_required' => __('Launch mode requires a final title before a job can be queued.', 'kuchnia-twist'),
            'launch_assets_required' => __('Launch mode requires both a real blog hero image and a real Facebook image.', 'kuchnia-twist'),
        ];

        if (!isset($messages[$notice_key])) {
            return;
        }

        $class = in_array($notice_key, ['launch_title_required', 'launch_assets_required', 'invalid_job', 'existing_post_conflict'], true) ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($messages[$notice_key]) . '</p></div>';
    }

    private function render_job_summary(array $job, array $system_status = []): void
    {
        $events = $this->get_job_events((int) $job['id'], 12);
        $event_stats = $this->get_job_event_stats((int) $job['id']);
        ?>
        <div class="kt-summary">
            <div class="kt-summary__hero">
                <div>
                    <p class="kt-summary__eyebrow"><?php echo esc_html(sprintf(__('Job #%d', 'kuchnia-twist'), (int) $job['id'])); ?></p>
                    <h3><?php echo esc_html($job['topic']); ?></h3>
                    <div class="kt-summary__meta">
                        <span><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></span>
                        <span><?php echo esc_html(sprintf(__('Updated %s', 'kuchnia-twist'), $this->format_admin_datetime($job['updated_at']))); ?></span>
                    </div>
                </div>
                <div class="kt-summary__status">
                    <span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($this->format_human_label($job['status'])); ?></span>
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
            </dl>

            <section class="kt-detail-block">
                <h4><?php esc_html_e('Request Snapshot', 'kuchnia-twist'); ?></h4>
                <div class="kt-summary-list">
                    <div>
                        <span><?php esc_html_e('Requested title', 'kuchnia-twist'); ?></span>
                        <strong><?php echo esc_html($this->job_requested_title($job)); ?></strong>
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
                </div>
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
                    </div>
                    <?php if (!empty($generated_snapshot['excerpt'])) : ?>
                        <div class="kt-generated-copy">
                            <label for="kt-generated-excerpt-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Excerpt', 'kuchnia-twist'); ?></label>
                            <textarea id="kt-generated-excerpt-<?php echo (int) $job['id']; ?>" rows="3" readonly><?php echo esc_textarea($generated_snapshot['excerpt']); ?></textarea>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($generated_snapshot['seo_description'])) : ?>
                        <div class="kt-generated-copy">
                            <label for="kt-generated-seo-<?php echo (int) $job['id']; ?>"><?php esc_html_e('SEO Description', 'kuchnia-twist'); ?></label>
                            <textarea id="kt-generated-seo-<?php echo (int) $job['id']; ?>" rows="3" readonly><?php echo esc_textarea($generated_snapshot['seo_description']); ?></textarea>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($job['permalink']) || !empty($job['facebook_post_id']) || !empty($job['facebook_comment_id'])) : ?>
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

            <?php if (!empty($job['permalink']) || in_array($job['status'], ['failed', 'partial_failure'], true)) : ?>
                <section class="kt-detail-block">
                    <h4><?php esc_html_e('Actions', 'kuchnia-twist'); ?></h4>
                    <div class="kt-inline-actions">
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
                </section>
            <?php endif; ?>

            <?php if (!empty($job['facebook_caption'])) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <label for="kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Facebook Caption', 'kuchnia-twist'); ?></label>
                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-facebook-caption-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                    </div>
                    <textarea id="kt-facebook-caption-<?php echo (int) $job['id']; ?>" rows="5" readonly><?php echo esc_textarea($job['facebook_caption']); ?></textarea>
                </section>
            <?php endif; ?>

            <?php if (!empty($job['group_share_kit'])) : ?>
                <section class="kt-detail-block">
                    <div class="kt-detail-block__head">
                        <label for="kt-group-share-kit-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Group Share Kit', 'kuchnia-twist'); ?></label>
                        <button type="button" class="button button-small kt-copy-button" data-copy-target="#kt-group-share-kit-<?php echo (int) $job['id']; ?>"><?php esc_html_e('Copy', 'kuchnia-twist'); ?></button>
                    </div>
                    <textarea id="kt-group-share-kit-<?php echo (int) $job['id']; ?>" rows="6" readonly><?php echo esc_textarea($job['group_share_kit']); ?></textarea>
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

    private function render_job_stage_rail(array $job): void
    {
        $steps = $this->job_stage_items($job);
        if (!$steps) {
            return;
        }
        ?>
        <section class="kt-stage-rail" aria-label="<?php esc_attr_e('Job progress', 'kuchnia-twist'); ?>">
            <?php foreach ($steps as $step) : ?>
                <div class="kt-stage-step is-<?php echo esc_attr($step['state']); ?>">
                    <span class="kt-stage-step__dot" aria-hidden="true"></span>
                    <strong><?php echo esc_html($step['label']); ?></strong>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private function job_stage_items(array $job): array
    {
        $sequence = [
            'queued'             => __('Queued', 'kuchnia-twist'),
            'generating'         => __('Generating', 'kuchnia-twist'),
            'publishing_blog'    => __('WordPress', 'kuchnia-twist'),
            'publishing_facebook'=> __('Facebook', 'kuchnia-twist'),
        ];

        $stage         = sanitize_key((string) ($job['stage'] ?? $job['status'] ?? 'queued'));
        $status        = sanitize_key((string) ($job['status'] ?? 'queued'));
        $current_index = array_search($stage, array_keys($sequence), true);
        $current_index = $current_index === false ? 0 : (int) $current_index;
        $items         = [];
        $keys          = array_keys($sequence);

        foreach ($sequence as $key => $label) {
            $index = array_search($key, $keys, true);
            $index = $index === false ? 0 : (int) $index;
            $state = 'pending';

            if ($status === 'completed') {
                $state = 'complete';
            } elseif (in_array($status, ['failed', 'partial_failure'], true)) {
                if ($index < $current_index) {
                    $state = 'complete';
                } elseif ($index === $current_index) {
                    $state = 'problem';
                }
            } else {
                if ($index < $current_index) {
                    $state = 'complete';
                } elseif ($index === $current_index) {
                    $state = 'current';
                }
            }

            $items[] = [
                'key'   => $key,
                'label' => $label,
                'state' => $state,
            ];
        }

        $items[] = [
            'key'   => 'outcome',
            'label' => $status === 'completed'
                ? __('Completed', 'kuchnia-twist')
                : (in_array($status, ['failed', 'partial_failure'], true) ? __('Needs attention', 'kuchnia-twist') : __('In progress', 'kuchnia-twist')),
            'state' => $status === 'completed' ? 'complete' : (in_array($status, ['failed', 'partial_failure'], true) ? 'problem' : 'current'),
        ];

        return $items;
    }

    private function render_job_asset_badges(array $job): void
    {
        $assets = [
            'blog_image_id'         => __('Hero attached', 'kuchnia-twist'),
            'facebook_image_id'     => __('Facebook image attached', 'kuchnia-twist'),
            'featured_image_id'     => __('Featured image ready', 'kuchnia-twist'),
            'facebook_image_result_id' => __('Facebook result ready', 'kuchnia-twist'),
        ];

        foreach ($assets as $key => $label) {
            if (!empty($job[$key])) {
                echo '<span class="kt-asset-pill">' . esc_html($label) . '</span>';
            }
        }
    }

    private function job_has_media(array $job): bool
    {
        foreach (['blog_image', 'facebook_image', 'featured_image', 'facebook_image_result'] as $key) {
            if (!empty($job[$key]['url'])) {
                return true;
            }
        }

        return false;
    }

    private function render_job_media_cards(array $job): void
    {
        $items = [
            'blog_image'           => __('Queued Hero', 'kuchnia-twist'),
            'facebook_image'       => __('Queued Facebook Image', 'kuchnia-twist'),
            'featured_image'       => __('Published Featured Image', 'kuchnia-twist'),
            'facebook_image_result'=> __('Published Facebook Result', 'kuchnia-twist'),
        ];

        foreach ($items as $key => $label) {
            $media = $job[$key] ?? [];
            if (empty($media['url'])) {
                continue;
            }
            ?>
            <article class="kt-media-card">
                <img src="<?php echo esc_url($media['url']); ?>" alt="">
                <div class="kt-media-card__body">
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if (!empty($media['title'])) : ?>
                        <span><?php echo esc_html($media['title']); ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php
        }
    }

    private function job_author_label(array $job): string
    {
        if (!empty($job['created_by'])) {
            $user = get_userdata((int) $job['created_by']);
            if ($user instanceof WP_User && $user->display_name !== '') {
                return $user->display_name;
            }
        }

        return __('Unknown', 'kuchnia-twist');
    }

    private function job_requested_title(array $job): string
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $title = sanitize_text_field((string) ($request['title_override'] ?? $job['title_override'] ?? ''));

        return $title !== '' ? $title : __('AI generated title', 'kuchnia-twist');
    }

    private function job_site_label(array $job): string
    {
        $request = is_array($job['request_payload'] ?? null) ? $job['request_payload'] : [];
        $site = sanitize_text_field((string) ($request['site_name'] ?? ''));

        return $site !== '' ? $site : get_bloginfo('name');
    }

    private function job_generated_snapshot(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        if (!$generated) {
            return [];
        }

        $content_html = (string) ($generated['content_html'] ?? '');
        $word_count = $content_html !== '' ? str_word_count(wp_strip_all_tags($content_html)) : 0;

        return array_filter([
            'title'           => sanitize_text_field((string) ($generated['title'] ?? '')),
            'slug'            => sanitize_title((string) ($generated['slug'] ?? '')),
            'excerpt'         => sanitize_text_field((string) ($generated['excerpt'] ?? '')),
            'seo_description' => sanitize_text_field((string) ($generated['seo_description'] ?? '')),
            'word_count'      => $word_count,
        ]);
    }

    private function format_admin_datetime(string $datetime): string
    {
        if ($datetime === '') {
            return __('Unknown', 'kuchnia-twist');
        }

        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
    }

    private function format_human_label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function retry_link(array $job): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=kuchnia_twist_retry_job&job_id=' . (int) $job['id']), 'kuchnia_twist_retry_job');
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

    private function parse_topics(string $topics_text): array
    {
        $topics = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $topics_text)));
        return $topics ?: kuchnia_twist_launch_topics();
    }

    private function content_types(): array
    {
        return [
            'recipe'     => __('Recipe', 'kuchnia-twist'),
            'food_fact'  => __('Food Fact', 'kuchnia-twist'),
            'food_story' => __('Food Story', 'kuchnia-twist'),
        ];
    }

    private function default_settings(): array
    {
        return [
            'topics_text'                => implode("\n", kuchnia_twist_launch_topics()),
            'brand_voice'                => 'Warm, practical, calm, and editorial. The site should sound like a trusted home-cooking publication rather than a generic SEO blog.',
            'article_prompt'             => 'Write original, useful, and substantial home-cooking content with clean headings, specific guidance, and no filler. Avoid fabricated first-person memories or expert claims the publication cannot support.',
            'default_cta'                => 'Read the full article on the blog.',
            'editor_name'                => '',
            'editor_role'                => 'Founding editor',
            'editor_bio'                 => 'Kuchnia Twist is edited as a warm home-cooking journal focused on practical recipes, useful ingredient explainers, and slower story-led kitchen essays.',
            'editor_public_email'        => '',
            'editor_business_email'      => '',
            'editor_photo_id'            => 0,
            'social_instagram_url'       => '',
            'social_facebook_url'        => '',
            'social_pinterest_url'       => '',
            'social_tiktok_url'          => '',
            'social_follow_label'        => 'Follow Kuchnia Twist',
            'openai_model'               => 'gpt-5-mini',
            'openai_image_model'         => 'gpt-image-1.5',
            'openai_api_key'             => '',
            'image_style'                => 'Natural food photography, editorial light, appetizing detail, no text overlays, premium magazine look.',
            'image_generation_mode'      => 'manual_only',
            'facebook_graph_version'     => 'v22.0',
            'facebook_page_id'           => '',
            'facebook_page_access_token' => '',
            'utm_source'                 => 'facebook',
            'utm_campaign_prefix'        => 'kuchnia-twist',
        ];
    }

    private function get_settings(): array
    {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings());
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
}
