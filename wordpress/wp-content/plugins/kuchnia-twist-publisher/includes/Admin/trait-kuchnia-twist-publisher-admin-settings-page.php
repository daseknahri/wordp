<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Admin_Settings_Page_Trait
{
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
                        <label class="kt-field-span-full"><span><?php esc_html_e('Facebook Post Teaser CTA', 'kuchnia-twist'); ?></span><input type="text" name="facebook_post_teaser_cta" value="<?php echo esc_attr($settings['facebook_post_teaser_cta']); ?>"></label>
                        <label class="kt-field-span-full"><span><?php esc_html_e('Facebook Comment Link CTA', 'kuchnia-twist'); ?></span><input type="text" name="facebook_comment_link_cta" value="<?php echo esc_attr($settings['facebook_comment_link_cta']); ?>"></label>
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
}
