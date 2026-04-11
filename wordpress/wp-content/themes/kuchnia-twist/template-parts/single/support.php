<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_article_support');
if (!is_array($context) || empty($context['is_final_page'])) {
    return;
}

$editor_profile = is_array($context['editor_profile'] ?? null) ? $context['editor_profile'] : [];
$about_page = $context['about_page'] ?? null;
$contact_page = $context['contact_page'] ?? null;
$editorial_policy = $context['editorial_policy'] ?? null;
?>

<section class="article-support">
    <div class="article-support__editor">
        <span class="eyebrow"><?php echo esc_html((string) ($editor_profile['role'] ?? '')); ?></span>
        <h2><?php echo esc_html((string) ($editor_profile['name'] ?? '')); ?></h2>
        <p><?php echo esc_html((string) ($editor_profile['bio'] ?? '')); ?></p>
    </div>
    <div class="article-support__links">
        <?php if ($about_page instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('About', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
        <?php if ($editorial_policy instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($editorial_policy)); ?>"><?php esc_html_e('Editorial Policy', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
        <?php if ($contact_page instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($contact_page)); ?>"><?php esc_html_e('Contact', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
    </div>
</section>
