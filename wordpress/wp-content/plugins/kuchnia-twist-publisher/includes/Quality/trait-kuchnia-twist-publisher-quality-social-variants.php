<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Social_Variants_Trait
{
    private function normalized_social_variant_fingerprint(array $variant): string
    {
        return sanitize_title(wp_strip_all_tags($this->build_facebook_post_preview($variant)));
    }

    private function normalized_social_hook_fingerprint(array $variant): string
    {
        return sanitize_title(wp_strip_all_tags((string) ($variant['hook'] ?? '')));
    }

    private function normalized_social_opening_fingerprint(array $variant): string
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        return sanitize_title((string) ($lines[0] ?? ''));
    }

    private function social_variant_looks_weak(array $variant, string $article_title = '', string $content_type = 'recipe', string $article_excerpt = ''): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $hook_words = str_word_count($hook);
        $caption_words = str_word_count(wp_strip_all_tags($caption));
        $caption_lines = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $hook_front_load_score = $this->front_loaded_click_signal_score($hook, $content_type);
        $unanchored_pronoun_lead = preg_match('/^(it|this|that|these|they)\b/i', $hook) === 1
            && !$this->social_variant_anchor_signal($variant, $article_title, $article_excerpt);
        $superiority_bait = preg_match('/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you\'re doing|amateurs?|rookie move|lazy cooks)\b/i', $hook . ' ' . $caption) === 1;

        return $hook === ''
            || $hook_words < 4
            || $hook_words > 18
            || $caption === ''
            || $caption_words < 14
            || $caption_words > 85
            || $caption_lines < 2
            || $caption_lines > 5
            || $hook_front_load_score < 0
            || $unanchored_pronoun_lead
            || $superiority_bait
            || $this->contains_cheap_suspense_pattern($hook)
            || ($normalized_title !== '' && $normalized_hook === $normalized_title)
            || preg_match('/(https?:\/\/|www\.)/i', $caption) === 1
            || preg_match('/(^|\s)#[a-z0-9_]+/i', $caption) === 1;
    }

    private function social_variant_front_loaded_signal(array $variant, string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        return $this->front_loaded_click_signal_score($hook, $content_type) > 0;
    }

    private function contains_cheap_suspense_pattern(string $text): bool
    {
        return preg_match('/\b(what happens next|nobody tells you|no one tells you|what they don\'?t tell you|the secret(?: to)?|finally revealed|you(?:\'ll| will) never guess|hidden truth)\b/i', sanitize_text_field($text)) === 1;
    }

    private function social_variant_pain_point_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|get wrong|confusion|assumption)\b/i', $text) === 1;
    }

    private function social_variant_payoff_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(payoff|result|better|easier|faster|simpler|worth it|works|crisp|crispy|creamy|juicy|clearer|smarter|useful|difference)\b/i', $text) === 1;
    }

    private function social_variant_proof_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(\d+\s?(?:minute|min|minutes|mins|step|steps)|one[- ]pan|sheet pan|air fryer|skillet|oven|temperature|label|pantry|fridge|crispy|creamy|cheesy|garlicky|juicy|golden|without drying|without going soggy|that keeps|which keeps|because|so it stays|so you get)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.2 && $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2) {
            return true;
        }

        return false;
    }

    private function social_variant_curiosity_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        if ($hook === '' || $this->contains_cheap_suspense_pattern($hook) || $this->contains_cheap_suspense_pattern($caption)) {
            return false;
        }
        if (preg_match('/\b(why|how|turns out|actually|the difference|detail|changes|what most people|get wrong|mistake|truth|assumption)\b/i', $hook) !== 1) {
            return false;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($hook . ' ' . $caption, $article_context) >= 0.12) {
            return true;
        }

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
    }

    private function social_variant_contrast_signal(array $variant): bool
    {
        $text = sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        return preg_match('/\b(instead of|rather than|not just|not the|more than|less about|without turning|but not|vs\.?|versus|the part that|what changes|what most people miss)\b/i', $text) === 1;
    }

    private function social_variant_resolves_early(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $needs_resolution = preg_match('/[?]|\b(why|how|turns out|actually|the difference|changes|truth|mistake|what most people|get wrong|instead of|rather than|not just|more than|less about|vs\.?|versus)\b/i', $hook) === 1;

        if ($early_caption === '') {
            return false;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $overlap_hit = $article_context !== '' && $this->shared_words_ratio($early_caption, $article_context) >= 0.16;
        $front_loaded_hit = $this->front_loaded_click_signal_score($early_caption, $content_type) > 0;
        $concrete_hit = preg_match('/\b(crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|faster|easier|save|problem|result|payoff|difference|detail|reason|because|instead|clearer|better)\b/i', $early_caption) === 1;

        if (!$needs_resolution) {
            return $overlap_hit || ($front_loaded_hit && $concrete_hit);
        }

        return $overlap_hit || ($front_loaded_hit && $concrete_hit);
    }

    private function social_variant_specificity_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        $text = trim($hook . ' ' . $caption);
        if ($text === '') {
            return 0;
        }

        $score = 0;
        if ($this->front_loaded_click_signal_score($hook, $content_type) > 0) {
            $score += 1;
        }
        if (preg_match('/\b(one-pan|sheet pan|air fryer|skillet|weeknight|budget|crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|myth|faster|easier|save|result|payoff|ingredient|texture|timing|answer)\b/i', $text) === 1) {
            $score += 1;
        }
        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '') {
            $overlap = $this->shared_words_ratio($text, $article_context);
            if ($overlap >= 0.12 && $overlap <= 0.7) {
                $score += 1;
            }
        }
        if (preg_match('/\b\d+\b/', $hook) === 1) {
            $score += 1;
        }

        return $score;
    }

    private function social_variant_novelty_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? '')));
        $text = trim($hook . ' ' . $caption);
        if ($text === '') {
            return 0;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $title_overlap = $article_title !== '' ? $this->shared_words_ratio($text, $article_title) : 0.0;
        $context_overlap = $article_context !== '' ? $this->shared_words_ratio($text, $article_context) : 0.0;
        $score = 0;

        if ($context_overlap >= 0.16 && $title_overlap <= 0.58) {
            $score += 2;
        } elseif ($context_overlap >= 0.08 && $title_overlap <= 0.68) {
            $score += 1;
        }
        if ($this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2 && $title_overlap <= 0.58) {
            $score += 1;
        }
        if ($this->contains_cheap_suspense_pattern($hook) || $this->contains_cheap_suspense_pattern($caption)) {
            $score -= 1;
        }

        return max(0, $score);
    }

    private function build_social_anchor_phrases(string $article_title = '', string $article_excerpt = ''): array
    {
        $phrases = [];
        $seen = [];
        foreach ([$article_title, $article_excerpt] as $source) {
            foreach (preg_split('/\s*(?:,| and | with | without | or )\s*/i', sanitize_text_field($source)) ?: [] as $part) {
                $phrase = trim(sanitize_text_field($part));
                $fingerprint = sanitize_title($phrase);
                if ($phrase === '' || $fingerprint === '' || isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    private function social_variant_anchor_signal(array $variant, string $article_title = '', string $article_excerpt = ''): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        foreach ($this->build_social_anchor_phrases($article_title, $article_excerpt) as $target) {
            $overlap = $this->shared_words_ratio($text, $target);
            if ($overlap >= 0.18 || (str_word_count($target) <= 2 && $overlap >= 0.12)) {
                return true;
            }
        }

        return false;
    }

    private function social_variant_relatability_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(busy night|weeknight|after work|family dinner|home cook|at home|takeout night|budget dinner|feed everyone|tonight|make this tonight|fridge|pantry)\b/i';
        $fact_pattern = '/\b(in your kitchen|at home|home cook|next time you cook|next time you buy|next time you store|next time you shop|your pantry|your fridge|the label|grocery aisle|home kitchen)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if (preg_match('/\b(you|your|home|kitchen|dinner|cook)\b/i', $text) === 1 && $article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16) {
            return true;
        }

        return false;
    }

    private function social_variant_self_recognition_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(if your|when your|if you keep|if dinner keeps|if this keeps|the reason your|why your|you know that moment when|you know the night when)\b/i';
        $fact_pattern = '/\b(if your|when your|if you keep|if that label keeps|if this keeps|the reason your|why your|you know that moment when|you know the shopping moment when)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $repeated_outcome = preg_match('/\b(keeps getting|keeps turning|keeps ending up|still turns|still ends up|still feels|same mistake|same result|same flat|same soggy|same dry|same bland|same confusion|same waste)\b/i', $text) === 1;
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $article_pain_overlap = $article_context !== '' && $this->shared_words_ratio($text, $article_excerpt) >= 0.16;

        if (preg_match($pattern, $text) === 1 && ($repeated_outcome || $article_pain_overlap)) {
            return true;
        }

        return preg_match('/\b(your|you)\b/i', $text) === 1
            && $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            && (
                $repeated_outcome
                || $article_pain_overlap
                || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            )
            && $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt);
    }

    private function social_variant_conversation_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(comment|tag|share|send this|drop a|tell me in the comments|let me know)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(your house|your table|your family|in your family|the person who|the friend who|most home cooks|a lot of home cooks|everyone thinks|everyone assumes|if you always|the way you always|which one|debate|split)\b/i';
        $fact_pattern = '/\b(your kitchen|your pantry|your fridge|your grocery cart|at the store|on the label|the version you always buy|what most people buy|most people think|a lot of people assume|if you always|which one|debate|split)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        return $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            && $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            && (
                $this->social_variant_contrast_signal($variant)
                || $this->social_variant_pain_point_signal($variant)
                || preg_match('/\b(people|everyone|most|house|family|table|friend|buy|shop|order)\b/i', $text) === 1
            );
    }

    private function social_variant_actionability_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(next time you|before you|use this|skip the|start with|watch for|look for|keep it|swap in|swap out|do this|try this|store it|cook it|buy it|save this for|make this when)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.18 && preg_match('/\b(you|your|next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_immediacy_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(tonight|this week|this weekend|after work|before dinner|next grocery run|next shop|next time you cook|next time you shop|next time you make|weeknight|tomorrow night)\b/i';
        $fact_pattern = '/\b(this week|this weekend|next grocery run|next time you buy|next time you shop|next time you cook|next time you order|next time you store|before you buy|before you cook|before you order)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        if (preg_match($pattern, $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16 && preg_match('/\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_consequence_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(otherwise|or you keep|or it keeps|costs you|keeps costing|keeps wasting|wastes time|wastes money|ends up|turns dry|turns soggy|falls flat|miss the detail|miss that|without the detail|keep repeating|same mistake|less payoff|more effort|still paying for|still stuck with)\b/i', $text) === 1) {
            return true;
        }

        $article_context = trim($article_title . ' ' . $article_excerpt);
        if ($article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.18 && preg_match('/\b(miss|waste|cost|repeat|stuck|flat|dry|soggy|harder|payoff|effort)\b/i', $text) === 1) {
            return true;
        }

        return false;
    }

    private function social_variant_habit_shift_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        $recipe_pattern = '/\b(if you always|if you still|the way you always|usual move|usual dinner move|default dinner move|instead of|rather than|stop doing|stop treating|swap|trade|skip the|break the habit|usual habit|same dinner habit|keep doing)\b/i';
        $fact_pattern = '/\b(if you always|if you still|the way you always|usual move|default move|instead of|rather than|stop doing|swap|trade|skip the|break the habit|usual habit|same shopping habit|same kitchen habit|keep doing)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $shift_words = preg_match('/\b(always|still|instead of|rather than|swap|trade|usual|default|habit|keep doing|stop doing|break the habit|same mistake)\b/i', $text) === 1;
        $better_result =
            $this->social_variant_contrast_signal($variant)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type);
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $socially_recognizable =
            $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type);

        if (preg_match($pattern, $text) === 1 && $better_result && $grounded) {
            return true;
        }

        return $shift_words
            && $socially_recognizable
            && $better_result
            && $grounded;
    }

    private function social_variant_savvy_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(smart cooks|real cooks|good cooks know|bad cooks|lazy cooks|amateurs?|rookie move)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(smarter move|smarter dinner move|better move|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the method that works|the version worth making|the version worth repeating|worth using|worth making)\b/i';
        $fact_pattern = '/\b(smarter move|smarter buy|better buy|better pick|better choice|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the version worth buying|the version worth keeping|the detail worth knowing|worth checking|worth buying|worth using)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $smart_choice_words = preg_match('/\b(smarter|cleaner|better|worth|reliable|more reliable|better call|better bet|better pick|better choice|better move|good call)\b/i', $text) === 1;
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $useful_signal =
            $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_promise_sync_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $overlap_signal = $article_context !== '' && $this->shared_words_ratio($text, $article_context) >= 0.16;

        if (preg_match($pattern, $text) === 1 && $grounded && $useful_signal) {
            return true;
        }

        return $smart_choice_words
            && $grounded
            && ($useful_signal || $overlap_signal);
    }

    private function social_variant_identity_shift_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $text = trim(sanitize_text_field((string) ($variant['hook'] ?? '') . ' ' . wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you\'re doing|amateurs?|rookie move|lazy cooks)\b/i', $text) === 1) {
            return false;
        }

        $recipe_pattern = '/\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old weeknight move|past the usual dinner drag|no longer stuck with|graduate from)\b/i';
        $fact_pattern = '/\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old shopping move|past the usual confusion|no longer stuck with|graduate from)\b/i';
        $pattern = $content_type === 'recipe' ? $recipe_pattern : $fact_pattern;
        $shift_words = preg_match('/\b(done with|leave behind|move past|past the usual|no longer stuck with|stop settling|old default|usual default|graduate from|break out of)\b/i', $text) === 1;
        $grounded =
            $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt)
            || $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2;
        $practical_lift =
            $this->social_variant_savvy_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_payoff_signal($variant);
        $recognition =
            $this->social_variant_self_recognition_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type);

        if (preg_match($pattern, $text) === 1 && $grounded && $practical_lift && $recognition) {
            return true;
        }

        return $shift_words
            && $grounded
            && $practical_lift
            && $recognition;
    }

    private function social_variant_focus_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $lead_window = trim(sanitize_text_field($hook . ' ' . $early_caption));
        if ($lead_window === '') {
            return false;
        }

        $separator_count = preg_match_all('/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/i', $lead_window);
        $promise_hit_count = count(array_filter([
            $this->social_variant_pain_point_signal($variant),
            $this->social_variant_payoff_signal($variant),
            $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_curiosity_signal($variant, $article_title, $article_excerpt, $content_type),
            $this->social_variant_contrast_signal($variant),
        ]));

        $article_context = trim($article_title . ' ' . $article_excerpt);
        $focused_overlap = $article_context !== '' && $this->shared_words_ratio($lead_window, $article_context) >= 0.18;

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $this->front_loaded_click_signal_score($hook, $content_type) >= 0
            && str_word_count($hook) <= 13
            && str_word_count($early_caption !== '' ? $early_caption : $hook) <= 24
            && (int) $separator_count <= 3
            && $promise_hit_count <= 4
            && $focused_overlap;
    }

    private function social_variant_promise_sync_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $early_caption = trim(implode(' ', array_slice($lines, 0, 2)));
        $lead_window = trim(sanitize_text_field($hook . ' ' . $early_caption));
        if ($lead_window === '') {
            return false;
        }

        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $title_overlap = $article_title !== '' ? $this->shared_words_ratio($lead_window, $article_title) : 0.0;
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $signal_overlap = $article_context !== '' ? $this->shared_words_ratio($lead_window, $article_context) : 0.0;
        $promise_hit =
            $this->social_variant_pain_point_signal($variant)
            || $this->social_variant_payoff_signal($variant)
            || $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type);

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $this->front_loaded_click_signal_score($hook !== '' ? $hook : $early_caption, $content_type) > 0
            && $normalized_hook !== ''
            && $normalized_hook !== $normalized_title
            && ($title_overlap >= 0.12 || $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt))
            && ($signal_overlap >= 0.14 || $promise_hit);
    }

    private function social_variant_two_step_signal(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): bool
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        if (count($lines) < 2) {
            return false;
        }

        $line1 = (string) ($lines[0] ?? '');
        $line2 = (string) ($lines[1] ?? '');
        $line1_words = str_word_count($line1);
        $line2_words = str_word_count($line2);
        $line_overlap = max(
            $this->shared_words_ratio($line1, $line2),
            $this->shared_words_ratio($line2, $line1)
        );
        $line1_start = sanitize_title(implode(' ', array_slice(preg_split('/\s+/', strtolower($line1)) ?: [], 0, 2)));
        $line2_start = sanitize_title(implode(' ', array_slice(preg_split('/\s+/', strtolower($line2)) ?: [], 0, 2)));
        $line1_variant = ['hook' => '', 'caption' => $line1];
        $line2_variant = ['hook' => '', 'caption' => $line2];
        $line1_problem_clue =
            $this->social_variant_pain_point_signal($line1_variant)
            || $this->social_variant_proof_signal($line1_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_curiosity_signal($line1_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_contrast_signal($line1_variant)
            || $this->front_loaded_click_signal_score($line1, $content_type) > 0;
        $line1_payoff = $this->social_variant_payoff_signal($line1_variant);
        $line2_use_or_result =
            $this->social_variant_payoff_signal($line2_variant)
            || $this->social_variant_actionability_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_consequence_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            || $this->social_variant_proof_signal($line2_variant, $article_title, $article_excerpt, $content_type);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $line2_distinct_enough =
            $this->social_variant_specificity_score($line2_variant, $article_title, $article_excerpt, $content_type) >= 1
            || ($article_context !== '' && $this->shared_words_ratio($line2, $article_context) >= 0.16);
        $complementary_flow =
            ($line1_problem_clue && $line2_use_or_result)
            || ($line1_payoff && (
                $this->social_variant_proof_signal($line2_variant, $article_title, $article_excerpt, $content_type)
                || $this->social_variant_actionability_signal($line2_variant, $article_title, $article_excerpt, $content_type)
                || $this->social_variant_consequence_signal($line2_variant, $article_title, $article_excerpt, $content_type)
            ));

        return $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type) >= 2
            && $line1_words >= 4
            && $line1_words <= 14
            && $line2_words >= 4
            && $line2_words <= 16
            && preg_match('/^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here\'?s why)\b/i', $line1) !== 1
            && preg_match('/^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here\'?s why)\b/i', $line2) !== 1
            && $line_overlap <= 0.72
            && $line1_start !== ''
            && $line1_start !== $line2_start
            && $line2_distinct_enough
            && $complementary_flow;
    }

    private function social_variant_scannability_signal(array $variant, string $content_type = 'recipe'): bool
    {
        $caption = trim((string) ($variant['caption'] ?? ''));
        $lines = array_values(array_filter(array_map(
            static fn ($line): string => sanitize_text_field(wp_strip_all_tags((string) $line)),
            preg_split('/\r\n|\r|\n/', $caption) ?: []
        )));
        $lines = array_slice($lines, 0, 4);
        if (count($lines) < 3) {
            return false;
        }

        $line_word_counts = array_map(static fn (string $line): int => str_word_count($line), $lines);
        $short_lines = count(array_filter($line_word_counts, static fn (int $count): bool => $count >= 3 && $count <= 12));
        $line_starts = array_values(array_filter(array_map(static function (string $line): string {
            $parts = preg_split('/\s+/', strtolower($line)) ?: [];
            return sanitize_title(implode(' ', array_slice($parts, 0, 2)));
        }, $lines)));
        $unique_starts = array_unique($line_starts);
        $repeated_adjacent = false;
        for ($index = 1; $index < count($lines); $index++) {
            $previous = (string) ($lines[$index - 1] ?? '');
            $current = (string) ($lines[$index] ?? '');
            if (max($this->shared_words_ratio($current, $previous), $this->shared_words_ratio($previous, $current)) >= 0.72) {
                $repeated_adjacent = true;
                break;
            }
        }
        $overloaded_lines = count(array_filter($lines, static fn (string $line): bool => preg_match('/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/i', $line) === 1));
        $front_loaded_lines = count(array_filter($lines, fn (string $line): bool => $this->front_loaded_click_signal_score($line, $content_type) > 0));

        return $short_lines >= 2
            && count($unique_starts) >= min(count($lines), 3)
            && !$repeated_adjacent
            && $overloaded_lines <= 1
            && $front_loaded_lines >= 1;
    }

    private function social_variant_generic_penalty(array $variant): int
    {
        $hook = strtolower(sanitize_text_field((string) ($variant['hook'] ?? '')));
        $caption = strtolower(sanitize_text_field(wp_strip_all_tags((string) ($variant['caption'] ?? ''))));
        $patterns = [
            '/\byou need to try\b/i',
            '/\byou should\b/i',
            '/\bmust try\b/i',
            '/\bthis is\b/i',
            '/\bthis one\b/i',
            '/\bthese are\b/i',
            '/\bhere\'?s why\b/i',
            '/\bso good\b/i',
            '/\bbest ever\b/i',
            '/\byou won\'?t believe\b/i',
            '/\bi\'m obsessed\b/i',
            '/\bgame changer\b/i',
            '/\breal cooks\b/i',
            '/\bgood cooks know\b/i',
            '/\bsmart cooks\b/i',
            '/\bserious cooks\b/i',
            '/\bpeople who know better\b/i',
            '/\bif you know what you\'re doing\b/i',
            '/\bamateurs?\b/i',
            '/\brookie move\b/i',
            '/\blazy cooks\b/i',
            '/\bthis one is everything\b/i',
            '/\btotal winner\b/i',
            '/\bwhat happens next\b/i',
            '/\bnobody tells you\b/i',
            '/\bno one tells you\b/i',
            '/\bwhat they don\'?t tell you\b/i',
            '/\bthe secret(?: to)?\b/i',
            '/\bfinally revealed\b/i',
            '/\byou(?:\'ll| will) never guess\b/i',
            '/\bhidden truth\b/i',
        ];

        $penalty = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $hook) === 1) {
                $penalty += 6;
            }
            if (preg_match($pattern, $caption) === 1) {
                $penalty += 4;
            }
        }

        return $penalty;
    }

    private function classify_social_hook_form(array $variant): string
    {
        $hook = strtolower(sanitize_text_field((string) ($variant['hook'] ?? '')));
        if ($hook === '') {
            return '';
        }
        if (preg_match('/^\d+\b/', $hook) === 1) {
            return 'numbered';
        }
        if (strpos($hook, '?') !== false || preg_match('/^(why|how|what|when|which)\b/', $hook) === 1) {
            return 'question';
        }
        if (preg_match('/\b(instead of|rather than|not just|not the|what most people|get wrong|vs\.?|versus)\b/', $hook) === 1) {
            return 'contrast';
        }
        if (preg_match('/^(stop|avoid|fix|skip|quit|never)\b/', $hook) === 1 || preg_match('/\b(mistake|wrong|avoid|fix)\b/', $hook) === 1) {
            return 'correction';
        }
        if (preg_match('/^(save|make|keep|use|try|cook|shop)\b/', $hook) === 1) {
            return 'directive';
        }
        if (preg_match('/\b(faster|easier|better|crispy|creamy|juicy|budget|weeknight|shortcut|payoff|result)\b/', $hook) === 1) {
            return 'payoff';
        }
        if (preg_match('/\b(problem|waste|stuck|mistake|harder|overpay|dry|soggy|flat)\b/', $hook) === 1) {
            return 'problem';
        }

        return 'statement';
    }

    private function social_variant_score(array $variant, string $article_title = '', string $article_excerpt = '', string $content_type = 'recipe'): int
    {
        $hook = sanitize_text_field((string) ($variant['hook'] ?? ''));
        $caption = trim((string) ($variant['caption'] ?? ''));
        $hook_words = str_word_count($hook);
        $caption_words = str_word_count(wp_strip_all_tags($caption));
        $caption_lines = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $caption))));
        $normalized_hook = sanitize_title($hook);
        $normalized_title = sanitize_title($article_title);
        $angle_key = $this->normalize_hook_angle_key((string) ($variant['angle_key'] ?? $variant['angleKey'] ?? ''), $content_type);
        $overlap = $this->shared_words_ratio($hook, $article_title);
        $article_context = trim($article_title . ' ' . $article_excerpt);
        $context_overlap = $article_context !== '' ? $this->shared_words_ratio($hook . ' ' . wp_strip_all_tags($caption), $article_context) : 0.0;
        $specificity_score = $this->social_variant_specificity_score($variant, $article_title, $article_excerpt, $content_type);
        $anchor_score = $this->social_variant_anchor_signal($variant, $article_title, $article_excerpt) ? 2 : 0;
        $relatability_score = $this->social_variant_relatability_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $recognition_score = $this->social_variant_self_recognition_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $conversation_score = $this->social_variant_conversation_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $savvy_score = $this->social_variant_savvy_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $identity_shift_score = $this->social_variant_identity_shift_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $novelty_score = $this->social_variant_novelty_score($variant, $article_title, $article_excerpt, $content_type);
        $pain_point_score = $this->social_variant_pain_point_signal($variant) ? 2 : 0;
        $payoff_score = $this->social_variant_payoff_signal($variant) ? 2 : 0;
        $proof_score = $this->social_variant_proof_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $actionability_score = $this->social_variant_actionability_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $immediacy_score = $this->social_variant_immediacy_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $consequence_score = $this->social_variant_consequence_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $habit_shift_score = $this->social_variant_habit_shift_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $focus_score = $this->social_variant_focus_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $promise_sync_score = $this->social_variant_promise_sync_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $scannability_score = $this->social_variant_scannability_signal($variant, $content_type) ? 1 : 0;
        $two_step_score = $this->social_variant_two_step_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $curiosity_score = $this->social_variant_curiosity_signal($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $contrast_score = $this->social_variant_contrast_signal($variant) ? 1 : 0;
        $resolution_score = $this->social_variant_resolves_early($variant, $article_title, $article_excerpt, $content_type) ? 1 : 0;
        $hook_front_load_score = $this->front_loaded_click_signal_score($hook, $content_type);
        $score = 0;

        if ($angle_key !== '') {
            $score += 4;
        }
        $score += ($hook_words >= 6 && $hook_words <= 11) ? 6 : 3;
        $score += ($caption_words >= 22 && $caption_words <= 55) ? 5 : 2;
        $score += ($caption_lines >= 3 && $caption_lines <= 4) ? 4 : 2;
        if ($normalized_title !== '' && $normalized_hook !== $normalized_title) {
            $score += 4;
        }
        if ($overlap <= 0.45) {
            $score += 3;
        } elseif ($overlap >= 0.8) {
            $score -= 5;
        }
        if ($article_context !== '') {
            if ($context_overlap >= 0.16 && $context_overlap <= 0.7) {
                $score += 4;
            } elseif ($context_overlap >= 0.08) {
                $score += 2;
            } elseif ($context_overlap === 0.0) {
                $score -= 2;
            }
        }

        $score += $specificity_score;
        $score += $anchor_score;
        $score += $relatability_score;
        $score += $recognition_score;
        $score += $conversation_score;
        $score += $savvy_score;
        $score += $identity_shift_score;
        $score += $novelty_score;
        $score += $pain_point_score;
        $score += $payoff_score;
        $score += $proof_score;
        $score += $actionability_score;
        $score += $immediacy_score;
        $score += $consequence_score;
        $score += $habit_shift_score;
        $score += $focus_score;
        $score += $promise_sync_score;
        $score += $scannability_score;
        $score += $two_step_score;
        $score += $curiosity_score;
        $score += $contrast_score;
        $score += $resolution_score;
        $score += $hook_front_load_score;

        return $score - $this->social_variant_generic_penalty($variant);
    }
}
