<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Settings_Defaults_Trait
{
    private function default_publication_role(): string
    {
        return implode("\n", [
            'You are the lead editorial writer for kuchniatwist.',
            'Write for a food publication that wants strong clicks, honest payoff, and articles that feel worth the visit.',
            'Your job is to produce publishable recipe articles and food explainers that sound human, specific, and confident.',
            'Win the click with real value, then reward the visit with clear structure, useful detail, and sharp editorial judgment.',
            'Make the work strong enough that a first-time social visitor becomes a returning reader.',
        ]);
    }

    private function default_voice_brief(): string
    {
        return implode("\n", [
            'Warm, practical, craveable, and trustworthy.',
            'Write for real home cooks and curious food readers who want reliable recipes, sharper kitchen understanding, and a concrete payoff after the click.',
            'Lead with usefulness, appetite, and honest curiosity.',
            'Serve both first-time social visitors and loyal returning readers without sounding broad, repetitive, or overexplained.',
            'The tone should feel premium, human, and editorial rather than robotic, spammy, or overhyped.',
        ]);
    }

    private function default_global_guardrails(): string
    {
        return implode("\n", [
            'No fake personal stories, invented reporting, fabricated authority, or made-up facts.',
            'No filler SEO intros, generic throat-clearing, or padded explanations.',
            'No spammy clickbait, fake cliffhangers, or hollow viral language.',
            'No medical, nutrition, detox, or expert claims beyond ordinary kitchen guidance.',
            'Keep paragraphs short, specific, and human.',
            'Every promise in the title, hook, excerpt, or caption must be honestly supported by the article.',
            'Never burn trust just to chase a social click.',
            'Do not use generic openers like "when it comes to" or "in today\'s busy world."',
        ]);
    }

    private function default_recipe_master_direction(): string
    {
        return implode("\n", [
            'Turn a dish name into a publishable multi-page recipe article package.',
            'Focus this stage on title quality, excerpt, SEO description, article pages, page flow, recipe-card readiness, and image direction.',
            'Make the article highly clickable without being misleading.',
            'Keep the recipe practical, credible, and worth publishing on a real food site.',
            'Social candidate generation is handled separately, so prioritize article quality and reading momentum here.',
        ]);
    }

    private function default_recipe_article_guidance(): string
    {
        return implode("\n", [
            'Open fast with appetite, concrete payoff, and a real reason to care.',
            'Prefer title and opening combinations that signal a specific payoff like speed, texture, comfort, convenience, or a useful shortcut.',
            'Use 1 to 2 short opening paragraphs, then H2 sections for why this recipe works, ingredient notes, practical method, and serving or storage tips.',
            'Build the article as 2 to 3 intentional pages: page 1 hooks, page 2 carries the deepest useful detail, and page 3, if needed, should feel earned and lead naturally into the recipe card.',
            'Assume many readers arrive from social cold, so page 1 should quickly prove why this recipe is worth cooking tonight or this week.',
            'Keep the recipe realistic, cookable, and repeatable for home kitchens.',
            'Prioritize taste, texture, convenience, comfort, and kitchen usefulness over fluff.',
            'Do not paste the full ingredient list and full numbered method into the article body; let the recipe card carry the full recipe.',
        ]);
    }

    private function default_food_fact_article_guidance(): string
    {
        return implode("\n", [
            'Treat the entered title as a working topic, not a locked final headline.',
            'Lead with a direct answer and improve the final publish title if a clearer, sharper version exists.',
            'Prefer title and opening combinations that frame a real kitchen pain point, mistake, misunderstanding, or practical payoff instead of vague listicle energy.',
            'Use H2 sections for the direct answer, what most people get wrong, what is actually happening, and the practical takeaway.',
            'Build the article as 2 to 3 intentional pages: page 1 answers fast, page 2 explains what is really happening or what people get wrong, and page 3 only appears if it delivers a strong practical takeaway.',
            'Assume many readers arrive from social cold, so the first screen should answer fast and show why the detail changes a real cook, shop, or storage decision.',
            'Stay in editorial explainer territory only and focus on kitchen truth, clarity, and click-worthy payoff instead of vague listicle filler.',
            'Do not output ingredients, instructions, or recipe-style metadata for food facts.',
        ]);
    }

    private function default_facebook_variant_guidance(): string
    {
        return implode("\n", [
            'Generate a strong pool of recipe Facebook candidates, not clones.',
            'The final selected set must give each selected page a different angle such as quick dinner, comfort food, budget-friendly, beginner-friendly, crowd-pleaser, or better-than-takeout.',
            'Hooks should be short, specific, and interruptive without fake hype or title echo.',
            'Favor hooks and captions that name a real payoff, shortcut, texture, timing, or cooking relief point instead of vague excitement.',
            'Pull the right reader onto the blog with an honest concrete payoff, not shallow hype.',
            'Give enough clue to earn trust, but leave enough unresolved value that the article still feels worth tapping.',
            'Captions should be 2 to 5 short lines that add real taste, texture, ease, timing, or payoff.',
            'Make at least some hooks feel sharper than safe generic social copy while staying honest and specific.',
            'No links, hashtags, emoji clutter, or repeated hook-as-caption openings.',
        ]);
    }

    private function default_food_fact_social_guidance(): string
    {
        return implode("\n", [
            'Generate a strong pool of food-fact Facebook candidates that feel myth-busting, surprising, corrective, or practically useful.',
            'The final selected set should vary angles such as surprising truth, kitchen mistake, smarter shortcut, ingredient truth, or what most people get wrong.',
            'Hooks should be short, specific, and curiosity-led without fake bait or title echo.',
            'Favor hooks and captions that name a real pain point, mistake, misunderstanding, or practical fix instead of broad curiosity alone.',
            'Pull the right reader onto the blog with a trustworthy concrete reason to click.',
            'Give enough clue to earn trust, but keep the fuller answer and practical payoff inside the article.',
            'Captions should be 2 to 5 short lines that reveal the payoff or practical takeaway.',
            'Make at least some hooks feel punchy enough to stop scrolling, but avoid hollow listicle hype or obvious bait phrasing.',
            'No links, hashtags, emoji clutter, or empty listicle hype.',
        ]);
    }

    private function default_group_share_guidance(): string
    {
        return 'Write a useful manual-share blurb that feels natural in food groups, highlights the practical payoff, and leaves the link to the operator or tracked follow-up.';
    }

    private function down_pointing_finger_emoji(): string
    {
        return html_entity_decode('&#x1F447;', ENT_QUOTES, 'UTF-8');
    }

    private function default_facebook_post_teaser_cta(): string
    {
        return $this->down_pointing_finger_emoji() . ' Full article in the first comment below.';
    }

    private function default_facebook_comment_link_cta(): string
    {
        return 'Read the full article on the blog.';
    }

    private function default_pinterest_draft_guidance(): string
    {
        return 'Draft a Pinterest-ready package with a keyword-clear title, a useful description, concise save-intent keywords, and vertical image direction anchored in the article\'s real payoff.';
    }

    private function default_image_style_brief(): string
    {
        return implode("\n", [
            'Use realistic, appetizing food photography with natural light, clean composition, believable texture, and no text overlays.',
            'For recipes, bias toward finished-dish hero imagery.',
            'For food explainers, use the most useful food subject for the article, which may be an ingredient, prep scene, or finished food rather than a plated dish.',
        ]);
    }

    private function legacy_guardrails_text(array $settings): string
    {
        return trim(implode("\n", array_filter([
            trim((string) ($settings['editorial_do_guidance'] ?? '')),
            trim((string) ($settings['editorial_dont_guidance'] ?? '')),
            trim((string) ($settings['banned_claim_guidance'] ?? '')),
        ])));
    }

    private function default_settings(): array
    {
        return [
            'topics_text'                => implode("\n", kuchnia_twist_active_launch_topics()),
            'publication_profile_name'   => 'kuchniatwist',
            'publication_role'           => $this->default_publication_role(),
            'brand_voice'                => $this->default_voice_brief(),
            'global_guardrails'          => $this->default_global_guardrails(),
            'editorial_do_guidance'      => 'Lead with concrete kitchen detail, use helpful headings, and keep the tone calm, specific, and useful.',
            'editorial_dont_guidance'    => 'Avoid filler openings, AI mention, fabricated first-person memories, and unsupported expert language.',
            'banned_claim_guidance'      => 'Avoid medical, nutritional, or safety claims beyond ordinary kitchen guidance the publication can reasonably support.',
            'shared_link_policy'         => 'Include at least three relevant internal kuchniatwist links inside the article body.',
            'recipe_master_prompt'       => $this->default_recipe_master_direction(),
            'article_prompt'             => $this->default_recipe_article_guidance(),
            'food_fact_article_prompt'   => $this->default_food_fact_article_guidance(),
            'recipe_preset_guidance'     => 'Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical value to reward the click and earn a repeat visit.',
            'food_fact_preset_guidance'  => 'Treat the entered title as a working topic, answer it directly, correct common confusion, explain what is really happening, and finish with a practical takeaway that makes the reader want to come back.',
            'facebook_caption_guidance'  => $this->default_facebook_variant_guidance(),
            'food_fact_facebook_caption_guidance' => $this->default_food_fact_social_guidance(),
            'group_share_guidance'       => $this->default_group_share_guidance(),
            'facebook_post_teaser_cta'   => $this->default_facebook_post_teaser_cta(),
            'facebook_comment_link_cta'  => $this->default_facebook_comment_link_cta(),
            'default_cta'                => $this->default_facebook_comment_link_cta(),
            'site_public_email'          => 'contact@kuchniatwist.pl',
            'editor_name'                => 'Anna Kowalska',
            'editor_role'                => __('Editorial desk', 'kuchnia-twist'),
            'editor_bio'                 => __('Independent home-cooking journal focused on practical recipes and useful food facts.', 'kuchnia-twist'),
            'editor_public_email'        => 'contact@kuchniatwist.pl',
            'editor_business_email'      => '',
            'editor_photo_id'            => 0,
            'social_instagram_url'       => '',
            'social_facebook_url'        => '',
            'social_pinterest_url'       => '',
            'social_tiktok_url'          => '',
            'social_follow_label'        => 'Follow kuchniatwist',
            'openai_model'               => 'gpt-5-mini',
            'openai_image_model'         => 'gpt-image-1.5',
            'openai_api_key'             => '',
            'image_style'                => $this->default_image_style_brief(),
            'image_generation_mode'      => 'uploaded_first_generate_missing',
            'daily_publish_time'         => '09:00',
            'repair_enabled'             => '1',
            'repair_attempts'            => 1,
            'strict_contract_mode'       => '0',
            'facebook_graph_version'     => 'v22.0',
            'facebook_page_id'           => '',
            'facebook_page_access_token' => '',
            'facebook_pages'             => [],
            'utm_source'                 => 'facebook',
            'utm_campaign_prefix'        => 'kuchnia-twist',
        ];
    }
}
