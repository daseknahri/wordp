<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="archive-shell section" data-reveal>
    <div class="archive-shell__header">
        <span class="eyebrow"><?php esc_html_e('Not found', 'kuchnia-twist'); ?></span>
        <h1><?php esc_html_e('This page slipped out of the pantry.', 'kuchnia-twist'); ?></h1>
        <p><?php esc_html_e('Try a quick search, jump back to the homepage, or head straight into one of the editorial pillars below.', 'kuchnia-twist'); ?></p>
        <div class="archive-shell__tools">
            <?php get_search_form(); ?>
            <div class="chip-links">
                <?php foreach (kuchnia_twist_pillar_nav_items() as $item) : ?>
                    <a class="chip-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="home-hero__actions">
            <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Go home', 'kuchnia-twist'); ?></a>
        </div>
    </div>
</section>
<?php
get_footer();
