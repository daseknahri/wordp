<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_article_progress');
if (!is_array($context) || empty($context['is_multipage'])) {
    return;
}

$current_page = (int) ($context['current_page'] ?? 1);
$total_pages = (int) ($context['total_pages'] ?? 1);
$page_progress = (int) ($context['page_progress'] ?? 0);
?>

<section class="article-progress" aria-label="<?php esc_attr_e('Reading progress', 'kuchnia-twist'); ?>">
    <div class="article-progress__head">
        <div>
            <span class="eyebrow"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'kuchnia-twist'), $current_page, $total_pages)); ?></span>
        </div>
    </div>
    <div class="article-progress__track" aria-hidden="true">
        <span style="width: <?php echo esc_attr((string) $page_progress); ?>%;"></span>
    </div>
</section>
