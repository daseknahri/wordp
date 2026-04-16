<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Contracts_Trait
{
    private function content_type_profile(string $content_type): array
    {
        $registry = $this->content_type_registry();
        $key = sanitize_key($content_type ?: 'recipe');
        if (!isset($registry[$key])) {
            $key = 'recipe';
        }
        $definition = $registry[$key];

        return [
            'key'             => $key,
            'contract_version'=> self::CONTENT_PACKAGE_CONTRACT_VERSION,
            'package_shape'   => 'canonical_content_package',
            'input_mode'      => (string) ($definition['input_mode'] ?? ($key === 'recipe' ? 'dish_name' : 'working_title')),
            'article_stage'   => $key . '_article',
            'validation_mode' => (string) ($definition['validation_mode'] ?? ($key === 'recipe' ? 'recipe_article' : 'editorial_article')),
            'rendering_mode'  => (string) ($definition['rendering_mode'] ?? ($key === 'recipe' ? 'recipe_multipage' : 'editorial_multipage')),
            'recipe_required' => !empty($definition['recipe_required']),
        ];
    }

    private function channel_profile(string $channel): array
    {
        $registry = $this->channel_registry();
        $key = sanitize_key($channel);
        if (!isset($registry[$key])) {
            $key = 'facebook';
        }
        $definition = $registry[$key];

        return [
            'key'          => $key,
            'contract_version' => self::CHANNEL_ADAPTER_CONTRACT_VERSION,
            'live'         => !empty($definition['live']),
            'adapter'      => sanitize_key((string) ($definition['adapter'] ?? 'page_distribution')),
            'output_shape' => sanitize_key((string) ($definition['output_shape'] ?? 'social_pack')),
            'input_package'=> sanitize_key((string) ($definition['input_package'] ?? 'content_package')),
        ];
    }

    private function generated_contract_versions(array $generated): array
    {
        $content_machine = is_array($generated['content_machine'] ?? null) ? $generated['content_machine'] : [];
        $versions = is_array($content_machine['contract_versions'] ?? null) ? $content_machine['contract_versions'] : [];
        $channels = is_array($generated['channels'] ?? null) ? $generated['channels'] : [];
        $facebook = is_array($channels['facebook'] ?? null) ? $channels['facebook'] : [];
        $facebook_groups = is_array($channels['facebook_groups'] ?? null) ? $channels['facebook_groups'] : [];
        $pinterest = is_array($channels['pinterest'] ?? null) ? $channels['pinterest'] : [];
        $package = is_array($generated['content_package'] ?? null) ? $generated['content_package'] : [];

        return [
            'content_package' => sanitize_text_field((string) ($versions['content_package'] ?? $package['contract_version'] ?? '')),
            'channel_adapters' => sanitize_text_field((string) ($versions['channel_adapters'] ?? $facebook['contract_version'] ?? $facebook_groups['contract_version'] ?? $pinterest['contract_version'] ?? '')),
        ];
    }

    private function is_canonical_contract_version(string $value, string $prefix): bool
    {
        return str_starts_with(sanitize_text_field($value), $prefix);
    }

    private function canonical_contract_checks(array $generated, array $job = [], int $target_pages = 0): array
    {
        $versions = $this->generated_contract_versions($generated);
        $package_contract_enforced = $this->is_canonical_contract_version((string) ($versions['content_package'] ?? ''), 'content-package-v');
        $channel_contract_enforced = $this->is_canonical_contract_version((string) ($versions['channel_adapters'] ?? ''), 'channel-adapters-v');
        $raw_package = is_array($generated['content_package'] ?? null) ? $generated['content_package'] : [];
        $raw_channels = is_array($generated['channels'] ?? null) ? $generated['channels'] : [];
        $raw_facebook = is_array($raw_channels['facebook'] ?? null) ? $raw_channels['facebook'] : [];
        $raw_facebook_groups = is_array($raw_channels['facebook_groups'] ?? null) ? $raw_channels['facebook_groups'] : [];
        $raw_pinterest = is_array($raw_channels['pinterest'] ?? null) ? $raw_channels['pinterest'] : [];
        $content_machine = is_array($generated['content_machine'] ?? null) ? $generated['content_machine'] : [];
        $contracts = is_array($content_machine['contracts'] ?? null) ? $content_machine['contracts'] : [];
        $fallbacks = is_array($contracts['fallbacks'] ?? null) ? $contracts['fallbacks'] : [];
        $package_fallback = !empty($fallbacks['content_package']);
        $facebook_fallback = !empty($fallbacks['facebook']);
        $facebook_groups_fallback = !empty($fallbacks['facebook_groups']);
        $pinterest_fallback = !empty($fallbacks['pinterest']);
        $content_type = sanitize_key((string) ($raw_package['content_type'] ?? $generated['content_type'] ?? $job['content_type'] ?? 'recipe'));
        if ($content_type === '') {
            $content_type = 'recipe';
        }

        $warning_checks = [];

        if ($package_contract_enforced) {
            $has_package_pages = !empty($raw_package['content_pages']) && is_array($raw_package['content_pages'])
                && count(array_filter(array_map(
                    static fn ($page): string => trim(wp_strip_all_tags((string) $page)),
                    $raw_package['content_pages']
                ))) > 0;
            $has_package_body = trim(wp_strip_all_tags((string) ($raw_package['content_html'] ?? ''))) !== '' || $has_package_pages;
            $package_profile = is_array($raw_package['profile'] ?? null) ? $raw_package['profile'] : [];
            $package_looks_canonical =
                !empty($raw_package)
                && sanitize_text_field((string) ($raw_package['contract_version'] ?? '')) !== ''
                && sanitize_key((string) ($raw_package['package_shape'] ?? '')) === 'canonical_content_package'
                && sanitize_key((string) ($raw_package['source_layer'] ?? '')) === 'article_engine'
                && sanitize_text_field((string) ($raw_package['title'] ?? '')) !== ''
                && sanitize_title((string) ($raw_package['slug'] ?? '')) !== ''
                && $has_package_body
                && !empty($raw_package['page_flow']) && is_array($raw_package['page_flow'])
                && !empty($package_profile)
                && ($content_type !== 'recipe' || is_array($raw_package['recipe'] ?? null));

            if (!$package_looks_canonical || $package_fallback) {
                $warning_checks[] = 'package_contract_drift';
            }
        }

        if ($channel_contract_enforced) {
            $facebook_required =
                $target_pages > 0
                || !empty($raw_facebook)
                || !empty($generated['social_pack'])
                || !empty($generated['facebook_distribution']);
            if ($facebook_required) {
                $facebook_looks_canonical =
                    !empty($raw_facebook)
                    && sanitize_text_field((string) ($raw_facebook['contract_version'] ?? '')) !== ''
                    && sanitize_key((string) ($raw_facebook['input_package'] ?? '')) === 'content_package'
                    && is_array($raw_facebook['profile'] ?? null)
                    && is_array($raw_facebook['selected'] ?? null)
                    && is_array($raw_facebook['distribution'] ?? null);
                if (!$facebook_looks_canonical || $facebook_fallback) {
                    $warning_checks[] = 'facebook_adapter_contract_drift';
                }
            }

            $facebook_groups_looks_canonical =
                !empty($raw_facebook_groups)
                && sanitize_text_field((string) ($raw_facebook_groups['contract_version'] ?? '')) !== ''
                && sanitize_key((string) ($raw_facebook_groups['input_package'] ?? '')) === 'content_package'
                && is_array($raw_facebook_groups['profile'] ?? null)
                && is_array($raw_facebook_groups['draft'] ?? null);
            if (!$facebook_groups_looks_canonical || $facebook_groups_fallback) {
                $warning_checks[] = 'facebook_groups_adapter_contract_drift';
            }

            $pinterest_looks_canonical =
                !empty($raw_pinterest)
                && sanitize_text_field((string) ($raw_pinterest['contract_version'] ?? '')) !== ''
                && sanitize_key((string) ($raw_pinterest['input_package'] ?? '')) === 'content_package'
                && is_array($raw_pinterest['profile'] ?? null)
                && is_array($raw_pinterest['draft'] ?? null);
            if (!$pinterest_looks_canonical || $pinterest_fallback) {
                $warning_checks[] = 'pinterest_adapter_contract_drift';
            }
        }

        return [
            'package_contract_enforced' => $package_contract_enforced,
            'channel_contract_enforced' => $channel_contract_enforced,
            'warning_checks' => array_values(array_unique($warning_checks)),
        ];
    }

    private function normalize_social_pack_variants(array $pack, string $content_type): array
    {
        return array_values(array_filter(array_map(
            function ($variant) use ($content_type): array {
                if (!is_array($variant)) {
                    return [];
                }

                $normalized = array_filter([
                    'id'           => sanitize_key((string) ($variant['id'] ?? '')),
                    'angle_key'    => $this->normalize_hook_angle_key((string) ($variant['angle_key'] ?? $variant['angleKey'] ?? ''), $content_type),
                    'hook'         => sanitize_text_field((string) ($variant['hook'] ?? '')),
                    'caption'      => sanitize_textarea_field((string) ($variant['caption'] ?? ($variant['post_message'] ?? $variant['postMessage'] ?? ''))),
                    'cta_hint'     => sanitize_text_field((string) ($variant['cta_hint'] ?? $variant['ctaHint'] ?? '')),
                    'post_message' => sanitize_textarea_field((string) ($variant['post_message'] ?? $variant['postMessage'] ?? '')),
                ]);

                return $normalized;
            },
            $pack
        )));
    }

    private function normalize_facebook_distribution_payload($distribution, array $job = [], string $content_type = 'recipe'): array
    {
        $distribution = is_array($distribution) ? $distribution : [];
        $pages = is_array($distribution['pages'] ?? null) ? $distribution['pages'] : [];
        $normalized = [];

        foreach ($pages as $page_id => $page) {
            if (!is_array($page)) {
                continue;
            }

            $id = sanitize_text_field((string) ($page['page_id'] ?? $page_id));
            if ($id === '') {
                continue;
            }

            $normalized[$id] = [
                'page_id'         => $id,
                'label'           => sanitize_text_field((string) ($page['label'] ?? '')),
                'angle_key'       => $this->normalize_hook_angle_key((string) ($page['angle_key'] ?? $page['angleKey'] ?? ''), $content_type),
                'hook'            => sanitize_text_field((string) ($page['hook'] ?? '')),
                'caption'         => sanitize_textarea_field((string) ($page['caption'] ?? ($page['post_message'] ?? $page['postMessage'] ?? ''))),
                'cta_hint'        => sanitize_text_field((string) ($page['cta_hint'] ?? $page['ctaHint'] ?? '')),
                'post_message'    => sanitize_textarea_field((string) ($page['post_message'] ?? $page['postMessage'] ?? '')),
                'post_id'         => sanitize_text_field((string) ($page['post_id'] ?? $page['postId'] ?? '')),
                'post_url'        => esc_url_raw((string) ($page['post_url'] ?? $page['postUrl'] ?? '')),
                'comment_message' => sanitize_textarea_field((string) ($page['comment_message'] ?? $page['commentMessage'] ?? '')),
                'comment_id'      => sanitize_text_field((string) ($page['comment_id'] ?? $page['commentId'] ?? '')),
                'comment_url'     => esc_url_raw((string) ($page['comment_url'] ?? $page['commentUrl'] ?? '')),
                'status'          => sanitize_key((string) ($page['status'] ?? '')),
                'error'           => sanitize_text_field((string) ($page['error'] ?? '')),
            ];
        }

        return ['pages' => $normalized];
    }

    private function normalized_generated_content_package(array $generated, array $job = []): array
    {
        $raw = is_array($generated['content_package'] ?? null) ? $generated['content_package'] : [];
        $raw_has_package = !empty($raw);
        $content_type = sanitize_key((string) ($raw['content_type'] ?? $generated['content_type'] ?? $job['content_type'] ?? 'recipe'));
        if ($content_type === '') {
            $content_type = 'recipe';
        }

        $content_html = (string) ($raw['content_html'] ?? $generated['content_html'] ?? '');
        $content_pages = is_array($raw['content_pages'] ?? null) ? $raw['content_pages'] : (is_array($generated['content_pages'] ?? null) ? $generated['content_pages'] : []);
        $content_pages = array_values(array_filter(array_map(
            static fn ($page): string => trim((string) $page),
            $content_pages
        ), static fn ($page): bool => $page !== ''));
        if (empty($content_pages) && $content_html !== '') {
            $content_pages = array_values(array_filter(preg_split('/\s*<!--nextpage-->\s*/i', $content_html) ?: []));
        }
        if ($content_html === '' && !empty($content_pages)) {
            $content_html = implode("\n<!--nextpage-->\n", $content_pages);
        }
        $content_html = $this->sanitize_post_content_with_page_breaks($content_html);

        $page_flow = $this->normalize_generated_page_flow(
            is_array($raw['page_flow'] ?? null) ? $raw['page_flow'] : (is_array($generated['page_flow'] ?? null) ? $generated['page_flow'] : []),
            $content_pages
        );
        $profile = is_array($raw['profile'] ?? null)
            ? array_replace($this->content_type_profile($content_type), $raw['profile'])
            : $this->content_type_profile($content_type);
        $package = [
            'contract_version' => sanitize_text_field((string) ($raw['contract_version'] ?? self::CONTENT_PACKAGE_CONTRACT_VERSION)),
            'package_shape'    => sanitize_key((string) ($raw['package_shape'] ?? 'canonical_content_package')),
            'source_layer'     => sanitize_key((string) ($raw['source_layer'] ?? ($raw_has_package ? 'article_engine' : 'legacy_mirror'))),
            'content_type'    => $content_type,
            'topic_seed'      => sanitize_text_field((string) ($raw['topic_seed'] ?? $generated['topic_seed'] ?? $job['topic'] ?? '')),
            'title'           => sanitize_text_field((string) ($raw['title'] ?? $generated['title'] ?? '')),
            'slug'            => sanitize_title((string) ($raw['slug'] ?? $generated['slug'] ?? '')),
            'excerpt'         => sanitize_text_field((string) ($raw['excerpt'] ?? $generated['excerpt'] ?? '')),
            'seo_description' => sanitize_text_field((string) ($raw['seo_description'] ?? $generated['seo_description'] ?? '')),
            'content_html'    => $content_html,
            'content_pages'   => $content_pages,
            'page_flow'       => $page_flow,
            'image_prompt'    => sanitize_textarea_field((string) ($raw['image_prompt'] ?? $generated['image_prompt'] ?? '')),
            'image_alt'       => sanitize_text_field((string) ($raw['image_alt'] ?? $generated['image_alt'] ?? '')),
            'article_signals' => is_array($raw['article_signals'] ?? null) ? $raw['article_signals'] : [],
            'quality_summary' => is_array($raw['quality_summary'] ?? null) ? $raw['quality_summary'] : [],
            'profile'         => $profile,
        ];

        if ($content_type === 'recipe') {
            $package['recipe'] = is_array($raw['recipe'] ?? null) ? $raw['recipe'] : (is_array($generated['recipe'] ?? null) ? $generated['recipe'] : []);
        }

        return $package;
    }

    private function build_pinterest_draft_from_package(array $package, array $existing = [], string $fallback_guidance = ''): array
    {
        $signals = is_array($package['article_signals'] ?? null) ? $package['article_signals'] : [];
        $keywords_source = implode(' ', array_filter([
            $package['content_type'] ?? '',
            $signals['heading_topic'] ?? '',
            $signals['ingredient_focus'] ?? '',
            $signals['meta_line'] ?? '',
        ]));
        $keywords = preg_split('/[\s,]+/', strtolower((string) $keywords_source)) ?: [];
        $keywords = array_values(array_unique(array_filter(array_map(
            static fn ($keyword): string => sanitize_title((string) $keyword),
            $keywords
        ), static fn ($keyword): bool => $keyword !== '' && strlen($keyword) > 2)));

        return [
            'pin_title'            => sanitize_text_field((string) ($existing['pin_title'] ?? $package['title'] ?? '')),
            'pin_description'      => sanitize_textarea_field((string) ($existing['pin_description'] ?? $package['excerpt'] ?? '')),
            'pin_keywords'         => !empty($existing['pin_keywords']) && is_array($existing['pin_keywords'])
                ? array_values(array_filter(array_map('sanitize_title', $existing['pin_keywords'])))
                : array_slice($keywords, 0, 12),
            'image_prompt_override'=> sanitize_textarea_field((string) ($existing['image_prompt_override'] ?? trim(((string) ($package['image_prompt'] ?? '')) . "\nVertical Pinterest pin composition, 2:3 aspect ratio, clean focal hierarchy."))),
            'image_format_hint'    => sanitize_text_field((string) ($existing['image_format_hint'] ?? '1000x1500 vertical pin')),
            'overlay_text'         => sanitize_text_field((string) ($existing['overlay_text'] ?? $package['title'] ?? '')),
            'guidance'             => sanitize_textarea_field((string) ($existing['guidance'] ?? $fallback_guidance)),
        ];
    }

    private function build_facebook_groups_draft_from_package(array $package, array $existing = [], string $fallback_share_kit = '', string $fallback_guidance = ''): array
    {
        $signals = is_array($package['article_signals'] ?? null) ? $package['article_signals'] : [];
        $fallback = trim(implode("\n", array_filter([
            sanitize_text_field((string) ($signals['pain_line'] ?? $signals['summary_line'] ?? '')),
            sanitize_text_field((string) ($signals['payoff_line'] ?? $package['excerpt'] ?? '')),
        ])));
        $share_blurb = sanitize_textarea_field((string) ($existing['share_blurb'] ?? $existing['group_share_kit'] ?? ($fallback_share_kit !== '' ? $fallback_share_kit : $fallback)));

        return [
            'share_blurb'  => $share_blurb,
            'sharing_mode' => sanitize_key((string) ($existing['sharing_mode'] ?? 'manual_operator_share')),
            'input_package'=> sanitize_key((string) ($existing['input_package'] ?? 'content_package')),
            'guidance'     => sanitize_textarea_field((string) ($existing['guidance'] ?? $fallback_guidance)),
        ];
    }

    private function generated_channels(array $generated, array $job = []): array
    {
        $package = $this->normalized_generated_content_package($generated, $job);
        $raw_channels = is_array($generated['channels'] ?? null) ? $generated['channels'] : [];
        $facebook_raw = is_array($raw_channels['facebook'] ?? null) ? $raw_channels['facebook'] : [];
        $facebook_groups_raw = is_array($raw_channels['facebook_groups'] ?? null) ? $raw_channels['facebook_groups'] : [];
        $pinterest_raw = is_array($raw_channels['pinterest'] ?? null) ? $raw_channels['pinterest'] : [];

        $facebook_candidates = $this->normalize_social_pack_variants(
            is_array($facebook_raw['candidates'] ?? null)
                ? $facebook_raw['candidates']
                : (is_array($generated['social_candidates'] ?? null) ? $generated['social_candidates'] : []),
            (string) ($package['content_type'] ?? 'recipe')
        );
        $facebook_selected = $this->normalize_social_pack_variants(
            is_array($facebook_raw['selected'] ?? null)
                ? $facebook_raw['selected']
                : (is_array($generated['social_pack'] ?? null) ? $generated['social_pack'] : []),
            (string) ($package['content_type'] ?? 'recipe')
        );

        return [
            'facebook' => [
                'channel'         => 'facebook',
                'contract_version'=> sanitize_text_field((string) ($facebook_raw['contract_version'] ?? self::CHANNEL_ADAPTER_CONTRACT_VERSION)),
                'live'            => true,
                'profile'         => is_array($facebook_raw['profile'] ?? null) ? array_replace($this->channel_profile('facebook'), $facebook_raw['profile']) : $this->channel_profile('facebook'),
                'input_package'   => sanitize_key((string) ($facebook_raw['input_package'] ?? 'content_package')),
                'candidates'      => $facebook_candidates,
                'selected'        => $facebook_selected,
                'distribution'    => $this->normalize_facebook_distribution_payload(
                    is_array($facebook_raw['distribution'] ?? null)
                        ? $facebook_raw['distribution']
                        : (is_array($generated['facebook_distribution'] ?? null) ? $generated['facebook_distribution'] : []),
                    $job,
                    (string) ($package['content_type'] ?? 'recipe')
                ),
                'quality_summary' => is_array($facebook_raw['quality_summary'] ?? null) ? $facebook_raw['quality_summary'] : [],
            ],
            'facebook_groups' => [
                'channel'         => 'facebook_groups',
                'contract_version'=> sanitize_text_field((string) ($facebook_groups_raw['contract_version'] ?? self::CHANNEL_ADAPTER_CONTRACT_VERSION)),
                'live'            => false,
                'profile'         => is_array($facebook_groups_raw['profile'] ?? null) ? array_replace($this->channel_profile('facebook_groups'), $facebook_groups_raw['profile']) : $this->channel_profile('facebook_groups'),
                'input_package'   => sanitize_key((string) ($facebook_groups_raw['input_package'] ?? 'content_package')),
                'draft'           => $this->build_facebook_groups_draft_from_package(
                    $package,
                    is_array($facebook_groups_raw['draft'] ?? null) ? $facebook_groups_raw['draft'] : [],
                    sanitize_textarea_field((string) ($generated['group_share_kit'] ?? '')),
                    $this->default_group_share_guidance()
                ),
                'quality_summary' => is_array($facebook_groups_raw['quality_summary'] ?? null) ? $facebook_groups_raw['quality_summary'] : [],
            ],
            'pinterest' => [
                'channel'         => 'pinterest',
                'contract_version'=> sanitize_text_field((string) ($pinterest_raw['contract_version'] ?? self::CHANNEL_ADAPTER_CONTRACT_VERSION)),
                'live'            => false,
                'profile'         => is_array($pinterest_raw['profile'] ?? null) ? array_replace($this->channel_profile('pinterest'), $pinterest_raw['profile']) : $this->channel_profile('pinterest'),
                'input_package'   => sanitize_key((string) ($pinterest_raw['input_package'] ?? 'content_package')),
                'draft'           => $this->build_pinterest_draft_from_package(
                    $package,
                    is_array($pinterest_raw['draft'] ?? null) ? $pinterest_raw['draft'] : [],
                    $this->default_pinterest_draft_guidance()
                ),
                'quality_summary' => is_array($pinterest_raw['quality_summary'] ?? null) ? $pinterest_raw['quality_summary'] : [],
            ],
        ];
    }

    private function sync_generated_contract_containers(array $generated, array $job = []): array
    {
        $content_machine = is_array($generated['content_machine'] ?? null) ? $generated['content_machine'] : [];
        $contracts = is_array($content_machine['contracts'] ?? null) ? $content_machine['contracts'] : [];
        $legacy_job = array_key_exists('legacy_job', $contracts) ? (bool) $contracts['legacy_job'] : null;
        $typed_contract_job = array_key_exists('typed_contract_job', $contracts) ? (bool) $contracts['typed_contract_job'] : null;
        if ($legacy_job === null && $typed_contract_job === null) {
            $legacy_job = true;
            $typed_contract_job = false;
        } elseif ($legacy_job === null) {
            $legacy_job = !$typed_contract_job;
        } elseif ($typed_contract_job === null) {
            $typed_contract_job = !$legacy_job;
        }
        $raw_channels = is_array($generated['channels'] ?? null) ? $generated['channels'] : [];
        $fallbacks = [
            'content_package' => empty($generated['content_package']) || !is_array($generated['content_package']),
            'facebook' => empty($raw_channels['facebook']) || !is_array($raw_channels['facebook']),
            'facebook_groups' => empty($raw_channels['facebook_groups']) || !is_array($raw_channels['facebook_groups']),
            'pinterest' => empty($raw_channels['pinterest']) || !is_array($raw_channels['pinterest']),
        ];
        $content_package = $this->normalized_generated_content_package($generated, $job);
        $generated = array_merge($generated, [
            'content_type'    => sanitize_key((string) ($content_package['content_type'] ?? ($generated['content_type'] ?? 'recipe'))),
            'topic_seed'      => sanitize_text_field((string) ($content_package['topic_seed'] ?? ($generated['topic_seed'] ?? ''))),
            'title'           => sanitize_text_field((string) ($content_package['title'] ?? ($generated['title'] ?? ''))),
            'slug'            => sanitize_title((string) ($content_package['slug'] ?? ($generated['slug'] ?? ''))),
            'excerpt'         => sanitize_text_field((string) ($content_package['excerpt'] ?? ($generated['excerpt'] ?? ''))),
            'seo_description' => sanitize_text_field((string) ($content_package['seo_description'] ?? ($generated['seo_description'] ?? ''))),
            'content_html'    => (string) ($content_package['content_html'] ?? ($generated['content_html'] ?? '')),
            'content_pages'   => is_array($content_package['content_pages'] ?? null) ? $content_package['content_pages'] : [],
            'page_flow'       => is_array($content_package['page_flow'] ?? null) ? $content_package['page_flow'] : [],
            'image_prompt'    => sanitize_textarea_field((string) ($content_package['image_prompt'] ?? ($generated['image_prompt'] ?? ''))),
            'image_alt'       => sanitize_text_field((string) ($content_package['image_alt'] ?? ($generated['image_alt'] ?? ''))),
            'recipe'          => ($content_package['content_type'] ?? 'recipe') === 'recipe' && is_array($content_package['recipe'] ?? null)
                ? $content_package['recipe']
                : [],
            'content_package' => $content_package,
        ]);

        $channels = $this->generated_channels($generated, $job);
        $facebook_caption = $this->derive_legacy_facebook_caption(array_merge($generated, ['channels' => $channels]), $job);
        $group_share_kit = $this->derive_legacy_group_share_kit(array_merge($generated, ['channels' => $channels]));
        $contract_versions = is_array($content_machine['contract_versions'] ?? null) ? $content_machine['contract_versions'] : [];
        $channel_contract_version = sanitize_text_field((string) ($channels['facebook']['contract_version'] ?? $contract_versions['channel_adapters'] ?? self::CHANNEL_ADAPTER_CONTRACT_VERSION));
        $content_machine['contract_versions'] = [
            'content_package' => sanitize_text_field((string) ($content_package['contract_version'] ?? $contract_versions['content_package'] ?? self::CONTENT_PACKAGE_CONTRACT_VERSION)),
            'channel_adapters' => $channel_contract_version,
        ];
        $content_machine['contracts'] = array_merge($contracts, [
            'legacy_job' => $legacy_job,
            'typed_contract_job' => $typed_contract_job,
            'fallbacks' => $fallbacks,
        ]);

        return array_merge($generated, [
            'social_candidates'     => is_array($channels['facebook']['candidates'] ?? null) ? $channels['facebook']['candidates'] : [],
            'social_pack'           => is_array($channels['facebook']['selected'] ?? null) ? $channels['facebook']['selected'] : [],
            'facebook_distribution' => is_array($channels['facebook']['distribution'] ?? null) ? $channels['facebook']['distribution'] : ['pages' => []],
            'facebook_caption'      => $facebook_caption,
            'group_share_kit'       => $group_share_kit,
            'channels'              => $channels,
            'content_machine'       => $content_machine,
        ]);
    }

    private function job_social_pack(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $channels = $this->generated_channels($generated, $job);

        return is_array($channels['facebook']['selected'] ?? null) ? $channels['facebook']['selected'] : [];
    }

    private function strip_hook_echo_from_caption(string $hook, string $caption): string
    {
        $hook = sanitize_text_field($hook);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        if ($hook === '' || count($lines) < 2) {
            return implode("\n", $lines);
        }

        if (sanitize_title($lines[0]) === sanitize_title($hook)) {
            array_shift($lines);
        }

        return implode("\n", $lines);
    }

    private function build_facebook_post_preview(array $variant): string
    {
        $settings = $this->get_settings();
        $persisted = trim((string) ($variant['post_message'] ?? $variant['postMessage'] ?? ''));
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = $this->strip_hook_echo_from_caption($hook, (string) ($variant['caption'] ?? ''));
        $post_id = sanitize_text_field((string) ($variant['post_id'] ?? $variant['postId'] ?? ''));

        if ($hook !== '' || trim($caption) !== '') {
            return $this->append_facebook_comment_teaser(
                trim(implode("\n\n", array_filter([$hook, $caption]))),
                $this->facebook_post_teaser_cta_setting($settings)
            );
        }

        if ($persisted !== '') {
            if ($post_id !== '') {
                return sanitize_textarea_field($persisted);
            }

            return $this->append_facebook_comment_teaser($persisted, $this->facebook_post_teaser_cta_setting($settings));
        }

        return '';
    }

    private function build_facebook_comment_preview(array $job, array $page): string
    {
        $persisted = trim((string) ($page['comment_message'] ?? $page['commentMessage'] ?? ''));
        $comment_id = sanitize_text_field((string) ($page['comment_id'] ?? $page['commentId'] ?? ''));
        if ($persisted !== '') {
            if ($comment_id !== '') {
                return sanitize_textarea_field($persisted);
            }
        }

        $permalink = esc_url_raw((string) ($job['permalink'] ?? ''));
        if ($permalink === '') {
            return sanitize_textarea_field($persisted);
        }

        $settings = $this->get_settings();
        $content_label = 'facebook_comment_' . sanitize_title((string) ($page['label'] ?? $page['page_id'] ?? 'page'));
        $tracked_url = $this->build_tracked_distribution_url($permalink, $settings, $job, $content_label);
        $cta = $this->facebook_comment_link_cta_setting($settings);
        $message = trim(implode("\n", array_filter([
            $this->down_pointing_finger_emoji() . ' ' . ($cta !== '' ? $cta : __('Read the full article on the blog.', 'kuchnia-twist')),
            $tracked_url,
        ])));

        return sanitize_textarea_field($message);
    }

    private function append_facebook_comment_teaser(string $message, string $teaser = ''): string
    {
        $message = sanitize_textarea_field($message);
        if ($message === '' || preg_match('/\bfirst comment\b/i', $message) === 1) {
            return $message;
        }

        $teaser = sanitize_text_field($teaser);
        if ($teaser === '') {
            $teaser = $this->facebook_post_teaser_cta_setting();
        }

        return sanitize_textarea_field(trim($message . "\n\n" . $teaser));
    }

    private function facebook_post_teaser_cta_setting(array $settings = []): string
    {
        if (empty($settings)) {
            $settings = $this->get_settings();
        }

        $cta = sanitize_text_field((string) ($settings['facebook_post_teaser_cta'] ?? ''));
        return $cta !== '' ? $cta : ($this->down_pointing_finger_emoji() . ' ' . __('Full article in the first comment below.', 'kuchnia-twist'));
    }

    private function facebook_comment_link_cta_setting(array $settings = []): string
    {
        if (empty($settings)) {
            $settings = $this->get_settings();
        }

        $cta = sanitize_text_field((string) ($settings['facebook_comment_link_cta'] ?? $settings['default_cta'] ?? ''));
        return $cta !== '' ? $cta : __('Read the full article on the blog.', 'kuchnia-twist');
    }

    private function derive_legacy_facebook_caption(array $generated, array $job = []): string
    {
        $channels = $this->generated_channels($generated, $job);
        $facebook = is_array($channels['facebook'] ?? null) ? $channels['facebook'] : [];
        $selected = is_array($facebook['selected'] ?? null) ? $facebook['selected'] : [];
        foreach ($selected as $variant) {
            if (is_array($variant)) {
                $preview = $this->build_facebook_post_preview($variant);
                if ($preview !== '') {
                    return sanitize_textarea_field($preview);
                }
            }
        }

        $distribution_pages = is_array($facebook['distribution']['pages'] ?? null) ? $facebook['distribution']['pages'] : [];
        foreach ($distribution_pages as $page) {
            if (is_array($page)) {
                $preview = $this->build_facebook_post_preview($page);
                if ($preview !== '') {
                    return sanitize_textarea_field($preview);
                }
            }
        }

        return sanitize_textarea_field((string) ($generated['facebook_caption'] ?? ''));
    }

    private function derive_legacy_group_share_kit(array $generated): string
    {
        $channels = $this->generated_channels($generated, []);
        $facebook_groups = is_array($channels['facebook_groups'] ?? null) ? $channels['facebook_groups'] : [];
        if (!empty($facebook_groups['draft']['share_blurb'])) {
            return sanitize_textarea_field((string) $facebook_groups['draft']['share_blurb']);
        }

        return sanitize_textarea_field((string) ($generated['group_share_kit'] ?? ''));
    }

    private function build_tracked_distribution_url(string $permalink, array $settings, array $job, string $content_label): string
    {
        if ($permalink === '') {
            return '';
        }

        $title_source = '';
        if (!empty($job['generated_payload']) && is_array($job['generated_payload'])) {
            $package = $this->normalized_generated_content_package($job['generated_payload'], $job);
            $title_source = (string) (($package['slug'] ?? '') ?: ($package['title'] ?? '') ?: ($job['generated_payload']['slug'] ?? '') ?: ($job['generated_payload']['title'] ?? ''));
        }
        if ($title_source === '') {
            $title_source = (string) ($job['topic'] ?? 'recipe');
        }

        return add_query_arg([
            'utm_source'   => sanitize_key((string) ($settings['utm_source'] ?? 'facebook')) ?: 'facebook',
            'utm_medium'   => 'social',
            'utm_campaign' => substr(
                sanitize_title((string) ($settings['utm_campaign_prefix'] ?? 'kuchnia-twist')) . '-' . sanitize_title($title_source),
                0,
                80
            ),
            'utm_content'  => sanitize_title($content_label),
        ], $permalink);
    }

    private function job_facebook_distribution(array $job): array
    {
        $generated = is_array($job['generated_payload'] ?? null) ? $job['generated_payload'] : [];
        $channels = $this->generated_channels($generated, $job);

        return is_array($channels['facebook']['distribution'] ?? null)
            ? $channels['facebook']['distribution']
            : ['pages' => []];
    }

    private function resolve_contract_job_flags(array $generated): array
    {
        $content_machine = is_array($generated['content_machine'] ?? null) ? $generated['content_machine'] : [];
        $contracts = is_array($content_machine['contracts'] ?? null) ? $content_machine['contracts'] : [];
        $legacy_job = array_key_exists('legacy_job', $contracts) ? (bool) $contracts['legacy_job'] : null;
        $typed_contract_job = array_key_exists('typed_contract_job', $contracts) ? (bool) $contracts['typed_contract_job'] : null;
        if ($legacy_job === null && $typed_contract_job === null) {
            $legacy_job = true;
            $typed_contract_job = false;
        } elseif ($legacy_job === null) {
            $legacy_job = !$typed_contract_job;
        } elseif ($typed_contract_job === null) {
            $typed_contract_job = !$legacy_job;
        }

        return [
            'legacy_job' => $legacy_job,
            'typed_contract_job' => $typed_contract_job,
            'fallbacks' => is_array($contracts['fallbacks'] ?? null) ? $contracts['fallbacks'] : [],
        ];
    }
}
