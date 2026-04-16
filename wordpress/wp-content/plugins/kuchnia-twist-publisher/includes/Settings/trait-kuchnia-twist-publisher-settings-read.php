<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Settings_Read_Trait
{
    private function get_settings(): array
    {
        $settings = wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings());
        $settings['image_generation_mode'] = $this->sanitize_image_generation_mode($settings['image_generation_mode'] ?? 'uploaded_first_generate_missing');
        $strict_contract_env = trim((string) getenv('AUTOPOST_STRICT_CONTRACT_MODE'));
        if ($strict_contract_env !== '') {
            $settings['strict_contract_mode'] = in_array(strtolower($strict_contract_env), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        $legacy_publication_role_defaults = [
            implode("\n", [
                'You are the lead editorial writer for kuchniatwist.',
                'Write for a food publication that wants strong clicks, honest payoff, and articles that feel worth the visit.',
                'Your job is to produce publishable recipe articles and food explainers that sound human, specific, and confident.',
            ]),
            implode("\n", [
                'You are the lead editorial writer for kuchniatwist.',
                'Write for a food publication that wants strong clicks, honest payoff, and articles that feel worth the visit.',
                'Your job is to produce publishable recipe articles and food explainers that sound human, specific, and confident.',
                'Win the click with real value, then reward the visit with clear structure, useful detail, and sharp editorial judgment.',
            ]),
        ];
        $legacy_voice_defaults = [
            'Warm, practical, calm, and editorial. The site should sound like a trusted home-cooking publication rather than a generic SEO blog.',
            'Warm, practical, craveable, and trustworthy. Write for real home cooks who want clear steps, reliable results, and recipes worth repeating.',
            implode("\n", [
                'Warm, practical, craveable, and trustworthy.',
                'Write for real home cooks who want clear steps, reliable results, and recipes worth repeating.',
                'The tone should feel premium and human, not robotic, spammy, or overhyped.',
            ]),
            implode("\n", [
                'Warm, practical, craveable, and trustworthy.',
                'Write for real home cooks who want clear steps, reliable results, and recipes worth repeating.',
                'Lead with appetite, usefulness, and honest payoff so every click feels earned.',
                'The tone should feel premium and human, not robotic, spammy, or overhyped.',
            ]),
            implode("\n", [
                'Warm, practical, craveable, and trustworthy.',
                'Write for real home cooks and curious food readers who want reliable recipes, sharper kitchen understanding, and a concrete payoff after the click.',
                'Lead with usefulness, appetite, and honest curiosity.',
                'The tone should feel premium, human, and editorial rather than robotic, spammy, or overhyped.',
            ]),
        ];
        $legacy_guardrail_defaults = [
            implode("\n", [
                'No fake personal stories or invented family memories.',
                'No filler SEO intros, generic throat-clearing, or padded explanations.',
                'No spammy clickbait, fake cliffhangers, or weak viral language.',
                'No medical, nutrition, weight-loss, detox, or expert claims beyond ordinary kitchen guidance.',
                'Keep paragraphs short, specific, and human.',
                'Every promise in the hook or title must be honestly supported by the recipe.',
            ]),
            implode("\n", [
                'No fake personal stories or invented family memories.',
                'No filler SEO intros, generic throat-clearing, or padded explanations.',
                'No spammy clickbait, fake cliffhangers, or weak viral language.',
                'No medical, nutrition, weight-loss, detox, or expert claims beyond ordinary kitchen guidance.',
                'Keep paragraphs short, specific, and human.',
                'Every promise in the hook or title must be honestly supported by the recipe.',
                'Do not use generic openers like "when it comes to" or "in today\'s busy world."',
            ]),
            implode("\n", [
                'No fake personal stories, invented reporting, fabricated authority, or made-up facts.',
                'No filler SEO intros, generic throat-clearing, or padded explanations.',
                'No spammy clickbait, fake cliffhangers, or hollow viral language.',
                'No medical, nutrition, detox, or expert claims beyond ordinary kitchen guidance.',
                'Keep paragraphs short, specific, and human.',
                'Every promise in the title, hook, excerpt, or caption must be honestly supported by the article.',
                'Do not use generic openers like "when it comes to" or "in today\'s busy world."',
            ]),
        ];
        $legacy_master_defaults = [
            'Generate a premium, practical recipe article from the dish name. Return a complete blog package, recipe card data, image direction, and a strong multi-page Facebook social pack with one unique hook-led variant per selected page.',
            'Turn the dish name into a complete premium recipe package with a strong title, excerpt, SEO description, useful article body, structured recipe card, image direction, and one unique Facebook variant per selected page.',
            implode("\n", [
                'Turn the dish name into one complete premium recipe package.',
                'Return a strong title, excerpt, SEO description, useful article body, structured recipe card, image direction, and one unique Facebook variant per selected page.',
                'Make the recipe feel highly clickable without being misleading.',
                'Make the output original, practical, cookable, and worth publishing on a real food site.',
            ]),
            implode("\n", [
                'Turn the dish name into one complete premium recipe package.',
                'Return a strong title, excerpt, SEO description, useful article body, structured recipe card, image direction, and one unique Facebook variant per selected page.',
                'Make the recipe feel highly clickable without being misleading.',
                'Make the output original, practical, cookable, and worth publishing on a real food site.',
                'Keep the blog article strong enough to justify the click and the Facebook variants sharp enough to earn it.',
            ]),
        ];
        $legacy_article_defaults = [
            'Write original, useful, and substantial home-cooking content with clean headings, specific guidance, and no filler.',
            'Open with appetite and payoff. Use H2 sections for why this recipe works, ingredient notes, practical method, and serving or storage tips. Keep the recipe realistic, cookable, and repeatable for home kitchens.',
            implode("\n", [
                'Open with appetite, payoff, and a clear reason to care.',
                'Use H2 sections for why this recipe works, ingredient notes, practical method, and serving or storage tips.',
                'Keep the recipe realistic, cookable, and repeatable for home kitchens.',
                'Prioritize taste, texture, convenience, comfort, and kitchen usefulness over fluff.',
                'Do not paste the full ingredient list and full numbered method into the article body; keep those in the recipe card.',
            ]),
            implode("\n", [
                'Open with appetite, payoff, and a clear reason to care.',
                'Use 1 to 2 short opening paragraphs, then H2 sections for why this recipe works, ingredient notes, practical method, and serving or storage tips.',
                'Build the article as 2 to 3 intentional pages: page 1 hooks the reader, page 2 deepens the useful detail, and page 3, if needed, should feel earned and lead naturally into the recipe card.',
                'Keep the recipe realistic, cookable, and repeatable for home kitchens.',
                'Prioritize taste, texture, convenience, comfort, and kitchen usefulness over fluff.',
                'Do not paste the full ingredient list and full numbered method into the article body; keep those in the recipe card.',
            ]),
        ];
        $legacy_food_fact_article_defaults = [
            implode("\n", [
                'Lead with a direct answer, not a slow introduction.',
                'Use H2 sections for the direct answer, what most people get wrong, what is actually happening, and the practical takeaway.',
                'Build the article as 2 to 3 intentional pages: page 1 answers the question fast, page 2 explains the why, and page 3, if needed, lands the practical takeaway.',
                'Keep the piece useful, specific, and strongly editorial without drifting into recipe-card structure.',
                'Focus on kitchen truth, clarity, and click-worthy payoff instead of vague listicle filler.',
                'Do not output ingredients, instructions, or recipe-style metadata for food facts.',
            ]),
        ];
        $legacy_recipe_preset_defaults = [
            'Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical value to reward the click.',
        ];
        $legacy_food_fact_preset_defaults = [
            'Treat the entered title as a working topic, answer it directly, correct common confusion, explain what is really happening, and finish with a practical takeaway.',
        ];
        $legacy_social_defaults = [
            'Write a short, hook-led Facebook caption that feels conversational and never includes the link.',
            'Generate one distinct variant per selected Facebook page. Use short curiosity-led hooks and 2 to 5 short caption lines. Vary the angle across pages: quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleasing, or better-than-takeout. No links, hashtags, or fake cliffhangers.',
            implode("\n", [
                'Generate a strong pool of recipe Facebook candidates, not clones.',
                'The final selected set must give each selected page a different angle such as quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleaser, or better-than-takeout.',
                'Hooks should be short, specific, and interruptive without fake hype or title echo.',
                'Favor hooks and captions that name a real payoff, shortcut, texture, timing, or cooking relief point instead of vague excitement.',
                'Captions should be 2 to 5 short lines that add real taste, texture, ease, timing, or payoff.',
                'Make at least some hooks feel sharper than safe generic social copy while staying honest and specific.',
                'No links, hashtags, emoji clutter, or repeated hook-as-caption openings.',
            ]),
            implode("\n", [
                'Generate one distinct Facebook variant per selected page.',
                'Each variant must have a short hook plus a 2 to 5 line caption.',
                'Vary the angle across pages: quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleasing, or better-than-takeout.',
                'Hooks must feel curiosity-led and high-performing without sounding fake or low-trust.',
                'No links, hashtags, emoji clutter, or fake cliffhangers.',
                'Do not repeat the same sentence pattern across all variants.',
            ]),
            implode("\n", [
                'Generate one distinct Facebook variant per selected page.',
                'Each variant must have a short hook plus a 2 to 5 line caption.',
                'Vary the angle across pages: quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleasing, or better-than-takeout.',
                'Keep the variant order aligned with the selected Facebook pages so each page receives one distinct angle.',
                'Hooks should usually stay within roughly 4 to 18 words and should not just repeat the title.',
                'Captions should expand the hook with taste, ease, texture, payoff, or usefulness, then close with a light CTA.',
                'Do not repeat the hook as the first caption line.',
                'No links, hashtags, emoji clutter, or fake cliffhangers.',
                'Do not repeat the same sentence pattern across all variants.',
            ]),
            implode("\n", [
                'Generate one distinct Facebook variant per selected page.',
                'Each variant must have a short hook plus a 2 to 5 line caption.',
                'Vary the angle across pages: quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleasing, or better-than-takeout.',
                'Keep the variant order aligned with the selected Facebook pages so each page receives one distinct angle.',
                'Hooks should usually stay within roughly 4 to 18 words and should not just repeat the title.',
                'Captions should expand the hook with taste, ease, texture, payoff, or usefulness, then close with a light CTA.',
                'Make at least some hooks feel sharper and more interruptive than a safe generic social post, while staying honest and specific.',
                'Do not repeat the hook as the first caption line.',
                'No links, hashtags, emoji clutter, fake cliffhangers, or generic hooks like "you need to try this" or "best ever."',
                'Do not repeat the same sentence pattern across all variants.',
            ]),
        ];
        $legacy_food_fact_social_defaults = [
            implode("\n", [
                'Generate a strong pool of food-fact Facebook candidates that feel myth-busting, surprising, corrective, or practically useful.',
                'The final selected set should vary angles such as surprising truth, kitchen mistake, smarter shortcut, ingredient truth, or what most people get wrong.',
                'Hooks should be short, specific, and curiosity-led without fake bait or title echo.',
                'Favor hooks and captions that name a real pain point, mistake, misunderstanding, or practical fix instead of broad curiosity alone.',
                'Captions should be 2 to 5 short lines that reveal the payoff or practical takeaway.',
                'Make at least some hooks feel punchy enough to stop scrolling, but avoid hollow listicle hype or obvious bait phrasing.',
                'No links, hashtags, emoji clutter, or empty listicle hype.',
            ]),
            implode("\n", [
                'Generate one distinct Facebook variant per selected page for title-first food explainers.',
                'Use angles like myth busting, surprising truth, kitchen mistake, smarter shortcut, ingredient truth, or what most people get wrong.',
                'Hooks should be short, curiosity-led, and specific without sounding fake or low-trust.',
                'Captions should be 2 to 5 short lines that explain the payoff, reveal the mistake, or promise a practical takeaway.',
                'Make at least some hooks feel punchy enough to stop scrolling, but avoid hollow listicle hype or obvious bait phrasing.',
                'No links, hashtags, emoji clutter, or empty listicle hype.',
            ]),
        ];
        $legacy_image_defaults = [
            'Natural food photography, editorial light, appetizing detail, no text overlays, premium magazine look.',
            'Realistic, appetizing food photography with natural light, warm editorial tone, clean plating, and no text overlays.',
            implode("\n", [
                'Realistic, appetizing food photography.',
                'Natural light, warm editorial tone, clean plating, and no text overlays.',
                'Make the dish look premium, craveable, and believable for a food blog and Facebook feed.',
            ]),
            implode("\n", [
                'Realistic, appetizing food photography.',
                'Natural light, warm editorial tone, clean plating, believable texture, and no text overlays.',
                'Make the dish look premium, craveable, and believable for a food blog hero or Facebook feed.',
            ]),
        ];
        $legacy_cta_defaults = [
            'Read the full recipe on the blog.',
            'Read the full article on the blog.',
        ];

        $settings['publication_role'] = trim((string) ($settings['publication_role'] ?? ''));
        if ($settings['publication_role'] === '' || in_array($settings['publication_role'], $legacy_publication_role_defaults, true)) {
            $settings['publication_role'] = $this->default_publication_role();
        }

        $settings['brand_voice'] = trim((string) ($settings['brand_voice'] ?? ''));
        if ($settings['brand_voice'] === '' || in_array($settings['brand_voice'], $legacy_voice_defaults, true)) {
            $settings['brand_voice'] = $this->default_voice_brief();
        }

        $settings['global_guardrails'] = trim((string) ($settings['global_guardrails'] ?? ''));
        if ($settings['global_guardrails'] === '' || in_array($settings['global_guardrails'], $legacy_guardrail_defaults, true)) {
            $settings['global_guardrails'] = $this->legacy_guardrails_text($settings) ?: $this->default_global_guardrails();
        }

        $settings['recipe_master_prompt'] = trim((string) ($settings['recipe_master_prompt'] ?? ''));
        if ($settings['recipe_master_prompt'] === '' || in_array($settings['recipe_master_prompt'], $legacy_master_defaults, true)) {
            $settings['recipe_master_prompt'] = $this->default_recipe_master_direction();
        }

        $settings['article_prompt'] = trim((string) ($settings['article_prompt'] ?? ''));
        if ($settings['article_prompt'] === '' || in_array($settings['article_prompt'], $legacy_article_defaults, true)) {
            $settings['article_prompt'] = $this->default_recipe_article_guidance();
        }

        $settings['food_fact_article_prompt'] = trim((string) ($settings['food_fact_article_prompt'] ?? ''));
        if ($settings['food_fact_article_prompt'] === '' || in_array($settings['food_fact_article_prompt'], $legacy_food_fact_article_defaults, true)) {
            $settings['food_fact_article_prompt'] = $this->default_food_fact_article_guidance();
        }

        $settings['recipe_preset_guidance'] = trim((string) ($settings['recipe_preset_guidance'] ?? ''));
        if ($settings['recipe_preset_guidance'] === '' || in_array($settings['recipe_preset_guidance'], $legacy_recipe_preset_defaults, true)) {
            $settings['recipe_preset_guidance'] = 'Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical value to reward the click and earn a repeat visit.';
        }

        $settings['food_fact_preset_guidance'] = trim((string) ($settings['food_fact_preset_guidance'] ?? ''));
        if ($settings['food_fact_preset_guidance'] === '' || in_array($settings['food_fact_preset_guidance'], $legacy_food_fact_preset_defaults, true)) {
            $settings['food_fact_preset_guidance'] = 'Treat the entered title as a working topic, answer it directly, correct common confusion, explain what is really happening, and finish with a practical takeaway that makes the reader want to come back.';
        }

        $settings['facebook_caption_guidance'] = trim((string) ($settings['facebook_caption_guidance'] ?? ''));
        if ($settings['facebook_caption_guidance'] === '' || in_array($settings['facebook_caption_guidance'], $legacy_social_defaults, true)) {
            $settings['facebook_caption_guidance'] = $this->default_facebook_variant_guidance();
        }

        $settings['food_fact_facebook_caption_guidance'] = trim((string) ($settings['food_fact_facebook_caption_guidance'] ?? ''));
        if ($settings['food_fact_facebook_caption_guidance'] === '' || in_array($settings['food_fact_facebook_caption_guidance'], $legacy_food_fact_social_defaults, true)) {
            $settings['food_fact_facebook_caption_guidance'] = $this->default_food_fact_social_guidance();
        }

        $settings['group_share_guidance'] = trim((string) ($settings['group_share_guidance'] ?? ''));
        if ($settings['group_share_guidance'] === '') {
            $settings['group_share_guidance'] = $this->default_group_share_guidance();
        }

        $settings['image_style'] = trim((string) ($settings['image_style'] ?? ''));
        if ($settings['image_style'] === '' || in_array($settings['image_style'], $legacy_image_defaults, true)) {
            $settings['image_style'] = $this->default_image_style_brief();
        }

        $settings['facebook_post_teaser_cta'] = trim((string) ($settings['facebook_post_teaser_cta'] ?? ''));
        if ($settings['facebook_post_teaser_cta'] === '') {
            $settings['facebook_post_teaser_cta'] = $this->default_facebook_post_teaser_cta();
        }

        $settings['facebook_comment_link_cta'] = trim((string) ($settings['facebook_comment_link_cta'] ?? ($settings['default_cta'] ?? '')));
        if ($settings['facebook_comment_link_cta'] === '' || in_array($settings['facebook_comment_link_cta'], $legacy_cta_defaults, true)) {
            $settings['facebook_comment_link_cta'] = $this->default_facebook_comment_link_cta();
        }

        $settings['default_cta'] = $settings['facebook_comment_link_cta'];

        $settings['facebook_pages'] = $this->facebook_pages($settings, false, false);
        return $settings;
    }

    private function facebook_pages(array $settings, bool $active_only = false, bool $strip_tokens = false): array
    {
        $pages = [];
        $raw_pages = $settings['facebook_pages'] ?? [];

        if (is_array($raw_pages)) {
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
        }

        $legacy_page_id = sanitize_text_field((string) ($settings['facebook_page_id'] ?? ''));
        $legacy_token   = trim((string) ($settings['facebook_page_access_token'] ?? ''));
        if ($legacy_page_id !== '' && !isset($pages[$legacy_page_id])) {
            $pages[$legacy_page_id] = [
                'page_id'      => $legacy_page_id,
                'label'        => __('Primary Page', 'kuchnia-twist'),
                'access_token' => $legacy_token,
                'active'       => $legacy_token !== '',
            ];
        }

        $pages = array_values($pages);

        if ($active_only) {
            $pages = array_values(array_filter(
                $pages,
                static fn (array $page): bool => !empty($page['active']) && $page['page_id'] !== '' && $page['access_token'] !== ''
            ));
        }

        if ($strip_tokens) {
            $pages = array_map(
                static function (array $page): array {
                    unset($page['access_token']);
                    return $page;
                },
                $pages
            );
        }

        return $pages;
    }
}
