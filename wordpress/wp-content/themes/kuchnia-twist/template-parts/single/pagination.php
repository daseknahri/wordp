<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_article_pagination');
if (!is_array($context) || empty($context['is_multipage'])) {
    return;
}

$current_page = (int) ($context['current_page'] ?? 1);
$total_pages = (int) ($context['total_pages'] ?? 1);
$is_recipe = !empty($context['is_recipe']);
$next_is_final = $current_page + 1 === $total_pages;
?>

<nav class="article-pagination" aria-label="<?php esc_attr_e('Article page navigation', 'kuchnia-twist'); ?>">
    <div class="article-pagination__links">
        <?php if ($current_page > 1) : ?>
            <?php echo _wp_link_page($current_page - 1); ?>
                <span><?php esc_html_e('Previous page', 'kuchnia-twist'); ?></span>
            </a>
        <?php else : ?>
            <span class="article-pagination__spacer" aria-hidden="true"></span>
        <?php endif; ?>

        <?php if ($current_page < $total_pages) : ?>
            <?php echo _wp_link_page($current_page + 1); ?>
                <span><?php echo esc_html($is_recipe && $next_is_final ? __('Continue to recipe', 'kuchnia-twist') : __('Next page', 'kuchnia-twist')); ?></span>
            </a>
        <?php endif; ?>
    </div>
</nav>
