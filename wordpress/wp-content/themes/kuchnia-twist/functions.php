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

add_filter('wp_resource_hints', function (array $urls, string $relation_type) {
    if ($relation_type === 'preconnect') {
        $urls[] = 'https://fonts.googleapis.com';
        $urls[] = [
            'href'        => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
    }

    return $urls;
}, 10, 2);

add_filter('excerpt_length', function () {
    return 24;
}, 99);

add_filter('excerpt_more', function () {
    return '...';
});

add_filter('wp_robots', function (array $robots) {
    if (is_search()) {
        $robots['noindex'] = true;
        $robots['follow'] = true;
        return $robots;
    }

    if (!isset($robots['max-image-preview'])) {
        $robots['max-image-preview'] = 'large';
    }

    return $robots;
});

function kuchnia_twist_meta_description()
{
    if (is_front_page()) {
        return __('Cookable recipes and clear food facts from kuchniatwist, an independent home-cooking journal.', 'kuchnia-twist');
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

            if (is_page()) {
                $profile = kuchnia_twist_page_profile(get_post($post_id));
                if (is_array($profile)) {
                    $intro = trim((string) ($profile['intro'] ?? ''));
                    if ($intro !== '') {
                        return $intro;
                    }
                }
            }

            $content = wp_strip_all_tags((string) get_post_field('post_content', $post_id));
            if ($content !== '') {
                return wp_trim_words($content, 28, '');
            }
        }
    }

    return kuchnia_twist_site_summary();
}

function kuchnia_twist_site_summary()
{
    return __('Cookable recipes and clear food facts for everyday home cooking.', 'kuchnia-twist');
}

function kuchnia_twist_meta_canonical_url()
{
    if (is_front_page()) {
        $canonical = home_url('/');
        $paged = max(1, (int) get_query_var('paged'));
        if ($paged > 1) {
            $canonical = trailingslashit($canonical) . user_trailingslashit('page/' . $paged, 'paged');
        }

        return $canonical;
    }

    if (is_home() && !is_front_page()) {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id > 0) {
            $canonical = (string) get_permalink($posts_page_id);
            $paged = max(1, (int) get_query_var('paged'));
            if ($paged > 1) {
                $canonical = trailingslashit($canonical) . user_trailingslashit('page/' . $paged, 'paged');
            }

            return $canonical;
        }
    }

    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id > 0) {
            $canonical = wp_get_canonical_url($post_id);
            if (is_string($canonical) && $canonical !== '') {
                return $canonical;
            }

            return (string) get_permalink($post_id);
        }
    }

    if (is_search()) {
        $args = ['s' => get_search_query()];
        $paged = max(1, (int) get_query_var('paged'));
        if ($paged > 1) {
            $args['paged'] = $paged;
        }

        return (string) add_query_arg($args, home_url('/'));
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $canonical = get_term_link($term);
            if (!is_wp_error($canonical)) {
                $paged = max(1, (int) get_query_var('paged'));
                if ($paged > 1) {
                    $canonical = trailingslashit($canonical) . user_trailingslashit('page/' . $paged, 'paged');
                }

                return (string) $canonical;
            }
        }
    }

    if (is_post_type_archive()) {
        $post_type = get_query_var('post_type');
        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }
        if (is_string($post_type) && $post_type !== '') {
            $canonical = get_post_type_archive_link($post_type);
            if (is_string($canonical) && $canonical !== '') {
                $paged = max(1, (int) get_query_var('paged'));
                if ($paged > 1) {
                    $canonical = trailingslashit($canonical) . user_trailingslashit('page/' . $paged, 'paged');
                }

                return $canonical;
            }
        }
    }

    if (is_archive()) {
        return (string) get_pagenum_link(max(1, (int) get_query_var('paged')));
    }

    return home_url('/');
}

function kuchnia_twist_meta_image_from_attachment($image_id, string $fallback_alt = '')
{
    $image_id = (int) $image_id;
    if ($image_id <= 0) {
        return [];
    }

    $image_data = wp_get_attachment_image_src($image_id, 'full');
    $image_url = is_array($image_data) ? (string) ($image_data[0] ?? '') : (string) wp_get_attachment_url($image_id);
    if ($image_url === '') {
        return [];
    }

    $image_alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
    if ($image_alt === '') {
        $image_alt = $fallback_alt;
    }

    $image = [
        'url' => $image_url,
        'alt' => $image_alt,
    ];

    if (is_array($image_data)) {
        $width = (int) ($image_data[1] ?? 0);
        $height = (int) ($image_data[2] ?? 0);
        if ($width > 0) {
            $image['width'] = $width;
        }
        if ($height > 0) {
            $image['height'] = $height;
        }
    }

    $mime_type = (string) get_post_mime_type($image_id);
    if ($mime_type !== '') {
        $image['type'] = $mime_type;
    }

    return $image;
}

function kuchnia_twist_meta_image()
{
    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id > 0 && has_post_thumbnail($post_id)) {
            $image_id = (int) get_post_thumbnail_id($post_id);
            return kuchnia_twist_meta_image_from_attachment($image_id, get_the_title($post_id));
        }
    }

    if (is_front_page()) {
        $lead_posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => '_thumbnail_id',
        ]);

        if ($lead_posts) {
            $lead_post = $lead_posts[0];
            if ($lead_post instanceof WP_Post && has_post_thumbnail($lead_post)) {
                $image_id = (int) get_post_thumbnail_id($lead_post);
                return kuchnia_twist_meta_image_from_attachment($image_id, get_the_title($lead_post));
            }
        }
    }

    if (is_home() || is_archive() || is_search()) {
        global $wp_query;

        if ($wp_query instanceof WP_Query && !empty($wp_query->posts)) {
            $lead_post = $wp_query->posts[0];
            if ($lead_post instanceof WP_Post && has_post_thumbnail($lead_post)) {
                $image_id = (int) get_post_thumbnail_id($lead_post);
                return kuchnia_twist_meta_image_from_attachment($image_id, get_the_title($lead_post));
            }
        }
    }

    return [];
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

    $title = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(wp_get_document_title())));
    $canonical = trim((string) kuchnia_twist_meta_canonical_url());
    $image = kuchnia_twist_meta_image();
    $image_url = trim((string) ($image['url'] ?? ''));
    $image_alt = trim((string) ($image['alt'] ?? ''));
    $image_width = (int) ($image['width'] ?? 0);
    $image_height = (int) ($image['height'] ?? 0);
    $image_type = trim((string) ($image['type'] ?? ''));
    $og_type = is_singular('post') ? 'article' : 'website';
    $locale = str_replace('_', '-', get_locale());

    printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($description));
    if ($canonical !== '') {
        printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($canonical));
        printf("<meta property=\"og:url\" content=\"%s\">\n", esc_url($canonical));
    }
    if ($title !== '') {
        printf("<meta property=\"og:title\" content=\"%s\">\n", esc_attr($title));
        printf("<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr($title));
    }
    printf("<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr(get_bloginfo('name')));
    printf("<meta property=\"og:locale\" content=\"%s\">\n", esc_attr($locale));
    printf("<meta property=\"og:type\" content=\"%s\">\n", esc_attr($og_type));
    printf("<meta property=\"og:description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta name=\"twitter:card\" content=\"%s\">\n", esc_attr($image_url !== '' ? 'summary_large_image' : 'summary'));

    $author_schema = kuchnia_twist_schema_author();
    if (is_array($author_schema) && !empty($author_schema['name'])) {
        printf("<meta name=\"author\" content=\"%s\">\n", esc_attr((string) $author_schema['name']));
        if (is_singular('post')) {
            printf("<meta property=\"article:author\" content=\"%s\">\n", esc_attr((string) $author_schema['name']));
        }
    }

    if ($image_url !== '') {
        printf("<meta property=\"og:image\" content=\"%s\">\n", esc_url($image_url));
        printf("<meta name=\"twitter:image\" content=\"%s\">\n", esc_url($image_url));
        if ($image_width > 0) {
            printf("<meta property=\"og:image:width\" content=\"%d\">\n", $image_width);
        }
        if ($image_height > 0) {
            printf("<meta property=\"og:image:height\" content=\"%d\">\n", $image_height);
        }
        if ($image_type !== '') {
            printf("<meta property=\"og:image:type\" content=\"%s\">\n", esc_attr($image_type));
        }
        if ($image_alt !== '') {
            printf("<meta property=\"og:image:alt\" content=\"%s\">\n", esc_attr($image_alt));
            printf("<meta name=\"twitter:image:alt\" content=\"%s\">\n", esc_attr($image_alt));
        }
    }

    if (is_singular('post')) {
        printf("<meta property=\"article:published_time\" content=\"%s\">\n", esc_attr(get_post_time(DATE_W3C, true)));
        printf("<meta property=\"article:modified_time\" content=\"%s\">\n", esc_attr(get_post_modified_time(DATE_W3C, true)));

        $primary_category = kuchnia_twist_primary_category(get_queried_object_id());
        if ($primary_category instanceof WP_Term) {
            printf("<meta property=\"article:section\" content=\"%s\">\n", esc_attr($primary_category->name));
        }
    }
}, 1);

add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    $client = kuchnia_twist_adsense_client_id();
    if ($client === '') {
        return;
    }

    printf(
        "<script async src=\"https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s\" crossorigin=\"anonymous\"></script>\n",
        esc_attr($client)
    );
}, 5);

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

function kuchnia_twist_get_page_flow($post_id)
{
    $page_flow = get_post_meta($post_id, 'kuchnia_twist_page_flow', true);

    if (!is_array($page_flow)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($item) {
        if (!is_array($item)) {
            return null;
        }

        $index = (int) ($item['index'] ?? 0);
        $label = trim((string) ($item['label'] ?? ''));
        $summary = trim((string) ($item['summary'] ?? ''));
        if ($index < 1 || $label === '') {
            return null;
        }

        return [
            'index' => $index,
            'label' => $label,
            'summary' => $summary,
        ];
    }, $page_flow)));
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

function kuchnia_twist_brand_mark_url()
{
    $asset = kuchnia_twist_asset_url('assets/brand-seal.svg');
    return is_string($asset) ? $asset : '';
}

function kuchnia_twist_editor_portrait_relative_path()
{
    return 'assets/editor-portrait.png';
}

function kuchnia_twist_editor_portrait_url()
{
    return kuchnia_twist_asset_url(kuchnia_twist_editor_portrait_relative_path());
}

function kuchnia_twist_publication_settings()
{
    $defaults = [
        'editor_name'           => 'Anna Kowalska',
        'editor_role'           => __('Editorial desk', 'kuchnia-twist'),
        'editor_bio'            => __('Independent home-cooking journal focused on practical recipes and useful food facts.', 'kuchnia-twist'),
        'editor_public_email'   => 'contact@kuchniatwist.pl',
        'editor_business_email' => '',
        'editor_photo_id'       => 0,
        'adsense_client_id'     => '',
        'social_instagram_url'  => '',
        'social_facebook_url'   => '',
        'social_pinterest_url'  => '',
        'social_tiktok_url'     => '',
        'social_follow_label'   => __('Follow kuchniatwist', 'kuchnia-twist'),
    ];

    return wp_parse_args(get_option('kuchnia_twist_settings', []), $defaults);
}

function kuchnia_twist_adsense_client_id()
{
    $settings = kuchnia_twist_publication_settings();
    $client = trim((string) ($settings['adsense_client_id'] ?? ''));
    if ($client === '') {
        $env_client = getenv('ADSENSE_CLIENT_ID');
        if (is_string($env_client)) {
            $client = trim($env_client);
        }
    }

    if ($client === '') {
        return '';
    }

    return str_starts_with($client, 'ca-pub-') ? $client : '';
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
    ];

    $about = get_page_by_path('about');
    if ($about instanceof WP_Post) {
        $items[] = ['label' => __('About', 'kuchnia-twist'), 'url' => get_permalink($about)];
    }
    $contact = get_page_by_path('contact');
    if ($contact instanceof WP_Post) {
        $items[] = ['label' => __('Contact', 'kuchnia-twist'), 'url' => get_permalink($contact)];
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
        'terms'            => __('Terms', 'kuchnia-twist'),
        'advertising-disclosure' => __('Advertising', 'kuchnia-twist'),
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

function kuchnia_twist_trust_page_definitions()
{
    return [
        'about' => [
            'title'   => __('About', 'kuchnia-twist'),
            'excerpt' => __('Learn about kuchniatwist, the editorial desk, and the journal standards.', 'kuchnia-twist'),
        ],
        'contact' => [
            'title'   => __('Contact', 'kuchnia-twist'),
            'excerpt' => __('Reach the editor with recipe questions, corrections, or partnership inquiries.', 'kuchnia-twist'),
        ],
        'privacy-policy' => [
            'title'   => __('Privacy Policy', 'kuchnia-twist'),
            'excerpt' => __('How kuchniatwist handles data, cookies, and third-party services.', 'kuchnia-twist'),
        ],
        'cookie-policy' => [
            'title'   => __('Cookie Policy', 'kuchnia-twist'),
            'excerpt' => __('What cookies are used on the site and how to control them.', 'kuchnia-twist'),
        ],
        'editorial-policy' => [
            'title'   => __('Editorial Policy', 'kuchnia-twist'),
            'excerpt' => __('How recipes and food facts are written, reviewed, and updated.', 'kuchnia-twist'),
        ],
        'terms' => [
            'title'   => __('Terms', 'kuchnia-twist'),
            'excerpt' => __('Terms of use for the kuchniatwist journal and its content.', 'kuchnia-twist'),
        ],
        'advertising-disclosure' => [
            'title'   => __('Advertising Disclosure', 'kuchnia-twist'),
            'excerpt' => __('How advertising and sponsorships are handled at kuchniatwist.', 'kuchnia-twist'),
        ],
    ];
}

function kuchnia_twist_ensure_trust_pages()
{
    $definitions = kuchnia_twist_trust_page_definitions();
    if (!$definitions) {
        return;
    }

    $created_any = false;
    $author_id = 0;
    $default_user = kuchnia_twist_default_editor_user();
    if ($default_user instanceof WP_User) {
        $author_id = (int) $default_user->ID;
    }

    foreach ($definitions as $slug => $definition) {
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            continue;
        }

        $excerpt = trim((string) ($definition['excerpt'] ?? ''));
        $post_id = wp_insert_post([
            'post_title'     => $definition['title'],
            'post_name'      => $slug,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => $author_id,
            'post_excerpt'   => $excerpt,
            'post_content'   => '',
            'comment_status' => 'closed',
            'meta_input'     => $excerpt !== '' ? [
                'kuchnia_twist_seo_description' => $excerpt,
            ] : [],
        ], true);

        if (!is_wp_error($post_id) && $post_id > 0) {
            $created_any = true;
        }
    }

    if ($created_any || !get_option('kuchnia_twist_trust_pages_seeded')) {
        update_option('kuchnia_twist_trust_pages_seeded', 1);
    }
}

add_action('init', 'kuchnia_twist_ensure_trust_pages');

function kuchnia_twist_pillar_nav_items()
{
    $items = [];
    $pillars = [
        'recipes'      => __('Recipes', 'kuchnia-twist'),
        'food-facts'   => __('Food Facts', 'kuchnia-twist'),
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

    return $label !== '' ? $label : __('Follow kuchniatwist', 'kuchnia-twist');
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
        echo '<a class="social-links__item social-links__item--' . esc_attr($profile['slug']) . '" href="' . esc_url($profile['url']) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr($profile['label']) . '">';
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
            'label' => __('Email this post', 'kuchnia-twist'),
            'url'   => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url),
        ],
        [
            'slug'  => 'copy',
            'label' => __('Copy post link', 'kuchnia-twist'),
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
            echo '<button type="button" class="share-links__item share-links__item--copy" data-copy-link="' . esc_attr($link['url']) . '" aria-label="' . esc_attr($link['label']) . '">';
            echo '<span class="share-links__icon">' . kuchnia_twist_social_icon_svg('copy') . '</span>';
            echo '<span class="share-links__label">' . esc_html__('Copy link', 'kuchnia-twist') . '</span>';
            echo '</button>';
            continue;
        }

        $target = str_starts_with($link['url'], 'mailto:') ? '' : ' target="_blank" rel="noopener noreferrer"';
        echo '<a class="share-links__item share-links__item--' . esc_attr($link['slug']) . '" href="' . esc_url($link['url']) . '"' . $target . ' aria-label="' . esc_attr($link['label']) . '">';
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
    $legacy_default_bio = __('Independent home-cooking journal with recipes and food facts.', 'kuchnia-twist');
    $default_bio = __('Independent home-cooking journal focused on practical recipes and useful food facts.', 'kuchnia-twist');
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

    if ($bio === $legacy_default_bio) {
        $bio = $default_bio;
    }

    return [
        'name'           => $name !== '' ? $name : get_bloginfo('name'),
        'role'           => $role !== '' ? $role : __('Editorial desk', 'kuchnia-twist'),
        'bio'            => $bio !== '' ? $bio : $default_bio,
        'public_email'   => is_email($email) ? $email : '',
        'business_email' => is_email($business) ? $business : '',
        'photo_id'       => $photo_id,
        'photo_url'      => kuchnia_twist_editor_portrait_url(),
    ];
}

function kuchnia_twist_render_editor_portrait($alt = '', array $attributes = [])
{
    $defaults = [
        'class'    => 'author-card__image',
        'loading'  => 'lazy',
        'decoding' => 'async',
        'src'      => kuchnia_twist_editor_portrait_url(),
        'alt'      => $alt !== '' ? $alt : __('Anna Kowalska, editor at kuchniatwist', 'kuchnia-twist'),
    ];

    $attributes = wp_parse_args($attributes, $defaults);
    $html = '<img';

    foreach ($attributes as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $html .= sprintf(' %s="%s"', esc_attr((string) $name), esc_attr((string) $value));
    }

    $html .= '>';

    echo $html;
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

function kuchnia_twist_get_post_media_markup($post_id = 0, $size = 'kuchnia-twist-card', array $attrs = [])
{
    $post_id = $post_id ?: get_the_ID();

    if (!$post_id || !has_post_thumbnail($post_id)) {
        return '';
    }

    return (string) get_the_post_thumbnail(
        $post_id,
        $size,
        array_merge(
            [
                'loading'  => 'lazy',
                'decoding' => 'async',
            ],
            $attrs
        )
    );
}

function kuchnia_twist_render_post_card($post_id)
{
    $category     = kuchnia_twist_primary_category($post_id);
    $media_markup = kuchnia_twist_get_post_media_markup($post_id, 'kuchnia-twist-card');
    ?>
    <article class="feed-card<?php echo $media_markup === '' ? ' feed-card--text-only' : ''; ?>">
        <?php if ($media_markup !== '') : ?>
            <a class="feed-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
                <?php echo $media_markup; ?>
            </a>
        <?php endif; ?>
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
        $items[] = ['label' => __('Latest posts', 'kuchnia-twist'), 'url' => ''];
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

function kuchnia_twist_extract_page_label($content, $fallback = '')
{
    $content = (string) $content;
    if (preg_match('/<h([23])[^>]*>(.*?)<\/h\1>/is', $content, $matches)) {
        $heading = trim(wp_strip_all_tags((string) $matches[2]));
        $heading = preg_replace('/^[0-9]+\s*[:.)-]?\s*/', '', (string) $heading);
        if ($heading !== '') {
            return wp_trim_words($heading, 8, '...');
        }
    }

    if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $matches)) {
        $paragraph = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
        if ($paragraph !== '') {
            $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph];
            $lead = trim((string) ($sentences[0] ?? $paragraph));
            if ($lead !== '') {
                return wp_trim_words($lead, 8, '...');
            }
        }
    }

    $plaintext = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($content)));
    if ($plaintext !== '') {
        return wp_trim_words($plaintext, 8, '...');
    }

    return trim((string) $fallback);
}

function kuchnia_twist_extract_page_summary($content, $fallback = '')
{
    $content = (string) $content;

    if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $matches)) {
        $paragraph = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($matches[1] ?? ''))));
        if ($paragraph !== '') {
            $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph])));
            $summary = (string) ($sentences[1] ?? $sentences[0] ?? $paragraph);
            if ($summary !== '') {
                return wp_trim_words($summary, 18, '...');
            }
        }
    }

    $plaintext = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($content)));
    if ($plaintext !== '') {
        return wp_trim_words($plaintext, 18, '...');
    }

    return trim((string) $fallback);
}

function kuchnia_twist_prepare_article_content($post_id)
{
    global $page;

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [
            'content'         => '',
            'headings'        => [],
            'page_count'      => 1,
            'current_page'    => 1,
            'current_label'   => '',
            'current_summary' => '',
            'next_page_label' => '',
            'next_page_summary' => '',
            'remaining_pages' => [],
            'remaining_page_count' => 0,
            'final_page_label' => '',
            'final_page_summary' => '',
            'page_labels'     => [],
        ];
    }

    $raw_content = (string) $post->post_content;
    $raw_pages = preg_split('/\s*<!--nextpage-->\s*/i', $raw_content) ?: [$raw_content];
    $page_count = max(1, count($raw_pages));
    $current_page = max(1, min($page_count, (int) ($page ?: 1)));
    $current_index = $current_page - 1;
    $current_raw = (string) ($raw_pages[$current_index] ?? '');
    $next_raw = (string) ($raw_pages[$current_index + 1] ?? '');
    $stored_page_flow = kuchnia_twist_get_page_flow($post_id);
    $page_labels = [];
    foreach ($raw_pages as $index => $raw_page) {
        $stored_page = $stored_page_flow[$index] ?? [];
        $label = trim((string) ($stored_page['label'] ?? ''));
        if ($label === '') {
            $label = kuchnia_twist_extract_page_label($raw_page, sprintf(__('Page %d', 'kuchnia-twist'), $index + 1));
        }
        $summary = trim((string) ($stored_page['summary'] ?? ''));
        if ($summary === '') {
            $summary = kuchnia_twist_extract_page_summary($raw_page);
        }
        $page_labels[] = [
            'index'   => $index + 1,
            'label'   => $label,
            'summary' => $summary,
            'current' => ($index + 1) === $current_page,
        ];
    }
    $remaining_pages = array_values(array_filter(array_slice($page_labels, $current_index + 1), static function ($page_item): bool {
        return !empty($page_item['index']);
    }));
    $final_page = !empty($page_labels) ? (array) end($page_labels) : [];
    reset($page_labels);
    $content = apply_filters('the_content', $current_raw);
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
        'content'         => $content,
        'headings'        => $headings,
        'page_count'      => $page_count,
        'current_page'    => $current_page,
        'current_label'   => trim((string) ($page_labels[$current_index]['label'] ?? kuchnia_twist_extract_page_label($current_raw, get_the_title($post)))),
        'current_summary' => trim((string) ($page_labels[$current_index]['summary'] ?? kuchnia_twist_extract_page_summary($current_raw))),
        'next_page_label' => trim((string) ($page_labels[$current_index + 1]['label'] ?? kuchnia_twist_extract_page_label($next_raw))),
        'next_page_summary' => trim((string) ($page_labels[$current_index + 1]['summary'] ?? kuchnia_twist_extract_page_summary($next_raw))),
        'remaining_pages' => $remaining_pages,
        'remaining_page_count' => max(0, count($remaining_pages)),
        'final_page_label' => trim((string) ($final_page['label'] ?? '')),
        'final_page_summary' => trim((string) ($final_page['summary'] ?? '')),
        'page_labels'     => $page_labels,
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
        'eyebrow'     => __('Latest posts', 'kuchnia-twist'),
        'title'       => get_bloginfo('name'),
        'description' => __('New recipes and food facts from a home-cooking journal that values clarity over noise.', 'kuchnia-twist'),
    ];

    if (is_home()) {
        $context['eyebrow'] = __('Latest posts', 'kuchnia-twist');
        $context['title'] = __('The latest from kuchniatwist', 'kuchnia-twist');
        $context['description'] = __('The newest recipes and food facts from kuchniatwist, gathered in one clean feed.', 'kuchnia-twist');
    } elseif (is_search()) {
        $query_text = trim((string) get_search_query());
        $match_count = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
        $context['eyebrow'] = __('Search results', 'kuchnia-twist');
        $context['title'] = __('Search', 'kuchnia-twist');
        if ($query_text !== '') {
            if ($match_count > 0) {
                $context['description'] = sprintf(
                    _n('Showing %1$s result for "%2$s".', 'Showing %1$s results for "%2$s".', $match_count, 'kuchnia-twist'),
                    number_format_i18n($match_count),
                    $query_text
                );
            } else {
                $context['description'] = sprintf(__('No results yet for "%s". Try a broader ingredient, dish, or kitchen question.', 'kuchnia-twist'), $query_text);
            }
        } else {
            $context['description'] = __('Search by ingredient, dish, technique, or cooking question.', 'kuchnia-twist');
        }
    } elseif (is_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $context['eyebrow'] = __('Journal pillar', 'kuchnia-twist');
            $context['title'] = single_cat_title('', false);
            $term_description = wp_strip_all_tags(category_description($term));
            $term_seo = trim((string) get_term_meta($term->term_id, 'kuchnia_twist_seo_description', true));
            $context['description'] = $term_seo !== '' ? $term_seo : $term_description;

            $pillar_map = [
                'recipes' => [
                    'description' => __('Cookable recipes with clear steps, realistic timing, and dependable results.', 'kuchnia-twist'),
                ],
                'food-facts' => [
                    'description' => __('Short explainers that clear myths and sharpen everyday cooking decisions.', 'kuchnia-twist'),
                ],
            ];

            if ($context['description'] === '' && isset($pillar_map[$term->slug])) {
                $context['description'] = $pillar_map[$term->slug]['description'];
            }
            if ($context['description'] === '') {
                $context['description'] = __('A focused archive of posts gathered around one kitchen theme.', 'kuchnia-twist');
            }
        }
    } elseif (is_archive()) {
        $context['eyebrow'] = __('Archive', 'kuchnia-twist');
        $context['title'] = wp_strip_all_tags(get_the_archive_title());
        $context['description'] = wp_strip_all_tags(get_the_archive_description()) ?: __('A calm, readable index of the journal, grouped by time and theme.', 'kuchnia-twist');
    }

    return $context;
}

function kuchnia_twist_render_listing_empty_state(string $message, array $args = [])
{
    $eyebrow   = trim((string) ($args['eyebrow'] ?? __('Nothing here yet', 'kuchnia-twist')));
    $title     = trim((string) ($args['title'] ?? __('Keep exploring', 'kuchnia-twist')));
    $show_home = !empty($args['show_home']);
    ?>
    <div class="search-rescue search-rescue--text-only">
        <div class="search-rescue__copy">
            <?php if ($eyebrow !== '') : ?>
                <span class="eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <?php if ($title !== '') : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <p class="empty-state"><?php echo esc_html($message); ?></p>
            <div class="search-rescue__actions">
                <?php if ($show_home) : ?>
                    <a class="button button--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Go home', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <div class="rescue-links">
                    <?php kuchnia_twist_pillar_links(); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
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
            'direction' => __('Previous post', 'kuchnia-twist'),
            'title'     => get_the_title($previous),
            'url'       => get_permalink($previous),
        ];
    }

    if ($next instanceof WP_Post) {
        $links[] = [
            'direction' => __('Next post', 'kuchnia-twist'),
            'title'     => get_the_title($next),
            'url'       => get_permalink($next),
        ];
    }

    return $links;
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
            'intro' => __('kuchniatwist is a home-cooking journal focused on cookable recipes and clear food facts for everyday kitchens.', 'kuchnia-twist'),
            'body' => [
                __('kuchniatwist stays close to real kitchens: recipes you can cook and food facts you can use without guesswork.', 'kuchnia-twist'),
                __('Every post is edited to be practical and clear. Ingredients are chosen with intention, steps are written to avoid guesswork, and explanations stay focused on what helps you cook.', 'kuchnia-twist'),
                __('If something needs a correction or more detail, the contact page is always open.', 'kuchnia-twist'),
            ],
            'highlights' => [
                __('Recipes are written to be cooked, not just browsed.', 'kuchnia-twist'),
                __('Food facts stay specific, plainspoken, and useful.', 'kuchnia-twist'),
                __('Guidance favors clarity over trend-chasing.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('What readers will find here', 'kuchnia-twist'),
                    'body' => __('Every post sits in one of two clear categories so the promise is obvious before you click.', 'kuchnia-twist'),
                    'items' => [
                        __('Recipes written for real kitchens with clear steps and practical payoff.', 'kuchnia-twist'),
                        __('Food facts that explain techniques, ingredients, and kitchen myths without filler.', 'kuchnia-twist'),
                    ],
                ],
                [
                    'title' => __('How the journal stays accountable', 'kuchnia-twist'),
                    'body' => sprintf(__('Readers should be able to see who edits the journal, how to get in touch, and where standards and corrections are explained. That visible structure matters as much as the recipes themselves, and it starts with %s.', 'kuchnia-twist'), $editor['name']),
                ],
                [
                    'title' => __('Why the archive stays focused', 'kuchnia-twist'),
                    'body' => __('The archive stays tight so readers can trust the tone, structure, and usefulness of each post.', 'kuchnia-twist'),
                ],
            ],
        ],
        'contact' => [
            'eyebrow' => __('Reach the editor', 'kuchnia-twist'),
            'intro' => __('Use this page for recipe questions, corrections, sourcing notes, or partnership inquiries that fit the journal.', 'kuchnia-twist'),
            'body' => array_values(array_filter([
                __('We read every message and keep the feedback loop tight.', 'kuchnia-twist'),
                __('For recipe help, include the recipe name, the step you were on, and what went wrong so we can answer quickly.', 'kuchnia-twist'),
                __('For partnerships, share the brand, timeline, and how the collaboration fits a home-cooking journal.', 'kuchnia-twist'),
                __('Replies typically land within a few business days.', 'kuchnia-twist'),
                $public_email !== ''
                    ? sprintf(__('Email us directly at <a href="mailto:%1$s">%1$s</a>.', 'kuchnia-twist'), esc_attr($public_email))
                    : '',
            ], static function ($value) {
                return is_string($value) && trim($value) !== '';
            })),
            'highlights' => [
                __('Recipe questions and corrections are welcome.', 'kuchnia-twist'),
                __('The contact email stays public and easy to find.', 'kuchnia-twist'),
                __('Replies aim to arrive within a few business days.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Why readers write', 'kuchnia-twist'),
                    'body' => __('The most useful messages improve clarity, correct an error, or open a partnership idea that fits the journal without distracting from its food focus.', 'kuchnia-twist'),
                    'items' => [
                        __('Recipe clarification or kitchen troubleshooting.', 'kuchnia-twist'),
                        __('Corrections, sourcing notes, or factual updates.', 'kuchnia-twist'),
                        __('Partnership and sponsorship inquiries that fit the journal.', 'kuchnia-twist'),
                    ],
                ],
                [
                    'title' => __('How the contact routes work', 'kuchnia-twist'),
                    'body' => $business_email !== ''
                        ? sprintf(__('The journal keeps one public contact email at %1$s and a separate business route at %2$s, so readers and partners have a clear way to reach the right person.', 'kuchnia-twist'), $public_email, $business_email)
                        : sprintf(__('The journal keeps one public contact email at %s so readers always have a clear route back to the editor.', 'kuchnia-twist'), $public_email),
                ],
                [
                    'title' => __('Corrections matter', 'kuchnia-twist'),
                    'body' => __('A clear contact page makes it easier for readers to flag unclear steps, factual errors, or missing context before those problems linger in the archive.', 'kuchnia-twist'),
                ],
            ],
        ],
        'privacy-policy' => [
            'eyebrow' => __('Privacy policy', 'kuchnia-twist'),
            'intro' => __('This page explains the data handling involved in running the site today and what changes if ads or analytics are added later.', 'kuchnia-twist'),
            'body' => [
                __('kuchniatwist collects the minimum data needed to serve pages, prevent abuse, and respond to messages. That can include standard server logs and basic WordPress cookies.', 'kuchnia-twist'),
                __('Third-party vendors, including Google, use cookies to serve ads based on a user\'s prior visits to this website or other websites.', 'kuchnia-twist'),
                __("Google's advertising cookies enable Google and its partners to serve ads based on visits to this site and/or other sites on the Internet.", 'kuchnia-twist'),
                sprintf(
                    __('You can opt out of personalized advertising by visiting <a href="%1$s" target="_blank" rel="noopener">Ads Settings</a>, or opt out of some third-party vendors\' use of cookies for personalized advertising by visiting <a href="%2$s" target="_blank" rel="noopener">aboutads.info</a>.', 'kuchnia-twist'),
                    esc_url('https://adssettings.google.com'),
                    esc_url('https://www.aboutads.info/choices/')
                ),
                __('We do not sell personal information and we avoid unnecessary tracking tools beyond what is required for ads, security, and hosting.', 'kuchnia-twist'),
            ],
            'highlights' => [
                __('Basic hosting and security data may be processed to serve the site.', 'kuchnia-twist'),
                __('Ad vendors, including Google, may use cookies to serve or measure ads.', 'kuchnia-twist'),
                __('If the toolset changes, this page changes before those tools go live.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('What is in use now', 'kuchnia-twist'),
                    'body' => __('The site relies on ordinary hosting, security, and reader contact handling rather than on tracking-heavy marketing systems.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Why the page stays specific', 'kuchnia-twist'),
                    'body' => __('Privacy pages are most useful when they describe the tools actually in use, not every service a website might install someday.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('If the setup changes', 'kuchnia-twist'),
                    'body' => __('If newsletters, advertising, affiliate tools, or analytics are added later, this page should be updated before those services go live.', 'kuchnia-twist'),
                ],
            ],
        ],
        'cookie-policy' => [
            'eyebrow' => __('Cookie guide', 'kuchnia-twist'),
            'intro' => __('This page explains the limited cookie use connected to the site right now and what would change if the toolset expands later.', 'kuchnia-twist'),
            'body' => [
                __('Cookies are small files stored by your browser that help a site remember preferences and keep essential features working.', 'kuchnia-twist'),
                __('Right now, cookies are expected to come from WordPress itself (for example, comment form preferences) and from the hosting stack.', 'kuchnia-twist'),
                __('When ads are shown, third-party vendors including Google may set cookies to serve, personalize, or measure advertising.', 'kuchnia-twist'),
                __("Google's advertising cookies enable Google and its partners to serve ads based on visits to this site and/or other sites on the Internet.", 'kuchnia-twist'),
                sprintf(
                    __('You can opt out of personalized advertising in <a href="%1$s" target="_blank" rel="noopener">Ads Settings</a> or opt out of some third-party vendors\' cookies for personalized advertising by visiting <a href="%2$s" target="_blank" rel="noopener">aboutads.info</a>.', 'kuchnia-twist'),
                    esc_url('https://adssettings.google.com'),
                    esc_url('https://www.aboutads.info/choices/')
                ),
                __('You can also block or delete cookies in your browser settings, but some features may not work as intended.', 'kuchnia-twist'),
            ],
            'highlights' => [
                __('Only essential platform and hosting cookies are expected right now.', 'kuchnia-twist'),
                __('Ad vendors, including Google, may use cookies to serve or measure ads.', 'kuchnia-twist'),
                __('If tracking or ad technology is added later, this page is updated at the same time.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Where cookies may come from', 'kuchnia-twist'),
                    'body' => __('Right now, cookies are expected to come from WordPress itself and the hosting stack rather than from advertising or analytics networks.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Why the page stays simple', 'kuchnia-twist'),
                    'body' => __('Cookie language is most useful when it describes the real tools on the site instead of collapsing into generic legal filler.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('When to revisit it', 'kuchnia-twist'),
                    'body' => __('If analytics, ad tags, new plugins, or embedded social widgets are added, this page should be reviewed at the same time.', 'kuchnia-twist'),
                ],
            ],
        ],
        'editorial-policy' => [
            'eyebrow' => __('Editorial standards', 'kuchnia-twist'),
            'intro' => __('This page explains how the journal handles recipes, explainers, corrections, and commercial disclosure.', 'kuchnia-twist'),
            'body' => [
                __('Every post is edited for clarity, accuracy, and usefulness. We would rather publish fewer posts than let a confusing one through.', 'kuchnia-twist'),
                __('Recipes are tested and rewritten until the steps are unambiguous. Measurements, timing, and yields are updated when a better method is found.', 'kuchnia-twist'),
                __('Food facts are written to explain one idea at a time. We avoid medical claims and overstated certainty; when something changes, we update the post.', 'kuchnia-twist'),
                __('If a post includes sponsorship or affiliate links, it is clearly labeled.', 'kuchnia-twist'),
            ],
            'highlights' => [
                __('Recipes aim to be practical and reader-first.', 'kuchnia-twist'),
                __('Food facts stay careful, specific, and plainspoken.', 'kuchnia-twist'),
                __('Corrections and disclosures are handled clearly.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Recipe testing and clarity', 'kuchnia-twist'),
                    'body' => __('Recipe posts should be written so a home cook can follow them without guessing. If a method improves over time, the clearest version should replace the old one.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Food facts and sourcing', 'kuchnia-twist'),
                    'body' => __('Fact-led posts should avoid invented claims, shallow summary writing, and fake precision. If a point needs sourcing, explain it carefully and update it when necessary.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Corrections and updates', 'kuchnia-twist'),
                    'body' => __("If a recipe instruction is unclear or a factual statement needs revision, correct it promptly and keep the journal's standards visible through that process.", 'kuchnia-twist'),
                ],
            ],
        ],
        'terms' => [
            'eyebrow' => __('Terms of use', 'kuchnia-twist'),
            'intro' => __('These terms explain how the site can be used and what readers can expect when they visit kuchniatwist.', 'kuchnia-twist'),
            'body' => [
                __('By using this site, you agree to the terms below. If you do not agree, please do not use the site.', 'kuchnia-twist'),
                __('The content is provided for general cooking guidance and editorial information. It should not replace professional advice.', 'kuchnia-twist'),
                __('We may update these terms as the site evolves. Changes are posted on this page.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Use of content', 'kuchnia-twist'),
                    'body' => __('You may read, share, and reference the content for personal, non-commercial use. Republishing or scraping content without permission is not allowed.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Accuracy and changes', 'kuchnia-twist'),
                    'body' => __('We aim for clarity and accuracy, but recipes and articles can change as they are improved. You are responsible for your own cooking decisions.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Third-party links', 'kuchnia-twist'),
                    'body' => __('The site may link to third-party resources or include third-party advertising. We are not responsible for those external services.', 'kuchnia-twist'),
                ],
            ],
        ],
        'advertising-disclosure' => [
            'eyebrow' => __('Advertising disclosure', 'kuchnia-twist'),
            'intro' => __('This page explains how advertising and sponsorships are handled on kuchniatwist.', 'kuchnia-twist'),
            'body' => [
                __('kuchniatwist may display advertising to support the site. Ads are served by third-party partners and are kept separate from editorial decisions.', 'kuchnia-twist'),
                __('If a post includes sponsorships or affiliate links, it will be clearly labeled in the post.', 'kuchnia-twist'),
                __('We do not accept paid placements that change editorial standards or cooking guidance.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Editorial independence', 'kuchnia-twist'),
                    'body' => __('Editorial choices are made for reader usefulness first. Advertising does not decide what we publish.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Sponsored or affiliate content', 'kuchnia-twist'),
                    'body' => __('If a post includes sponsored content or affiliate links, it will be disclosed clearly in the post.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Questions', 'kuchnia-twist'),
                    'body' => __('If you have questions about advertising or partnerships, contact the editor for details.', 'kuchnia-twist'),
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
        'kuchniatwist is a food journal built around recipes',
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
            $make_link('editorial-policy', __('Read the Editorial Policy', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
        'contact' => [
            $make_link('about', __('About the journal', 'kuchnia-twist')),
            $make_link('editorial-policy', __('Review editorial standards', 'kuchnia-twist')),
        ],
        'privacy-policy' => [
            $make_link('cookie-policy', __('Review cookie use', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
        'cookie-policy' => [
            $make_link('privacy-policy', __('Review the privacy policy', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
        'editorial-policy' => [
            $make_link('about', __('About the journal', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
        'terms' => [
            $make_link('privacy-policy', __('Review the privacy policy', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
        'advertising-disclosure' => [
            $make_link('editorial-policy', __('Review editorial standards', 'kuchnia-twist')),
            $make_link('contact', __('Contact the editor', 'kuchnia-twist')),
        ],
    ];

    return array_values(array_filter($map[$slug] ?? [$make_link('home', __('Back to the homepage', 'kuchnia-twist'))]));
}

function kuchnia_twist_render_browser_chrome_meta()
{
    echo '<meta name="theme-color" content="#f6f0e8">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    echo '<meta name="p:domain_verify" content="740e0aa93b5470e329fc2042a009d4cf">' . "\n";
}

add_action('wp_head', function () {
    if (has_site_icon()) {
        kuchnia_twist_render_browser_chrome_meta();
        return;
    }

    echo '<link rel="icon" href="' . esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')) . '" type="image/svg+xml">';
    echo "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')) . '">' . "\n";
    kuchnia_twist_render_browser_chrome_meta();
}, 1);

function kuchnia_twist_schema_logo_url()
{
    if (function_exists('has_site_icon') && has_site_icon()) {
        $site_icon = get_site_icon_url(512);
        if (is_string($site_icon) && $site_icon !== '') {
            return $site_icon;
        }
    }

    $custom_logo_id = (int) get_theme_mod('custom_logo');
    if ($custom_logo_id > 0) {
        $custom_logo = wp_get_attachment_image_url($custom_logo_id, 'full');
        if (is_string($custom_logo) && $custom_logo !== '') {
            return $custom_logo;
        }
    }

    return '';
}

function kuchnia_twist_schema_image_object_from_attachment($attachment_id, $size = 'full', $fallback_alt = '')
{
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return null;
    }

    $image_data = wp_get_attachment_image_src($attachment_id, $size);
    if (!is_array($image_data) || empty($image_data[0])) {
        return null;
    }

    $image = [
        '@type' => 'ImageObject',
        'url'   => (string) $image_data[0],
    ];

    $width = (int) ($image_data[1] ?? 0);
    $height = (int) ($image_data[2] ?? 0);
    if ($width > 0) {
        $image['width'] = $width;
    }
    if ($height > 0) {
        $image['height'] = $height;
    }

    $mime_type = (string) get_post_mime_type($attachment_id);
    if ($mime_type !== '') {
        $image['encodingFormat'] = $mime_type;
    }

    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    if ($alt === '') {
        $alt = trim((string) $fallback_alt);
    }
    if ($alt !== '') {
        $image['caption'] = $alt;
    }

    return $image;
}

function kuchnia_twist_schema_image_object_from_url($url, $width = 0, $height = 0, $caption = '')
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    $image = [
        '@type' => 'ImageObject',
        'url'   => $url,
    ];

    if ((int) $width > 0) {
        $image['width'] = (int) $width;
    }

    if ((int) $height > 0) {
        $image['height'] = (int) $height;
    }

    if ($caption !== '') {
        $image['caption'] = $caption;
    }

    return $image;
}

function kuchnia_twist_schema_publisher()
{
    $profile = kuchnia_twist_editor_profile();
    $publisher = [
        '@type' => 'Organization',
        '@id'   => trailingslashit(home_url('/')) . '#/schema/publisher',
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];

    $description = trim((string) kuchnia_twist_site_summary());
    if ($description !== '') {
        $publisher['description'] = $description;
    }

    $logo_object = null;
    $site_icon_id = function_exists('has_site_icon') && has_site_icon() ? (int) get_option('site_icon') : 0;
    if ($site_icon_id > 0) {
        $logo_object = kuchnia_twist_schema_image_object_from_attachment($site_icon_id, 'full', get_bloginfo('name'));
    }
    if (!$logo_object) {
        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id > 0) {
            $logo_object = kuchnia_twist_schema_image_object_from_attachment($custom_logo_id, 'full', get_bloginfo('name'));
        }
    }

    if ($logo_object) {
        $publisher['logo'] = $logo_object;
    } else {
        $logo_url = kuchnia_twist_schema_logo_url();
        if ($logo_url !== '') {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo_url,
            ];
        }
    }

    if (!empty($profile['public_email'])) {
        $publisher['email'] = $profile['public_email'];
    }

    $contact_points = [];
    if (!empty($profile['public_email'])) {
        $contact_points[] = [
            '@type'             => 'ContactPoint',
            'contactType'       => 'editorial',
            'email'             => $profile['public_email'],
            'availableLanguage' => 'en',
        ];
    }
    if (!empty($profile['business_email'])) {
        $contact_points[] = [
            '@type'             => 'ContactPoint',
            'contactType'       => 'business',
            'email'             => $profile['business_email'],
            'availableLanguage' => 'en',
        ];
    }
    if ($contact_points) {
        $publisher['contactPoint'] = $contact_points;
    }

    $same_as = array_values(array_map(static function ($profile) {
        return $profile['url'];
    }, kuchnia_twist_social_profiles()));

    if ($same_as) {
        $publisher['sameAs'] = $same_as;
    }

    return $publisher;
}

function kuchnia_twist_schema_author()
{
    $profile = kuchnia_twist_editor_profile();
    $site_name = trim((string) get_bloginfo('name'));
    $author_name = trim((string) ($profile['name'] ?? ''));
    $about_page = get_page_by_path('about');
    $about_url = $about_page instanceof WP_Post ? get_permalink($about_page) : home_url('/');
    $photo_id = !empty($profile['photo_id']) ? (int) $profile['photo_id'] : 0;
    $photo_url = trim((string) ($profile['photo_url'] ?? ''));

    if ($author_name === '' || strtolower($author_name) === strtolower($site_name)) {
        return [
            '@type' => 'Organization',
            '@id'   => trailingslashit(home_url('/')) . '#/schema/publisher',
            'name'  => $site_name,
            'url'   => home_url('/'),
        ];
    }

    $author = [
        '@type' => 'Person',
        '@id'   => trailingslashit(home_url('/')) . '#/schema/editor',
        'name'  => $author_name,
        'url'   => $about_url,
    ];

    if (!empty($profile['role'])) {
        $author['jobTitle'] = $profile['role'];
    }

    if (!empty($profile['public_email'])) {
        $author['email'] = $profile['public_email'];
    }

    if ($photo_id > 0) {
        $author_image = kuchnia_twist_schema_image_object_from_attachment($photo_id, 'full', $author_name);
        if ($author_image) {
            $author['image'] = $author_image;
        }
    } elseif ($photo_url !== '') {
        $author_image = kuchnia_twist_schema_image_object_from_url($photo_url, 240, 240, $author_name);
        if ($author_image) {
            $author['image'] = $author_image;
        }
    }

    $same_as = array_values(array_map(static function ($profile) {
        return $profile['url'];
    }, kuchnia_twist_social_profiles()));

    if ($same_as) {
        $author['sameAs'] = $same_as;
    }

    return $author;
}

function kuchnia_twist_schema_website()
{
    $website = [
        '@type'     => 'WebSite',
        '@id'       => trailingslashit(home_url('/')) . '#/schema/website',
        'url'       => home_url('/'),
        'name'      => get_bloginfo('name'),
        'inLanguage'=> str_replace('_', '-', get_locale()),
        'publisher' => ['@id' => trailingslashit(home_url('/')) . '#/schema/publisher'],
    ];

    $description = trim((string) kuchnia_twist_meta_description());
    if ($description !== '') {
        $website['description'] = $description;
    }

    $website['potentialAction'] = [
        '@type'       => 'SearchAction',
        'target'      => add_query_arg('s', '{search_term_string}', home_url('/')),
        'query-input' => 'required name=search_term_string',
    ];

    return $website;
}

function kuchnia_twist_schema_breadcrumbs($post = null)
{
    $items = kuchnia_twist_get_breadcrumb_items($post);
    if (count($items) < 2) {
        return null;
    }

    $list_items = [];

    foreach ($items as $index => $item) {
        $entry = [
            '@type'    => 'ListItem',
            'position' => $index + 1,
            'name'     => $item['label'],
        ];

        if (!empty($item['url'])) {
            $entry['item'] = $item['url'];
        }

        $list_items[] = $entry;
    }

    return [
        '@type'           => 'BreadcrumbList',
        '@id'             => trailingslashit(home_url('/')) . '#/schema/breadcrumbs',
        'itemListElement' => $list_items,
    ];
}

function kuchnia_twist_schema_page_entity()
{
    if (is_singular('post')) {
        $post_id      = get_the_ID();
        $recipe_data  = kuchnia_twist_get_recipe_data($post_id);
        $content_type = get_post_meta($post_id, 'kuchnia_twist_content_type', true);
        $image_id     = (int) get_post_thumbnail_id($post_id);
        $description  = trim((string) get_post_meta($post_id, 'kuchnia_twist_seo_description', true));

        if ($description === '') {
            $description = trim((string) get_the_excerpt($post_id));
        }

        $common = [
            '@id'              => trailingslashit(get_permalink($post_id)) . '#/schema/main',
            'name'             => get_the_title($post_id),
            'headline'         => get_the_title($post_id),
            'description'      => $description,
            'datePublished'    => get_the_date(DATE_W3C, $post_id),
            'dateModified'     => get_the_modified_date(DATE_W3C, $post_id),
            'mainEntityOfPage' => get_permalink($post_id),
            'author'           => kuchnia_twist_schema_author(),
            'publisher'        => ['@id' => trailingslashit(home_url('/')) . '#/schema/publisher'],
            'isPartOf'         => ['@id' => trailingslashit(home_url('/')) . '#/schema/website'],
        ];

        if ($image_id > 0) {
            $image_object = kuchnia_twist_schema_image_object_from_attachment($image_id, 'full', get_the_title($post_id));
            if ($image_object) {
                $common['image'] = [$image_object];
            }
        }

        $category = kuchnia_twist_primary_category($post_id);
        if ($category instanceof WP_Term) {
            $common['articleSection'] = $category->name;
        }

        if ($content_type === 'recipe' && !empty($recipe_data)) {
            $common['@type'] = 'Recipe';
            $common['recipeIngredient'] = $recipe_data['ingredients'] ?? [];
            $common['recipeInstructions'] = array_map(static function ($step) {
                return ['@type' => 'HowToStep', 'text' => $step];
            }, $recipe_data['instructions'] ?? []);
            if (!empty($recipe_data['prep_time'])) {
                $common['prepTime'] = $recipe_data['prep_time'];
            }
            if (!empty($recipe_data['cook_time'])) {
                $common['cookTime'] = $recipe_data['cook_time'];
            }
            if (!empty($recipe_data['total_time'])) {
                $common['totalTime'] = $recipe_data['total_time'];
            }
            if (!empty($recipe_data['yield'])) {
                $common['recipeYield'] = $recipe_data['yield'];
            }
            if ($category instanceof WP_Term) {
                $common['recipeCategory'] = $category->name;
            }

            return $common;
        }

        $common['@type'] = 'Article';
        return $common;
    }

    if (is_page()) {
        $post_id = get_the_ID();
        $page_slug = (string) get_post_field('post_name', $post_id);
        $page_type_map = [
            'about'   => 'AboutPage',
            'contact' => 'ContactPage',
        ];
        $entity = [
            '@type'            => $page_type_map[$page_slug] ?? 'WebPage',
            '@id'              => trailingslashit(get_permalink($post_id)) . '#/schema/page',
            'name'             => get_the_title($post_id),
            'url'              => get_permalink($post_id),
            'description'      => kuchnia_twist_meta_description(),
            'isPartOf'         => ['@id' => trailingslashit(home_url('/')) . '#/schema/website'],
            'publisher'        => ['@id' => trailingslashit(home_url('/')) . '#/schema/publisher'],
            'author'           => kuchnia_twist_schema_author(),
            'mainEntityOfPage' => get_permalink($post_id),
        ];

        $image_id = (int) get_post_thumbnail_id($post_id);
        if ($image_id > 0) {
            $image_object = kuchnia_twist_schema_image_object_from_attachment($image_id, 'full', get_the_title($post_id));
            if ($image_object) {
                $entity['image'] = [$image_object];
            }
        }

        return $entity;
    }

    if (is_front_page()) {
        return [
            '@type'            => 'WebPage',
            '@id'              => trailingslashit(home_url('/')) . '#/schema/home',
            'name'             => get_bloginfo('name'),
            'url'              => home_url('/'),
            'description'      => kuchnia_twist_meta_description(),
            'isPartOf'         => ['@id' => trailingslashit(home_url('/')) . '#/schema/website'],
            'publisher'        => ['@id' => trailingslashit(home_url('/')) . '#/schema/publisher'],
            'mainEntityOfPage' => home_url('/'),
        ];
    }

    if (is_home() || is_archive() || is_search()) {
        $canonical = trim((string) kuchnia_twist_meta_canonical_url());
        return [
            '@type'            => is_search() ? 'SearchResultsPage' : 'CollectionPage',
            '@id'              => $canonical . '#/schema/collection',
            'name'             => wp_get_document_title(),
            'url'              => $canonical,
            'description'      => kuchnia_twist_meta_description(),
            'isPartOf'         => ['@id' => trailingslashit(home_url('/')) . '#/schema/website'],
            'publisher'        => ['@id' => trailingslashit(home_url('/')) . '#/schema/publisher'],
            'mainEntityOfPage' => $canonical,
        ];
    }

    return null;
}

add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    $graph = [
        kuchnia_twist_schema_publisher(),
        kuchnia_twist_schema_website(),
    ];

    $breadcrumbs = kuchnia_twist_schema_breadcrumbs();
    if (is_array($breadcrumbs)) {
        $graph[] = $breadcrumbs;
    }

    $page_entity = kuchnia_twist_schema_page_entity();
    if (is_array($page_entity)) {
        $graph[] = $page_entity;
    }

    $graph = array_values(array_filter($graph));
    if (!$graph) {
        return;
    }

    echo '<script type="application/ld+json">' . wp_json_encode([
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ]) . '</script>';
}, 20);

