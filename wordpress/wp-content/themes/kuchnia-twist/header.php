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
<a class="skip-link" href="#content"><?php esc_html_e('Skip to content', 'kuchnia-twist'); ?></a>
<div class="site-shell">
    <header class="site-header">
        <div class="site-header__inner">
            <div class="site-branding">
                <a class="site-branding__symbol" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')); ?>" alt="">
                </a>
                <div class="site-branding__copy">
                    <span class="site-branding__kicker"><?php esc_html_e('Editorial food journal', 'kuchnia-twist'); ?></span>
                    <div class="site-branding__lockup">
                        <?php if (function_exists('the_custom_logo') && has_custom_logo()) : ?>
                            <div class="site-branding__logo"><?php the_custom_logo(); ?></div>
                        <?php else : ?>
                            <a class="site-branding__mark" href="<?php echo esc_url(home_url('/')); ?>">
                                <img class="site-branding__wordmark-image" src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-wordmark.svg')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            </a>
                        <?php endif; ?>
                    </div>
                    <p class="site-branding__tag"><?php echo esc_html(get_bloginfo('description')); ?></p>
                </div>
            </div>
            <button
                class="site-nav-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="site-header-panel"
            >
                <span class="site-nav-toggle__label"><?php esc_html_e('Menu', 'kuchnia-twist'); ?></span>
                <span class="site-nav-toggle__icon" aria-hidden="true"></span>
            </button>
            <div class="site-header__panel" id="site-header-panel">
                <div class="site-header__tools">
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
                    <div class="site-header__search">
                        <?php get_search_form(); ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main id="content" class="site-main">
