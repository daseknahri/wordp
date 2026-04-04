<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="listing-header">
    <span class="eyebrow"><?php esc_html_e('Not found', 'kuchnia-twist'); ?></span>
    <h1><?php esc_html_e('This page slipped out of the pantry.', 'kuchnia-twist'); ?></h1>
    <p><?php esc_html_e('Try the homepage or browse one of the food pillars below.', 'kuchnia-twist'); ?></p>
    <div class="hero__actions">
        <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Go home', 'kuchnia-twist'); ?></a>
    </div>
</section>
<?php
get_footer();
