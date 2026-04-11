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
$public_email = sanitize_email((string) ($context['public_email'] ?? ''));
?>

<section class="article-support">
    <div class="article-support__editor">
        <span class="eyebrow"><?php esc_html_e('About the journal', 'kuchnia-twist'); ?></span>
        <h2><?php echo esc_html((string) ($editor_profile['name'] ?? '')); ?></h2>
        <?php if (!empty($editor_profile['role'])) : ?>
            <p class="article-support__role"><?php echo esc_html((string) $editor_profile['role']); ?></p>
        <?php endif; ?>
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
        <?php if (is_email($public_email)) : ?>
            <a class="chip-link" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
        <?php endif; ?>
    </div>
</section>
