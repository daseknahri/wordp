<?php
/**
 * Plugin Name: Kuchnia Twist Publisher
 * Description: Topic-driven article and Facebook publishing workflow for Kuchnia Twist.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: kuchnia-twist
 */

defined('ABSPATH') || exit;

final class Kuchnia_Twist_Publisher
{
    private const VERSION = '1.0.0';
    private const OPTION_KEY = 'kuchnia_twist_settings';
    private const VERSION_KEY = 'kuchnia_twist_publisher_version';
    private const THEME_BOOTSTRAP_KEY = 'kuchnia_twist_theme_bootstrapped';

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
        add_action('admin_menu', [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_kuchnia_twist_create_job', [$this, 'handle_create_job']);
        add_action('admin_post_kuchnia_twist_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_kuchnia_twist_retry_job', [$this, 'handle_retry_job']);
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

        wp_enqueue_style(
            'kuchnia-twist-admin',
            plugins_url('admin.css', __FILE__),
            [],
            self::VERSION
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
    }

    public function render_publisher_page(): void
    {
        if (!current_user_can('publish_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'kuchnia-twist'));
        }

        $settings   = $this->get_settings();
        $topics     = $this->parse_topics($settings['topics_text']);
        $jobs       = $this->get_jobs(12);
        $selected   = isset($_GET['job_id']) ? $this->get_job((int) $_GET['job_id']) : ($jobs[0] ?? null);
        $notice_key = isset($_GET['kt_notice']) ? sanitize_key(wp_unslash($_GET['kt_notice'])) : '';
        ?>
        <div class="wrap kt-admin">
            <h1><?php esc_html_e('Kuchnia Twist Publisher', 'kuchnia-twist'); ?></h1>
            <?php $this->render_notice($notice_key); ?>
            <div class="kt-admin-grid">
                <section class="kt-card">
                    <h2><?php esc_html_e('Generate & Publish', 'kuchnia-twist'); ?></h2>
                    <p><?php esc_html_e('Select a topic, optionally override the title or images, and let the queue handle the article plus Facebook flow.', 'kuchnia-twist'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="kt-form">
                        <?php wp_nonce_field('kuchnia_twist_create_job'); ?>
                        <input type="hidden" name="action" value="kuchnia_twist_create_job">
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
                        <label>
                            <span><?php esc_html_e('Optional Title Override', 'kuchnia-twist'); ?></span>
                            <input type="text" name="title_override" placeholder="<?php esc_attr_e('Leave empty to let AI generate it', 'kuchnia-twist'); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Optional Blog Hero Image', 'kuchnia-twist'); ?></span>
                            <input type="file" name="blog_image" accept="image/*">
                        </label>
                        <label>
                            <span><?php esc_html_e('Optional Facebook Image', 'kuchnia-twist'); ?></span>
                            <input type="file" name="facebook_image" accept="image/*">
                        </label>
                        <button type="submit" class="button button-primary button-hero"><?php esc_html_e('Generate & Publish', 'kuchnia-twist'); ?></button>
                    </form>
                </section>

                <section class="kt-card">
                    <h2><?php esc_html_e('Status Panel', 'kuchnia-twist'); ?></h2>
                    <?php if ($selected) : ?>
                        <?php $this->render_job_summary($selected); ?>
                    <?php else : ?>
                        <p><?php esc_html_e('No jobs yet. Your first generated article will show its publishing state here.', 'kuchnia-twist'); ?></p>
                    <?php endif; ?>
                </section>
            </div>

            <section class="kt-card">
                <div class="kt-table-head">
                    <h2><?php esc_html_e('Recent Jobs', 'kuchnia-twist'); ?></h2>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kuchnia-twist-settings')); ?>"><?php esc_html_e('Open Settings', 'kuchnia-twist'); ?></a>
                </div>
                <table class="widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Topic', 'kuchnia-twist'); ?></th>
                        <th><?php esc_html_e('Type', 'kuchnia-twist'); ?></th>
                        <th><?php esc_html_e('Status', 'kuchnia-twist'); ?></th>
                        <th><?php esc_html_e('Updated', 'kuchnia-twist'); ?></th>
                        <th><?php esc_html_e('Actions', 'kuchnia-twist'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($jobs) : ?>
                        <?php foreach ($jobs as $job) : ?>
                            <tr>
                                <td><?php echo esc_html($job['topic']); ?></td>
                                <td><?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></td>
                                <td><span class="kt-status kt-status--<?php echo esc_attr($job['status']); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $job['status']))); ?></span></td>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $job['updated_at'])); ?></td>
                                <td class="kt-actions">
                                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $job['id'])); ?>"><?php esc_html_e('View', 'kuchnia-twist'); ?></a>
                                    <?php if (!empty($job['permalink'])) : ?>
                                        <a class="button" href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Post', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                    <?php if (in_array($job['status'], ['failed', 'partial_failure'], true)) : ?>
                                        <a class="button" href="<?php echo esc_url($this->retry_link($job)); ?>"><?php esc_html_e('Retry', 'kuchnia-twist'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No jobs have been queued yet.', 'kuchnia-twist'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
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
        ?>
        <div class="wrap kt-admin">
            <h1><?php esc_html_e('Publishing Settings', 'kuchnia-twist'); ?></h1>
            <?php if (isset($_GET['kt_saved'])) : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Settings saved.', 'kuchnia-twist'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="kt-settings">
                <?php wp_nonce_field('kuchnia_twist_save_settings'); ?>
                <input type="hidden" name="action" value="kuchnia_twist_save_settings">

                <section class="kt-card">
                    <h2><?php esc_html_e('Topic List & Voice', 'kuchnia-twist'); ?></h2>
                    <label><span><?php esc_html_e('Topics (one per line)', 'kuchnia-twist'); ?></span><textarea name="topics_text" rows="8"><?php echo esc_textarea($settings['topics_text']); ?></textarea></label>
                    <label><span><?php esc_html_e('Brand Voice', 'kuchnia-twist'); ?></span><textarea name="brand_voice" rows="4"><?php echo esc_textarea($settings['brand_voice']); ?></textarea></label>
                    <label><span><?php esc_html_e('Article Prompt Guidance', 'kuchnia-twist'); ?></span><textarea name="article_prompt" rows="6"><?php echo esc_textarea($settings['article_prompt']); ?></textarea></label>
                    <label><span><?php esc_html_e('Default CTA Text', 'kuchnia-twist'); ?></span><input type="text" name="default_cta" value="<?php echo esc_attr($settings['default_cta']); ?>"></label>
                </section>

                <section class="kt-card">
                    <h2><?php esc_html_e('OpenAI Settings', 'kuchnia-twist'); ?></h2>
                    <label><span><?php esc_html_e('Text Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_model" value="<?php echo esc_attr($settings['openai_model']); ?>"></label>
                    <label><span><?php esc_html_e('Image Model', 'kuchnia-twist'); ?></span><input type="text" name="openai_image_model" value="<?php echo esc_attr($settings['openai_image_model']); ?>"></label>
                    <label><span><?php esc_html_e('API Key', 'kuchnia-twist'); ?></span><input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>"></label>
                    <label><span><?php esc_html_e('Image Style Guidance', 'kuchnia-twist'); ?></span><textarea name="image_style" rows="4"><?php echo esc_textarea($settings['image_style']); ?></textarea></label>
                </section>

                <section class="kt-card">
                    <h2><?php esc_html_e('Facebook Settings', 'kuchnia-twist'); ?></h2>
                    <label><span><?php esc_html_e('Graph API Version', 'kuchnia-twist'); ?></span><input type="text" name="facebook_graph_version" value="<?php echo esc_attr($settings['facebook_graph_version']); ?>"></label>
                    <label><span><?php esc_html_e('Facebook Page ID', 'kuchnia-twist'); ?></span><input type="text" name="facebook_page_id" value="<?php echo esc_attr($settings['facebook_page_id']); ?>"></label>
                    <label><span><?php esc_html_e('Facebook Page Access Token', 'kuchnia-twist'); ?></span><textarea name="facebook_page_access_token" rows="4"><?php echo esc_textarea($settings['facebook_page_access_token']); ?></textarea></label>
                    <label><span><?php esc_html_e('UTM Source', 'kuchnia-twist'); ?></span><input type="text" name="utm_source" value="<?php echo esc_attr($settings['utm_source']); ?>"></label>
                    <label><span><?php esc_html_e('UTM Campaign Prefix', 'kuchnia-twist'); ?></span><input type="text" name="utm_campaign_prefix" value="<?php echo esc_attr($settings['utm_campaign_prefix']); ?>"></label>
                </section>

                <section class="kt-card">
                    <h2><?php esc_html_e('Environment Status', 'kuchnia-twist'); ?></h2>
                    <p><?php echo $this->get_worker_secret() ? esc_html__('Worker secret is configured.', 'kuchnia-twist') : esc_html__('Worker secret is missing. Set CONTENT_PIPELINE_SHARED_SECRET in Coolify.', 'kuchnia-twist'); ?></p>
                    <p><?php echo getenv('OPENAI_API_KEY') ? esc_html__('OpenAI API key is also available from the container environment.', 'kuchnia-twist') : esc_html__('OpenAI API key is not set in the WordPress container environment.', 'kuchnia-twist'); ?></p>
                </section>

                <p><button type="submit" class="button button-primary button-hero"><?php esc_html_e('Save Settings', 'kuchnia-twist'); ?></button></p>
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

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . (int) $wpdb->insert_id . '&kt_notice=job_created'));
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
            'openai_model'               => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? '')),
            'openai_image_model'         => sanitize_text_field(wp_unslash($_POST['openai_image_model'] ?? '')),
            'openai_api_key'             => trim((string) wp_unslash($_POST['openai_api_key'] ?? '')),
            'image_style'                => trim((string) wp_unslash($_POST['image_style'] ?? '')),
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

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-publisher&job_id=' . $job_id . '&kt_notice=job_queued'));
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

        return rest_ensure_response(['ok' => true]);
    }

    private function install(): void
    {
        global $wpdb;
        $table           = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

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

        update_option(self::OPTION_KEY, wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings()));
        update_option(self::VERSION_KEY, self::VERSION, false);

        $this->ensure_category('recipe');
        $this->ensure_category('food_fact');
        $this->ensure_category('food_story');
        $this->ensure_core_pages();
    }

    private function ensure_core_pages(): void
    {
        $pages = [
            'about' => [
                'title'   => __('About', 'kuchnia-twist'),
                'content' => "<p>Kuchnia Twist is a food journal built around recipes, food facts, and story-led kitchen writing. Edit this page to tell your own story before applying for AdSense.</p>",
            ],
            'contact' => [
                'title'   => __('Contact', 'kuchnia-twist'),
                'content' => '<p>Use this page to add your contact details, business email, and editorial contact information.</p>',
            ],
            'privacy-policy' => [
                'title'   => __('Privacy Policy', 'kuchnia-twist'),
                'content' => '<p>Replace this placeholder with your real privacy policy before production monetization.</p>',
            ],
            'cookie-policy' => [
                'title'   => __('Cookie Policy', 'kuchnia-twist'),
                'content' => '<p>Replace this placeholder with your actual cookie policy and consent information.</p>',
            ],
            'editorial-policy' => [
                'title'   => __('Editorial Policy', 'kuchnia-twist'),
                'content' => '<p>Explain how recipes are tested, how food facts are sourced, and how stories are edited.</p>',
            ],
        ];

        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_title'   => $page['title'],
                    'post_name'    => $slug,
                    'post_content' => $page['content'],
                ]);
            }
        }
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

    private function get_jobs(int $limit = 10): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_name()} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
        return array_map([$this, 'prepare_job_record'], $rows ?: []);
    }

    private function get_job(int $job_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name()} WHERE id = %d", $job_id), ARRAY_A);
        return $row ? $this->prepare_job_record($row) : null;
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

    private function render_notice(string $notice_key): void
    {
        $messages = [
            'job_created'   => __('Publishing job queued. The background worker will pick it up shortly.', 'kuchnia-twist'),
            'job_queued'    => __('Job queued again for processing.', 'kuchnia-twist'),
            'job_missing'   => __('The selected job could not be found.', 'kuchnia-twist'),
            'invalid_job'   => __('Please select a valid topic and content type.', 'kuchnia-twist'),
            'duplicate_job' => __('A matching job is already in progress, so the existing one was kept instead of creating a duplicate.', 'kuchnia-twist'),
        ];

        if (!isset($messages[$notice_key])) {
            return;
        }

        echo '<div class="notice notice-success"><p>' . esc_html($messages[$notice_key]) . '</p></div>';
    }

    private function render_job_summary(array $job): void
    {
        ?>
        <div class="kt-summary">
            <div><strong><?php esc_html_e('Topic:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['topic']); ?></div>
            <div><strong><?php esc_html_e('Status:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['status']); ?></div>
            <div><strong><?php esc_html_e('Stage:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['stage']); ?></div>
            <div><strong><?php esc_html_e('Content Type:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($this->content_types()[$job['content_type']] ?? $job['content_type']); ?></div>
            <?php if (!empty($job['permalink'])) : ?><div><strong><?php esc_html_e('Blog Post:', 'kuchnia-twist'); ?></strong> <a href="<?php echo esc_url($job['permalink']); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open article', 'kuchnia-twist'); ?></a></div><?php endif; ?>
            <?php if (!empty($job['facebook_post_id'])) : ?><div><strong><?php esc_html_e('Facebook Post ID:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['facebook_post_id']); ?></div><?php endif; ?>
            <?php if (!empty($job['facebook_comment_id'])) : ?><div><strong><?php esc_html_e('First Comment ID:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['facebook_comment_id']); ?></div><?php endif; ?>
            <?php if (!empty($job['error_message'])) : ?><div class="kt-error"><strong><?php esc_html_e('Latest Error:', 'kuchnia-twist'); ?></strong> <?php echo esc_html($job['error_message']); ?></div><?php endif; ?>
            <?php if (!empty($job['facebook_caption'])) : ?><label><span><?php esc_html_e('Facebook Caption', 'kuchnia-twist'); ?></span><textarea rows="5" readonly><?php echo esc_textarea($job['facebook_caption']); ?></textarea></label><?php endif; ?>
            <?php if (!empty($job['group_share_kit'])) : ?><label><span><?php esc_html_e('Group Share Kit', 'kuchnia-twist'); ?></span><textarea rows="6" readonly><?php echo esc_textarea($job['group_share_kit']); ?></textarea></label><?php endif; ?>
        </div>
        <?php
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
        return $topics ?: ['Seasonal comfort meals', 'Useful food facts', 'Kitchen stories worth retelling'];
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
            'topics_text'                => "Seasonal comfort meals\nKitchen-saving cooking techniques\nIngredient stories people remember",
            'brand_voice'                => 'Warm, useful, story-aware, and editorial without sounding stiff.',
            'article_prompt'             => 'Write original, useful, and substantial food content with clean headings and no filler.',
            'default_cta'                => 'Read the full article on the blog.',
            'openai_model'               => 'gpt-5-mini',
            'openai_image_model'         => 'gpt-image-1.5',
            'openai_api_key'             => '',
            'image_style'                => 'Natural food photography, editorial light, appetizing detail, no text overlays, premium magazine look.',
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
}

register_activation_hook(__FILE__, [Kuchnia_Twist_Publisher::class, 'activate']);
Kuchnia_Twist_Publisher::instance();
