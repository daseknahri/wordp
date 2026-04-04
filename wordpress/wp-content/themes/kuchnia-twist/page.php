<?php

defined('ABSPATH') || exit;

get_header();
?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $profile        = kuchnia_twist_page_profile(get_post());
    $has_body       = kuchnia_twist_page_has_meaningful_body(get_post());
    $action_links   = $profile ? kuchnia_twist_page_action_links(get_post_field('post_name', get_the_ID())) : [];
    $page_slug      = get_post_field('post_name', get_the_ID());
    ?>
    <article class="page-shell <?php echo $profile ? 'page-shell--trust' : ''; ?> page-shell--<?php echo esc_attr($page_slug); ?>">
        <header class="page-hero">
            <div class="page-hero__copy">
                <span class="eyebrow"><?php echo esc_html($profile['eyebrow'] ?? __('Page', 'kuchnia-twist')); ?></span>
                <h1><?php the_title(); ?></h1>
                <?php if (!empty($profile['intro'])) : ?>
                    <p class="page-hero__lede"><?php echo esc_html($profile['intro']); ?></p>
                <?php elseif (has_excerpt()) : ?>
                    <p class="page-hero__lede"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($profile['highlights'])) : ?>
                <aside class="page-highlights" aria-label="<?php esc_attr_e('Page highlights', 'kuchnia-twist'); ?>">
                    <?php foreach ($profile['highlights'] as $highlight) : ?>
                        <p><?php echo esc_html($highlight); ?></p>
                    <?php endforeach; ?>
                </aside>
            <?php endif; ?>
        </header>

        <?php if ($has_body) : ?>
            <div class="prose page-prose">
                <?php the_content(); ?>
            </div>
        <?php endif; ?>

        <?php if ($profile && !empty($profile['sections'])) : ?>
            <section class="page-sections" aria-label="<?php esc_attr_e('Supporting details', 'kuchnia-twist'); ?>">
                <?php foreach ($profile['sections'] as $section) : ?>
                    <article class="page-section-card">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <p><?php echo esc_html($section['body']); ?></p>
                        <?php if (!empty($section['items'])) : ?>
                            <ul class="page-section-card__list">
                                <?php foreach ($section['items'] as $item) : ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($action_links) : ?>
            <section class="page-callout">
                <div>
                    <span class="eyebrow"><?php esc_html_e('Next step', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('Keep the trust pages connected, visible, and easy to scan.', 'kuchnia-twist'); ?></h2>
                </div>
                <div class="page-callout__links">
                    <?php foreach ($action_links as $action_link) : ?>
                        <a class="text-link" href="<?php echo esc_url($action_link['url']); ?>"><?php echo esc_html($action_link['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php
get_footer();
