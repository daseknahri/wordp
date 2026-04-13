<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_article_related');
if (!is_array($context)) {
    return;
}

$post_id = (int) ($context['post_id'] ?? 0);
$category = $context['category'] ?? null;
$story_links = is_array($context['story_links'] ?? null) ? $context['story_links'] : [];
?>

<section class="related-section section">
    <div class="section-heading section-heading--split">
        <div>
            <h2><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></h2>
            <p>
                <?php
                if ($category instanceof WP_Term) {
                    printf(
                        esc_html__('More from %s, selected to keep the flow going.', 'kuchnia-twist'),
                        esc_html($category->name)
                    );
                } else {
                    esc_html_e('A few more reads to keep the flow going.', 'kuchnia-twist');
                }
                ?>
            </p>
        </div>
    </div>
    <div class="story-grid">
        <?php
        $related = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post__not_in'   => $post_id ? [$post_id] : [],
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
        <div class="section-heading">
            <div>
                <h2><?php esc_html_e('Next in the journal', 'kuchnia-twist'); ?></h2>
                <p><?php esc_html_e('Jump to the next read without returning to the archive.', 'kuchnia-twist'); ?></p>
            </div>
        </div>
        <div class="story-nav__grid">
            <?php foreach ($story_links as $story_link) : ?>
                <a class="story-nav__item" href="<?php echo esc_url($story_link['url']); ?>">
                    <span class="story-nav__direction"><?php echo esc_html($story_link['direction']); ?></span>
                    <span class="story-nav__title"><?php echo esc_html($story_link['title']); ?></span>
                    <span class="story-nav__meta"><?php echo esc_html($story_link['eyebrow']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
