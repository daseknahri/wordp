<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_single_type');
$content_type = is_array($context) ? (string) ($context['content_type'] ?? '') : '';
$summary = is_array($context) ? trim((string) ($context['current_page_summary'] ?? '')) : '';
$is_final_page = !empty($context['is_final_page']);
$is_multipage = !empty($context['is_multipage']);

if ($content_type !== 'food_fact') {
    return;
}

?>
<?php if ($summary !== '' && (!$is_multipage || $is_final_page)) : ?>
    <section class="article-support article-support--fact">
        <div class="article-support__editor">
            <span class="eyebrow"><?php esc_html_e('Quick takeaway', 'kuchnia-twist'); ?></span>
            <p class="article-support__caption"><?php esc_html_e('The short version to remember before you move on.', 'kuchnia-twist'); ?></p>
            <p><?php echo esc_html($summary); ?></p>
        </div>
    </section>
<?php endif; ?>
