<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="listing-header listing-header--rescue">
    <div class="rescue-layout">
        <div class="rescue-copy">
            <span class="eyebrow"><?php esc_html_e('Not found', 'kuchnia-twist'); ?></span>
            <h1><?php esc_html_e('This page slipped out of the pantry.', 'kuchnia-twist'); ?></h1>
            <p><?php esc_html_e('Try the homepage, run a search, or jump back into one of the food pillars below.', 'kuchnia-twist'); ?></p>
            <div class="listing-search">
                <?php get_search_form(); ?>
            </div>
            <div class="hero__actions">
                <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Go home', 'kuchnia-twist'); ?></a>
            </div>
            <div class="rescue-links">
                <?php kuchnia_twist_pillar_links(); ?>
            </div>
        </div>
        <aside class="rescue-art">
            <img src="<?php echo esc_url(kuchnia_twist_asset_url('assets/media-404.svg')); ?>" alt="">
        </aside>
    </div>
</section>
<?php
get_footer();
