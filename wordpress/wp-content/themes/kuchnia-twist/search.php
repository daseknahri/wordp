<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="listing-header">
    <?php kuchnia_twist_render_breadcrumbs(); ?>
    <span class="eyebrow"><?php esc_html_e('Search results', 'kuchnia-twist'); ?></span>
    <h1><?php esc_html_e('Search the journal', 'kuchnia-twist'); ?></h1>
    <p>
        <?php
        printf(
            esc_html__('Showing results for "%s".', 'kuchnia-twist'),
            esc_html(get_search_query())
        );
        ?>
    </p>
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
        <div class="search-rescue">
            <div class="search-rescue__copy">
                <p class="empty-state"><?php esc_html_e('No matching articles yet. Try a broader ingredient, technique, or story topic.', 'kuchnia-twist'); ?></p>
                <div class="rescue-links">
                    <?php kuchnia_twist_pillar_links(); ?>
                </div>
            </div>
            <div class="search-rescue__art">
                <img src="<?php echo esc_url(kuchnia_twist_asset_url('assets/media-search.svg')); ?>" alt="">
            </div>
        </div>
    <?php endif; ?>
</section>
<?php kuchnia_twist_render_posts_pagination(); ?>
<?php
get_footer();
