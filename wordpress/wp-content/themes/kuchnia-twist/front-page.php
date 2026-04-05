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
$stories_posts     = kuchnia_twist_get_posts_by_category_slug('food-stories', 3, $exclude_ids);
$about_page        = get_page_by_path('about');
$contact_page      = get_page_by_path('contact');
$editorial_policy  = get_page_by_path('editorial-policy');
$public_email      = kuchnia_twist_public_contact_email();
$follow_label      = kuchnia_twist_social_follow_label();
$has_social        = kuchnia_twist_has_social_profiles();
$editor_profile    = kuchnia_twist_editor_profile();
$hero_image        = $hero_post && has_post_thumbnail($hero_post) ? get_the_post_thumbnail_url($hero_post, 'full') : kuchnia_twist_context_media_url('hero');
$hero_category     = $hero_post ? kuchnia_twist_primary_category($hero_post->ID) : null;
$recipe_lead       = $recipes_posts[0] ?? null;
$recipe_stack      = $recipe_lead ? array_slice($recipes_posts, 1) : [];
$fact_lead         = $facts_posts[0] ?? null;
$facts_stack       = $fact_lead ? array_slice($facts_posts, 1) : [];
$story_lead        = $stories_posts[0] ?? null;
$story_stack       = $story_lead ? array_slice($stories_posts, 1) : [];
?>

<section class="home-hero" data-reveal>
    <div class="home-hero__media">
        <img src="<?php echo esc_url($hero_image); ?>" alt="">
    </div>
    <div class="home-hero__content">
        <div class="home-hero__copy">
            <h1><?php bloginfo('name'); ?></h1>
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
            <?php if ($has_social) : ?>
                <a class="button button--ghost" href="#follow-journal"><?php echo esc_html($follow_label); ?></a>
            <?php else : ?>
                <button class="button button--ghost" type="button" data-search-toggle><?php esc_html_e('Open Search', 'kuchnia-twist'); ?></button>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="discovery-rail section" data-reveal>
    <div class="discovery-rail__grid">
        <div class="discovery-rail__search">
            <?php get_search_form(); ?>
        </div>
        <div class="chip-links chip-links--wide">
            <?php foreach (kuchnia_twist_pillar_nav_items() as $item) : ?>
                <a class="chip-link" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
            <?php endforeach; ?>
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
            <article class="feature-story">
                <a class="feature-story__media" href="<?php echo esc_url(get_permalink($recipe_lead)); ?>">
                    <?php if (has_post_thumbnail($recipe_lead)) : ?>
                        <?php echo get_the_post_thumbnail($recipe_lead, 'kuchnia-twist-hero'); ?>
                    <?php else : ?>
                        <?php kuchnia_twist_render_media_placeholder('recipes', __('Latest recipe image', 'kuchnia-twist')); ?>
                    <?php endif; ?>
                </a>
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
                    <article class="compact-story">
                        <a class="compact-story__media" href="<?php echo esc_url(get_permalink($post_item)); ?>">
                            <?php if (has_post_thumbnail($post_item)) : ?>
                                <?php echo get_the_post_thumbnail($post_item, 'kuchnia-twist-card'); ?>
                            <?php else : ?>
                                <?php kuchnia_twist_render_media_placeholder('recipes', __('Recipe image', 'kuchnia-twist')); ?>
                            <?php endif; ?>
                        </a>
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

<section class="editorial-module section" data-reveal>
    <div class="section-heading section-heading--split">
        <div>
            <h2><?php esc_html_e('Food Stories', 'kuchnia-twist'); ?></h2>
        </div>
        <?php $stories_url = kuchnia_twist_category_url_by_slug('food-stories'); ?>
        <?php if ($stories_url !== '') : ?>
            <a class="text-link" href="<?php echo esc_url($stories_url); ?>"><?php esc_html_e('Read the stories', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
    </div>

    <div class="editorial-module__layout">
        <?php if ($story_lead) : ?>
            <article class="feature-story feature-story--story">
                <a class="feature-story__media" href="<?php echo esc_url(get_permalink($story_lead)); ?>">
                    <?php if (has_post_thumbnail($story_lead)) : ?>
                        <?php echo get_the_post_thumbnail($story_lead, 'kuchnia-twist-hero'); ?>
                    <?php else : ?>
                        <?php kuchnia_twist_render_media_placeholder('food-stories', __('Feature story image', 'kuchnia-twist')); ?>
                    <?php endif; ?>
                </a>
                <div class="feature-story__body">
                    <span class="eyebrow"><?php esc_html_e('Story lead', 'kuchnia-twist'); ?></span>
                    <h3><a href="<?php echo esc_url(get_permalink($story_lead)); ?>"><?php echo esc_html(get_the_title($story_lead)); ?></a></h3>
                    <p><?php echo esc_html(get_the_excerpt($story_lead)); ?></p>
                </div>
            </article>
        <?php endif; ?>

        <div class="story-stack">
            <?php foreach ($story_stack as $post_item) : ?>
                <article class="compact-story compact-story--story">
                    <div class="compact-story__body">
                        <span class="eyebrow"><?php esc_html_e('Story', 'kuchnia-twist'); ?></span>
                        <h3><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></h3>
                        <p><?php echo esc_html(get_the_excerpt($post_item)); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($has_social || $public_email) : ?>
    <section class="follow-panel section" id="follow-journal" data-reveal>
        <div class="follow-panel__copy"><h2><?php echo esc_html($follow_label); ?></h2></div>
        <div class="follow-panel__actions">
            <?php if ($has_social) : ?>
                <?php kuchnia_twist_render_social_links('social-links--panel', true); ?>
            <?php endif; ?>
            <?php if ($public_email) : ?>
                <a class="button button--ghost" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php esc_html_e('Email the editorial desk', 'kuchnia-twist'); ?></a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="support-strip section" data-reveal>
    <div class="support-strip__editor">
        <div class="support-strip__avatar">
            <?php if (!empty($editor_profile['photo_id'])) : ?>
                <?php echo wp_get_attachment_image((int) $editor_profile['photo_id'], 'thumbnail', false, ['class' => 'support-strip__avatar-image']); ?>
            <?php else : ?>
                <?php echo get_avatar($public_email ?: get_the_author_meta('user_email'), 88, '', $editor_profile['name'], ['class' => 'support-strip__avatar-image']); ?>
            <?php endif; ?>
        </div>
        <div>
            <h2><?php echo esc_html($editor_profile['name']); ?></h2>
        </div>
    </div>

    <div class="support-strip__links">
        <?php if ($about_page instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('About', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
        <?php if ($editorial_policy instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($editorial_policy)); ?>"><?php esc_html_e('Editorial Policy', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
        <?php if ($contact_page instanceof WP_Post) : ?>
            <a class="chip-link" href="<?php echo esc_url(get_permalink($contact_page)); ?>"><?php esc_html_e('Contact', 'kuchnia-twist'); ?></a>
        <?php endif; ?>
    </div>
</section>

<?php
get_footer();
