<?php

defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 90,
        'width'       => 320,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'kuchnia-twist'),
    ]);

    add_image_size('kuchnia-twist-hero', 1600, 1000, true);
    add_image_size('kuchnia-twist-card', 960, 720, true);
});

add_action('wp_enqueue_scripts', function () {
    $theme_css = get_template_directory() . '/assets/theme.css';
    $theme_js  = get_template_directory() . '/assets/theme.js';

    wp_enqueue_style(
        'kuchnia-twist-fonts',
        'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'kuchnia-twist-style',
        get_template_directory_uri() . '/assets/theme.css',
        ['kuchnia-twist-fonts'],
        file_exists($theme_css) ? (string) filemtime($theme_css) : '1.0.0'
    );
    wp_enqueue_script(
        'kuchnia-twist-theme',
        get_template_directory_uri() . '/assets/theme.js',
        [],
        file_exists($theme_js) ? (string) filemtime($theme_js) : '1.0.0',
        true
    );
});

add_filter('excerpt_length', function () {
    return 24;
}, 99);

add_filter('excerpt_more', function () {
    return '...';
});

function kuchnia_twist_meta_description()
{
    if (is_front_page()) {
        return __('Warm home cooking, useful food facts, and slower kitchen stories from Kuchnia Twist, an independent editorial food journal.', 'kuchnia-twist');
    }

    if (is_home() || is_archive() || is_search()) {
        $context = kuchnia_twist_archive_context();
        return (string) ($context['description'] ?? '');
    }

    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id > 0) {
            $seo_description = trim((string) get_post_meta($post_id, 'kuchnia_twist_seo_description', true));
            if ($seo_description !== '') {
                return $seo_description;
            }

            if (has_excerpt($post_id)) {
                return wp_strip_all_tags(get_the_excerpt($post_id));
            }

            $content = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
            if ($content !== '') {
                return wp_trim_words($content, 28, '');
            }
        }
    }

    return (string) get_bloginfo('description');
}

add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    $description = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(kuchnia_twist_meta_description())));
    if ($description === '') {
        return;
    }

    $description = function_exists('wp_html_excerpt')
        ? wp_html_excerpt($description, 155, '...')
        : mb_substr($description, 0, 155);

    printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta property=\"og:description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr($description));
}, 1);

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

function kuchnia_twist_asset_url($relative_path)
{
    return trailingslashit(get_template_directory_uri()) . ltrim($relative_path, '/');
}

function kuchnia_twist_publication_settings()
{
    $defaults = [
        'editor_name'           => '',
        'editor_role'           => __('Founding editor', 'kuchnia-twist'),
        'editor_bio'            => __('Kuchnia Twist is edited as a warm home-cooking journal focused on practical recipes, useful ingredient explainers, and slower story-led kitchen essays.', 'kuchnia-twist'),
        'editor_public_email'   => '',
        'editor_business_email' => '',
        'editor_photo_id'       => 0,
        'social_instagram_url'  => '',
        'social_facebook_url'   => '',
        'social_pinterest_url'  => '',
        'social_tiktok_url'     => '',
        'social_follow_label'   => __('Follow Kuchnia Twist', 'kuchnia-twist'),
    ];

    return wp_parse_args(get_option('kuchnia_twist_settings', []), $defaults);
}

function kuchnia_twist_category_url_by_slug($slug)
{
    $term = get_category_by_slug($slug);
    return $term instanceof WP_Term ? (string) get_category_link($term) : '';
}

function kuchnia_twist_primary_nav_items()
{
    $items = [
        ['label' => __('Home', 'kuchnia-twist'), 'url' => home_url('/')],
        ['label' => __('Recipes', 'kuchnia-twist'), 'url' => kuchnia_twist_category_url_by_slug('recipes')],
        ['label' => __('Food Facts', 'kuchnia-twist'), 'url' => kuchnia_twist_category_url_by_slug('food-facts')],
        ['label' => __('Food Stories', 'kuchnia-twist'), 'url' => kuchnia_twist_category_url_by_slug('food-stories')],
    ];

    $about = get_page_by_path('about');
    if ($about instanceof WP_Post) {
        $items[] = ['label' => __('About', 'kuchnia-twist'), 'url' => get_permalink($about)];
    }

    return array_values(array_filter($items, static function ($item) {
        return !empty($item['url']) && !is_wp_error($item['url']);
    }));
}

function kuchnia_twist_trust_nav_items()
{
    $items = [];
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
            $items[] = [
                'label' => $label,
                'url'   => get_permalink($page),
            ];
        }
    }

    return $items;
}

function kuchnia_twist_pillar_nav_items()
{
    $items = [];
    $pillars = [
        'recipes'      => __('Recipes', 'kuchnia-twist'),
        'food-facts'   => __('Food Facts', 'kuchnia-twist'),
        'food-stories' => __('Food Stories', 'kuchnia-twist'),
    ];

    foreach ($pillars as $slug => $label) {
        $url = kuchnia_twist_category_url_by_slug($slug);
        if ($url !== '') {
            $items[] = [
                'label' => $label,
                'url'   => $url,
                'slug'  => $slug,
            ];
        }
    }

    return $items;
}

function kuchnia_twist_is_nav_item_current($url)
{
    $url = untrailingslashit((string) $url);
    if ($url === '') {
        return false;
    }

    if (is_front_page() || is_home()) {
        return $url === untrailingslashit(home_url('/'));
    }

    if (is_page()) {
        return $url === untrailingslashit(get_permalink());
    }

    if (is_category()) {
        return $url === untrailingslashit(get_category_link(get_queried_object_id()));
    }

    if (is_single()) {
        $category = kuchnia_twist_primary_category(get_the_ID());
        if ($category instanceof WP_Term) {
            return $url === untrailingslashit(get_category_link($category));
        }
    }

    return false;
}

function kuchnia_twist_social_follow_label()
{
    $settings = kuchnia_twist_publication_settings();
    $label = trim((string) ($settings['social_follow_label'] ?? ''));

    return $label !== '' ? $label : __('Follow Kuchnia Twist', 'kuchnia-twist');
}

function kuchnia_twist_social_profiles()
{
    $settings = kuchnia_twist_publication_settings();
    $profiles = [
        'instagram' => [
            'label' => __('Instagram', 'kuchnia-twist'),
            'url'   => trim((string) ($settings['social_instagram_url'] ?? '')),
        ],
        'facebook' => [
            'label' => __('Facebook', 'kuchnia-twist'),
            'url'   => trim((string) ($settings['social_facebook_url'] ?? '')),
        ],
        'pinterest' => [
            'label' => __('Pinterest', 'kuchnia-twist'),
            'url'   => trim((string) ($settings['social_pinterest_url'] ?? '')),
        ],
        'tiktok' => [
            'label' => __('TikTok', 'kuchnia-twist'),
            'url'   => trim((string) ($settings['social_tiktok_url'] ?? '')),
        ],
    ];

    $result = [];
    foreach ($profiles as $slug => $profile) {
        $url = esc_url_raw($profile['url']);
        if ($url === '') {
            continue;
        }

        $result[] = [
            'slug'  => $slug,
            'label' => $profile['label'],
            'url'   => $url,
        ];
    }

    return $result;
}

function kuchnia_twist_has_social_profiles()
{
    return !empty(kuchnia_twist_social_profiles());
}

function kuchnia_twist_social_icon_svg($slug)
{
    $icons = [
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.25" y="3.25" width="17.5" height="17.5" rx="5"></rect><circle cx="12" cy="12" r="4.2"></circle><circle cx="17.3" cy="6.8" r="1.2" fill="currentColor" stroke="none"></circle></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M13.4 20v-6.5h2.2l.4-2.7h-2.6V9.1c0-.8.3-1.5 1.6-1.5h1.2V5.3c-.2 0-.9-.1-1.9-.1-2.4 0-3.9 1.5-3.9 4.2v1.5H8v2.7h2.4V20z"></path></svg>',
        'pinterest' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12.5 4C8.3 4 6 6.7 6 9.6c0 2.2 1.2 3.4 1.9 3.4.3 0 .5-.9.5-1.2 0-.3-.8-.9-.8-2.1 0-2.5 1.9-4.3 4.3-4.3 2.1 0 3.7 1.2 3.7 3.4 0 2.5-1.1 5.9-3.4 5.9-1.2 0-2.2-.9-2-2.2.3-1.5.9-3 1-4.5 0-.7-.4-1.3-1.1-1.3-1 0-1.8 1.1-1.8 2.5 0 .9.3 1.5.3 1.5L7.2 18c-.2 1 .1 2 .1 2 .1 0 1.2-1.5 1.4-2.4l.4-1.5c.4.8 1.5 1.5 2.7 1.5 3.6 0 6-3.3 6-7.7C17.8 6.3 15.5 4 12.5 4z"></path></svg>',
        'tiktok'    => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M14.7 4.5c.6 1.6 1.7 2.7 3.3 3.2v2.5c-1.1 0-2.3-.3-3.3-.9v5.3c0 3-2 5.1-5.1 5.1S4.5 17.5 4.5 14.7s2.2-5 5-5c.3 0 .7 0 1 .1V12c-.3-.1-.6-.2-1-.2-1.5 0-2.6 1.1-2.6 2.7s1.1 2.7 2.6 2.7 2.7-1 2.7-2.9V4.5z"></path></svg>',
        'email'     => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5h16v11H4z"></path><path d="M4.8 7.2 12 13l7.2-5.8"></path></svg>',
        'copy'      => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M6.5 15.5H6A2 2 0 0 1 4 13.5V6a2 2 0 0 1 2-2h7.5a2 2 0 0 1 2 2v.5"></path></svg>',
    ];

    return $icons[$slug] ?? '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle></svg>';
}

function kuchnia_twist_render_social_links($class = '', $show_labels = false)
{
    $profiles = kuchnia_twist_social_profiles();
    if (!$profiles) {
        return;
    }

    $class_attr = trim('social-links ' . $class);
    echo '<div class="' . esc_attr($class_attr) . '" aria-label="' . esc_attr(kuchnia_twist_social_follow_label()) . '">';
    foreach ($profiles as $profile) {
        echo '<a class="social-links__item social-links__item--' . esc_attr($profile['slug']) . '" href="' . esc_url($profile['url']) . '" target="_blank" rel="noreferrer">';
        echo '<span class="social-links__icon">' . kuchnia_twist_social_icon_svg($profile['slug']) . '</span>';
        if ($show_labels) {
            echo '<span class="social-links__label">' . esc_html($profile['label']) . '</span>';
        } else {
            echo '<span class="screen-reader-text">' . esc_html($profile['label']) . '</span>';
        }
        echo '</a>';
    }
    echo '</div>';
}

function kuchnia_twist_get_posts_by_category_slug($slug, $limit = 4, array $exclude = [])
{
    $term = get_category_by_slug($slug);
    if (!$term instanceof WP_Term) {
        return [];
    }

    return get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'post__not_in'   => array_values(array_filter(array_map('intval', $exclude))),
        'cat'            => $term->term_id,
    ]);
}

function kuchnia_twist_share_links($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $url     = get_permalink($post_id);
    $title   = get_the_title($post_id);
    $media   = get_the_post_thumbnail_url($post_id, 'full');

    if (!$url || !$title) {
        return [];
    }

    return [
        [
            'slug'  => 'facebook',
            'label' => __('Share on Facebook', 'kuchnia-twist'),
            'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        ],
        [
            'slug'  => 'pinterest',
            'label' => __('Save to Pinterest', 'kuchnia-twist'),
            'url'   => 'https://www.pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&description=' . rawurlencode($title) . ($media ? '&media=' . rawurlencode($media) : ''),
        ],
        [
            'slug'  => 'email',
            'label' => __('Email this article', 'kuchnia-twist'),
            'url'   => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url),
        ],
        [
            'slug'  => 'copy',
            'label' => __('Copy article link', 'kuchnia-twist'),
            'url'   => $url,
        ],
    ];
}

function kuchnia_twist_render_share_links($post_id = 0, $class = '')
{
    $links = kuchnia_twist_share_links($post_id);
    if (!$links) {
        return;
    }

    $class_attr = trim('share-links ' . $class);
    echo '<div class="' . esc_attr($class_attr) . '">';
    foreach ($links as $link) {
        if ($link['slug'] === 'copy') {
            echo '<button type="button" class="share-links__item share-links__item--copy" data-copy-link="' . esc_attr($link['url']) . '">';
            echo '<span class="share-links__icon">' . kuchnia_twist_social_icon_svg('copy') . '</span>';
            echo '<span class="share-links__label">' . esc_html__('Copy link', 'kuchnia-twist') . '</span>';
            echo '</button>';
            continue;
        }

        echo '<a class="share-links__item share-links__item--' . esc_attr($link['slug']) . '" href="' . esc_url($link['url']) . '" target="_blank" rel="noreferrer">';
        echo '<span class="share-links__icon">' . kuchnia_twist_social_icon_svg($link['slug']) . '</span>';
        echo '<span class="share-links__label">' . esc_html($link['label']) . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

function kuchnia_twist_default_editor_user()
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $users = get_users([
        'role__in' => ['administrator', 'editor', 'author'],
        'number'   => 1,
        'orderby'  => 'ID',
        'order'    => 'ASC',
    ]);

    $user = $users[0] ?? false;
    return $user;
}

function kuchnia_twist_editor_profile()
{
    $settings = kuchnia_twist_publication_settings();
    $user     = kuchnia_twist_default_editor_user();
    $name     = trim((string) ($settings['editor_name'] ?? ''));
    $role     = trim((string) ($settings['editor_role'] ?? ''));
    $bio      = trim((string) ($settings['editor_bio'] ?? ''));
    $email    = sanitize_email((string) ($settings['editor_public_email'] ?? ''));
    $business = sanitize_email((string) ($settings['editor_business_email'] ?? ''));
    $photo_id = (int) ($settings['editor_photo_id'] ?? 0);

    if ($name === '' && $user instanceof WP_User) {
        $name = trim((string) $user->display_name);
    }

    if ($email === '' && $user instanceof WP_User) {
        $email = sanitize_email((string) $user->user_email);
    }

    if ($bio === '' && $user instanceof WP_User) {
        $bio = trim((string) get_the_author_meta('description', $user->ID));
    }

    return [
        'name'           => $name !== '' ? $name : get_bloginfo('name'),
        'role'           => $role !== '' ? $role : __('Founding editor', 'kuchnia-twist'),
        'bio'            => $bio !== '' ? $bio : __('Kuchnia Twist is an English-language home-cooking journal built around recipes, useful food facts, and slower story-led kitchen essays.', 'kuchnia-twist'),
        'public_email'   => is_email($email) ? $email : '',
        'business_email' => is_email($business) ? $business : '',
        'photo_id'       => $photo_id,
    ];
}

function kuchnia_twist_page_featured_image_url($slug)
{
    $page = get_page_by_path($slug);
    if (!$page instanceof WP_Post || !has_post_thumbnail($page)) {
        return '';
    }

    return (string) get_the_post_thumbnail_url($page, 'full');
}

function kuchnia_twist_fallback_media_url($context = 'journal')
{
    $map = [
        'hero'         => 'assets/media-hero-default.svg',
        'desk'         => 'assets/media-desk.svg',
        'feature'      => 'assets/media-feature.svg',
        'journal'      => 'assets/media-journal.svg',
        'recipes'      => 'assets/media-recipes.svg',
        'food-facts'   => 'assets/media-food-facts.svg',
        'food-stories' => 'assets/media-food-stories.svg',
        'trust'        => 'assets/media-trust.svg',
        'about'        => 'assets/media-about.svg',
        'contact'      => 'assets/media-contact.svg',
    ];

    $asset = $map[$context] ?? $map['journal'];
    return kuchnia_twist_asset_url($asset);
}

function kuchnia_twist_context_media_url($context = 'journal')
{
    static $cache = [];

    if (isset($cache[$context])) {
        return $cache[$context];
    }

    $page_contexts = [
        'about' => 'about',
        'contact' => 'contact',
        'privacy-policy' => 'about',
        'cookie-policy' => 'contact',
        'editorial-policy' => 'about',
        'trust' => 'about',
        'desk' => 'about',
    ];

    if (isset($page_contexts[$context])) {
        $page_image = kuchnia_twist_page_featured_image_url($page_contexts[$context]);
        if ($page_image !== '') {
            return $cache[$context] = $page_image;
        }
    }

    $query_args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_thumbnail_id',
    ];

    if (in_array($context, ['recipes', 'food-facts', 'food-stories'], true)) {
        $term = get_category_by_slug($context);
        if ($term instanceof WP_Term) {
            $query_args['cat'] = $term->term_id;
        }
    }

    $posts = get_posts($query_args);
    if ($posts && has_post_thumbnail($posts[0])) {
        return $cache[$context] = (string) get_the_post_thumbnail_url($posts[0], 'full');
    }

    return $cache[$context] = kuchnia_twist_fallback_media_url($context);
}

function kuchnia_twist_media_context_for_post($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $category = kuchnia_twist_primary_category($post_id);
    if ($category instanceof WP_Term) {
        return $category->slug;
    }

    return 'journal';
}

function kuchnia_twist_render_media_placeholder($context = 'journal', $label = '')
{
    $context = $context ?: 'journal';
    $label = $label !== '' ? $label : __('Kuchnia Twist editorial artwork', 'kuchnia-twist');

    echo '<span class="story-card__placeholder">';
    printf(
        '<img class="story-card__placeholder-art" src="%s" alt="">',
        esc_url(kuchnia_twist_fallback_media_url($context))
    );
    printf('<span class="story-card__placeholder-label">%s</span>', esc_html($label));
    echo '</span>';
}

function kuchnia_twist_render_nav_links()
{
    foreach (kuchnia_twist_primary_nav_items() as $item) {
        if (!empty($item['url']) && !is_wp_error($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
        }
    }
}

function kuchnia_twist_policy_links()
{
    foreach (kuchnia_twist_trust_nav_items() as $item) {
        if (!empty($item['url']) && !is_wp_error($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
        }
    }
}

function kuchnia_twist_pillar_links()
{
    foreach (kuchnia_twist_pillar_nav_items() as $item) {
        if (!empty($item['url']) && !is_wp_error($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
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
    $placeholder_context = kuchnia_twist_media_context_for_post($post_id);
    ?>
    <article class="feed-card">
        <a class="feed-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
            <?php if (has_post_thumbnail($post_id)) : ?>
                <?php echo get_the_post_thumbnail($post_id, 'kuchnia-twist-card'); ?>
            <?php else : ?>
                <?php kuchnia_twist_render_media_placeholder($placeholder_context, __('Fresh from the kitchen journal', 'kuchnia-twist')); ?>
            <?php endif; ?>
        </a>
        <div class="feed-card__body">
            <?php if ($category instanceof WP_Term) : ?>
                <span class="eyebrow"><?php echo esc_html($category->name); ?></span>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
            <p><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
            <div class="feed-card__meta">
                <span><?php echo esc_html(get_the_date('', $post_id)); ?></span>
                <span><?php echo esc_html(kuchnia_twist_estimated_read_time($post_id)); ?> min read</span>
            </div>
        </div>
    </article>
    <?php
}

function kuchnia_twist_get_breadcrumb_items($post = null)
{
    $items = [
        ['label' => __('Home', 'kuchnia-twist'), 'url' => home_url('/')],
    ];

    if (is_front_page()) {
        return [];
    }

    if (is_single()) {
        $post = $post ? get_post($post) : get_post();
        $category = $post ? kuchnia_twist_primary_category($post->ID) : null;
        if ($category instanceof WP_Term) {
            $items[] = ['label' => $category->name, 'url' => get_category_link($category)];
        }
        if ($post instanceof WP_Post) {
            $items[] = ['label' => get_the_title($post), 'url' => ''];
        }
        return $items;
    }

    if (is_page()) {
        $post = $post ? get_post($post) : get_post();
        if ($post instanceof WP_Post) {
            $items[] = ['label' => get_the_title($post), 'url' => ''];
        }
        return $items;
    }

    if (is_category()) {
        $items[] = ['label' => single_cat_title('', false), 'url' => ''];
        return $items;
    }

    if (is_search()) {
        $items[] = ['label' => sprintf(__('Search: %s', 'kuchnia-twist'), get_search_query()), 'url' => ''];
        return $items;
    }

    if (is_home()) {
        $items[] = ['label' => __('Latest Articles', 'kuchnia-twist'), 'url' => ''];
        return $items;
    }

    if (is_archive()) {
        $items[] = ['label' => wp_strip_all_tags(get_the_archive_title()), 'url' => ''];
        return $items;
    }

    return $items;
}

function kuchnia_twist_render_breadcrumbs($post = null)
{
    $items = kuchnia_twist_get_breadcrumb_items($post);
    if (count($items) < 2) {
        return;
    }

    echo '<nav class="breadcrumbs" aria-label="' . esc_attr__('Breadcrumbs', 'kuchnia-twist') . '"><ol>';
    foreach ($items as $index => $item) {
        $is_last = $index === array_key_last($items);
        echo '<li>';
        if (!$is_last && !empty($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['label']));
        } else {
            printf('<span>%s</span>', esc_html($item['label']));
        }
        echo '</li>';
    }
    echo '</ol></nav>';
}

function kuchnia_twist_prepare_article_content($post_id)
{
    $content = apply_filters('the_content', get_post_field('post_content', $post_id));
    $headings = [];
    $used_ids = [];

    $content = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', static function ($matches) use (&$headings, &$used_ids) {
        $level = (int) $matches[1];
        $attrs = (string) $matches[2];
        $inner = (string) $matches[3];
        $text = trim(wp_strip_all_tags($inner));

        if ($text === '') {
            return $matches[0];
        }

        if (preg_match('/\sid=(["\'])(.*?)\1/i', $attrs, $id_match)) {
            $id = sanitize_title($id_match[2]) ?: $id_match[2];
        } else {
            $base = sanitize_title($text);
            $base = $base !== '' ? $base : 'section';
            $id = $base;
            $counter = 2;

            while (in_array($id, $used_ids, true)) {
                $id = $base . '-' . $counter;
                $counter++;
            }

            $used_ids[] = $id;
            $attrs = trim($attrs);
            $attrs = $attrs !== '' ? $attrs . ' ' : '';
            $attrs .= 'id="' . esc_attr($id) . '"';
            $headings[] = [
                'level' => $level,
                'text'  => $text,
                'id'    => $id,
            ];
            return sprintf('<h%d %s>%s</h%d>', $level, $attrs, $inner, $level);
        }

        $used_ids[] = $id;
        $headings[] = [
            'level' => $level,
            'text'  => $text,
            'id'    => $id,
        ];

        return $matches[0];
    }, $content);

    return [
        'content'  => $content,
        'headings' => $headings,
    ];
}

function kuchnia_twist_publication_initials()
{
    $name = trim((string) get_bloginfo('name'));
    if ($name === '') {
        return 'KT';
    }

    $words    = preg_split('/\s+/', $name) ?: [];
    $initials = '';

    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'KT';
}

function kuchnia_twist_trust_page_count()
{
    $trust_slugs = ['about', 'contact', 'privacy-policy', 'cookie-policy', 'editorial-policy'];
    $trust_count = 0;

    foreach ($trust_slugs as $slug) {
        if (get_page_by_path($slug) instanceof WP_Post) {
            $trust_count++;
        }
    }

    return $trust_count;
}

function kuchnia_twist_public_contact_email()
{
    $profile = kuchnia_twist_editor_profile();
    $email = sanitize_email((string) ($profile['public_email'] ?? ''));
    return is_email($email) ? $email : '';
}

function kuchnia_twist_business_contact_email()
{
    $profile = kuchnia_twist_editor_profile();
    $email = sanitize_email((string) ($profile['business_email'] ?? ''));
    return is_email($email) ? $email : '';
}

function kuchnia_twist_archive_context()
{
    global $wp_query;

    $context = [
        'eyebrow'     => __('Latest articles', 'kuchnia-twist'),
        'title'       => get_bloginfo('name'),
        'description' => __('Fresh writing from the kitchen journal, shaped to be useful, memorable, and easy to trust.', 'kuchnia-twist'),
        'art'         => kuchnia_twist_context_media_url('journal'),
        'notes'       => [
            __('Articles are grouped into clear editorial pillars.', 'kuchnia-twist'),
            __('Policy pages and contact details stay visible across the site.', 'kuchnia-twist'),
            __('The archive is designed to feel curated rather than cluttered.', 'kuchnia-twist'),
        ],
    ];

    if (is_home()) {
        $context['eyebrow'] = __('Latest articles', 'kuchnia-twist');
        $context['title'] = __('The latest from Kuchnia Twist', 'kuchnia-twist');
        $context['description'] = __('A running view of the newest recipes, explainers, and story-led pieces published into the journal.', 'kuchnia-twist');
        $context['art'] = kuchnia_twist_context_media_url('journal');
    } elseif (is_search()) {
        $query_text = trim((string) get_search_query());
        $match_count = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
        $context['eyebrow'] = __('Search results', 'kuchnia-twist');
        $context['title'] = __('Search the journal', 'kuchnia-twist');
        $context['description'] = $query_text !== ''
            ? sprintf(
                _n('Showing %1$s result for "%2$s".', 'Showing %1$s results for "%2$s".', max(1, $match_count), 'kuchnia-twist'),
                number_format_i18n($match_count),
                $query_text
            )
            : __('Use the search field to look through recipes, food facts, and story-led pieces.', 'kuchnia-twist');
        $context['art'] = kuchnia_twist_context_media_url('journal');
        $context['notes'] = [
            __('Search works best with ingredients, techniques, and story topics rather than very broad phrases.', 'kuchnia-twist'),
            __('Results still stay inside the same editorial pillars as the rest of the site.', 'kuchnia-twist'),
            __('If a search comes up thin, the pillar archives are still the fastest way back into the journal.', 'kuchnia-twist'),
        ];
    } elseif (is_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $context['eyebrow'] = __('Editorial pillar', 'kuchnia-twist');
            $context['title'] = single_cat_title('', false);
            $context['description'] = wp_strip_all_tags(category_description($term)) ?: __('A focused archive of articles gathered under one editorial theme.', 'kuchnia-twist');
            $context['art'] = kuchnia_twist_context_media_url($term->slug);

            $pillar_map = [
                'recipes' => [
                    'description' => __('Recipe articles are written to feel cookable, calm, and genuinely worth saving for later.', 'kuchnia-twist'),
                    'notes' => [
                        __('Methods should read clearly on the first pass.', 'kuchnia-twist'),
                        __('Ingredients and timing are surfaced without unnecessary clutter.', 'kuchnia-twist'),
                        __('The archive favors practical, repeatable cooking.', 'kuchnia-twist'),
                    ],
                ],
                'food-facts' => [
                    'description' => __('Food facts break down ingredients, techniques, and kitchen questions in a way that stays concrete and readable.', 'kuchnia-twist'),
                    'notes' => [
                        __('Explainers are meant to be specific, not fluffy.', 'kuchnia-twist'),
                        __('The tone stays editorial rather than textbook dry.', 'kuchnia-twist'),
                        __('Useful context matters more than volume.', 'kuchnia-twist'),
                    ],
                ],
                'food-stories' => [
                    'description' => __('Food stories give the publication warmth, memory, and a more human editorial rhythm.', 'kuchnia-twist'),
                    'notes' => [
                        __('Narrative pieces should still stay anchored in real food context.', 'kuchnia-twist'),
                        __('They add personality without breaking trust.', 'kuchnia-twist'),
                        __('This pillar helps the site feel like a publication, not a recipe dump.', 'kuchnia-twist'),
                    ],
                ],
            ];

            if (isset($pillar_map[$term->slug])) {
                $context['description'] = $pillar_map[$term->slug]['description'];
                $context['notes'] = $pillar_map[$term->slug]['notes'];
            }
        }
    } elseif (is_archive()) {
        $context['eyebrow'] = __('Archive', 'kuchnia-twist');
        $context['title'] = wp_strip_all_tags(get_the_archive_title());
        $context['description'] = wp_strip_all_tags(get_the_archive_description()) ?: __('A grouped view into the journal, with the same editorial standards carried across every archive page.', 'kuchnia-twist');
        $context['art'] = kuchnia_twist_context_media_url('journal');
    }

    return $context;
}

function kuchnia_twist_current_listing_lead_post()
{
    global $wp_query;

    if (!$wp_query instanceof WP_Query || empty($wp_query->posts)) {
        return null;
    }

    $lead = $wp_query->posts[0];
    return $lead instanceof WP_Post ? $lead : null;
}

function kuchnia_twist_render_listing_overview()
{
    global $wp_query;

    $lead = kuchnia_twist_current_listing_lead_post();
    $trust_count = kuchnia_twist_trust_page_count();
    $found_posts = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
    $stats = [];

    if (is_search()) {
        $stats[] = [
            'label' => __('Search matches', 'kuchnia-twist'),
            'value' => number_format_i18n($found_posts),
            'detail' => __('The point is to get readers back into a relevant pillar quickly, not overwhelm them with noise.', 'kuchnia-twist'),
        ];
    } elseif (is_category()) {
        $term = get_queried_object();
        $stats[] = [
            'label' => __('Stories in this pillar', 'kuchnia-twist'),
            'value' => number_format_i18n($found_posts),
            'detail' => $term instanceof WP_Term
                ? sprintf(__('Every piece here belongs to %s, which keeps the archive feeling coherent.', 'kuchnia-twist'), $term->name)
                : __('A tighter pillar archive is easier to browse and easier to trust.', 'kuchnia-twist'),
        ];
    } elseif (is_home()) {
        $stats[] = [
            'label' => __('Articles in the latest feed', 'kuchnia-twist'),
            'value' => number_format_i18n($found_posts),
            'detail' => __('The latest view is meant to feel curated, not endless.', 'kuchnia-twist'),
        ];
    } else {
        $stats[] = [
            'label' => __('Archive results', 'kuchnia-twist'),
            'value' => number_format_i18n($found_posts),
            'detail' => __('Even the archive views should keep the same editorial shape as the rest of the site.', 'kuchnia-twist'),
        ];
    }

    $stats[] = [
        'label' => __('Latest in this view', 'kuchnia-twist'),
        'value' => $lead instanceof WP_Post ? get_the_date('M j, Y', $lead) : __('Soon', 'kuchnia-twist'),
        'detail' => __('Fresh work helps the publication feel maintained and genuinely active.', 'kuchnia-twist'),
    ];
    $stats[] = [
        'label' => __('Trust pages live', 'kuchnia-twist'),
        'value' => number_format_i18n($trust_count),
        'detail' => __('About, contact, privacy, cookies, and editorial standards stay close to the archive.', 'kuchnia-twist'),
    ];

    $quick_links = [];

    foreach ([
        'recipes' => __('Recipes', 'kuchnia-twist'),
        'food-facts' => __('Food Facts', 'kuchnia-twist'),
        'food-stories' => __('Food Stories', 'kuchnia-twist'),
    ] as $slug => $label) {
        $term = get_category_by_slug($slug);
        if ($term instanceof WP_Term) {
            $quick_links[] = [
                'label' => $label,
                'url' => get_category_link($term),
            ];
        }
    }

    $about_page = get_page_by_path('about');
    if ($about_page instanceof WP_Post) {
        $quick_links[] = [
            'label' => __('About', 'kuchnia-twist'),
            'url' => get_permalink($about_page),
        ];
    }

    echo '<div class="listing-overview" data-reveal>';

    echo '<div class="listing-stats">';
    foreach ($stats as $stat) {
        echo '<article class="listing-stat">';
        printf('<span class="eyebrow">%s</span>', esc_html($stat['label']));
        printf('<h2>%s</h2>', esc_html($stat['value']));
        printf('<p>%s</p>', esc_html($stat['detail']));
        echo '</article>';
    }
    echo '</div>';

    echo '<aside class="listing-lead">';
    if ($lead instanceof WP_Post) {
        $lead_category = kuchnia_twist_primary_category($lead->ID);
        echo '<a class="listing-lead__media" href="' . esc_url(get_permalink($lead)) . '">';
        if (has_post_thumbnail($lead)) {
            echo get_the_post_thumbnail($lead, 'kuchnia-twist-card');
        } else {
            kuchnia_twist_render_media_placeholder(kuchnia_twist_media_context_for_post($lead->ID), __('Lead story in this view', 'kuchnia-twist'));
        }
        echo '</a>';
        echo '<div class="listing-lead__body">';
        if ($lead_category instanceof WP_Term) {
            printf('<span class="eyebrow">%s</span>', esc_html($lead_category->name));
        } else {
            printf('<span class="eyebrow">%s</span>', esc_html__('Lead story', 'kuchnia-twist'));
        }
        printf('<h2><a href="%s">%s</a></h2>', esc_url(get_permalink($lead)), esc_html(get_the_title($lead)));
        printf('<p>%s</p>', esc_html(get_the_excerpt($lead)));
        echo '<div class="listing-lead__meta">';
        printf('<span>%s</span>', esc_html(get_the_date('', $lead)));
        printf('<span>%s %s</span>', esc_html(kuchnia_twist_estimated_read_time($lead->ID)), esc_html__('min read', 'kuchnia-twist'));
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="listing-lead__body listing-lead__body--empty">';
        printf('<span class="eyebrow">%s</span>', esc_html__('Browse next', 'kuchnia-twist'));
        printf('<h2>%s</h2>', esc_html__('The strongest way back into the journal is through a clear pillar or trust page.', 'kuchnia-twist'));
        printf('<p>%s</p>', esc_html__('Even when a query comes back light, the publication should still feel oriented and easy to navigate.', 'kuchnia-twist'));
        echo '</div>';
    }

    if ($quick_links) {
        echo '<div class="listing-quick-links">';
        foreach ($quick_links as $link) {
            printf('<a href="%s">%s</a>', esc_url($link['url']), esc_html($link['label']));
        }
        echo '</div>';
    }
    echo '</aside>';

    echo '</div>';
}

function kuchnia_twist_render_listing_header(array $context, string $panel_label = '')
{
    $panel_label = $panel_label !== '' ? $panel_label : __('Publication snapshot', 'kuchnia-twist');
    $notes = !empty($context['notes']) && is_array($context['notes']) ? $context['notes'] : [];
    ?>
    <section class="listing-header listing-header--feature" data-reveal>
        <div class="listing-hero">
            <div class="listing-hero__copy">
                <?php kuchnia_twist_render_breadcrumbs(); ?>
                <span class="eyebrow"><?php echo esc_html($context['eyebrow'] ?? ''); ?></span>
                <h1><?php echo esc_html($context['title'] ?? ''); ?></h1>
                <p><?php echo esc_html($context['description'] ?? ''); ?></p>
                <div class="listing-search">
                    <?php get_search_form(); ?>
                </div>
            </div>
            <aside class="listing-hero__panel">
                <img class="listing-hero__art" src="<?php echo esc_url($context['art'] ?? kuchnia_twist_fallback_media_url('journal')); ?>" alt="">
                <span class="site-footer__eyebrow"><?php echo esc_html($panel_label); ?></span>
                <?php if ($notes) : ?>
                    <div class="listing-hero__notes">
                        <?php foreach ($notes as $note) : ?>
                            <p><?php echo esc_html($note); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
        <?php kuchnia_twist_render_listing_overview(); ?>
    </section>
    <?php
}

function kuchnia_twist_render_listing_empty_state(string $message, string $art_url = '')
{
    $art_url = $art_url !== '' ? $art_url : kuchnia_twist_fallback_media_url('journal');
    ?>
    <div class="search-rescue">
        <div class="search-rescue__copy">
            <p class="empty-state"><?php echo esc_html($message); ?></p>
            <div class="rescue-links">
                <?php kuchnia_twist_pillar_links(); ?>
            </div>
        </div>
        <div class="search-rescue__art">
            <img src="<?php echo esc_url($art_url); ?>" alt="">
        </div>
    </div>
    <?php
}

function kuchnia_twist_story_practice($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $category = kuchnia_twist_primary_category($post_id);
    $slug = $category instanceof WP_Term ? $category->slug : '';
    $content_type = (string) get_post_meta($post_id, 'kuchnia_twist_content_type', true);

    $practice = [
        'eyebrow' => __('Editorial practice', 'kuchnia-twist'),
        'title'   => __('Each article feels specific, readable, and genuinely useful.', 'kuchnia-twist'),
        'items'   => [
            __('Clear structure matters more than content volume.', 'kuchnia-twist'),
            __('Related trust pages remain one click away.', 'kuchnia-twist'),
            __('The goal is long-term reader confidence, not thin traffic bait.', 'kuchnia-twist'),
        ],
    ];

    if ($content_type === 'recipe' || $slug === 'recipes') {
        $practice['title'] = __('Recipe posts are shaped to be practical first.', 'kuchnia-twist');
        $practice['items'] = [
            __('Ingredients and method should stay easy to scan while cooking.', 'kuchnia-twist'),
            __('Times and yield are surfaced before the recipe card ends.', 'kuchnia-twist'),
            __('The writing should help home cooks, not just fill space.', 'kuchnia-twist'),
        ];
    } elseif ($slug === 'food-facts') {
        $practice['title'] = __('Food fact pieces should explain without drifting into filler.', 'kuchnia-twist');
        $practice['items'] = [
            __('Claims should stay concrete and readable.', 'kuchnia-twist'),
            __('Context matters more than trivia for its own sake.', 'kuchnia-twist'),
            __('Readers should finish knowing something useful.', 'kuchnia-twist'),
        ];
    } elseif ($slug === 'food-stories') {
        $practice['title'] = __('Food stories add atmosphere without losing editorial discipline.', 'kuchnia-twist');
        $practice['items'] = [
            __('Narrative should still connect clearly to food or kitchen life.', 'kuchnia-twist'),
            __('The tone can be warmer while staying specific and trustworthy.', 'kuchnia-twist'),
            __('Storytelling should deepen the publication, not dilute it.', 'kuchnia-twist'),
        ];
    }

    return $practice;
}

function kuchnia_twist_publication_metrics()
{
    $published_posts = wp_count_posts('post');
    $published_count = $published_posts instanceof stdClass ? (int) ($published_posts->publish ?? 0) : 0;
    $trust_count = kuchnia_twist_trust_page_count();

    $latest_post = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    return [
        [
            'label' => __('Published articles', 'kuchnia-twist'),
            'value' => (string) $published_count,
            'detail'=> $published_count > 0
                ? __('The archive grows through recipes, food facts, and story-led pieces.', 'kuchnia-twist')
                : __('The archive is structured around a small, consistent set of editorial pillars.', 'kuchnia-twist'),
        ],
        [
            'label' => __('Editorial pillars', 'kuchnia-twist'),
            'value' => '3',
            'detail'=> __('Recipes, food facts, and food stories keep the publication structured.', 'kuchnia-twist'),
        ],
        [
            'label' => __('Trust pages live', 'kuchnia-twist'),
            'value' => (string) $trust_count,
            'detail'=> __('About, contact, policy, and standards pages stay visible around the site.', 'kuchnia-twist'),
        ],
        [
            'label' => __('Latest update', 'kuchnia-twist'),
            'value' => $latest_post ? get_the_date('M j, Y', $latest_post[0]) : __('Soon', 'kuchnia-twist'),
            'detail'=> __('Freshly published work helps the journal feel active and maintained.', 'kuchnia-twist'),
        ],
    ];
}

function kuchnia_twist_adjacent_story_links($post_id = 0)
{
    $post_id = $post_id ?: get_the_ID();
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    setup_postdata($post);
    $previous = get_previous_post();
    $next = get_next_post();
    wp_reset_postdata();

    $links = [];

    if ($previous instanceof WP_Post) {
        $links[] = [
            'direction' => __('Previous story', 'kuchnia-twist'),
            'title'     => get_the_title($previous),
            'url'       => get_permalink($previous),
        ];
    }

    if ($next instanceof WP_Post) {
        $links[] = [
            'direction' => __('Next story', 'kuchnia-twist'),
            'title'     => get_the_title($next),
            'url'       => get_permalink($next),
        ];
    }

    return $links;
}

function kuchnia_twist_reader_paths()
{
    $make_path = static function ($slug, $eyebrow, $title, $description) {
        $term = get_category_by_slug($slug);
        $count = 0;
        $url = home_url('/');

        if ($term instanceof WP_Term) {
            $count = (int) $term->count;
            $url = get_category_link($term);
        }

        return [
            'eyebrow'     => $eyebrow,
            'title'       => $title,
            'description' => $description,
            'count'       => $count,
            'count_label' => sprintf(
                _n('%s article in this pillar', '%s articles in this pillar', max(1, $count), 'kuchnia-twist'),
                number_format_i18n($count)
            ),
            'url'         => $url,
            'art'         => kuchnia_twist_context_media_url($slug),
        ];
    };

    return [
        $make_path(
            'recipes',
            __('Cook tonight', 'kuchnia-twist'),
            __('Start with the recipes that are meant to be useful on an ordinary day, not only on a perfect one.', 'kuchnia-twist'),
            __('This path is for practical readers who want something clear, calm, and easy to save for later.', 'kuchnia-twist')
        ),
        $make_path(
            'food-facts',
            __('Learn something useful', 'kuchnia-twist'),
            __('Open the explainers and ingredient pieces when you want clarity without textbook dryness.', 'kuchnia-twist'),
            __('This path works best for readers who like kitchen context, technique notes, and answers to small food questions.', 'kuchnia-twist')
        ),
        $make_path(
            'food-stories',
            __('Stay for the atmosphere', 'kuchnia-twist'),
            __('Read the slower story-led pieces when you want the publication to feel more human than transactional.', 'kuchnia-twist'),
            __('This path adds memory, narrative, and tone so the site feels like a journal instead of just an archive.', 'kuchnia-twist')
        ),
    ];
}

function kuchnia_twist_editorial_desk()
{
    $editor_profile   = kuchnia_twist_editor_profile();
    $published_posts = wp_count_posts('post');
    $published_count = $published_posts instanceof stdClass ? (int) ($published_posts->publish ?? 0) : 0;
    $latest_post     = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    $latest_story    = $latest_post ? get_the_title($latest_post[0]) : __('Warm home cooking, useful explainers, and reflective kitchen essays', 'kuchnia-twist');
    $public_email    = kuchnia_twist_public_contact_email();

    return [
        'eyebrow' => __('From the editorial desk', 'kuchnia-twist'),
        'title'   => sprintf(__('A calm food journal works best when readers can feel the editor behind it.', 'kuchnia-twist')),
        'body'    => sprintf(__('%1$s edits Kuchnia Twist as a warm home-cooking publication with visible standards, a steady archive rhythm, and enough structure that readers can understand the site quickly.', 'kuchnia-twist'), $editor_profile['name']),
        'art'     => kuchnia_twist_context_media_url('desk'),
        'notes'   => [
            [
                'label' => __('Current archive', 'kuchnia-twist'),
                'title' => sprintf(
                    _n('%s published article', '%s published articles', max(1, $published_count), 'kuchnia-twist'),
                    number_format_i18n($published_count)
                ),
                'body'  => __('The goal is a tighter archive with clearer pillars, not volume for its own sake.', 'kuchnia-twist'),
            ],
            [
                'label' => __('Latest story', 'kuchnia-twist'),
                'title' => $latest_story,
                'body'  => __('Fresh work should reinforce the same editorial voice readers see in the homepage and trust pages.', 'kuchnia-twist'),
            ],
            [
                'label' => __('Reader access', 'kuchnia-twist'),
                'title' => $public_email !== '' ? $public_email : __('Contact stays one click away', 'kuchnia-twist'),
                'body'  => __('Questions, corrections, and partnership enquiries stay visible and welcome instead of hidden.', 'kuchnia-twist'),
            ],
            [
                'label' => __('What this journal favors', 'kuchnia-twist'),
                'title' => __('Cookable recipes, useful explainers, and slower publication-voice essays', 'kuchnia-twist'),
                'body'  => __('That mix helps the publication feel rounded, human, and worth returning to after the first click.', 'kuchnia-twist'),
            ],
        ],
    ];
}

function kuchnia_twist_render_posts_pagination()
{
    the_posts_pagination([
        'mid_size'  => 1,
        'prev_text' => __('Previous', 'kuchnia-twist'),
        'next_text' => __('Next', 'kuchnia-twist'),
    ]);
}

function kuchnia_twist_page_profile($post = null)
{
    $post = $post ? get_post($post) : get_post();
    $editor = kuchnia_twist_editor_profile();
    $public_email = kuchnia_twist_public_contact_email();
    $business_email = kuchnia_twist_business_contact_email();

    if (!$post instanceof WP_Post || $post->post_type !== 'page') {
        return null;
    }

    $profiles = [
        'about' => [
            'eyebrow' => __('About the journal', 'kuchnia-twist'),
            'intro' => __('Kuchnia Twist is a calm food publication built around warm home cooking, useful ingredient explainers, and story-led kitchen essays.', 'kuchnia-twist'),
            'highlights' => [
                __('Three editorial pillars keep the site focused.', 'kuchnia-twist'),
                __('Articles are written to be genuinely useful before they try to convert.', 'kuchnia-twist'),
                __('The layout is intentionally clean so trust signals are easy to find.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('What readers will find here', 'kuchnia-twist'),
                    'body' => __("Every published piece should sit inside one of the site's core promises so the archive stays coherent as it grows.", 'kuchnia-twist'),
                    'items' => [
                        __('Recipes that feel cookable, clear, and worth saving.', 'kuchnia-twist'),
                        __('Food facts that explain techniques, ingredients, or kitchen myths without filler.', 'kuchnia-twist'),
                        __('Food stories that add personality, memory, and editorial depth.', 'kuchnia-twist'),
                    ],
                ],
                [
                    'title' => __('What makes the site more trustworthy', 'kuchnia-twist'),
                    'body' => sprintf(__('Trust grows when the publication feels consistent. That means clearer navigation, real policy pages, stronger single-article pages, and a visible editor in %s.', 'kuchnia-twist'), $editor['name']),
                ],
                [
                    'title' => __('Why readers land here', 'kuchnia-twist'),
                    'body' => __('This page works as the editorial introduction to the whole archive: who runs the publication, what it publishes, and why the site is arranged with visible standards.', 'kuchnia-twist'),
                ],
            ],
        ],
        'contact' => [
            'eyebrow' => __('Reach the editorial desk', 'kuchnia-twist'),
            'intro' => __('This page keeps the editorial desk easy to reach for readers, collaborators, and relevant business enquiries, with a clear sense of what belongs in the inbox.', 'kuchnia-twist'),
            'highlights' => [
                __('Recipe questions, corrections, and relevant business enquiries are welcome here.', 'kuchnia-twist'),
                __('The editorial inbox stays visible so the publication remains reachable.', 'kuchnia-twist'),
                __('Response expectations are stated plainly so the site feels current and responsibly run.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Why readers write', 'kuchnia-twist'),
                    'body' => __('The most useful messages are the ones that improve clarity, correct an error, or open a partnership that fits the site without distracting from its editorial focus.', 'kuchnia-twist'),
                    'items' => [
                        __('Recipe clarification or kitchen troubleshooting.', 'kuchnia-twist'),
                        __('Corrections, sourcing notes, or factual updates.', 'kuchnia-twist'),
                        __('Partnership and sponsorship enquiries that fit the publication.', 'kuchnia-twist'),
                    ],
                ],
                [
                    'title' => __('How the desk stays reachable', 'kuchnia-twist'),
                    'body' => $business_email !== ''
                        ? sprintf(__('The publication keeps one public editorial inbox at %1$s, a separate business route at %2$s, and a simple response-time expectation so readers can see that the site is both active and accountable.', 'kuchnia-twist'), $public_email, $business_email)
                        : sprintf(__('The publication keeps one public editorial inbox at %s and a simple response-time expectation so readers can see that the site is active and accountable.', 'kuchnia-twist'), $public_email),
                ],
                [
                    'title' => __('A visible publishing standard', 'kuchnia-twist'),
                    'body' => __('Even a small independent site feels more credible when the contact page is explicit, current, and easy to scan on mobile.', 'kuchnia-twist'),
                ],
            ],
        ],
        'privacy-policy' => [
            'eyebrow' => __('Privacy policy', 'kuchnia-twist'),
            'intro' => __('This page explains what the site may collect at launch, how that information is used, and which tools are intentionally not active yet.', 'kuchnia-twist'),
            'highlights' => [
                __('Basic hosting and security data may be processed to serve the site.', 'kuchnia-twist'),
                __('No newsletter, ad-tech, affiliate, or third-party analytics tools are active at launch.', 'kuchnia-twist'),
                __('If site tools change later, this page changes with them.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('What is in scope at launch', 'kuchnia-twist'),
                    'body' => __('The launch policy focuses on routine site delivery, security, and reader contact rather than on tracking-heavy systems, because the publication is not launching with ads, affiliate tools, or non-essential analytics.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Why this matters for trust', 'kuchnia-twist'),
                    'body' => __('Readers and advertising platforms both expect policy pages to be easy to find and specific enough to reflect the tools actually used on the site.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('What changes later', 'kuchnia-twist'),
                    'body' => __('If analytics, newsletters, ads, or affiliate tools are added later, this page should be updated before those systems become part of normal site operation.', 'kuchnia-twist'),
                ],
            ],
        ],
        'cookie-policy' => [
            'eyebrow' => __('Cookie guide', 'kuchnia-twist'),
            'intro' => __('This page explains the limited cookie use associated with the launch site and what would change if new tools are introduced later.', 'kuchnia-twist'),
            'highlights' => [
                __('Only essential platform and hosting cookies are expected at launch.', 'kuchnia-twist'),
                __('No ad-tech, affiliate, or third-party analytics cookies are part of the launch setup.', 'kuchnia-twist'),
                __('If tracking or ad technology is added later, this page is updated at the same time.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Where cookies may come from', 'kuchnia-twist'),
                    'body' => __('At launch, cookies are expected to come from WordPress itself and the hosting stack, rather than from advertising or analytics networks.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('What keeps the page useful', 'kuchnia-twist'),
                    'body' => __('Cookie language stays most useful when it describes the real tools in use on the site instead of collapsing into vague legal filler.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('When to revisit it', 'kuchnia-twist'),
                    'body' => __('If you install analytics, ad tags, new plugins, or embedded social widgets, this page should be reviewed at the same time.', 'kuchnia-twist'),
                ],
            ],
        ],
        'editorial-policy' => [
            'eyebrow' => __('Editorial standards', 'kuchnia-twist'),
            'intro' => __('An editorial policy gives the publication a visible backbone. It explains how recipes are structured, how fact-led pieces are handled, and how corrections are approached.', 'kuchnia-twist'),
            'highlights' => [
                __('Recipe work should aim to be practical and reader-first.', 'kuchnia-twist'),
                __('Food facts should be handled with care and clarity.', 'kuchnia-twist'),
                __('Story-led essays should stay clearly in publication voice rather than fake autobiography.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Recipe testing and clarity', 'kuchnia-twist'),
                    'body' => __('Recipe posts should be written so a home cook can follow them without guessing. If you adapt or improve a method later, update the article with the clearest version.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Food facts and sourcing', 'kuchnia-twist'),
                    'body' => __('Fact-led articles should avoid invented claims, shallow summary writing, and fake precision. If a point needs sourcing, explain it carefully and update it when necessary.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Corrections and updates', 'kuchnia-twist'),
                    'body' => __("If a recipe instruction is unclear or a factual statement needs revision, correct it promptly and keep the publication's standards visible through that process.", 'kuchnia-twist'),
                ],
            ],
        ],
    ];

    return $profiles[$post->post_name] ?? null;
}

function kuchnia_twist_page_has_meaningful_body($post = null)
{
    $post = $post ? get_post($post) : get_post();

    if (!$post instanceof WP_Post) {
        return false;
    }

    $text = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($post->post_content))));
    if ($text === '') {
        return false;
    }

    $placeholder_markers = [
        'replace this placeholder',
        'use this page to add your contact details',
        'explain how recipes are tested',
        'kuchnia twist is a food journal built around recipes',
    ];

    foreach ($placeholder_markers as $marker) {
        if (strpos($text, $marker) !== false) {
            return false;
        }
    }

    return str_word_count($text) > 24;
}

function kuchnia_twist_page_action_links($slug)
{
    $make_link = static function ($target, $label) {
        if ($target === 'home') {
            return [
                'label' => $label,
                'url'   => home_url('/'),
            ];
        }

        $page = get_page_by_path($target);
        if (!$page instanceof WP_Post) {
            return null;
        }

        return [
            'label' => $label,
            'url'   => get_permalink($page),
        ];
    };

    $map = [
        'about' => [
            $make_link('editorial-policy', __('Read the editorial policy', 'kuchnia-twist')),
            $make_link('contact', __('Open the contact page', 'kuchnia-twist')),
        ],
        'contact' => [
            $make_link('about', __('Read the about page', 'kuchnia-twist')),
            $make_link('editorial-policy', __('See editorial standards', 'kuchnia-twist')),
        ],
        'privacy-policy' => [
            $make_link('cookie-policy', __('Review the cookie page', 'kuchnia-twist')),
            $make_link('contact', __('Contact the site owner', 'kuchnia-twist')),
        ],
        'cookie-policy' => [
            $make_link('privacy-policy', __('Review the privacy page', 'kuchnia-twist')),
            $make_link('contact', __('Contact the site owner', 'kuchnia-twist')),
        ],
        'editorial-policy' => [
            $make_link('about', __('About the publication', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editorial desk', 'kuchnia-twist')),
        ],
    ];

    return array_values(array_filter($map[$slug] ?? [$make_link('home', __('Back to the homepage', 'kuchnia-twist'))]));
}

function kuchnia_twist_page_signal_cards($post = null)
{
    $post = $post ? get_post($post) : get_post();

    if (!$post instanceof WP_Post || $post->post_type !== 'page') {
        return [];
    }

    $published_posts = wp_count_posts('post');
    $published_count = $published_posts instanceof stdClass ? (int) ($published_posts->publish ?? 0) : 0;
    $trust_count     = kuchnia_twist_trust_page_count();
    $public_email    = kuchnia_twist_public_contact_email();
    $modified_date   = get_the_modified_date('M j, Y', $post);

    $lead_cards = [
        'about' => [
            'label'  => __('Editorial pillars', 'kuchnia-twist'),
            'value'  => '3',
            'detail' => __('Recipes, food facts, and food stories keep the journal understandable from the first click.', 'kuchnia-twist'),
        ],
        'contact' => [
            'label'  => __('Editorial inbox', 'kuchnia-twist'),
            'value'  => $public_email !== '' ? antispambot($public_email) : __('Editorial email', 'kuchnia-twist'),
            'detail' => __('A visible response channel helps the publication feel real, maintained, and reachable.', 'kuchnia-twist'),
        ],
        'privacy-policy' => [
            'label'  => __('Privacy scope', 'kuchnia-twist'),
            'value'  => __('Site data', 'kuchnia-twist'),
            'detail' => __('This page should match the real analytics, forms, embeds, and ad tools used on the site.', 'kuchnia-twist'),
        ],
        'cookie-policy' => [
            'label'  => __('Cookie scope', 'kuchnia-twist'),
            'value'  => __('Site tools', 'kuchnia-twist'),
            'detail' => __('Consent language works best when it reflects the actual tracking and embedded services in use.', 'kuchnia-twist'),
        ],
        'editorial-policy' => [
            'label'  => __('Standards scope', 'kuchnia-twist'),
            'value'  => __('Recipes + facts + stories', 'kuchnia-twist'),
            'detail' => __('Editorial standards matter most when they guide every pillar, not only the fact-led pieces.', 'kuchnia-twist'),
        ],
    ];

    $signals = [
        $lead_cards[$post->post_name] ?? [
            'label'  => __('Editorial pillars', 'kuchnia-twist'),
            'value'  => '3',
            'detail' => __('Keeping every article inside a clear pillar makes the publication easier to trust and easier to browse.', 'kuchnia-twist'),
        ],
        [
            'label'  => __('Last reviewed', 'kuchnia-twist'),
            'value'  => $modified_date ?: __('Recently updated', 'kuchnia-twist'),
            'detail' => __('Trust pages should stay current as the publication, tools, and reader promises evolve.', 'kuchnia-twist'),
        ],
        [
            'label'  => __('Published archive', 'kuchnia-twist'),
            'value'  => number_format_i18n($published_count),
            'detail' => $published_count > 0
                ? __('Readers can move from trust pages back into a live journal instead of a static shell.', 'kuchnia-twist')
                : __('Once publishing starts, this page should still send readers back into the live archive.', 'kuchnia-twist'),
        ],
        [
            'label'  => __('Trust pages live', 'kuchnia-twist'),
            'value'  => number_format_i18n($trust_count),
            'detail' => __('About, contact, privacy, cookies, and standards should read like one connected trust layer.', 'kuchnia-twist'),
        ],
    ];

    return $signals;
}

function kuchnia_twist_trust_page_links($current_slug = '')
{
    $links = [];
    $slugs = ['about', 'contact', 'privacy-policy', 'cookie-policy', 'editorial-policy'];

    foreach ($slugs as $slug) {
        if ($slug === $current_slug) {
            continue;
        }

        $page = get_page_by_path($slug);
        if (!$page instanceof WP_Post) {
            continue;
        }

        $links[] = [
            'label' => get_the_title($page),
            'url'   => get_permalink($page),
        ];
    }

    return $links;
}

add_action('wp_head', function () {
    if (has_site_icon()) {
        return;
    }

    echo '<link rel="icon" href="' . esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')) . '" type="image/svg+xml">';
}, 1);

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
