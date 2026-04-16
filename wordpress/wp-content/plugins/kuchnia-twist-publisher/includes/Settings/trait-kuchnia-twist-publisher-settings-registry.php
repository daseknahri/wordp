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

    private function content_machine_settings(array $settings): array
    {
        $types = $this->content_type_registry();

        return [
            'prompt_version' => self::CONTENT_MACHINE_VERSION,
            'publication_profile' => [
                'id'          => 'default',
                'name'        => $settings['publication_profile_name'] !== '' ? $settings['publication_profile_name'] : get_bloginfo('name'),
                'role'        => $settings['publication_role'],
                'voice_brief' => $settings['brand_voice'],
                'guardrails'  => $settings['global_guardrails'],
            ],
            'content_presets' => [
                'recipe' => [
                    'label'    => $types['recipe']['label'],
                    'guidance' => trim((string) ($settings['recipe_preset_guidance'] ?? '')) !== '' ? $settings['recipe_preset_guidance'] : $settings['article_prompt'],
                    'min_words'=> (int) ($types['recipe']['min_words'] ?? 1200),
                ],
                'food_fact' => [
                    'label'    => $types['food_fact']['label'],
                    'guidance' => trim((string) ($settings['food_fact_preset_guidance'] ?? '')) !== '' ? $settings['food_fact_preset_guidance'] : $settings['food_fact_article_prompt'],
                    'min_words'=> (int) ($types['food_fact']['min_words'] ?? 1100),
                ],
            ],
            'channel_presets' => [
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
            ],
            'cadence' => [
                'mode'             => 'manual_recipe_publish_at',
                'timezone'         => wp_timezone_string() ?: 'UTC',
            ],
            'models' => [
                'text_model'      => $settings['openai_model'],
                'image_model'     => $settings['openai_image_model'],
                'repair_enabled'  => $settings['repair_enabled'] === '1',
                'repair_attempts' => (int) $settings['repair_attempts'],
            ],
            'contracts' => [
                'strict_contract_mode' => ($settings['strict_contract_mode'] ?? '0') === '1',
            ],
            'default_ctas' => [
                'facebook_post_teaser' => $settings['facebook_post_teaser_cta'],
                'facebook_comment_link' => $settings['facebook_comment_link_cta'],
            ],
            'default_cta' => $settings['facebook_comment_link_cta'],
        ];
    }

    private function job_content_machine_snapshot(array $settings, string $content_type): array
    {
        $machine = $this->content_machine_settings($settings);

        return [
            'prompt_version'      => $machine['prompt_version'],
            'publication_profile' => (string) ($machine['publication_profile']['name'] ?? get_bloginfo('name')),
            'content_preset'      => $content_type,
            'default_ctas'        => is_array($machine['default_ctas'] ?? null) ? $machine['default_ctas'] : [],
            'default_cta'         => (string) ($machine['default_cta'] ?? $this->default_facebook_comment_link_cta()),
        ];
    }
}
