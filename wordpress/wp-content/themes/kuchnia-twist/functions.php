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

function kuchnia_twist_public_contact_email()
{
    $email = sanitize_email((string) get_option('admin_email'));
    return is_email($email) ? $email : '';
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
