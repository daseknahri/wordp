<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="listing-header">
    <span class="eyebrow"><?php esc_html_e('Latest articles', 'kuchnia-twist'); ?></span>
    <h1><?php bloginfo('name'); ?></h1>
    <p><?php esc_html_e('Fresh writing from the kitchen journal, shaped to be useful and memorable.', 'kuchnia-twist'); ?></p>
</section>
<section class="story-grid">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php kuchnia_twist_render_post_card(get_the_ID()); ?>
        <?php endwhile; ?>
    <?php else : ?>
        <p class="empty-state"><?php esc_html_e('No posts yet. Your first published article will appear here.', 'kuchnia-twist'); ?></p>
    <?php endif; ?>
</section>
<?php the_posts_pagination(); ?>
<?php
get_footer();
