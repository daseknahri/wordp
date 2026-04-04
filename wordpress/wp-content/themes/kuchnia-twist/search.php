<?php

defined('ABSPATH') || exit;

get_header();
$context = kuchnia_twist_archive_context();
?>
<?php kuchnia_twist_render_listing_header($context, __('Search guide', 'kuchnia-twist')); ?>
<section class="story-grid" data-reveal>
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php kuchnia_twist_render_post_card(get_the_ID()); ?>
        <?php endwhile; ?>
    <?php else : ?>
        <?php kuchnia_twist_render_listing_empty_state(
            __('No matching articles yet. Try a broader ingredient, technique, or story topic.', 'kuchnia-twist'),
            kuchnia_twist_asset_url('assets/media-search.svg')
        ); ?>
    <?php endif; ?>
</section>
<?php kuchnia_twist_render_posts_pagination(); ?>
<?php
get_footer();
