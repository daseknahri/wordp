<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Scoring_Trait
{
    private function extract_opening_paragraph_text(string $content_html): string
    {
        if ($content_html !== '' && preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content_html, $matches)) {
            return sanitize_text_field(wp_strip_all_tags((string) ($matches[1] ?? '')));
        }

        return '';
    }

    private function shared_words_ratio(string $left, string $right): float
    {
        $stop_words = [
            'the', 'a', 'an', 'and', 'or', 'for', 'with', 'your', 'this', 'that', 'from', 'into',
            'about', 'what', 'when', 'why', 'how', 'most', 'more', 'than',
        ];

        $tokenize = static function (string $value) use ($stop_words): array {
            $text = strtolower(remove_accents(sanitize_text_field(wp_strip_all_tags($value))));
            $parts = preg_split('/[^a-z0-9]+/', $text) ?: [];

            return array_values(array_unique(array_filter(array_map(
                static fn ($token): string => trim((string) $token),
                $parts
            ), static fn ($token): bool => $token !== '' && strlen($token) > 2 && !in_array($token, $stop_words, true))));
        };

        $left_tokens = $tokenize($left);
        $right_tokens = $tokenize($right);
        if (empty($left_tokens) || empty($right_tokens)) {
            return 0.0;
        }

        $right_lookup = array_fill_keys($right_tokens, true);
        $shared = 0;
        foreach ($left_tokens as $token) {
            if (isset($right_lookup[$token])) {
                $shared++;
            }
        }

        return $shared / max(1, count($left_tokens));
    }

    private function title_looks_strong(string $title, string $topic = '', string $content_type = 'recipe'): bool
    {
        $text = sanitize_text_field($title);
        $word_count = str_word_count($text);
        if ($text === '' || $word_count < 4 || $word_count > 14) {
            return false;
        }
        if (preg_match('/\b(you won\'?t believe|best ever|game changer|what you need to know|everything you need to know|why everyone is talking about)\b/i', $text) === 1) {
            return false;
        }
        if ($topic !== '' && $this->shared_words_ratio($text, $topic) < 0.15) {
            return false;
        }
        if ($this->front_loaded_click_signal_score($text, $content_type) < 0) {
            return false;
        }

        return true;
    }

    private function excerpt_adds_new_value(string $title, string $excerpt): bool
    {
        $text = sanitize_text_field($excerpt);
        if (str_word_count($text) < 12) {
            return false;
        }

        return $this->shared_words_ratio($text, $title) < 0.82;
    }

    private function opening_paragraph_adds_new_value(string $content_html, string $title, string $excerpt = ''): bool
    {
        $opening = $this->extract_opening_paragraph_text($content_html);
        if (str_word_count($opening) < 16) {
            return false;
        }
        if ($this->shared_words_ratio($opening, $title) >= 0.85) {
            return false;
        }
        if ($excerpt !== '' && $this->shared_words_ratio($opening, $excerpt) >= 0.9) {
            return false;
        }

        return true;
    }

    private function front_loaded_click_signal_score(string $text, string $content_type = 'recipe'): int
    {
        $lead = strtolower(trim(wp_trim_words(sanitize_text_field($text), 5, '')));
        if ($lead === '') {
            return 0;
        }

        $score = 0;
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|truth|myth|actually|really|better|crispy|creamy|budget|weeknight|juicy|quick|simple|get wrong|most people)\b/i', $lead) === 1) {
            $score += 2;
        }
        if ($content_type === 'recipe' && preg_match('/\b(one-pan|sheet pan|air fryer|skillet|cheesy|garlicky|comfort|dinner|takeout)\b/i', $lead) === 1) {
            $score += 1;
        }
        if ($content_type === 'food_fact' && preg_match('/\b(why|how|what|truth|myth|mistake|actually)\b/i', $lead) === 1) {
            $score += 1;
        }
        if (preg_match('/\b\d+\b/', $lead) === 1) {
            $score += 1;
        }
        if (preg_match('/^(you need to|you should|this is|this one|these are|here\'?s why|the best)\b/i', $lead) === 1) {
            $score -= 2;
        }

        return $score;
    }

    private function contrast_click_signal_score(string $text): int
    {
        $normalized = strtolower(sanitize_text_field($text));
        if ($normalized === '') {
            return 0;
        }

        return preg_match('/\b(instead of|rather than|not just|not the|more than|less about|what most people miss|what changes|vs\.?|versus)\b/i', $normalized) === 1 ? 1 : 0;
    }

    private function headline_specificity_score(string $title, string $content_type = 'recipe', string $topic = ''): int
    {
        $text = sanitize_text_field($title);
        $normalized_title = sanitize_title($text);
        $normalized_topic = sanitize_title($topic);
        $words = str_word_count($text);
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 5 && $words <= 13) {
            $score += 3;
        } elseif ($words >= 4 && $words <= 16) {
            $score += 1;
        } else {
            $score -= 2;
        }

        if ($normalized_topic !== '' && $normalized_title !== '' && $normalized_title !== $normalized_topic) {
            $score += 2;
        }

        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|why|how|actually|really|most people|get wrong)\b/i', $text) === 1) {
            $score += 3;
        }
        if (preg_match('/\b(one-pan|weeknight|crispy|creamy|cheesy|garlicky|juicy|budget|air fryer|oven|skillet|better than takeout)\b/i', $text) === 1) {
            $score += 2;
        }
        if (preg_match('/\b\d+\b/', $text) === 1) {
            $score += 1;
        }
        $score += $this->front_loaded_click_signal_score($text, $content_type);
        $score += $this->contrast_click_signal_score($text);
        if (strpos($text, '?') !== false) {
            $score -= 1;
        }
        if (preg_match('/\b(recipe|guide|tips|ideas|facts|article)\b/i', $text) === 1 && $words <= 6) {
            $score -= 2;
        }
        if ($content_type === 'food_fact' && $normalized_topic !== '' && $normalized_title === $normalized_topic) {
            $score -= 2;
        }

        return $score;
    }

    private function opening_promise_alignment_score(string $title, string $opening_paragraph): int
    {
        $title_text = sanitize_text_field($title);
        $opening_text = sanitize_text_field($opening_paragraph);
        if ($title_text === '' || $opening_text === '') {
            return 0;
        }

        $overlap = $this->shared_words_ratio($title_text, $opening_text);
        $score = 0;
        if ($overlap >= 0.24) {
            $score += 3;
        } elseif ($overlap >= 0.14) {
            $score += 2;
        } elseif ($overlap >= 0.08) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|payoff|problem|why|how)\b/i', $opening_text) === 1) {
            $score += 1;
        }
        if ($this->front_loaded_click_signal_score($opening_text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($opening_text);

        return $score;
    }

    private function excerpt_click_signal_score(string $excerpt, string $title = '', string $opening_paragraph = ''): int
    {
        $text = sanitize_text_field($excerpt);
        $words = str_word_count($text);
        $title_overlap = $this->shared_words_ratio($text, $title);
        $opening_overlap = $opening_paragraph !== '' ? $this->shared_words_ratio($text, $opening_paragraph) : 0;
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 12 && $words <= 30) {
            $score += 2;
        } elseif ($words >= 10 && $words <= 36) {
            $score += 1;
        }
        if ($title_overlap <= 0.72) {
            $score += 2;
        } elseif ($title_overlap >= 0.9) {
            $score -= 2;
        }
        if ($opening_paragraph !== '' && $opening_overlap >= 0.08 && $opening_overlap <= 0.7) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i', $text) === 1) {
            $score += 2;
        }
        if ($this->front_loaded_click_signal_score($text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($text);

        return $score;
    }

    private function seo_description_signal_score(string $seo_description, string $title = '', string $excerpt = ''): int
    {
        $text = sanitize_text_field($seo_description);
        $words = str_word_count($text);
        $title_overlap = $this->shared_words_ratio($text, $title);
        $excerpt_overlap = $excerpt !== '' ? $this->shared_words_ratio($text, $excerpt) : 0;
        $score = 0;

        if ($text === '') {
            return 0;
        }
        if ($words >= 12 && $words <= 28) {
            $score += 2;
        } elseif ($words >= 10 && $words <= 32) {
            $score += 1;
        }
        if ($title_overlap <= 0.72) {
            $score += 2;
        } elseif ($title_overlap >= 0.9) {
            $score -= 2;
        }
        if ($excerpt !== '' && $excerpt_overlap >= 0.08 && $excerpt_overlap <= 0.8) {
            $score += 1;
        }
        if (preg_match('/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|why|how|payoff|comfort|crispy|creamy|juicy|budget|weeknight|truth|actually|really)\b/i', $text) === 1) {
            $score += 2;
        }
        if ($this->front_loaded_click_signal_score($text) > 0) {
            $score += 1;
        }
        $score += $this->contrast_click_signal_score($text);

        return $score;
    }
}
