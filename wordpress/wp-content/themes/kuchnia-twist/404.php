<?php

defined('ABSPATH') || exit;

get_header();
?>
<section class="archive-shell section" data-reveal>
    <div class="archive-shell__header">
        <span class="eyebrow"><?php esc_html_e('Not found', 'kuchnia-twist'); ?></span>
        <h1><?php esc_html_e('This page slipped out of the pantry.', 'kuchnia-twist'); ?></h1>
        <div class="archive-shell__actions">
            <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Go home', 'kuchnia-twist'); ?></a>
        </div>
    </div>
</section>
<?php
get_footer();
