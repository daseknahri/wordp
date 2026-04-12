<?php

defined('ABSPATH') || exit;

$primary_nav   = kuchnia_twist_primary_nav_items();
$pillar_nav    = kuchnia_twist_pillar_nav_items();
$trust_nav     = kuchnia_twist_trust_nav_items();
$public_email  = kuchnia_twist_public_contact_email();
$follow_label  = kuchnia_twist_social_follow_label();
$has_social    = kuchnia_twist_has_social_profiles();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('has-js');</script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e('Skip to content', 'kuchnia-twist'); ?></a>
<div class="screen-reader-text" aria-live="polite" aria-atomic="true" data-site-announcer></div>
<div class="site-shell">
    <header class="site-header">
        <div class="site-header__bar">
            <div class="masthead">
                <a class="masthead__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <span class="masthead__symbol">
                        <?php
                        $logo_id = function_exists('the_custom_logo') ? (int) get_theme_mod('custom_logo') : 0;
                        if ($logo_id > 0) {
                            echo wp_get_attachment_image($logo_id, 'thumbnail', false, [
                                'class' => 'masthead__symbol-image',
                                'loading' => 'eager',
                                'decoding' => 'async',
                                'fetchpriority' => 'high',
                                'alt' => get_bloginfo('name'),
                            ]);
                        } else {
                            echo '<img src="' . esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')) . '" alt="" width="32" height="32" decoding="async" fetchpriority="high">';
                        }
                        ?>
                    </span>
                    <span class="masthead__wordmark">
                        <?php if (function_exists('the_custom_logo') && has_custom_logo()) : ?>
                            <span class="masthead__wordmark-text"><?php bloginfo('name'); ?></span>
                        <?php else : ?>
                            <img class="masthead__wordmark-image" src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-wordmark.svg')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="170" height="44" decoding="async" fetchpriority="high">
                        <?php endif; ?>
                    </span>
                </a>

                <nav class="masthead__nav" aria-label="<?php esc_attr_e('Primary Navigation', 'kuchnia-twist'); ?>">
                    <?php foreach ($primary_nav as $item) : ?>
                        <?php $is_active = kuchnia_twist_is_nav_item_current($item['url']); ?>
                        <a href="<?php echo esc_url($item['url']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['label']); ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="masthead__actions">
                    <?php if ($has_social) : ?>
                        <div class="masthead__social" aria-label="<?php echo esc_attr($follow_label); ?>">
                            <?php kuchnia_twist_render_social_links('social-links--header'); ?>
                        </div>
                    <?php endif; ?>

                    <button
                        class="masthead__iconbutton"
                        type="button"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="site-search-sheet"
                        data-search-toggle
                    >
                        <span class="masthead__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="M16 16 21 21"></path></svg>
                        </span>
                        <span class="screen-reader-text"><?php esc_html_e('Open search', 'kuchnia-twist'); ?></span>
                    </button>

                    <button
                        class="masthead__iconbutton masthead__iconbutton--menu"
                        type="button"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="site-menu-sheet"
                        data-menu-toggle
                    >
                        <span class="masthead__icon masthead__icon--menu" aria-hidden="true">
                            <span></span><span></span><span></span>
                        </span>
                        <span class="screen-reader-text"><?php esc_html_e('Open menu', 'kuchnia-twist'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="site-header__panel">
            <div class="site-search-sheet" id="site-search-sheet" hidden aria-hidden="true" data-search-panel role="dialog" aria-modal="true" aria-labelledby="site-search-title">
                <div class="site-search-sheet__inner">
                    <div class="site-search-sheet__top">
                        <h2 id="site-search-title"><?php esc_html_e('Search', 'kuchnia-twist'); ?></h2>
                        <button class="site-search-sheet__close" type="button" data-search-close>
                            <span class="screen-reader-text"><?php esc_html_e('Close search', 'kuchnia-twist'); ?></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6 18 18"></path><path d="M18 6 6 18"></path></svg>
                        </button>
                    </div>
                    <p class="site-search-sheet__copy"><?php esc_html_e('Search recipes, food facts, and stories from the journal.', 'kuchnia-twist'); ?></p>
                    <?php get_search_form(); ?>
                </div>
            </div>

            <div class="menu-sheet" id="site-menu-sheet" hidden aria-hidden="true" data-menu-panel role="dialog" aria-modal="true" aria-labelledby="site-menu-title">
                <div class="menu-sheet__inner">
                    <div class="menu-sheet__top">
                        <h2 id="site-menu-title"><?php esc_html_e('Menu', 'kuchnia-twist'); ?></h2>
                        <button class="menu-sheet__close" type="button" data-menu-close>
                            <span class="screen-reader-text"><?php esc_html_e('Close menu', 'kuchnia-twist'); ?></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6 18 18"></path><path d="M18 6 6 18"></path></svg>
                        </button>
                    </div>

                    <div class="menu-sheet__grid">
                        <section class="menu-sheet__section">
                            <div class="menu-sheet__intro">
                                <span class="eyebrow"><?php esc_html_e('Primary', 'kuchnia-twist'); ?></span>
                                <p><?php esc_html_e('The main pages readers use most.', 'kuchnia-twist'); ?></p>
                            </div>
                            <div class="menu-sheet__links">
                                <?php foreach ($primary_nav as $item) : ?>
                                    <?php $is_active = kuchnia_twist_is_nav_item_current($item['url']); ?>
                                    <a href="<?php echo esc_url($item['url']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['label']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="menu-sheet__section">
                            <div class="menu-sheet__intro">
                                <span class="eyebrow"><?php esc_html_e('Pillars', 'kuchnia-twist'); ?></span>
                                <p><?php esc_html_e('Jump into the main reading lanes of the site.', 'kuchnia-twist'); ?></p>
                            </div>
                            <div class="chip-links">
                                <?php foreach ($pillar_nav as $item) : ?>
                                    <?php $is_active = kuchnia_twist_is_nav_item_current($item['url']); ?>
                                    <a class="chip-link<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['label']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?php if ($trust_nav) : ?>
                            <section class="menu-sheet__section">
                                <div class="menu-sheet__intro">
                                    <span class="eyebrow"><?php esc_html_e('Journal', 'kuchnia-twist'); ?></span>
                                    <p><?php esc_html_e('Background, editorial policy, and site standards.', 'kuchnia-twist'); ?></p>
                                </div>
                                <div class="menu-sheet__links menu-sheet__links--compact">
                                    <?php foreach ($trust_nav as $item) : ?>
                                        <?php $is_active = kuchnia_twist_is_nav_item_current($item['url']); ?>
                                        <a href="<?php echo esc_url($item['url']); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['label']); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ($public_email || $has_social) : ?>
                            <section class="menu-sheet__section">
                                <div class="menu-sheet__intro">
                                    <span class="eyebrow"><?php esc_html_e('Follow', 'kuchnia-twist'); ?></span>
                                    <p><?php esc_html_e('Follow new posts or contact the journal directly.', 'kuchnia-twist'); ?></p>
                                </div>
                                <?php if ($has_social) : ?>
                                    <?php kuchnia_twist_render_social_links('social-links--menu', true); ?>
                                <?php endif; ?>
                                <?php if ($public_email) : ?>
                                    <a class="menu-sheet__email" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="site-progress" aria-hidden="true">
            <span class="site-progress__bar"></span>
        </div>
    </header>
    <main id="content" class="site-main">
