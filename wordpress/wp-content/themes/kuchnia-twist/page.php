<?php

defined('ABSPATH') || exit;

get_header();
?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $profile       = kuchnia_twist_page_profile(get_post());
    $has_body      = kuchnia_twist_page_has_meaningful_body(get_post());
    $action_links  = $profile ? kuchnia_twist_page_action_links(get_post_field('post_name', get_the_ID())) : [];
    $reader_paths  = $profile ? kuchnia_twist_reader_paths() : [];
    $public_email  = kuchnia_twist_public_contact_email();
    $page_slug     = get_post_field('post_name', get_the_ID());
    $page_art      = has_post_thumbnail()
        ? get_the_post_thumbnail_url(get_the_ID(), 'full')
        : kuchnia_twist_context_media_url(in_array($page_slug, ['about', 'contact'], true) ? $page_slug : 'trust');
    ?>
    <article class="trust-shell page-shell page-shell--<?php echo esc_attr($page_slug); ?>">
        <header class="trust-shell__hero">
            <?php kuchnia_twist_render_breadcrumbs(get_post()); ?>
            <div class="trust-shell__hero-copy">
                <span class="eyebrow"><?php echo esc_html($profile['eyebrow'] ?? __('Page', 'kuchnia-twist')); ?></span>
                <h1><?php the_title(); ?></h1>
                <?php if (!empty($profile['intro'])) : ?>
                    <p><?php echo esc_html($profile['intro']); ?></p>
                <?php elseif (has_excerpt()) : ?>
                    <p><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>
            <div class="trust-shell__hero-media">
                <img src="<?php echo esc_url($page_art); ?>" alt="">
            </div>
        </header>

        <?php if ($profile && !empty($profile['highlights'])) : ?>
            <section class="trust-shell__strip">
                <?php foreach ($profile['highlights'] as $highlight) : ?>
                    <article class="trust-shell__note">
                        <span class="eyebrow"><?php esc_html_e('Publication signal', 'kuchnia-twist'); ?></span>
                        <p><?php echo esc_html($highlight); ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($has_body) : ?>
            <div class="prose trust-shell__prose">
                <?php the_content(); ?>
            </div>
        <?php endif; ?>

        <?php if ($profile && !empty($profile['sections'])) : ?>
            <section class="trust-shell__sections">
                <?php foreach ($profile['sections'] as $section) : ?>
                    <article class="trust-shell__card">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <p><?php echo esc_html($section['body']); ?></p>
                        <?php if (!empty($section['items'])) : ?>
                            <ul>
                                <?php foreach ($section['items'] as $item) : ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($reader_paths) : ?>
            <section class="trust-shell__paths">
                <div class="section-heading">
                    <span class="eyebrow"><?php esc_html_e('Keep reading', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('Trust pages work best when they still guide readers back into live editorial work.', 'kuchnia-twist'); ?></h2>
                </div>
                <div class="start-links">
                    <?php foreach ($reader_paths as $path) : ?>
                        <a class="start-link" href="<?php echo esc_url($path['url']); ?>">
                            <span class="eyebrow"><?php echo esc_html($path['eyebrow']); ?></span>
                            <strong><?php echo esc_html($path['title']); ?></strong>
                            <span><?php echo esc_html($path['count_label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($action_links || $public_email) : ?>
            <section class="trust-shell__actions">
                <div class="chip-links">
                    <?php foreach ($action_links as $action_link) : ?>
                        <a class="chip-link" href="<?php echo esc_url($action_link['url']); ?>"><?php echo esc_html($action_link['label']); ?></a>
                    <?php endforeach; ?>
                    <?php if ($public_email) : ?>
                        <a class="chip-link" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php
get_footer();
