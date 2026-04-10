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
    $current_page_label = trim((string) ($article['current_label'] ?? ''));
    $next_page_label    = trim((string) ($article['next_page_label'] ?? ''));
    $next_page_is_final = $is_multipage && ($current_page + 1) === $total_pages;
    $page_progress      = $is_multipage ? max(0, min(100, (int) round(($current_page / $total_pages) * 100))) : 100;
    $page_labels        = !empty($article['page_labels']) && is_array($article['page_labels']) ? $article['page_labels'] : [];
    $remaining_pages    = !empty($article['remaining_pages']) && is_array($article['remaining_pages']) ? $article['remaining_pages'] : [];
    $remaining_count    = max(0, (int) ($article['remaining_page_count'] ?? count($remaining_pages)));
    $next_page_summary  = trim((string) ($article['next_page_summary'] ?? ''));
    $current_page_summary = trim((string) ($article['current_summary'] ?? ''));
    $final_page_label   = trim((string) ($article['final_page_label'] ?? ''));
    if ($next_page_label === '') {
        if ($is_recipe && $next_page_is_final) {
            $next_page_label = __('Recipe card and final steps', 'kuchnia-twist');
        } elseif ($current_page < $total_pages) {
            $next_page_label = __('Next section', 'kuchnia-twist');
        }
    }
    if ($is_multipage && !$is_final_page && $next_page_summary === '') {
        if ($is_recipe && $next_page_is_final) {
            $next_page_summary = __('The final page unlocks the full recipe card, ingredients, and method.', 'kuchnia-twist');
        } elseif ($next_page_is_final) {
            $next_page_summary = __('The final page closes the article and keeps the reading path moving with related links.', 'kuchnia-twist');
        } else {
            $next_page_summary = __('Keep moving through the article to reach the next section without breaking the reading flow.', 'kuchnia-twist');
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
                <?php if ($is_recipe && $is_final_page) : ?>
                    <a class="button button--primary" href="#recipe-card"><?php esc_html_e('Jump to recipe', 'kuchnia-twist'); ?></a>
                <?php endif; ?>
                <button class="button button--ghost" type="button" data-search-toggle><?php esc_html_e('Search the journal', 'kuchnia-twist'); ?></button>
            </div>

            <div class="article-utility__share">
                <?php kuchnia_twist_render_share_links($post_id, 'share-links--inline'); ?>
                <?php if ($has_social) : ?>
                    <?php kuchnia_twist_render_social_links('social-links--inline'); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_multipage) : ?>
            <section class="article-progress" aria-label="<?php esc_attr_e('Article progress', 'kuchnia-twist'); ?>">
                <div class="article-progress__head">
                    <div>
                        <span class="eyebrow"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'kuchnia-twist'), $current_page, $total_pages)); ?></span>
                        <?php if ($current_page_label !== '') : ?>
                            <strong><?php echo esc_html($current_page_label); ?></strong>
                        <?php endif; ?>
                        <?php if ($current_page_summary !== '') : ?>
                            <p><?php echo esc_html($current_page_summary); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_final_page && $next_page_label !== '') : ?>
                        <p>
                            <?php
                            echo esc_html(
                                $remaining_count > 1
                                    ? sprintf(__('Up next: %1$s, then %2$d more pages.', 'kuchnia-twist'), $next_page_label, $remaining_count - 1)
                                    : sprintf(__('Up next: %s', 'kuchnia-twist'), $next_page_label)
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="article-progress__track" aria-hidden="true">
                    <span style="width: <?php echo esc_attr((string) $page_progress); ?>%;"></span>
                </div>
                <?php if ($page_labels) : ?>
                    <div class="article-progress__pages" aria-label="<?php esc_attr_e('Article pages', 'kuchnia-twist'); ?>">
                        <?php foreach ($page_labels as $page_item) : ?>
                            <?php
                            $page_index = (int) ($page_item['index'] ?? 0);
                            $page_label = trim((string) ($page_item['label'] ?? ''));
                            $is_current_page = !empty($page_item['current']);
                            if ($page_index < 1) {
                                continue;
                            }
                            ?>
                            <?php if ($is_current_page) : ?>
                                <span class="article-progress__page is-current" aria-current="page">
                                        <strong><?php echo esc_html(sprintf(__('Page %d', 'kuchnia-twist'), $page_index)); ?></strong>
                                        <?php if ($page_label !== '') : ?>
                                            <span><?php echo esc_html($page_label); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($page_item['summary'])) : ?>
                                            <small><?php echo esc_html((string) $page_item['summary']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                <?php else : ?>
                                    <?php echo _wp_link_page($page_index); ?>
                                        <span class="article-progress__page">
                                            <strong><?php echo esc_html(sprintf(__('Page %d', 'kuchnia-twist'), $page_index)); ?></strong>
                                            <?php if ($page_label !== '') : ?>
                                                <span><?php echo esc_html($page_label); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($page_item['summary'])) : ?>
                                                <small><?php echo esc_html((string) $page_item['summary']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
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

                <?php if ($is_multipage && !$is_final_page) : ?>
                    <section class="article-handoff" aria-label="<?php esc_attr_e('Continue reading', 'kuchnia-twist'); ?>">
                        <div class="article-handoff__copy">
                            <span class="eyebrow"><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></span>
                            <h2><?php echo esc_html($next_page_label !== '' ? $next_page_label : __('Next section', 'kuchnia-twist')); ?></h2>
                            <?php if ($next_page_summary !== '') : ?>
                                <p><?php echo esc_html($next_page_summary); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($remaining_pages)) : ?>
                                <div class="article-handoff__path">
                                    <strong><?php esc_html_e('Still ahead', 'kuchnia-twist'); ?></strong>
                                    <div class="article-handoff__path-list">
                                        <?php foreach ($remaining_pages as $remaining_page) : ?>
                                            <?php
                                            $remaining_label = trim((string) ($remaining_page['label'] ?? ''));
                                            $remaining_summary = trim((string) ($remaining_page['summary'] ?? ''));
                                            if ($remaining_label === '') {
                                                continue;
                                            }
                                            ?>
                                            <div class="article-handoff__path-item">
                                                <span><?php echo esc_html($remaining_label); ?></span>
                                                <?php if ($remaining_summary !== '' && $remaining_summary !== $remaining_label) : ?>
                                                    <small><?php echo esc_html($remaining_summary); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="article-handoff__action">
                            <?php echo _wp_link_page($current_page + 1); ?>
                                <span><?php echo esc_html($is_recipe && ($current_page + 1) === $total_pages ? __('Continue to recipe', 'kuchnia-twist') : __('Continue reading', 'kuchnia-twist')); ?></span>
                            </a>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($is_multipage) : ?>
                    <nav class="article-pagination" aria-label="<?php esc_attr_e('Article page navigation', 'kuchnia-twist'); ?>">
                        <div class="article-pagination__meta">
                            <span class="eyebrow"><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'kuchnia-twist'), $current_page, $total_pages)); ?></span>
                            <?php if (!$is_final_page && $next_page_label !== '') : ?>
                                <p>
                                    <?php
                                    echo esc_html(
                                        $remaining_count > 1
                                            ? sprintf(__('Up next: %1$s, then %2$d more pages.', 'kuchnia-twist'), $next_page_label, $remaining_count - 1)
                                            : sprintf(__('Up next: %s', 'kuchnia-twist'), $next_page_label)
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($next_page_summary !== '') : ?>
                                <p><?php echo esc_html($next_page_summary); ?></p>
                            <?php endif; ?>
                            <?php if ($is_final_page && $final_page_label !== '') : ?>
                                <p><?php echo esc_html(sprintf(__('You reached the final page: %s', 'kuchnia-twist'), $final_page_label)); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="article-pagination__links">
                            <?php if ($current_page > 1) : ?>
                                <?php echo _wp_link_page($current_page - 1); ?>
                                    <span><?php esc_html_e('Previous page', 'kuchnia-twist'); ?></span>
                                </a>
                            <?php else : ?>
                                <span class="article-pagination__spacer" aria-hidden="true"></span>
                            <?php endif; ?>

                            <?php if ($current_page < $total_pages) : ?>
                                <?php echo _wp_link_page($current_page + 1); ?>
                                    <span><?php echo esc_html($is_recipe && ($current_page + 1) === $total_pages ? __('Continue to recipe', 'kuchnia-twist') : __('Next page', 'kuchnia-twist')); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>

                <?php if ($is_recipe && $is_final_page) : ?>
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

                <?php if ($is_final_page) : ?>
                    <section class="article-support">
                        <div class="article-support__editor">
                            <span class="eyebrow"><?php echo esc_html($editor_profile['role']); ?></span>
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
                <?php endif; ?>
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

        <?php if ($is_final_page) : ?>
            <section class="related-section section">
                <div class="section-heading section-heading--split">
                    <div>
                        <h2><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></h2>
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
        <?php endif; ?>
    </article>
<?php endwhile; ?>

<?php
get_footer();
