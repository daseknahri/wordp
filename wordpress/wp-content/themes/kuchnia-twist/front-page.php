<?php

defined('ABSPATH') || exit;

get_header();

$featured_posts = get_posts([
    'post_type'      => 'post',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
]);

$hero_post = $featured_posts ? $featured_posts[0] : null;
$hero_id   = $hero_post ? $hero_post->ID : 0;

$pillar_queries = [
    ['label' => __('Recipes', 'kuchnia-twist'), 'slug' => 'recipes'],
    ['label' => __('Food Facts', 'kuchnia-twist'), 'slug' => 'food-facts'],
    ['label' => __('Food Stories', 'kuchnia-twist'), 'slug' => 'food-stories'],
];
?>
<section class="hero">
    <div class="hero__copy">
        <span class="eyebrow"><?php esc_html_e('Editorial food journal', 'kuchnia-twist'); ?></span>
        <h1><?php esc_html_e('A food blog that feels rich, useful, and worth returning to.', 'kuchnia-twist'); ?></h1>
        <p><?php esc_html_e('Kuchnia Twist blends practical recipes, kitchen knowledge, and story-led food writing into one calm reading experience.', 'kuchnia-twist'); ?></p>
        <div class="hero__actions">
            <?php $recipes_category = get_category_by_slug('recipes'); ?>
            <?php if ($recipes_category instanceof WP_Term) : ?>
                <a class="button button--primary" href="<?php echo esc_url(get_category_link($recipes_category)); ?>"><?php esc_html_e('Browse Recipes', 'kuchnia-twist'); ?></a>
            <?php endif; ?>
            <?php if ($hero_post) : ?>
                <a class="button button--ghost" href="<?php echo esc_url(get_permalink($hero_post)); ?>"><?php esc_html_e('Read the latest feature', 'kuchnia-twist'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($hero_post) : ?>
        <article class="hero__feature">
            <?php if (has_post_thumbnail($hero_post)) : ?>
                <a href="<?php echo esc_url(get_permalink($hero_post)); ?>" class="hero__feature-media">
                    <?php echo get_the_post_thumbnail($hero_post, 'kuchnia-twist-hero'); ?>
                </a>
            <?php endif; ?>
            <div class="hero__feature-body">
                <?php $hero_category = kuchnia_twist_primary_category($hero_id); ?>
                <?php if ($hero_category instanceof WP_Term) : ?>
                    <span class="eyebrow"><?php echo esc_html($hero_category->name); ?></span>
                <?php endif; ?>
                <h2><a href="<?php echo esc_url(get_permalink($hero_post)); ?>"><?php echo esc_html(get_the_title($hero_post)); ?></a></h2>
                <p><?php echo esc_html(get_the_excerpt($hero_post)); ?></p>
            </div>
        </article>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__heading">
        <span class="eyebrow"><?php esc_html_e('Three pillars', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('Every post fits one clear promise.', 'kuchnia-twist'); ?></h2>
    </div>
    <div class="pillar-grid">
        <?php foreach ($pillar_queries as $pillar) : ?>
            <?php
            $category = get_category_by_slug($pillar['slug']);
            $posts    = $category ? get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'cat'            => $category->term_id,
            ]) : [];
            $post_id = $posts ? $posts[0]->ID : 0;
            ?>
            <article class="pillar-card">
                <span class="eyebrow"><?php echo esc_html($pillar['label']); ?></span>
                <?php if ($post_id) : ?>
                    <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
                    <p><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
                    <?php if ($category instanceof WP_Term) : ?>
                        <a class="text-link" href="<?php echo esc_url(get_category_link($category)); ?>"><?php esc_html_e('See all in this pillar', 'kuchnia-twist'); ?></a>
                    <?php endif; ?>
                <?php else : ?>
                    <h3><?php esc_html_e('This section is ready for your first story.', 'kuchnia-twist'); ?></h3>
                    <p><?php esc_html_e('Once you start publishing, each pillar will surface its best current piece here.', 'kuchnia-twist'); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section section--muted">
    <div class="section__heading">
        <span class="eyebrow"><?php esc_html_e('Fresh from the journal', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('Recent posts with room to breathe.', 'kuchnia-twist'); ?></h2>
    </div>
    <div class="story-grid">
        <?php
        $recent_posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'post__not_in'   => $hero_id ? [$hero_id] : [],
        ]);
        ?>
        <?php if ($recent_posts) : ?>
            <?php foreach ($recent_posts as $recent_post) : ?>
                <?php kuchnia_twist_render_post_card($recent_post->ID); ?>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="empty-state"><?php esc_html_e('Start publishing to populate the homepage with your editorial lineup.', 'kuchnia-twist'); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="section section--split">
    <div>
        <span class="eyebrow"><?php esc_html_e('Built for trust', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('A calmer layout makes your content, policies, and editorial identity easier to trust.', 'kuchnia-twist'); ?></h2>
    </div>
    <div class="check-list">
        <p><?php esc_html_e('Clear categories for recipes, food facts, and food stories.', 'kuchnia-twist'); ?></p>
        <p><?php esc_html_e('Dedicated trust pages for about, privacy, cookies, contact, and editorial policy.', 'kuchnia-twist'); ?></p>
        <p><?php esc_html_e('A reading experience designed to feel premium before ads ever arrive.', 'kuchnia-twist'); ?></p>
    </div>
</section>
<?php
get_footer();
