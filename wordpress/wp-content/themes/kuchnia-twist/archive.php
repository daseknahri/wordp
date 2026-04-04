<?php

defined('ABSPATH') || exit;

get_header();

global $wp_query;
$context   = kuchnia_twist_archive_context();
$posts     = $wp_query instanceof WP_Query ? $wp_query->posts : [];
$lead_post = $posts[0] ?? null;
$feed_posts = $lead_post ? array_slice($posts, 1) : [];
?>

<section class="archive-shell section" data-reveal>
    <div class="archive-shell__header">
        <?php kuchnia_twist_render_breadcrumbs(); ?>
        <span class="eyebrow"><?php echo esc_html($context['eyebrow'] ?? __('Archive', 'kuchnia-twist')); ?></span>
        <h1><?php echo esc_html($context['title'] ?? get_bloginfo('name')); ?></h1>
        <div class="archive-shell__tools">
            <?php get_search_form(); ?>
            <div class="chip-links">
                <?php foreach (kuchnia_twist_pillar_nav_items() as $item) : ?>
                    <a class="chip-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($lead_post instanceof WP_Post) : ?>
        <div class="archive-feed">
            <article class="archive-feed__lead">
                <a class="archive-feed__lead-media" href="<?php echo esc_url(get_permalink($lead_post)); ?>">
                    <?php if (has_post_thumbnail($lead_post)) : ?>
                        <?php echo get_the_post_thumbnail($lead_post, 'kuchnia-twist-hero'); ?>
                    <?php else : ?>
                        <?php kuchnia_twist_render_media_placeholder(kuchnia_twist_media_context_for_post($lead_post->ID), __('Lead archive image', 'kuchnia-twist')); ?>
                    <?php endif; ?>
                </a>
                <div class="archive-feed__lead-body">
                    <?php $lead_category = kuchnia_twist_primary_category($lead_post->ID); ?>
                    <?php if ($lead_category instanceof WP_Term) : ?>
                        <span class="eyebrow"><?php echo esc_html($lead_category->name); ?></span>
                    <?php endif; ?>
                    <h2><a href="<?php echo esc_url(get_permalink($lead_post)); ?>"><?php echo esc_html(get_the_title($lead_post)); ?></a></h2>
                    <p><?php echo esc_html(get_the_excerpt($lead_post)); ?></p>
                    <div class="feature-story__meta">
                        <span><?php echo esc_html(get_the_date('', $lead_post)); ?></span>
                        <span><?php echo esc_html(kuchnia_twist_estimated_read_time($lead_post->ID)); ?> min read</span>
                    </div>
                </div>
            </article>

            <div class="archive-feed__list">
                <?php foreach ($feed_posts as $post_item) : ?>
                    <article class="archive-item">
                        <a class="archive-item__media" href="<?php echo esc_url(get_permalink($post_item)); ?>">
                            <?php if (has_post_thumbnail($post_item)) : ?>
                                <?php echo get_the_post_thumbnail($post_item, 'kuchnia-twist-card'); ?>
                            <?php else : ?>
                                <?php kuchnia_twist_render_media_placeholder(kuchnia_twist_media_context_for_post($post_item->ID), __('Archive story image', 'kuchnia-twist')); ?>
                            <?php endif; ?>
                        </a>
                        <div class="archive-item__body">
                            <?php $item_category = kuchnia_twist_primary_category($post_item->ID); ?>
                            <?php if ($item_category instanceof WP_Term) : ?>
                                <span class="eyebrow"><?php echo esc_html($item_category->name); ?></span>
                            <?php endif; ?>
                            <h3><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></h3>
                            <p><?php echo esc_html(get_the_excerpt($post_item)); ?></p>
                            <div class="feed-card__meta">
                                <span><?php echo esc_html(get_the_date('', $post_item)); ?></span>
                                <span><?php echo esc_html(kuchnia_twist_estimated_read_time($post_item->ID)); ?> min read</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="search-rescue">
            <div class="search-rescue__copy">
                <p class="empty-state"><?php esc_html_e('Nothing has been filed into this view yet. Jump back into one of the pillars to keep browsing.', 'kuchnia-twist'); ?></p>
                <div class="chip-links">
                    <?php foreach (kuchnia_twist_pillar_nav_items() as $item) : ?>
                        <a class="chip-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="search-rescue__art">
                <img src="<?php echo esc_url($context['art'] ?? kuchnia_twist_context_media_url('journal')); ?>" alt="">
            </div>
        </div>
    <?php endif; ?>
</section>

<?php kuchnia_twist_render_posts_pagination(); ?>

<?php
get_footer();
