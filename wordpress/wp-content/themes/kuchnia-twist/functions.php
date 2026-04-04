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
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap',
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

function kuchnia_twist_pillar_links()
{
    $pillars = [
        'recipes' => __('Recipes', 'kuchnia-twist'),
        'food-facts' => __('Food Facts', 'kuchnia-twist'),
        'food-stories' => __('Food Stories', 'kuchnia-twist'),
    ];

    foreach ($pillars as $slug => $label) {
        $term = get_category_by_slug($slug);
        if ($term instanceof WP_Term) {
            printf('<a href="%s">%s</a>', esc_url(get_category_link($term)), esc_html($label));
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
    <article class="story-card">
        <a class="story-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
            <?php if (has_post_thumbnail($post_id)) : ?>
                <?php echo get_the_post_thumbnail($post_id, 'kuchnia-twist-card'); ?>
            <?php else : ?>
                <?php kuchnia_twist_render_media_placeholder($placeholder_context, __('Fresh from the kitchen journal', 'kuchnia-twist')); ?>
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
    $email = sanitize_email((string) get_option('admin_email'));
    return is_email($email) ? $email : '';
}

function kuchnia_twist_archive_context()
{
    global $wp_query;

    $context = [
        'eyebrow'     => __('Latest articles', 'kuchnia-twist'),
        'title'       => get_bloginfo('name'),
        'description' => __('Fresh writing from the kitchen journal, shaped to be useful, memorable, and easy to trust.', 'kuchnia-twist'),
        'art'         => kuchnia_twist_fallback_media_url('journal'),
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
        $context['art'] = kuchnia_twist_fallback_media_url('journal');
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
        $context['art'] = kuchnia_twist_asset_url('assets/media-search.svg');
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
            $context['art'] = kuchnia_twist_fallback_media_url($term->slug);

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
        $context['art'] = kuchnia_twist_fallback_media_url('journal');
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
        'title'   => __('Each article should feel specific, readable, and genuinely useful.', 'kuchnia-twist'),
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
                : __('The first strong article will set the tone for the archive.', 'kuchnia-twist'),
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
            'art'         => kuchnia_twist_fallback_media_url($slug),
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
    $published_posts = wp_count_posts('post');
    $published_count = $published_posts instanceof stdClass ? (int) ($published_posts->publish ?? 0) : 0;
    $latest_post     = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    $latest_story    = $latest_post ? get_the_title($latest_post[0]) : __('The first feature is being prepared.', 'kuchnia-twist');
    $public_email    = kuchnia_twist_public_contact_email();

    return [
        'eyebrow' => __('From the editorial desk', 'kuchnia-twist'),
        'title'   => __('The publication should feel looked after, not merely decorated.', 'kuchnia-twist'),
        'body'    => __('Kuchnia Twist is being shaped as a small food journal with visible standards, a steady archive rhythm, and enough structure that readers can quickly understand what kind of site they have landed on.', 'kuchnia-twist'),
        'art'     => kuchnia_twist_fallback_media_url('desk'),
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
                'body'  => __('Questions, corrections, and partnership enquiries should feel welcome instead of hidden.', 'kuchnia-twist'),
            ],
            [
                'label' => __('What this journal favors', 'kuchnia-twist'),
                'title' => __('Cookable recipes, useful explainers, and slower story-led pieces', 'kuchnia-twist'),
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

    if (!$post instanceof WP_Post || $post->post_type !== 'page') {
        return null;
    }

    $profiles = [
        'about' => [
            'eyebrow' => __('About the journal', 'kuchnia-twist'),
            'intro' => __('Kuchnia Twist is meant to feel more like a calm food publication than a disposable content site. The goal is useful, original food writing arranged around three pillars: recipes, food facts, and story-led features.', 'kuchnia-twist'),
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
                    'body' => __('Trust grows when the publication feels consistent. That means clearer navigation, real policy pages, stronger single-article pages, and a tone that sounds human.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('How to make this page truly yours', 'kuchnia-twist'),
                    'body' => __('Add your personal story, kitchen point of view, recipe philosophy, and why this publication exists. That turns this page from a starter framework into a real editorial introduction.', 'kuchnia-twist'),
                ],
            ],
        ],
        'contact' => [
            'eyebrow' => __('Reach the editorial desk', 'kuchnia-twist'),
            'intro' => __('This page should make it easy for readers, brands, and collaborators to know when to contact the site and what kind of response to expect.', 'kuchnia-twist'),
            'highlights' => [
                __('Use this page for recipe questions, corrections, and brand enquiries.', 'kuchnia-twist'),
                __('Keep one visible editorial email here before applying for monetization.', 'kuchnia-twist'),
                __('Set expectations clearly so replies feel professional and reliable.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Good reasons to get in touch', 'kuchnia-twist'),
                    'body' => __('Readers should know that thoughtful outreach is welcome, especially when it improves the publication or opens a relevant collaboration.', 'kuchnia-twist'),
                    'items' => [
                        __('Recipe clarification or kitchen troubleshooting.', 'kuchnia-twist'),
                        __('Corrections, sourcing notes, or factual updates.', 'kuchnia-twist'),
                        __('Partnership and sponsorship enquiries that fit the publication.', 'kuchnia-twist'),
                    ],
                ],
                [
                    'title' => __('What to add here in wp-admin', 'kuchnia-twist'),
                    'body' => __('Add your preferred business or editorial email, optional social links, and any response-time note you want readers to see.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('A simple professional standard', 'kuchnia-twist'),
                    'body' => __('Even a small independent site feels more credible when the contact page is explicit, current, and easy to scan on mobile.', 'kuchnia-twist'),
                ],
            ],
        ],
        'privacy-policy' => [
            'eyebrow' => __('Privacy starter', 'kuchnia-twist'),
            'intro' => __('This page should explain what data the site may collect, why it is collected, and how readers can contact you about privacy-related questions.', 'kuchnia-twist'),
            'highlights' => [
                __('Explain what information is collected and why.', 'kuchnia-twist'),
                __('Mention analytics, forms, ad tools, and third-party embeds if you use them.', 'kuchnia-twist'),
                __('Review this page before using ads or monetized tools.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('What a complete privacy page should cover', 'kuchnia-twist'),
                    'body' => __('A strong privacy page usually covers server logs, analytics, cookies, embedded media, contact forms, and any ad or affiliate systems connected to the site.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('Why this matters for trust', 'kuchnia-twist'),
                    'body' => __('Readers and advertising platforms both expect policy pages to be easy to find and specific enough to reflect the tools actually used on the site.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('What to customise next', 'kuchnia-twist'),
                    'body' => __('Replace starter wording with your real hosting, analytics, consent, email, and advertising details before treating this page as final.', 'kuchnia-twist'),
                ],
            ],
        ],
        'cookie-policy' => [
            'eyebrow' => __('Cookie guide', 'kuchnia-twist'),
            'intro' => __('Use this page to explain what cookies or similar technologies appear on the site, what they do, and how readers can manage their preferences.', 'kuchnia-twist'),
            'highlights' => [
                __('Clarify whether cookies are essential, analytical, or advertising-related.', 'kuchnia-twist'),
                __('Keep cookie and consent language simple enough for normal readers.', 'kuchnia-twist'),
                __('Review this page any time you add tracking or ad technology.', 'kuchnia-twist'),
            ],
            'sections' => [
                [
                    'title' => __('Where cookies may come from', 'kuchnia-twist'),
                    'body' => __('Cookies can come from WordPress itself, analytics tools, consent banners, embedded media, or future advertising systems.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('How to keep this page useful', 'kuchnia-twist'),
                    'body' => __('Describe the real tools in use on the site, avoid vague legal filler, and make it obvious how visitors can update consent choices.', 'kuchnia-twist'),
                ],
                [
                    'title' => __('When to revisit it', 'kuchnia-twist'),
                    'body' => __('If you install analytics, ad tags, new plugins, or embedded social widgets, this page should be reviewed at the same time.', 'kuchnia-twist'),
                ],
            ],
        ],
        'editorial-policy' => [
            'eyebrow' => __('Editorial standards', 'kuchnia-twist'),
            'intro' => __('An editorial policy gives the publication a clearer backbone. It explains how recipes are developed, how facts are handled, and how corrections are approached.', 'kuchnia-twist'),
            'highlights' => [
                __('Recipe work should aim to be practical and reader-first.', 'kuchnia-twist'),
                __('Food facts should be handled with care and clarity.', 'kuchnia-twist'),
                __('Corrections should be visible and treated seriously.', 'kuchnia-twist'),
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
            'value'  => $public_email !== '' ? antispambot($public_email) : __('Add admin email', 'kuchnia-twist'),
            'detail' => $public_email !== ''
                ? __('A visible response channel helps the publication feel real, maintained, and reachable.', 'kuchnia-twist')
                : __('Add a public email in Settings > General so this page does not feel unfinished.', 'kuchnia-twist'),
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
