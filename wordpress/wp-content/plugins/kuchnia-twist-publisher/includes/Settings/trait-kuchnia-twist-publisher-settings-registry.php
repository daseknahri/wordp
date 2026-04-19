<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Settings_Registry_Trait
{
    private function content_type_registry(): array
    {
        return [
            'recipe' => [
                'key'              => 'recipe',
                'label'            => __('Recipe', 'kuchnia-twist'),
                'active'           => true,
                'legacy_read_only' => false,
                'input_mode'       => 'dish_name',
                'validation_mode'  => 'recipe_article',
                'rendering_mode'   => 'recipe_multipage',
                'min_words'        => 1200,
                'recipe_required'  => true,
                'settings_keys'    => [
                    'content_preset_guidance' => 'recipe_preset_guidance',
                    'article_guidance'        => 'article_prompt',
                    'social_guidance'         => 'facebook_caption_guidance',
                ],
            ],
            'food_fact' => [
                'key'              => 'food_fact',
                'label'            => __('Food Fact', 'kuchnia-twist'),
                'active'           => true,
                'legacy_read_only' => false,
                'input_mode'       => 'working_title',
                'validation_mode'  => 'editorial_article',
                'rendering_mode'   => 'editorial_multipage',
                'min_words'        => 1100,
                'recipe_required'  => false,
                'settings_keys'    => [
                    'content_preset_guidance' => 'food_fact_preset_guidance',
                    'article_guidance'        => 'food_fact_article_prompt',
                    'social_guidance'         => 'food_fact_facebook_caption_guidance',
                ],
            ],
            'food_story' => [
                'key'              => 'food_story',
                'label'            => __('Food Story', 'kuchnia-twist'),
                'active'           => false,
                'legacy_read_only' => true,
                'input_mode'       => 'working_title',
                'validation_mode'  => 'editorial_article',
                'rendering_mode'   => 'editorial_multipage',
                'min_words'        => 1100,
                'recipe_required'  => false,
                'settings_keys'    => [
                    'content_preset_guidance' => 'food_story_preset_guidance',
                    'article_guidance'        => 'food_story_article_prompt',
                    'social_guidance'         => 'food_story_facebook_caption_guidance',
                ],
            ],
        ];
    }

    private function active_content_type_registry(): array
    {
        return array_filter(
            $this->content_type_registry(),
            static fn (array $profile): bool => !empty($profile['active'])
        );
    }

    private function content_types(): array
    {
        $types = [];
        foreach ($this->content_type_registry() as $key => $profile) {
            $types[$key] = (string) ($profile['label'] ?? $key);
        }

        return $types;
    }

    private function active_content_types(): array
    {
        $types = [];
        foreach ($this->active_content_type_registry() as $key => $profile) {
            $types[$key] = (string) ($profile['label'] ?? $key);
        }

        return $types;
    }

    private function queueable_content_types(): array
    {
        return $this->active_content_types();
    }

    private function is_active_content_type(string $content_type): bool
    {
        $registry = $this->content_type_registry();
        $key = sanitize_key($content_type);

        return !empty($registry[$key]['active']);
    }

    private function channel_registry(): array
    {
        return [
            'facebook' => [
                'key'                  => 'facebook',
                'label'                => __('Facebook Pages', 'kuchnia-twist'),
                'live'                 => true,
                'adapter'              => 'page_distribution',
                'output_shape'         => 'social_pack',
                'input_package'        => 'content_package',
                'request_target_shape' => [
                    'enabled' => false,
                    'page_ids' => [],
                    'pages' => [],
                ],
                'media_platform_hints' => [
                    'platform' => 'facebook',
                ],
            ],
            'facebook_groups' => [
                'key'                  => 'facebook_groups',
                'label'                => __('Facebook Groups', 'kuchnia-twist'),
                'live'                 => false,
                'adapter'              => 'manual_group_share',
                'output_shape'         => 'share_draft',
                'input_package'        => 'content_package',
                'request_target_shape' => [
                    'enabled' => false,
                    'mode' => 'manual_draft',
                ],
                'media_platform_hints' => [
                    'platform' => 'facebook_groups',
                ],
            ],
            'pinterest' => [
                'key'                  => 'pinterest',
                'label'                => __('Pinterest', 'kuchnia-twist'),
                'live'                 => false,
                'adapter'              => 'draft_pin',
                'output_shape'         => 'pin_draft',
                'input_package'        => 'content_package',
                'request_target_shape' => [
                    'enabled' => false,
                    'mode' => 'draft',
                ],
                'media_platform_hints' => [
                    'platform' => 'pinterest',
                ],
            ],
        ];
    }

    private function content_machine_publication_name(array $settings): string
    {
        $name = sanitize_text_field((string) ($settings['publication_profile_name'] ?? ''));
        return $name !== '' ? $name : get_bloginfo('name');
    }

    private function content_machine_publication_profile(array $settings): array
    {
        return [
            'id'          => 'default',
            'name'        => $this->content_machine_publication_name($settings),
            'role'        => $settings['publication_role'],
            'voice_brief' => $settings['brand_voice'],
            'guardrails'  => $settings['global_guardrails'],
        ];
    }

    private function content_machine_content_presets(array $settings): array
    {
        $types = $this->content_type_registry();

        return [
            'recipe' => [
                'label'     => $types['recipe']['label'],
                'guidance'  => trim((string) ($settings['recipe_preset_guidance'] ?? '')) !== '' ? $settings['recipe_preset_guidance'] : $settings['article_prompt'],
                'min_words' => (int) ($types['recipe']['min_words'] ?? 1200),
            ],
            'food_fact' => [
                'label'     => $types['food_fact']['label'],
                'guidance'  => trim((string) ($settings['food_fact_preset_guidance'] ?? '')) !== '' ? $settings['food_fact_preset_guidance'] : $settings['food_fact_article_prompt'],
                'min_words' => (int) ($types['food_fact']['min_words'] ?? 1100),
            ],
        ];
    }

    private function content_machine_channel_presets(array $settings): array
    {
        return [
            'recipe_master' => [
                'guidance' => $settings['recipe_master_prompt'],
            ],
            'article' => [
                'recipe' => [
                    'guidance' => $settings['article_prompt'],
                ],
                'food_fact' => [
                    'guidance' => $settings['food_fact_article_prompt'],
                ],
            ],
            'facebook_caption' => [
                'recipe' => [
                    'guidance' => $settings['facebook_caption_guidance'],
                ],
                'food_fact' => [
                    'guidance' => $settings['food_fact_facebook_caption_guidance'],
                ],
            ],
            'facebook_groups' => [
                'guidance' => $settings['group_share_guidance'],
            ],
            'pinterest' => [
                'guidance' => $this->default_pinterest_draft_guidance(),
            ],
            'image' => [
                'guidance' => $settings['image_style'],
            ],
        ];
    }

    private function content_machine_models(array $settings): array
    {
        return [
            'text_model'      => $settings['openai_model'],
            'image_model'     => $settings['openai_image_model'],
            'repair_enabled'  => $settings['repair_enabled'] === '1',
            'repair_attempts' => (int) $settings['repair_attempts'],
        ];
    }

    private function content_machine_contracts(array $settings): array
    {
        return [
            'strict_contract_mode' => ($settings['strict_contract_mode'] ?? '0') === '1',
        ];
    }

    private function content_machine_default_ctas(array $settings): array
    {
        return [
            'facebook_post_teaser' => $settings['facebook_post_teaser_cta'],
            'facebook_comment_link' => $settings['facebook_comment_link_cta'],
        ];
    }

    private function worker_runtime_openai_api_key(array $settings): string
    {
        $env_key = trim((string) getenv('OPENAI_API_KEY'));
        return $env_key !== '' ? $env_key : (string) ($settings['openai_api_key'] ?? '');
    }

    private function worker_runtime_openai_base_url(): string
    {
        $env_url = trim((string) getenv('OPENAI_BASE_URL'));
        return $env_url !== '' ? $env_url : 'https://api.openai.com/v1';
    }

    private function content_machine_site_policy(array $settings): array
    {
        return [
            'publication_name' => $this->content_machine_publication_name($settings),
            'pagination' => [
                'marker' => '<!--nextpage-->',
            ],
            'internal_links' => [
                'minimum_count' => 3,
                'shortcode_tag' => 'kuchnia_twist_link',
                'journal_label' => 'Keep reading across the journal',
                'library' => [
                    'shared' => [
                        ['slug' => 'recipes', 'label' => 'Recipes'],
                        ['slug' => 'food-facts', 'label' => 'Food Facts'],
                        ['slug' => 'fresh-garlic-vs-roasted-garlic-when-each-one-wins', 'label' => 'Fresh Garlic vs Roasted Garlic: When Each One Wins'],
                    ],
                    'recipe' => [
                        ['slug' => 'recipes', 'label' => 'Recipes'],
                        ['slug' => 'food-facts', 'label' => 'Food Facts'],
                        ['slug' => 'why-onions-need-more-time-than-most-recipes-admit', 'label' => 'Why Onions Need More Time Than Most Recipes Admit'],
                        ['slug' => 'what-tomato-paste-actually-does-in-a-pan', 'label' => 'What Tomato Paste Actually Does in a Pan'],
                        ['slug' => 'tomato-butter-beans-on-toast-with-garlic-and-lemon', 'label' => 'Tomato Butter Beans on Toast with Garlic and Lemon'],
                        ['slug' => 'crispy-sheet-pan-chicken-with-caramelized-onions-and-potatoes', 'label' => 'Crispy Sheet-Pan Chicken with Caramelized Onions and Potatoes'],
                    ],
                    'food_fact' => [
                        ['slug' => 'food-facts', 'label' => 'Food Facts'],
                        ['slug' => 'recipes', 'label' => 'Recipes'],
                        ['slug' => 'fresh-garlic-vs-roasted-garlic-when-each-one-wins', 'label' => 'Fresh Garlic vs Roasted Garlic: When Each One Wins'],
                        ['slug' => 'why-onions-need-more-time-than-most-recipes-admit', 'label' => 'Why Onions Need More Time Than Most Recipes Admit'],
                        ['slug' => 'what-tomato-paste-actually-does-in-a-pan', 'label' => 'What Tomato Paste Actually Does in a Pan'],
                        ['slug' => 'crispy-sheet-pan-chicken-with-caramelized-onions-and-potatoes', 'label' => 'Crispy Sheet-Pan Chicken with Caramelized Onions and Potatoes'],
                        ['slug' => 'tomato-butter-beans-on-toast-with-garlic-and-lemon', 'label' => 'Tomato Butter Beans on Toast with Garlic and Lemon'],
                    ],
                    'food_story' => [
                        ['slug' => 'food-stories', 'label' => 'Food Stories'],
                        ['slug' => 'recipes', 'label' => 'Recipes'],
                        ['slug' => 'the-quiet-value-of-a-soup-pot-on-a-busy-weeknight', 'label' => 'The Quiet Value of a Soup Pot on a Busy Weeknight'],
                        ['slug' => 'creamy-mushroom-barley-soup-for-busy-evenings', 'label' => 'Creamy Mushroom Barley Soup for Busy Evenings'],
                    ],
                ],
            ],
        ];
    }

    private function content_machine_platform_policy(array $settings): array
    {
        return [
            'rest' => [
                'namespace' => $this->worker_rest_namespace(),
                'routes' => $this->worker_platform_route_templates(),
            ],
            'auth' => [
                'secret_header' => $this->worker_secret_header_name(),
            ],
            'delivery' => [
                'utm_source' => (string) ($settings['utm_source'] ?? 'facebook'),
                'utm_campaign_prefix' => (string) ($settings['utm_campaign_prefix'] ?? 'kuchnia-twist'),
            ],
        ];
    }

    private function content_machine_settings(array $settings): array
    {
        return [
            'prompt_version' => self::CONTENT_MACHINE_VERSION,
            'publication_profile' => $this->content_machine_publication_profile($settings),
            'content_presets' => $this->content_machine_content_presets($settings),
            'channel_presets' => $this->content_machine_channel_presets($settings),
            'cadence' => [
                'mode'             => 'manual_recipe_publish_at',
                'timezone'         => wp_timezone_string() ?: 'UTC',
            ],
            'models' => $this->content_machine_models($settings),
            'contracts' => $this->content_machine_contracts($settings),
            'site_policy' => $this->content_machine_site_policy($settings),
            'platform_policy' => $this->content_machine_platform_policy($settings),
            'default_ctas' => $this->content_machine_default_ctas($settings),
            'default_cta' => $settings['facebook_comment_link_cta'],
        ];
    }

    private function content_machine_resolved_site_policy(?array $settings = null): array
    {
        $source_settings = !empty($settings) ? $settings : $this->get_settings();
        $policy = $this->content_machine_site_policy($source_settings);

        return is_array($policy) ? $policy : [];
    }

    private function content_machine_page_break_marker(?array $settings = null): string
    {
        $policy = $this->content_machine_resolved_site_policy($settings);
        $pagination = is_array($policy['pagination'] ?? null) ? $policy['pagination'] : [];
        $marker = trim((string) ($pagination['marker'] ?? ''));

        return $marker !== '' ? $marker : '<!--nextpage-->';
    }

    private function content_machine_page_break_pattern(?array $settings = null): string
    {
        return '/\s*' . preg_quote($this->content_machine_page_break_marker($settings), '/') . '\s*/i';
    }

    private function split_content_html_by_site_policy(string $content_html, ?array $settings = null): array
    {
        $content_html = trim($content_html);
        if ($content_html === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($page): string => trim((string) $page),
            preg_split($this->content_machine_page_break_pattern($settings), $content_html) ?: []
        ), static fn (string $page): bool => $page !== ''));
    }

    private function join_content_pages_by_site_policy(array $pages, ?array $settings = null): string
    {
        $normalized = array_values(array_filter(array_map(
            static fn ($page): string => trim((string) $page),
            $pages
        ), static fn (string $page): bool => $page !== ''));

        if (empty($normalized)) {
            return '';
        }

        return implode("\n" . $this->content_machine_page_break_marker($settings) . "\n", $normalized);
    }

    private function content_machine_internal_link_shortcode_tag(?array $settings = null): string
    {
        $policy = $this->content_machine_resolved_site_policy($settings);
        $internal_links = is_array($policy['internal_links'] ?? null) ? $policy['internal_links'] : [];
        $shortcode_tag = sanitize_key((string) ($internal_links['shortcode_tag'] ?? ''));

        return $shortcode_tag !== '' ? $shortcode_tag : 'kuchnia_twist_link';
    }

    private function content_machine_internal_link_minimum_count(?array $settings = null): int
    {
        $policy = $this->content_machine_resolved_site_policy($settings);
        $internal_links = is_array($policy['internal_links'] ?? null) ? $policy['internal_links'] : [];

        return max(1, (int) ($internal_links['minimum_count'] ?? 3));
    }

    private function job_content_machine_snapshot(array $settings, string $content_type): array
    {
        $machine = $this->content_machine_settings($settings);

        return [
            'prompt_version'      => $machine['prompt_version'],
            'publication_profile' => (string) ($machine['publication_profile']['name'] ?? $this->content_machine_publication_name($settings)),
            'content_preset'      => $content_type,
            'site_policy'         => is_array($machine['site_policy'] ?? null) ? $machine['site_policy'] : [],
            'platform_policy'     => is_array($machine['platform_policy'] ?? null) ? $machine['platform_policy'] : [],
            'default_ctas'        => is_array($machine['default_ctas'] ?? null) ? $machine['default_ctas'] : [],
            'default_cta'         => (string) ($machine['default_cta'] ?? $this->default_facebook_comment_link_cta()),
        ];
    }

    private function worker_runtime_settings_payload(array $settings): array
    {
        return [
            'site_name'                  => get_bloginfo('name'),
            'site_url'                   => home_url('/'),
            'brand_voice'                => $settings['brand_voice'],
            'article_prompt'             => $settings['article_prompt'],
            'facebook_post_teaser_cta'   => $settings['facebook_post_teaser_cta'],
            'facebook_comment_link_cta'  => $settings['facebook_comment_link_cta'],
            'default_cta'                => $settings['facebook_comment_link_cta'],
            'image_style'                => $settings['image_style'],
            'image_generation_mode'      => $settings['image_generation_mode'],
            'facebook_graph_version'     => $settings['facebook_graph_version'],
            'facebook_page_id'           => $settings['facebook_page_id'],
            'facebook_page_access_token' => $settings['facebook_page_access_token'],
            'facebook_pages'             => $this->facebook_pages($settings, false, false),
            'openai_model'               => $settings['openai_model'],
            'openai_image_model'         => $settings['openai_image_model'],
            'openai_api_key'             => $this->worker_runtime_openai_api_key($settings),
            'openai_base_url'            => $this->worker_runtime_openai_base_url(),
            'utm_source'                 => $settings['utm_source'],
            'utm_campaign_prefix'        => $settings['utm_campaign_prefix'],
            'content_machine'            => $this->content_machine_settings($settings),
        ];
    }
}
