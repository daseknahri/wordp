<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Publisher_Page_Trait
{
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
        $upload_max_bytes     = (int) wp_max_upload_size();
        $post_max_bytes       = (int) wp_convert_hr_to_bytes((string) ini_get('post_max_size'));
        $upload_limit_label   = size_format(max($upload_max_bytes, 0));
        $post_limit_label     = size_format(max($post_max_bytes, 0));
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
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="kt-form" data-upload-max-bytes="<?php echo esc_attr((string) $upload_max_bytes); ?>" data-post-max-bytes="<?php echo esc_attr((string) $post_max_bytes); ?>">
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
                                <input type="file" name="blog_image" accept="image/*" <?php echo $manual_only ? 'required' : ''; ?> data-upload-file-input>
                            </label>
                            <label>
                                <span><?php esc_html_e('Facebook Image', 'kuchnia-twist'); ?></span>
                                <input type="file" name="facebook_image" accept="image/*" <?php echo $manual_only ? 'required' : ''; ?> data-upload-file-input>
                            </label>
                        </div>
                        <p class="kt-detail-note" data-upload-limit-note>
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('Current server limit: %1$s per file and %2$s per request. Two large phone photos may need compression before upload.', 'kuchnia-twist'),
                                    $upload_limit_label,
                                    $post_limit_label
                                )
                            );
                            ?>
                        </p>
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
}
