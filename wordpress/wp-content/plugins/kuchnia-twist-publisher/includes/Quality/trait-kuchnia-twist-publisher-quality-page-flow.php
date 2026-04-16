<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Page_Flow_Trait
{
    private function extract_article_page_labels(array $pages): array
    {
        return $this->format_article_page_flow_labels($this->extract_article_page_flow($pages));
    }

    private function extract_article_page_flow(array $pages): array
    {
        $flow = [];

        foreach ($pages as $index => $page) {
            $page = trim((string) $page);
            if ($page === '') {
                continue;
            }

            $label = $this->extract_article_page_label_text($page, sprintf(__('Page %d', 'kuchnia-twist'), $index + 1));
            $summary = $this->extract_article_page_summary($page);

            $flow[] = [
                'index' => $index + 1,
                'label' => $label !== '' ? $label : sprintf(__('Page %d', 'kuchnia-twist'), $index + 1),
                'summary' => $summary,
            ];
        }

        return $flow;
    }

    private function normalize_generated_page_flow(array $flow, array $pages): array
    {
        $fallback = $this->extract_article_page_flow($pages);
        if (empty($flow)) {
            return $fallback;
        }

        $normalized = [];
        $used_labels = [];
        foreach ($fallback as $index => $page) {
            $raw = is_array($flow[$index] ?? null) ? $flow[$index] : [];
            $fallback_label = sanitize_text_field((string) ($page['label'] ?? sprintf(__('Page %d', 'kuchnia-twist'), $index + 1)));
            $fallback_summary = sanitize_text_field((string) ($page['summary'] ?? ''));
            $label = sanitize_text_field((string) ($raw['label'] ?? $raw['title'] ?? $raw['page_label'] ?? ''));
            $summary = sanitize_text_field((string) ($raw['summary'] ?? $raw['page_summary'] ?? $raw['description'] ?? ''));

            if (!$this->page_flow_label_looks_strong($label, $index + 1)) {
                $label = $fallback_label;
            }
            if (!$this->page_flow_summary_looks_strong($summary, $label)) {
                $summary = $fallback_summary;
            }

            $fingerprint = $this->normalize_page_flow_label_fingerprint($label);
            $fallback_fingerprint = $this->normalize_page_flow_label_fingerprint($fallback_label);
            if (($fingerprint === '' || in_array($fingerprint, $used_labels, true)) && $fallback_fingerprint !== '' && !in_array($fallback_fingerprint, $used_labels, true)) {
                $label = $fallback_label;
                $fingerprint = $fallback_fingerprint;
            }

            if ($fingerprint === '' || in_array($fingerprint, $used_labels, true)) {
                $derived_label = $this->derive_page_flow_label_from_summary($summary !== '' ? $summary : $fallback_summary, $fallback_label);
                $derived_fingerprint = $this->normalize_page_flow_label_fingerprint($derived_label);
                if ($derived_fingerprint !== '' && !in_array($derived_fingerprint, $used_labels, true) && $this->page_flow_label_looks_strong($derived_label, $index + 1)) {
                    $label = $derived_label;
                    $fingerprint = $derived_fingerprint;
                }
            }

            if (!$this->page_flow_summary_looks_strong($summary, $label)) {
                $summary = $fallback_summary;
            }
            if ($summary === '') {
                $summary = $fallback_summary;
            }
            if ($fingerprint !== '') {
                $used_labels[] = $fingerprint;
            }

            $normalized[] = [
                'index'   => (int) ($page['index'] ?? ($index + 1)),
                'label'   => $label !== '' ? wp_trim_words($label, 8, '...') : $fallback_label,
                'summary' => $summary !== '' ? wp_trim_words($summary, 18, '...') : $fallback_summary,
            ];
        }

        return $normalized;
    }

    private function format_article_page_flow_labels(array $flow): array
    {
        $labels = [];

        foreach ($flow as $page) {
            $label = (string) ($page['label'] ?? '');
            $summary = (string) ($page['summary'] ?? '');
            $index = (int) ($page['index'] ?? 0);

            if ($index < 1 || $label === '') {
                continue;
            }

            $labels[] = $summary !== '' && $summary !== $label
                ? sprintf(__('Page %1$d: %2$s - %3$s', 'kuchnia-twist'), $index, $label, $summary)
                : sprintf(__('Page %1$d: %2$s', 'kuchnia-twist'), $index, $label);
        }

        return $labels;
    }

    private function extract_article_page_label_text(string $page, string $fallback = ''): string
    {
        if (preg_match('/<h2\\b[^>]*>(.*?)<\\/h2>/is', $page, $matches)) {
            $label = sanitize_text_field(wp_strip_all_tags((string) ($matches[1] ?? '')));
            $label = preg_replace('/^[0-9]+\\s*[:.)-]?\\s*/', '', (string) $label);
            if ($label !== '') {
                return wp_trim_words($label, 8, '...');
            }
        }

        if (preg_match('/<p\\b[^>]*>(.*?)<\\/p>/is', $page, $matches)) {
            $paragraph = trim(preg_replace('/\\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
            if ($paragraph !== '') {
                $sentences = preg_split('/(?<=[.!?])\\s+/', $paragraph) ?: [$paragraph];
                $lead = trim((string) ($sentences[0] ?? $paragraph));
                if ($lead !== '') {
                    return wp_trim_words($lead, 8, '...');
                }
            }
        }

        $plaintext = preg_replace('/\\s+/', ' ', wp_strip_all_tags($page));
        if ($plaintext !== '') {
            return sanitize_text_field(wp_trim_words((string) $plaintext, 8, '...'));
        }

        return sanitize_text_field($fallback);
    }

    private function extract_article_page_summary(string $page): string
    {
        if (preg_match('/<p\\b[^>]*>(.*?)<\\/p>/is', $page, $matches)) {
            $paragraph = trim(preg_replace('/\\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
            if ($paragraph !== '') {
                $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\\s+/', $paragraph) ?: [$paragraph])));
                $summary = (string) ($sentences[1] ?? $sentences[0] ?? $paragraph);
                if ($summary !== '') {
                    return sanitize_text_field(wp_trim_words($summary, 18, '...'));
                }
            }
        }

        $plaintext = trim(preg_replace('/\\s+/', ' ', wp_strip_all_tags($page)));
        if ($plaintext !== '') {
            return sanitize_text_field(wp_trim_words($plaintext, 18, '...'));
        }

        return '';
    }

    private function normalize_page_flow_label_fingerprint(string $label): string
    {
        $label = strtolower(remove_accents(sanitize_text_field($label)));
        $label = preg_replace('/^(page|part|section|step)\\s+\\d+\\s*[:.)-]?\\s*/i', '', $label);
        $label = preg_replace('/[^a-z0-9\\s]/', ' ', (string) $label);

        return trim(preg_replace('/\\s+/', ' ', (string) $label));
    }

    private function page_flow_label_looks_strong(string $label, int $index = 0): bool
    {
        $text = sanitize_text_field($label);
        $fallback = sprintf('Page %d', $index > 0 ? $index : 1);
        $fingerprint = $this->normalize_page_flow_label_fingerprint($text !== '' ? $text : $fallback);
        if ($fingerprint === '') {
            return false;
        }

        if (str_word_count($fingerprint) < 2 || strlen($fingerprint) < 8) {
            return false;
        }

        return !preg_match('/^(page|part|section|continue|next page|keep reading|read more)\\b/i', $text);
    }

    private function page_flow_summary_looks_strong(string $summary, string $label = ''): bool
    {
        $text = sanitize_text_field($summary);
        if ($text === '') {
            return false;
        }

        $summary_fingerprint = $this->normalize_page_flow_label_fingerprint($text);
        $label_fingerprint = $this->normalize_page_flow_label_fingerprint($label);
        if (str_word_count($summary_fingerprint) < 6) {
            return false;
        }
        if ($label_fingerprint !== '' && $summary_fingerprint === $label_fingerprint) {
            return false;
        }

        return !preg_match('/^(page|part)\\s+\\d+\\b|^(keep reading|continue reading|read more|next up)\\b/i', $text);
    }

    private function derive_page_flow_label_from_summary(string $summary, string $fallback = ''): string
    {
        $source = sanitize_text_field($summary !== '' ? $summary : $fallback);
        if ($source === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\\s+/', $source) ?: [$source];
        $lead = trim((string) ($sentences[0] ?? $source));
        if ($lead === '') {
            $lead = $source;
        }

        return sanitize_text_field(wp_trim_words($lead, 8, '...'));
    }

    private function page_starts_with_expected_lead(string $page_html, int $index): bool
    {
        $page_html = trim($page_html);
        if ($page_html === '') {
            return false;
        }

        if ($index === 0) {
            return preg_match('/^<p\\b/i', $page_html) === 1;
        }

        return preg_match('/^<(h2|blockquote|ul|ol)\\b/i', $page_html) === 1;
    }
}
