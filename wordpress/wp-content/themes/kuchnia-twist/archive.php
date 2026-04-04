<?php

defined('ABSPATH') || exit;

get_header();
$context = kuchnia_twist_archive_context();
?>
<section class="listing-header listing-header--feature">
    <div class="listing-hero">
        <div class="listing-hero__copy">
            <?php kuchnia_twist_render_breadcrumbs(); ?>
            <span class="eyebrow"><?php echo esc_html($context['eyebrow']); ?></span>
            <h1><?php echo esc_html($context['title']); ?></h1>
            <p><?php echo esc_html($context['description']); ?></p>
            <div class="listing-search">
                <?php get_search_form(); ?>
            </div>
        </div>
        <aside class="listing-hero__panel">
            <img class="listing-hero__art" src="<?php echo esc_url($context['art']); ?>" alt="">
            <span class="site-footer__eyebrow"><?php esc_html_e('What to expect', 'kuchnia-twist'); ?></span>
            <div class="listing-hero__notes">
                <?php foreach ($context['notes'] as $note) : ?>
                    <p><?php echo esc_html($note); ?></p>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</section>
<section class="story-grid">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php kuchnia_twist_render_post_card(get_the_ID()); ?>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="search-rescue">
            <div class="search-rescue__copy">
                <p class="empty-state"><?php esc_html_e('Nothing has been filed here yet. This archive will fill as the publication grows.', 'kuchnia-twist'); ?></p>
                <div class="rescue-links">
                    <?php kuchnia_twist_pillar_links(); ?>
                </div>
            </div>
            <div class="search-rescue__art">
                <img src="<?php echo esc_url(kuchnia_twist_fallback_media_url('journal')); ?>" alt="">
            </div>
        </div>
    <?php endif; ?>
</section>
<?php kuchnia_twist_render_posts_pagination(); ?>
<?php
get_footer();
