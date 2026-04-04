<?php

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    $post_id            = get_the_ID();
    $category           = kuchnia_twist_primary_category($post_id);
    $recipe_data        = kuchnia_twist_get_recipe_data($post_id);
    $article            = kuchnia_twist_prepare_article_content($post_id);
    $headings           = $article['headings'];
    $is_updated         = get_the_modified_date('U') !== get_the_date('U');
    $editor_profile     = kuchnia_twist_editor_profile();
    $about_page         = get_page_by_path('about');
    $contact_page       = get_page_by_path('contact');
    $editorial_policy   = get_page_by_path('editorial-policy');
    $public_email       = kuchnia_twist_public_contact_email();
    $story_practice     = kuchnia_twist_story_practice($post_id);
    $story_links        = kuchnia_twist_adjacent_story_links($post_id);
    $has_social         = kuchnia_twist_has_social_profiles();
    ?>
    <article class="article-shell single-story">
        <header class="article-hero">
            <?php kuchnia_twist_render_breadcrumbs(get_post()); ?>
            <?php if ($category instanceof WP_Term) : ?>
                <span class="eyebrow"><?php echo esc_html($category->name); ?></span>
            <?php endif; ?>
            <h1><?php the_title(); ?></h1>
            <p class="article-hero__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
            <div class="article-hero__meta">
                <span><?php echo esc_html(get_the_date()); ?></span>
                <span><?php echo esc_html(kuchnia_twist_estimated_read_time($post_id)); ?> min read</span>
                <?php if ($is_updated) : ?>
                    <span><?php printf(esc_html__('Updated %s', 'kuchnia-twist'), esc_html(get_the_modified_date())); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <div class="article-utility">
            <div class="article-utility__actions">
                <?php if (!empty($recipe_data)) : ?>
                    <a class="button button--primary" href="#recipe-card"><?php esc_html_e('Jump to recipe', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <button class="button button--ghost" type="button" data-search-toggle><?php esc_html_e('Search next', 'kuchnia-twist'); ?></button>
            </div>

            <div class="article-utility__share">
                <?php kuchnia_twist_render_share_links($post_id, 'share-links--inline'); ?>
                <?php if ($has_social) : ?>
                    <?php kuchnia_twist_render_social_links('social-links--inline'); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="article-hero__media">
                <?php the_post_thumbnail('kuchnia-twist-hero'); ?>
            </figure>
        <?php endif; ?>

        <div class="article-layout">
            <div class="article-layout__main">
                <?php if ($headings) : ?>
                    <details class="article-toc-mobile">
                        <summary><?php esc_html_e('On this page', 'kuchnia-twist'); ?></summary>
                        <nav class="article-toc-mobile__links" aria-label="<?php esc_attr_e('Table of contents', 'kuchnia-twist'); ?>">
                            <?php foreach ($headings as $heading) : ?>
                                <a class="story-toc__link story-toc__link--<?php echo esc_attr($heading['level']); ?>" href="#<?php echo esc_attr($heading['id']); ?>"><?php echo esc_html($heading['text']); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </details>
                <?php endif; ?>

                <div class="prose article-prose">
                    <?php echo $article['content']; ?>
                </div>

                <?php if (!empty($recipe_data)) : ?>
                    <section class="recipe-panel" id="recipe-card">
                        <div class="recipe-panel__top">
                            <span class="eyebrow"><?php esc_html_e('Recipe card', 'kuchnia-twist'); ?></span>
                            <div class="recipe-panel__stats">
                                <?php if (!empty($recipe_data['prep_time'])) : ?><span><?php echo esc_html($recipe_data['prep_time']); ?></span><?php endif; ?>
                                <?php if (!empty($recipe_data['cook_time'])) : ?><span><?php echo esc_html($recipe_data['cook_time']); ?></span><?php endif; ?>
                                <?php if (!empty($recipe_data['yield'])) : ?><span><?php echo esc_html($recipe_data['yield']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="recipe-panel__grid">
                            <div>
                                <h2><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></h2>
                                <ul>
                                    <?php foreach (($recipe_data['ingredients'] ?? []) as $ingredient) : ?>
                                        <li><?php echo esc_html($ingredient); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div>
                                <h2><?php esc_html_e('Method', 'kuchnia-twist'); ?></h2>
                                <ol>
                                    <?php foreach (($recipe_data['instructions'] ?? []) as $step) : ?>
                                        <li><?php echo esc_html($step); ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="article-support">
                    <div class="article-support__editor">
                        <span class="eyebrow"><?php esc_html_e('From the editorial desk', 'kuchnia-twist'); ?></span>
                        <h2><?php echo esc_html($editor_profile['name']); ?></h2>
                        <p><?php echo esc_html($editor_profile['bio']); ?></p>
                    </div>
                    <div class="article-support__links">
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
            </div>

            <aside class="article-layout__rail single-story__rail">
                <section class="article-rail">
                    <span class="eyebrow"><?php esc_html_e('Article details', 'kuchnia-twist'); ?></span>
                    <div class="article-rail__meta">
                        <?php if ($category instanceof WP_Term) : ?>
                            <p><strong><?php esc_html_e('Pillar', 'kuchnia-twist'); ?></strong><span><?php echo esc_html($category->name); ?></span></p>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Published', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(get_the_date()); ?></span></p>
                        <p><strong><?php esc_html_e('Read time', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(kuchnia_twist_estimated_read_time($post_id)); ?> min</span></p>
                        <?php if ($is_updated) : ?>
                            <p><strong><?php esc_html_e('Updated', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(get_the_modified_date()); ?></span></p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($headings) : ?>
                    <section class="article-rail">
                        <span class="eyebrow"><?php esc_html_e('On this page', 'kuchnia-twist'); ?></span>
                        <nav class="story-toc" aria-label="<?php esc_attr_e('Table of contents', 'kuchnia-twist'); ?>">
                            <?php foreach ($headings as $heading) : ?>
                                <a class="story-toc__link story-toc__link--<?php echo esc_attr($heading['level']); ?>" href="#<?php echo esc_attr($heading['id']); ?>"><?php echo esc_html($heading['text']); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </section>
                <?php endif; ?>

                <section class="article-rail">
                    <span class="eyebrow"><?php echo esc_html($story_practice['eyebrow']); ?></span>
                    <h2><?php echo esc_html($story_practice['title']); ?></h2>
                    <ul class="story-practice__list">
                        <?php foreach ($story_practice['items'] as $item) : ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="article-rail">
                    <span class="eyebrow"><?php esc_html_e('Share or follow', 'kuchnia-twist'); ?></span>
                    <?php kuchnia_twist_render_share_links($post_id, 'share-links--rail'); ?>
                    <?php if ($has_social) : ?>
                        <?php kuchnia_twist_render_social_links('social-links--rail', true); ?>
                    <?php endif; ?>
                    <?php if ($public_email) : ?>
                        <a class="article-rail__mail" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                    <?php endif; ?>
                </section>
            </aside>
        </div>

        <section class="related-section section">
            <div class="section-heading section-heading--split">
                <div>
                    <span class="eyebrow"><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('More from the same editorial thread.', 'kuchnia-twist'); ?></h2>
                </div>
            </div>
            <div class="story-grid">
                <?php
                $related = new WP_Query([
                    'post_type'      => 'post',
                    'posts_per_page' => 3,
                    'post__not_in'   => [$post_id],
                    'category__in'   => $category instanceof WP_Term ? [$category->term_id] : [],
                ]);
                ?>
                <?php if ($related->have_posts()) : ?>
                    <?php while ($related->have_posts()) : $related->the_post(); ?>
                        <?php kuchnia_twist_render_post_card(get_the_ID()); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($story_links) : ?>
            <section class="story-nav">
                <span class="eyebrow"><?php esc_html_e('Continue through the journal', 'kuchnia-twist'); ?></span>
                <div class="story-nav__grid">
                    <?php foreach ($story_links as $story_link) : ?>
                        <a class="story-nav__item" href="<?php echo esc_url($story_link['url']); ?>">
                            <span class="story-nav__direction"><?php echo esc_html($story_link['direction']); ?></span>
                            <strong><?php echo esc_html($story_link['title']); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>

<?php
get_footer();
