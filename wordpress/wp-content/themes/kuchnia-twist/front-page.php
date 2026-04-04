<?php

defined('ABSPATH') || exit;

get_header();

$featured_posts = get_posts([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'post_status'    => 'publish',
]);

$hero_post = $featured_posts ? $featured_posts[0] : null;
$hero_id   = $hero_post ? $hero_post->ID : 0;
$hero_image = ($hero_post && has_post_thumbnail($hero_post))
    ? get_the_post_thumbnail_url($hero_post, 'full')
    : '';
$hero_background = $hero_image ?: kuchnia_twist_fallback_media_url('hero');
$lead_post = $featured_posts[1] ?? null;
$lead_id   = $lead_post ? $lead_post->ID : 0;
$secondary_posts = array_slice($featured_posts, $lead_post ? 2 : 1, 4);
$about_page = get_page_by_path('about');
$contact_page = get_page_by_path('contact');
$editorial_policy = get_page_by_path('editorial-policy');
$promises = [
    [
        'eyebrow' => __('Clear editorial shape', 'kuchnia-twist'),
        'title'   => __('Every article belongs to a real pillar so the archive feels curated, not cluttered.', 'kuchnia-twist'),
        'body'    => __('Recipes, food facts, and food stories each have a distinct job. That makes the homepage easier to understand and the publication easier to trust.', 'kuchnia-twist'),
    ],
    [
        'eyebrow' => __('Useful before promotional', 'kuchnia-twist'),
        'title'   => __('The writing is meant to help or delight first, before it ever tries to convert.', 'kuchnia-twist'),
        'body'    => __('That tone matters. Readers stay longer when the site feels like a publication with standards instead of a funnel disguised as a blog.', 'kuchnia-twist'),
    ],
    [
        'eyebrow' => __('Visible trust signals', 'kuchnia-twist'),
        'title'   => __('Policy pages, contact details, and editorial standards are surfaced as part of the experience.', 'kuchnia-twist'),
        'body'    => __('They are easy to find from the homepage, footer, and article pages so the publication reads as responsible and contactable.', 'kuchnia-twist'),
    ],
];

$pillar_queries = [
    [
        'label' => __('Recipes', 'kuchnia-twist'),
        'slug' => 'recipes',
        'description' => __('Cookable, comforting, and practical pieces designed to earn repeat visits instead of one-click traffic.', 'kuchnia-twist'),
    ],
    [
        'label' => __('Food Facts', 'kuchnia-twist'),
        'slug' => 'food-facts',
        'description' => __('Clear explanations that make ingredients, techniques, and kitchen myths easier to understand.', 'kuchnia-twist'),
    ],
    [
        'label' => __('Food Stories', 'kuchnia-twist'),
        'slug' => 'food-stories',
        'description' => __('Narrative food writing that gives the site texture, memory, and a stronger editorial identity.', 'kuchnia-twist'),
    ],
];
?>
<section class="hero hero--poster" style="--hero-image:url('<?php echo esc_url($hero_background); ?>')">
    <div class="hero__veil"></div>
    <div class="hero__inner">
        <div class="hero__copy">
            <div class="hero__signature">
                <img class="hero__signature-art" src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')); ?>" alt="">
                <span><?php esc_html_e('Independent kitchen publication', 'kuchnia-twist'); ?></span>
            </div>
            <span class="eyebrow"><?php esc_html_e('Editorial food journal', 'kuchnia-twist'); ?></span>
            <h1><?php bloginfo('name'); ?></h1>
            <p class="hero__lede"><?php esc_html_e('Recipes, food facts, and story-led kitchen writing shaped to feel generous, memorable, and worth trusting.', 'kuchnia-twist'); ?></p>
            <div class="hero__actions">
                <?php $recipes_category = get_category_by_slug('recipes'); ?>
                <?php if ($recipes_category instanceof WP_Term) : ?>
                    <a class="button button--primary" href="<?php echo esc_url(get_category_link($recipes_category)); ?>"><?php esc_html_e('Start With Recipes', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if ($about_page instanceof WP_Post) : ?>
                    <a class="button button--ghost" href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('Read The Editorial Note', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <article class="hero__feature-meta">
            <?php if ($hero_post) : ?>
                <span class="eyebrow"><?php esc_html_e('Latest feature', 'kuchnia-twist'); ?></span>
                <h2><a href="<?php echo esc_url(get_permalink($hero_post)); ?>"><?php echo esc_html(get_the_title($hero_post)); ?></a></h2>
                <p><?php echo esc_html(get_the_excerpt($hero_post)); ?></p>
                <div class="hero__meta-row">
                    <span><?php echo esc_html(get_the_date('', $hero_post)); ?></span>
                    <span><?php echo esc_html(kuchnia_twist_estimated_read_time($hero_id)); ?> min read</span>
                </div>
            <?php else : ?>
                <span class="eyebrow"><?php esc_html_e('House note', 'kuchnia-twist'); ?></span>
                <h2><?php esc_html_e('A warmer, slower food publication starts with consistent voice and generous articles.', 'kuchnia-twist'); ?></h2>
                <p><?php esc_html_e('The first stories you publish here will automatically take over this space. Until then, the site carries a finished editorial identity instead of looking half-built.', 'kuchnia-twist'); ?></p>
                <div class="hero__meta-row">
                    <span><?php esc_html_e('Recipes', 'kuchnia-twist'); ?></span>
                    <span><?php esc_html_e('Food Facts', 'kuchnia-twist'); ?></span>
                    <span><?php esc_html_e('Food Stories', 'kuchnia-twist'); ?></span>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="section section--pillars">
    <div class="section__heading">
        <span class="eyebrow"><?php esc_html_e('Three pillars', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('One publication, three clear reasons to return.', 'kuchnia-twist'); ?></h2>
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
            <article class="pillar-column">
                <span class="eyebrow"><?php echo esc_html($pillar['label']); ?></span>
                <p class="pillar-column__summary"><?php echo esc_html($pillar['description']); ?></p>
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

<section class="section section--journal">
    <div class="section__heading">
        <span class="eyebrow"><?php esc_html_e('Fresh from the journal', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('A quieter layout for the pieces carrying the site forward.', 'kuchnia-twist'); ?></h2>
    </div>
    <div class="journal-grid">
        <?php if ($lead_post) : ?>
            <article class="journal-lead">
                <a class="journal-lead__media" href="<?php echo esc_url(get_permalink($lead_post)); ?>">
                    <?php if (has_post_thumbnail($lead_post)) : ?>
                        <?php echo get_the_post_thumbnail($lead_post, 'kuchnia-twist-hero'); ?>
                    <?php else : ?>
                        <?php kuchnia_twist_render_media_placeholder('feature', __('A fresh feature is waiting here', 'kuchnia-twist')); ?>
                    <?php endif; ?>
                </a>
                <div class="journal-lead__body">
                    <?php $lead_category = kuchnia_twist_primary_category($lead_id); ?>
                    <?php if ($lead_category instanceof WP_Term) : ?>
                        <span class="eyebrow"><?php echo esc_html($lead_category->name); ?></span>
                    <?php endif; ?>
                    <h3><a href="<?php echo esc_url(get_permalink($lead_post)); ?>"><?php echo esc_html(get_the_title($lead_post)); ?></a></h3>
                    <p><?php echo esc_html(get_the_excerpt($lead_post)); ?></p>
                    <div class="hero__meta-row">
                        <span><?php echo esc_html(get_the_date('', $lead_post)); ?></span>
                        <span><?php echo esc_html(kuchnia_twist_estimated_read_time($lead_id)); ?> min read</span>
                    </div>
                </div>
            </article>
        <?php endif; ?>

        <div class="journal-stack">
            <?php if ($secondary_posts) : ?>
                <?php foreach ($secondary_posts as $secondary_post) : ?>
                    <?php $secondary_category = kuchnia_twist_primary_category($secondary_post->ID); ?>
                    <article class="journal-item">
                        <div>
                            <?php if ($secondary_category instanceof WP_Term) : ?>
                                <span class="eyebrow"><?php echo esc_html($secondary_category->name); ?></span>
                            <?php endif; ?>
                            <h3><a href="<?php echo esc_url(get_permalink($secondary_post)); ?>"><?php echo esc_html(get_the_title($secondary_post)); ?></a></h3>
                            <p><?php echo esc_html(get_the_excerpt($secondary_post)); ?></p>
                        </div>
                        <div class="journal-item__meta">
                            <span><?php echo esc_html(get_the_date('', $secondary_post)); ?></span>
                            <span><?php echo esc_html(kuchnia_twist_estimated_read_time($secondary_post->ID)); ?> min read</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php elseif (!$lead_post) : ?>
                <p class="empty-state"><?php esc_html_e('Start publishing to populate the homepage with your editorial lineup.', 'kuchnia-twist'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section--standards">
    <div class="section__heading">
        <span class="eyebrow"><?php esc_html_e('Publication standards', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('The site should feel editorially calm, easy to verify, and built for long-term trust.', 'kuchnia-twist'); ?></h2>
        <p><?php esc_html_e('Good design helps, but what really strengthens a young publication is clearer structure, visible standards, and reader-first articles that do not feel disposable.', 'kuchnia-twist'); ?></p>
    </div>
    <div class="standards-layout">
        <div class="standards-list">
            <?php foreach ($promises as $promise) : ?>
                <article class="standard-item">
                    <span class="eyebrow"><?php echo esc_html($promise['eyebrow']); ?></span>
                    <h3><?php echo esc_html($promise['title']); ?></h3>
                    <p><?php echo esc_html($promise['body']); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <aside class="standards-panel">
            <img class="standards-panel__art" src="<?php echo esc_url(kuchnia_twist_fallback_media_url('trust')); ?>" alt="">
            <span class="site-footer__eyebrow"><?php esc_html_e('Reader note', 'kuchnia-twist'); ?></span>
            <h3><?php esc_html_e('Kuchnia Twist is being built as an independent food journal, not a thin-content project.', 'kuchnia-twist'); ?></h3>
            <p><?php esc_html_e('That means keeping pages connected, using a consistent tone, and making it clear who is behind the publication and how readers can contact it.', 'kuchnia-twist'); ?></p>
            <div class="cta-band__links">
                <?php if ($about_page instanceof WP_Post) : ?>
                    <a href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('Meet the publication', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if ($contact_page instanceof WP_Post) : ?>
                    <a href="<?php echo esc_url(get_permalink($contact_page)); ?>"><?php esc_html_e('Open the contact page', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <?php if ($editorial_policy instanceof WP_Post) : ?>
                    <a href="<?php echo esc_url(get_permalink($editorial_policy)); ?>"><?php esc_html_e('Review editorial standards', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<section class="cta-band">
    <div class="cta-band__copy">
        <span class="eyebrow"><?php esc_html_e('Built for trust', 'kuchnia-twist'); ?></span>
        <h2><?php esc_html_e('A cleaner editorial shape makes the blog easier to trust before it asks anything from the reader.', 'kuchnia-twist'); ?></h2>
        <p><?php esc_html_e("Keep the publishing flow simple, then keep refining the site's voice, policies, and strongest articles. That sequence gives the blog a steadier path to approval and better returning traffic.", 'kuchnia-twist'); ?></p>
        <div class="hero__actions">
            <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('View All Articles', 'kuchnia-twist'); ?></a>
            <?php if ($about_page instanceof WP_Post) : ?>
                <a class="button button--ghost" href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('About Kuchnia Twist', 'kuchnia-twist'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="cta-band__panel">
        <p class="site-footer__eyebrow"><?php esc_html_e('Trust pages', 'kuchnia-twist'); ?></p>
        <div class="cta-band__links">
            <?php kuchnia_twist_policy_links(); ?>
        </div>
    </div>
</section>
<?php
get_footer();
