<?php

defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'kuchnia-twist'),
    ]);

    add_image_size('kuchnia-twist-hero', 1600, 1000, true);
    add_image_size('kuchnia-twist-card', 960, 720, true);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'kuchnia-twist-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'kuchnia-twist-style',
        get_template_directory_uri() . '/assets/theme.css',
        ['kuchnia-twist-fonts'],
        '1.0.0'
    );
});

add_filter('excerpt_length', function () {
    return 24;
}, 99);

add_filter('excerpt_more', function () {
    return '...';
});

function kuchnia_twist_get_recipe_data($post_id)
{
    $recipe_data = get_post_meta($post_id, 'kuchnia_twist_recipe_data', true);

    if (is_array($recipe_data)) {
        return $recipe_data;
    }

    if (is_string($recipe_data) && $recipe_data !== '') {
        $decoded = json_decode($recipe_data, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function kuchnia_twist_primary_category($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $terms   = get_the_category($post_id);
    return $terms ? $terms[0] : null;
}

function kuchnia_twist_render_nav_links()
{
    $category_link = static function ($slug) {
        $term = get_category_by_slug($slug);
        return $term instanceof WP_Term ? get_category_link($term) : '';
    };

    $items = [
        ['label' => __('Home', 'kuchnia-twist'), 'url' => home_url('/')],
        ['label' => __('Recipes', 'kuchnia-twist'), 'url' => $category_link('recipes')],
        ['label' => __('Food Facts', 'kuchnia-twist'), 'url' => $category_link('food-facts')],
        ['label' => __('Food Stories', 'kuchnia-twist'), 'url' => $category_link('food-stories')],
    ];

    $about = get_page_by_path('about');
    if ($about) {
        $items[] = ['label' => get_the_title($about), 'url' => get_permalink($about)];
    }

    foreach ($items as $item) {
        if (!empty($item['url']) && !is_wp_error($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
        }
    }
}

function kuchnia_twist_policy_links()
{
    $slugs = [
        'about'            => __('About', 'kuchnia-twist'),
        'contact'          => __('Contact', 'kuchnia-twist'),
        'privacy-policy'   => __('Privacy', 'kuchnia-twist'),
        'cookie-policy'    => __('Cookies', 'kuchnia-twist'),
        'editorial-policy' => __('Editorial Policy', 'kuchnia-twist'),
    ];

    foreach ($slugs as $slug => $label) {
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            printf('<a href="%s">%s</a>', esc_url(get_permalink($page)), esc_html($label));
        }
    }
}

function kuchnia_twist_estimated_read_time($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $content = wp_strip_all_tags(get_post_field('post_content', $post_id));
    $words   = str_word_count($content);
    return max(1, (int) ceil($words / 220));
}

function kuchnia_twist_render_post_card($post_id)
{
    $category = kuchnia_twist_primary_category($post_id);
    ?>
    <article class="story-card">
        <a class="story-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
            <?php if (has_post_thumbnail($post_id)) : ?>
                <?php echo get_the_post_thumbnail($post_id, 'kuchnia-twist-card'); ?>
            <?php else : ?>
                <span class="story-card__placeholder"><?php esc_html_e('Fresh from the kitchen journal', 'kuchnia-twist'); ?></span>
            <?php endif; ?>
        </a>
        <div class="story-card__body">
            <?php if ($category instanceof WP_Term) : ?>
                <span class="eyebrow"><?php echo esc_html($category->name); ?></span>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
            <p><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
            <div class="story-card__meta">
                <span><?php echo esc_html(get_the_date('', $post_id)); ?></span>
                <span><?php echo esc_html(kuchnia_twist_estimated_read_time($post_id)); ?> min read</span>
            </div>
        </div>
    </article>
    <?php
}

add_action('wp_head', function () {
    if (!is_singular('post')) {
        return;
    }

    $post_id      = get_the_ID();
    $recipe_data  = kuchnia_twist_get_recipe_data($post_id);
    $content_type = get_post_meta($post_id, 'kuchnia_twist_content_type', true);
    $image_url    = get_the_post_thumbnail_url($post_id, 'full');

    if ($content_type === 'recipe' && !empty($recipe_data)) {
        $schema = [
            '@context'          => 'https://schema.org',
            '@type'             => 'Recipe',
            'name'              => get_the_title($post_id),
            'description'       => get_the_excerpt($post_id),
            'author'            => ['@type' => 'Organization', 'name' => get_bloginfo('name')],
            'image'             => $image_url ? [$image_url] : [],
            'recipeIngredient'  => $recipe_data['ingredients'] ?? [],
            'recipeInstructions'=> array_map(static function ($step) {
                return ['@type' => 'HowToStep', 'text' => $step];
            }, $recipe_data['instructions'] ?? []),
            'prepTime'          => $recipe_data['prep_time'] ?? '',
            'cookTime'          => $recipe_data['cook_time'] ?? '',
            'totalTime'         => $recipe_data['total_time'] ?? '',
            'recipeYield'       => $recipe_data['yield'] ?? '',
        ];
    } else {
        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => get_the_title($post_id),
            'description'      => get_the_excerpt($post_id),
            'datePublished'    => get_the_date(DATE_W3C, $post_id),
            'dateModified'     => get_the_modified_date(DATE_W3C, $post_id),
            'author'           => ['@type' => 'Organization', 'name' => get_bloginfo('name')],
            'publisher'        => ['@type' => 'Organization', 'name' => get_bloginfo('name')],
            'image'            => $image_url ? [$image_url] : [],
            'mainEntityOfPage' => get_permalink($post_id),
        ];
    }

    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
});
