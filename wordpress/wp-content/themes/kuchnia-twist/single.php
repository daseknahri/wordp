<?php

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    $category    = kuchnia_twist_primary_category(get_the_ID());
    $recipe_data = kuchnia_twist_get_recipe_data(get_the_ID());
    ?>
    <article class="single-story">
        <header class="single-story__header">
            <?php if ($category instanceof WP_Term) : ?>
                <span class="eyebrow"><?php echo esc_html($category->name); ?></span>
            <?php endif; ?>
            <h1><?php the_title(); ?></h1>
            <div class="single-story__meta">
                <span><?php echo esc_html(get_the_date()); ?></span>
                <span><?php echo esc_html(kuchnia_twist_estimated_read_time()); ?> min read</span>
            </div>
            <p class="single-story__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="single-story__media">
                <?php the_post_thumbnail('kuchnia-twist-hero'); ?>
            </figure>
        <?php endif; ?>

        <div class="prose">
            <?php the_content(); ?>
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
