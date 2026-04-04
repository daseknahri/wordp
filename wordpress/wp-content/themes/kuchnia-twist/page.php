<?php

defined('ABSPATH') || exit;

get_header();
?>
<?php while (have_posts()) : the_post(); ?>
    <article class="page-shell">
        <header class="listing-header">
            <span class="eyebrow"><?php esc_html_e('Page', 'kuchnia-twist'); ?></span>
            <h1><?php the_title(); ?></h1>
        </header>
        <div class="prose">
            <?php the_content(); ?>
        </div>
    </article>
<?php endwhile; ?>
<?php
get_footer();
