<?php

defined('ABSPATH') || exit;

get_header();

global $wp_query;
$context   = kuchnia_twist_archive_context();
$posts     = $wp_query instanceof WP_Query ? $wp_query->posts : [];
$found_posts = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : count($posts);
$lead_post = $posts[0] ?? null;
$feed_posts = $lead_post ? array_slice($posts, 1) : [];
?>

<section class="archive-shell section" data-reveal>
    <div class="archive-shell__header">
        <?php kuchnia_twist_render_breadcrumbs(); ?>
        <span class="eyebrow"><?php echo esc_html($context['eyebrow'] ?? __('Latest posts', 'kuchnia-twist')); ?></span>
        <h1><?php echo esc_html($context['title'] ?? get_bloginfo('name')); ?></h1>
        <?php if (!empty($context['description'])) : ?>
            <p><?php echo esc_html($context['description']); ?></p>
        <?php else : ?>
            <p><?php esc_html_e('A running feed of the newest stories, recipes, and explainers from the journal.', 'kuchnia-twist'); ?></p>
        <?php endif; ?>
        <?php if ($found_posts > 0) : ?>
            <div class="archive-shell__status">
                <span><?php echo esc_html(sprintf(_n('%s published post', '%s published posts', $found_posts, 'kuchnia-twist'), number_format_i18n($found_posts))); ?></span>
                <span><?php esc_html_e('Ready to read', 'kuchnia-twist'); ?></span>
            </div>
        <?php endif; ?>
        <div class="archive-shell__tools">
            <div class="archive-shell__tool-card archive-shell__tool-card--browse">
                <div class="archive-shell__tool-intro">
                    <span class="eyebrow"><?php esc_html_e('Browse by pillar', 'kuchnia-twist'); ?></span>
                    <p><?php esc_html_e('Use the main lanes below to move into recipes, food facts, or slower reading.', 'kuchnia-twist'); ?></p>
                </div>
                <div class="chip-links">
                    <?php foreach (kuchnia_twist_pillar_nav_items() as $item) : ?>
                        <a class="chip-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($lead_post instanceof WP_Post) : ?>
        <div class="archive-feed">
            <?php $lead_media = kuchnia_twist_get_post_media_markup($lead_post->ID, 'kuchnia-twist-hero', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?>
            <article class="archive-feed__lead<?php echo $lead_media === '' ? ' archive-feed__lead--text-only' : ''; ?>">
                <?php if ($lead_media !== '') : ?>
                    <a class="archive-feed__lead-media" href="<?php echo esc_url(get_permalink($lead_post)); ?>">
                        <?php echo $lead_media; ?>
                    </a>
                <?php endif; ?>
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
                    <?php $item_media = kuchnia_twist_get_post_media_markup($post_item->ID, 'kuchnia-twist-card'); ?>
                    <article class="archive-item<?php echo $item_media === '' ? ' archive-item--text-only' : ''; ?>">
                        <?php if ($item_media !== '') : ?>
                            <a class="archive-item__media" href="<?php echo esc_url(get_permalink($post_item)); ?>">
                                <?php echo $item_media; ?>
                            </a>
                        <?php endif; ?>
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
        <?php
        kuchnia_twist_render_listing_empty_state(
            __('No posts are published here yet. Start with a pillar while the feed builds.', 'kuchnia-twist'),
            [
                'eyebrow' => __('Nothing published yet', 'kuchnia-twist'),
                'title'   => __('The main feed is quiet for now', 'kuchnia-twist'),
                'show_home' => true,
            ]
        );
        ?>
    <?php endif; ?>
</section>

<?php kuchnia_twist_render_posts_pagination(); ?>

<?php
get_footer();
