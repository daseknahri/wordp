<?php

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    global $page, $numpages, $multipage;
    $post_id            = get_the_ID();
    $content_type       = (string) get_post_meta($post_id, 'kuchnia_twist_content_type', true);
    $content_type       = $content_type !== '' ? $content_type : 'recipe';
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
    $story_links        = kuchnia_twist_adjacent_story_links($post_id);
    $has_social         = kuchnia_twist_has_social_profiles();
    $is_recipe          = $content_type === 'recipe' && !empty($recipe_data);
    $current_page       = max(1, (int) ($article['current_page'] ?? ($page ?? 1)));
    $total_pages        = max(1, (int) ($article['page_count'] ?? ($numpages ?? 1)));
    $is_multipage       = $total_pages > 1;
    $is_final_page      = $current_page >= $total_pages;
    $page_progress      = $is_multipage ? max(0, min(100, (int) round(($current_page / $total_pages) * 100))) : 100;
    $current_page_summary = trim((string) ($article['current_summary'] ?? ''));
    $page_labels          = is_array($article['page_labels'] ?? null) ? $article['page_labels'] : [];
    $previous_page_label  = trim((string) ($page_labels[$current_page - 2]['label'] ?? ''));
    $next_page_label      = trim((string) ($article['next_page_label'] ?? ''));
    $recipe_jump_url      = '';

    if ($is_recipe) {
        if ($is_multipage) {
            $recipe_page_link = _wp_link_page($total_pages);
            if (preg_match('/href="([^"]+)"/', (string) $recipe_page_link, $matches)) {
                $recipe_jump_url = trim((string) ($matches[1] ?? ''));
            }
        }

        if ($recipe_jump_url === '') {
            $recipe_jump_url = '#recipe-card';
        } else {
            $recipe_jump_url .= '#recipe-card';
        }
    }
    ?>
    <article class="article-shell single-story single-story--<?php echo esc_attr(sanitize_html_class($content_type)); ?>">
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
                <?php if ($is_recipe && $recipe_jump_url !== '') : ?>
                    <a class="button button--primary" href="<?php echo esc_url($recipe_jump_url); ?>"><?php esc_html_e('Jump to recipe', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
            </div>

            <div class="article-utility__share">
                <?php kuchnia_twist_render_share_links($post_id, 'share-links--inline'); ?>
                <?php if ($has_social) : ?>
                    <?php kuchnia_twist_render_social_links('social-links--inline'); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_multipage) : ?>
            <?php
            set_query_var('kt_article_progress', [
                'is_multipage' => $is_multipage,
                'current_page' => $current_page,
                'total_pages'  => $total_pages,
                'page_progress'=> $page_progress,
            ]);
            get_template_part('template-parts/single/progress');
            ?>
        <?php endif; ?>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="article-hero__media">
                <?php the_post_thumbnail('kuchnia-twist-hero', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?>
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

                <?php
                set_query_var('kt_single_type', [
                    'content_type'         => $content_type,
                    'is_final_page'        => $is_final_page,
                    'is_recipe'            => $is_recipe,
                    'recipe_data'          => $recipe_data,
                    'current_page_summary' => $current_page_summary,
                    'is_multipage'         => $is_multipage,
                ]);
                get_template_part('template-parts/single/type', $content_type);
                ?>

                <?php if ($is_multipage) : ?>
                    <?php
                    set_query_var('kt_article_pagination', [
                        'is_multipage'        => $is_multipage,
                        'current_page'        => $current_page,
                        'total_pages'         => $total_pages,
                        'is_recipe'           => $is_recipe,
                        'previous_page_label' => $previous_page_label,
                        'next_page_label'     => $next_page_label,
                    ]);
                    get_template_part('template-parts/single/pagination');
                    ?>
                <?php endif; ?>

                <?php
                set_query_var('kt_article_support', [
                    'is_final_page'   => $is_final_page,
                    'editor_profile'  => $editor_profile,
                    'about_page'      => $about_page,
                    'contact_page'    => $contact_page,
                    'editorial_policy'=> $editorial_policy,
                ]);
                get_template_part('template-parts/single/support');
                ?>
            </div>

            <aside class="article-layout__rail single-story__rail">
                <section class="article-rail">
                    <span class="eyebrow"><?php esc_html_e('At a glance', 'kuchnia-twist'); ?></span>
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

        <?php
        set_query_var('kt_article_related', [
            'is_final_page' => $is_final_page,
            'post_id'       => $post_id,
            'category'      => $category,
            'story_links'   => $story_links,
        ]);
        get_template_part('template-parts/single/related');
        ?>
    </article>
<?php endwhile; ?>

<?php
get_footer();
