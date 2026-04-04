<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="listing-header">
    <?php kuchnia_twist_render_breadcrumbs(); ?>
    <span class="eyebrow"><?php esc_html_e('Archive', 'kuchnia-twist'); ?></span>
    <h1><?php the_archive_title(); ?></h1>
    <p><?php echo esc_html(wp_strip_all_tags(get_the_archive_description())); ?></p>
    <div class="listing-search">
        <?php get_search_form(); ?>
    </div>
</section>
<section class="story-grid">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php kuchnia_twist_render_post_card(get_the_ID()); ?>
        <?php endwhile; ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('Nothing has been filed here yet.', 'kuchnia-twist'); ?></p>
    <?php endif; ?>
</section>
<?php the_posts_pagination(); ?>
<?php
get_footer();
