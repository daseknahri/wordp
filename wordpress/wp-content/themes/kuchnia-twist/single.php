<?php

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    $category            = kuchnia_twist_primary_category(get_the_ID());
    $recipe_data         = kuchnia_twist_get_recipe_data(get_the_ID());
    $article             = kuchnia_twist_prepare_article_content(get_the_ID());
    $headings            = $article['headings'];
    $is_updated          = get_the_modified_date('U') !== get_the_date('U');
    $author_name         = get_the_author();
    $author_description  = trim((string) get_the_author_meta('description'));
    $about_page          = get_page_by_path('about');
    $contact_page        = get_page_by_path('contact');
    $editorial_policy    = get_page_by_path('editorial-policy');
    $story_practice      = kuchnia_twist_story_practice(get_the_ID());
    $author_summary      = $author_description !== ''
        ? $author_description
        : __('This piece was prepared for Kuchnia Twist with the same house standards used across the journal: clear structure, practical value, and a strong editorial point of view around recipes, food facts, and kitchen stories.', 'kuchnia-twist');
    ?>
    <article class="single-story">
        <header class="single-story__header">
            <?php kuchnia_twist_render_breadcrumbs(get_post()); ?>
            <?php if ($category instanceof WP_Term) : ?>
                <span class="eyebrow"><?php echo esc_html($category->name); ?></span>
            <?php endif; ?>
            <h1><?php the_title(); ?></h1>
            <div class="single-story__meta">
                <span><?php echo esc_html(get_the_date()); ?></span>
                <span><?php echo esc_html(kuchnia_twist_estimated_read_time()); ?> min read</span>
                <?php if ($is_updated) : ?>
                    <span><?php printf(esc_html__('Updated %s', 'kuchnia-twist'), esc_html(get_the_modified_date())); ?></span>
                <?php endif; ?>
            </div>
            <p class="single-story__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
        </header>

        <div class="single-story__layout">
            <div class="single-story__main">
                <?php if (has_post_thumbnail()) : ?>
                    <figure class="single-story__media">
                        <?php the_post_thumbnail('kuchnia-twist-hero'); ?>
                    </figure>
                <?php endif; ?>

                <div class="prose">
                    <?php echo $article['content']; ?>
                </div>

                <?php if (!empty($recipe_data)) : ?>
                    <section class="recipe-panel">
                        <div class="recipe-panel__stats">
                            <?php if (!empty($recipe_data['prep_time'])) : ?><span><?php echo esc_html($recipe_data['prep_time']); ?></span><?php endif; ?>
                            <?php if (!empty($recipe_data['cook_time'])) : ?><span><?php echo esc_html($recipe_data['cook_time']); ?></span><?php endif; ?>
                            <?php if (!empty($recipe_data['yield'])) : ?><span><?php echo esc_html($recipe_data['yield']); ?></span><?php endif; ?>
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

                <section class="article-note">
                    <span class="eyebrow"><?php esc_html_e('Editorial note', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('This article is part of a publication built around useful food writing, not thin content.', 'kuchnia-twist'); ?></h2>
                    <p><?php esc_html_e('Kuchnia Twist groups every article into a clear editorial pillar so readers can browse recipes, food facts, and kitchen stories without getting lost in generic archives.', 'kuchnia-twist'); ?></p>
                    <div class="article-note__links">
                        <?php if ($category instanceof WP_Term) : ?>
                            <a class="text-link" href="<?php echo esc_url(get_category_link($category)); ?>"><?php esc_html_e('Browse this pillar', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                        <?php if ($editorial_policy instanceof WP_Post) : ?>
                            <a class="text-link" href="<?php echo esc_url(get_permalink($editorial_policy)); ?>"><?php esc_html_e('Read the editorial policy', 'kuchnia-twist'); ?></a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="editorial-bio">
                    <div class="editorial-bio__avatar">
                        <?php
                        echo get_avatar(
                            get_the_author_meta('user_email'),
                            88,
                            '',
                            $author_name,
                            ['class' => 'editorial-bio__avatar-image']
                        );
                        ?>
                    </div>
                    <div class="editorial-bio__body">
                        <span class="eyebrow"><?php esc_html_e('From the editorial desk', 'kuchnia-twist'); ?></span>
                        <h2><?php echo esc_html($author_name); ?></h2>
                        <p><?php echo esc_html($author_summary); ?></p>
                        <div class="article-note__links">
                            <?php if ($about_page instanceof WP_Post) : ?>
                                <a class="text-link" href="<?php echo esc_url(get_permalink($about_page)); ?>"><?php esc_html_e('About the publication', 'kuchnia-twist'); ?></a>
                            <?php endif; ?>
                            <?php if ($editorial_policy instanceof WP_Post) : ?>
                                <a class="text-link" href="<?php echo esc_url(get_permalink($editorial_policy)); ?>"><?php esc_html_e('Editorial standards', 'kuchnia-twist'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="single-story__rail">
                <section class="story-rail__card">
                    <span class="eyebrow"><?php esc_html_e('Article details', 'kuchnia-twist'); ?></span>
                    <div class="story-rail__meta">
                        <?php if ($category instanceof WP_Term) : ?>
                            <p><strong><?php esc_html_e('Pillar', 'kuchnia-twist'); ?></strong><span><?php echo esc_html($category->name); ?></span></p>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Published', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(get_the_date()); ?></span></p>
                        <?php if ($is_updated) : ?>
                            <p><strong><?php esc_html_e('Updated', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(get_the_modified_date()); ?></span></p>
                        <?php endif; ?>
                        <p><strong><?php esc_html_e('Read time', 'kuchnia-twist'); ?></strong><span><?php echo esc_html(kuchnia_twist_estimated_read_time()); ?> min</span></p>
                    </div>
                </section>

                <?php if ($headings) : ?>
                    <section class="story-rail__card">
                        <span class="eyebrow"><?php esc_html_e('On this page', 'kuchnia-twist'); ?></span>
                        <nav class="story-toc" aria-label="<?php esc_attr_e('Table of contents', 'kuchnia-twist'); ?>">
                            <?php foreach ($headings as $heading) : ?>
                                <a class="story-toc__link story-toc__link--<?php echo esc_attr($heading['level']); ?>" href="#<?php echo esc_attr($heading['id']); ?>"><?php echo esc_html($heading['text']); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </section>
                <?php endif; ?>

                <section class="story-rail__card story-practice">
                    <span class="eyebrow"><?php echo esc_html($story_practice['eyebrow']); ?></span>
                    <h2><?php echo esc_html($story_practice['title']); ?></h2>
                    <ul class="story-practice__list">
                        <?php foreach ($story_practice['items'] as $item) : ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="story-rail__card">
                    <span class="eyebrow"><?php esc_html_e('Keep exploring', 'kuchnia-twist'); ?></span>
                    <div class="story-rail__links">
                        <?php kuchnia_twist_pillar_links(); ?>
                        <?php
                        if ($about_page instanceof WP_Post) {
                            printf('<a href="%s">%s</a>', esc_url(get_permalink($about_page)), esc_html__('About the publication', 'kuchnia-twist'));
                        }
                        if ($contact_page instanceof WP_Post) {
                            printf('<a href="%s">%s</a>', esc_url(get_permalink($contact_page)), esc_html__('Contact Kuchnia Twist', 'kuchnia-twist'));
                        }
                        ?>
                    </div>
                </section>
            </aside>
        </div>

        <section class="section">
            <div class="section__heading">
                <span class="eyebrow"><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></span>
                <h2><?php esc_html_e('More from the same editorial thread.', 'kuchnia-twist'); ?></h2>
            </div>
            <div class="story-grid">
                <?php
                $related = new WP_Query([
                    'post_type'      => 'post',
                    'posts_per_page' => 3,
                    'post__not_in'   => [get_the_ID()],
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
    </article>
<?php endwhile; ?>

<?php
get_footer();
