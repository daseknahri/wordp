<?php

defined('ABSPATH') || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell">
    <header class="site-header">
        <div class="site-header__inner">
            <div class="site-branding">
                <a class="site-branding__mark" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
                <p class="site-branding__tag"><?php bloginfo('description'); ?></p>
            </div>
            <nav class="site-nav" aria-label="<?php esc_attr_e('Primary Navigation', 'kuchnia-twist'); ?>">
                <?php if (has_nav_menu('primary')) : ?>
                    <?php wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'site-nav__menu',
                        'fallback_cb'    => false,
                    ]); ?>
                <?php else : ?>
                    <div class="site-nav__fallback">
                        <?php kuchnia_twist_render_nav_links(); ?>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="site-main">
