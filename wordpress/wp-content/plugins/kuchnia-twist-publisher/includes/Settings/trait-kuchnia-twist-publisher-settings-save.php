<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Settings_Save_Trait
{
    private function sanitize_image_generation_mode($value): string
    {
        $value = (string) $value;

        if ($value === 'ai_fallback') {
            return 'uploaded_first_generate_missing';
        }

        return in_array($value, ['manual_only', 'uploaded_first_generate_missing'], true) ? $value : 'uploaded_first_generate_missing';
    }

    public function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'kuchnia-twist'));
        }

        check_admin_referer('kuchnia_twist_save_settings');

        $current  = $this->get_settings();
        $posted   = wp_unslash($_POST);
        $pages    = $this->sanitize_facebook_pages_input($posted['facebook_pages'] ?? []);
        $incoming = $this->build_settings_update_payload($posted, $current, $pages);

        update_option(self::OPTION_KEY, wp_parse_args($incoming, $current));

        wp_safe_redirect(admin_url('admin.php?page=kuchnia-twist-settings&kt_saved=1'));
        exit;
    }

    private function sanitize_facebook_pages_input($raw_pages): array
    {
        if (!is_array($raw_pages)) {
            return [];
        }

        $pages = [];
        foreach ($raw_pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $page_id = sanitize_text_field((string) ($page['page_id'] ?? ''));
            $label = sanitize_text_field((string) ($page['label'] ?? ''));
            $token = trim((string) ($page['access_token'] ?? ''));
            $active = !empty($page['active']);

            if ($page_id === '' || $label === '') {
                continue;
            }

            $pages[$page_id] = [
                'page_id'      => $page_id,
                'label'        => $label,
                'access_token' => $token,
                'active'       => $active,
            ];
        }

        return array_values($pages);
    }

    private function primary_facebook_page(array $pages): ?array
    {
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            if (!empty($page['active']) && trim((string) ($page['access_token'] ?? '')) !== '') {
                return $page;
            }
        }

        return !empty($pages) && is_array($pages[0]) ? $pages[0] : null;
    }

    private function posted_facebook_comment_link_cta(array $posted, array $current): string
    {
        $cta = sanitize_text_field((string) ($posted['facebook_comment_link_cta'] ?? ($current['facebook_comment_link_cta'] ?? '')));
        if ($cta === '') {
            $cta = $this->facebook_comment_link_cta_setting($current);
        }

        return $cta;
    }

    private function build_settings_update_payload(array $posted, array $current, array $pages): array
    {
        $primary_page = $this->primary_facebook_page($pages);
        $facebook_comment_link_cta = $this->posted_facebook_comment_link_cta($posted, $current);

        return [
            'topics_text'                => trim((string) ($posted['topics_text'] ?? ($current['topics_text'] ?? ''))),
            'publication_role'           => trim((string) ($posted['publication_role'] ?? ($current['publication_role'] ?? ''))),
            'brand_voice'                => trim((string) ($posted['brand_voice'] ?? ($current['brand_voice'] ?? ''))),
            'global_guardrails'          => trim((string) ($posted['global_guardrails'] ?? ($current['global_guardrails'] ?? ''))),
            'recipe_master_prompt'       => trim((string) ($posted['recipe_master_prompt'] ?? ($current['recipe_master_prompt'] ?? ''))),
            'article_prompt'             => trim((string) ($posted['article_prompt'] ?? ($current['article_prompt'] ?? ''))),
            'facebook_caption_guidance'  => trim((string) ($posted['facebook_caption_guidance'] ?? ($current['facebook_caption_guidance'] ?? ''))),
            'food_fact_article_prompt'   => trim((string) ($posted['food_fact_article_prompt'] ?? ($current['food_fact_article_prompt'] ?? ''))),
            'food_fact_facebook_caption_guidance' => trim((string) ($posted['food_fact_facebook_caption_guidance'] ?? ($current['food_fact_facebook_caption_guidance'] ?? ''))),
            'facebook_post_teaser_cta'   => sanitize_text_field((string) ($posted['facebook_post_teaser_cta'] ?? ($current['facebook_post_teaser_cta'] ?? ''))),
            'facebook_comment_link_cta'  => $facebook_comment_link_cta,
            'default_cta'                => $facebook_comment_link_cta,
            'editor_name'                => sanitize_text_field((string) ($posted['editor_name'] ?? ($current['editor_name'] ?? ''))),
            'editor_role'                => sanitize_text_field((string) ($posted['editor_role'] ?? ($current['editor_role'] ?? ''))),
            'editor_bio'                 => trim((string) ($posted['editor_bio'] ?? ($current['editor_bio'] ?? ''))),
            'editor_public_email'        => sanitize_email((string) ($posted['editor_public_email'] ?? ($current['editor_public_email'] ?? ''))),
            'editor_business_email'      => sanitize_email((string) ($posted['editor_business_email'] ?? ($current['editor_business_email'] ?? ''))),
            'editor_photo_id'            => absint($posted['editor_photo_id'] ?? ($current['editor_photo_id'] ?? 0)),
            'social_instagram_url'       => esc_url_raw(trim((string) ($posted['social_instagram_url'] ?? ($current['social_instagram_url'] ?? '')))),
            'social_facebook_url'        => esc_url_raw(trim((string) ($posted['social_facebook_url'] ?? ($current['social_facebook_url'] ?? '')))),
            'social_pinterest_url'       => esc_url_raw(trim((string) ($posted['social_pinterest_url'] ?? ($current['social_pinterest_url'] ?? '')))),
            'social_tiktok_url'          => esc_url_raw(trim((string) ($posted['social_tiktok_url'] ?? ($current['social_tiktok_url'] ?? '')))),
            'social_follow_label'        => sanitize_text_field((string) ($posted['social_follow_label'] ?? ($current['social_follow_label'] ?? ''))),
            'openai_model'               => sanitize_text_field((string) ($posted['openai_model'] ?? ($current['openai_model'] ?? ''))),
            'openai_image_model'         => sanitize_text_field((string) ($posted['openai_image_model'] ?? ($current['openai_image_model'] ?? ''))),
            'openai_api_key'             => trim((string) ($posted['openai_api_key'] ?? ($current['openai_api_key'] ?? ''))),
            'image_style'                => trim((string) ($posted['image_style'] ?? ($current['image_style'] ?? ''))),
            'image_generation_mode'      => $this->sanitize_image_generation_mode((string) ($posted['image_generation_mode'] ?? ($current['image_generation_mode'] ?? 'uploaded_first_generate_missing'))),
            'strict_contract_mode'       => array_key_exists('strict_contract_mode', $posted)
                ? (!empty($posted['strict_contract_mode']) ? '1' : '0')
                : (string) ($current['strict_contract_mode'] ?? '0'),
            'daily_publish_time'         => $this->sanitize_publish_time((string) ($posted['daily_publish_time'] ?? ($current['daily_publish_time'] ?? '09:00'))),
            'facebook_graph_version'     => sanitize_text_field((string) ($posted['facebook_graph_version'] ?? ($current['facebook_graph_version'] ?? ''))),
            'facebook_pages'             => $pages,
            'facebook_page_id'           => $primary_page['page_id'] ?? '',
            'facebook_page_access_token' => $primary_page['access_token'] ?? '',
            'utm_source'                 => sanitize_key((string) ($posted['utm_source'] ?? ($current['utm_source'] ?? 'facebook'))),
            'utm_campaign_prefix'        => sanitize_key((string) ($posted['utm_campaign_prefix'] ?? ($current['utm_campaign_prefix'] ?? 'kuchnia-twist'))),
        ];
    }
}
