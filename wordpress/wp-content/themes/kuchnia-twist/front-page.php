<?php

defined('ABSPATH') || exit;

get_header();

$latest_posts      = get_posts([
    'post_type'      => 'post',
    'posts_per_page' => 8,
    'post_status'    => 'publish',
]);
$hero_post         = $latest_posts[0] ?? null;
$hero_id           = $hero_post instanceof WP_Post ? (int) $hero_post->ID : 0;
$exclude_ids       = $hero_id ? [$hero_id] : [];
$recipes_posts     = kuchnia_twist_get_posts_by_category_slug('recipes', 5, $exclude_ids);
$facts_posts       = kuchnia_twist_get_posts_by_category_slug('food-facts', 4, $exclude_ids);
$site_lede         = kuchnia_twist_site_summary();
$hero_image_markup = '';
$hero_category     = $hero_post ? kuchnia_twist_primary_category($hero_post->ID) : null;
$recipe_lead       = $recipes_posts[0] ?? null;
$recipe_stack      = $recipe_lead ? array_slice($recipes_posts, 1) : [];
$fact_lead         = $facts_posts[0] ?? null;
$facts_stack       = $fact_lead ? array_slice($facts_posts, 1) : [];

if ($hero_post && has_post_thumbnail($hero_post)) {
    $hero_image_markup = get_the_post_thumbnail($hero_post, 'kuchnia-twist-hero', [
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'async',
        'sizes'         => '(max-width: 767px) 100vw, (max-width: 1199px) 92vw, 56vw',
        'alt'           => trim((string) get_post_meta(get_post_thumbnail_id($hero_post), '_wp_attachment_image_alt', true)) ?: get_the_title($hero_post),
    ]);
}

$hero_class = 'home-hero' . ($hero_image_markup === '' ? ' home-hero--without-media' : '');
?>

<section class="<?php echo esc_attr($hero_class); ?>" data-reveal>
    <?php if ($hero_image_markup !== '') : ?>
        <div class="home-hero__media">
            <?php echo $hero_image_markup; ?>
        </div>
    <?php endif; ?>
    <div class="home-hero__content">
        <div class="home-hero__copy">
            <h1><?php bloginfo('name'); ?></h1>
            <?php if ($site_lede !== '') : ?>
                <p class="home-hero__lede"><?php echo esc_html($site_lede); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($hero_post) : ?>
            <article class="home-hero__feature">
                <?php if ($hero_category instanceof WP_Term) : ?>
                    <span class="eyebrow"><?php echo esc_html($hero_category->name); ?></span>
                <?php endif; ?>
                <h2><a href="<?php echo esc_url(get_permalink($hero_post)); ?>"><?php echo esc_html(get_the_title($hero_post)); ?></a></h2>
                <p><?php echo esc_html(get_the_excerpt($hero_post)); ?></p>
                <div class="home-hero__meta">
                    <span><?php echo esc_html(get_the_date('', $hero_post)); ?></span>
                    <span><?php echo esc_html(kuchnia_twist_estimated_read_time($hero_post->ID)); ?> min read</span>
                </div>
            </article>
        <?php endif; ?>

        <div class="home-hero__actions">
            <?php $recipes_url = kuchnia_twist_category_url_by_slug('recipes'); ?>
            <?php if ($recipes_url !== '') : ?>
                <a class="button button--primary" href="<?php echo esc_url($recipes_url); ?>"><?php esc_html_e('Browse Recipes', 'kuchnia-twist'); ?></a>
            <?php endif; ?>
            <?php if (kuchnia_twist_has_social_profiles()) : ?>
                <div class="home-hero__social">
                    <span class="screen-reader-text"><?php echo esc_html(kuchnia_twist_social_follow_label()); ?></span>
                    <?php kuchnia_twist_render_social_links('social-links--inline', false); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="editorial-module section" data-reveal>
        <div class="section-heading section-heading--split">
        <div>
            <h2><?php esc_html_e('Recipes', 'kuchnia-twist'); ?></h2>
        </div>
        <?php if ($recipes_url !== '') : ?>
            <a class="text-link" href="<?php echo esc_url($recipes_url); ?>"><?php esc_html_e('See all recipes', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
    </div>

    <div class="editorial-module__layout editorial-module__layout--feature<?php echo empty($recipe_stack) ? ' editorial-module__layout--single' : ''; ?>">
        <?php if ($recipe_lead) : ?>
            <?php $recipe_lead_media = kuchnia_twist_get_post_media_markup($recipe_lead->ID, 'kuchnia-twist-hero'); ?>
            <article class="feature-story<?php echo $recipe_lead_media === '' ? ' feature-story--text-only' : ''; ?>">
                <?php if ($recipe_lead_media !== '') : ?>
                    <a class="feature-story__media" href="<?php echo esc_url(get_permalink($recipe_lead)); ?>">
                        <?php echo $recipe_lead_media; ?>
                    </a>
                <?php endif; ?>
                <div class="feature-story__body">
                    <span class="eyebrow"><?php esc_html_e('Lead recipe', 'kuchnia-twist'); ?></span>
                    <h3><a href="<?php echo esc_url(get_permalink($recipe_lead)); ?>"><?php echo esc_html(get_the_title($recipe_lead)); ?></a></h3>
                    <p><?php echo esc_html(get_the_excerpt($recipe_lead)); ?></p>
                    <div class="feature-story__meta">
                        <span><?php echo esc_html(get_the_date('', $recipe_lead)); ?></span>
                        <span><?php echo esc_html(kuchnia_twist_estimated_read_time($recipe_lead->ID)); ?> min read</span>
                    </div>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($recipe_stack) : ?>
            <div class="story-stack">
                <?php foreach ($recipe_stack as $post_item) : ?>
                    <?php $recipe_card_media = kuchnia_twist_get_post_media_markup($post_item->ID, 'kuchnia-twist-card'); ?>
                    <article class="compact-story<?php echo $recipe_card_media === '' ? ' compact-story--text-only' : ''; ?>">
                        <?php if ($recipe_card_media !== '') : ?>
                            <a class="compact-story__media" href="<?php echo esc_url(get_permalink($post_item)); ?>">
                                <?php echo $recipe_card_media; ?>
                            </a>
                        <?php endif; ?>
                        <div class="compact-story__body">
                            <span class="eyebrow"><?php esc_html_e('Recipe', 'kuchnia-twist'); ?></span>
                            <h3><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></h3>
                            <p><?php echo esc_html(get_the_excerpt($post_item)); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="editorial-module section" data-reveal>
        <div class="section-heading section-heading--split">
        <div>
            <h2><?php esc_html_e('Food Facts', 'kuchnia-twist'); ?></h2>
        </div>
        <?php $facts_url = kuchnia_twist_category_url_by_slug('food-facts'); ?>
        <?php if ($facts_url !== '') : ?>
            <a class="text-link" href="<?php echo esc_url($facts_url); ?>"><?php esc_html_e('Browse explainers', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
    </div>

    <div class="mini-feed">
        <?php if ($fact_lead) : ?>
            <article class="mini-feed__lead">
                <span class="eyebrow"><?php esc_html_e('Featured explainer', 'kuchnia-twist'); ?></span>
                <h3><a href="<?php echo esc_url(get_permalink($fact_lead)); ?>"><?php echo esc_html(get_the_title($fact_lead)); ?></a></h3>
                <p><?php echo esc_html(get_the_excerpt($fact_lead)); ?></p>
            </article>
        <?php endif; ?>

        <div class="mini-feed__rail">
            <?php foreach ($facts_stack as $post_item) : ?>
                <article class="mini-feed__item">
                    <span class="eyebrow"><?php esc_html_e('Food fact', 'kuchnia-twist'); ?></span>
                    <h3><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></h3>
                    <p><?php echo esc_html(get_the_excerpt($post_item)); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
get_footer();
