<?php

defined('ABSPATH') || exit;

get_header();
?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $profile          = kuchnia_twist_page_profile(get_post());
    $has_body         = kuchnia_twist_page_has_meaningful_body(get_post());
    $public_email     = kuchnia_twist_public_contact_email();
    $business_email   = kuchnia_twist_business_contact_email();
    $editor_profile   = kuchnia_twist_editor_profile();
    $page_slug        = get_post_field('post_name', get_the_ID());
    $page_art         = '';
    $updated_label    = get_the_modified_date();
    $profile_body     = $profile['body'] ?? [];
    $profile_body_list = $profile['body_list'] ?? [];
    $has_profile_body = !$has_body && (!empty($profile_body) || !empty($profile_body_list));

    if (has_post_thumbnail()) {
        $page_art = get_the_post_thumbnail(get_the_ID(), 'kuchnia-twist-hero', [
            'loading'  => 'eager',
            'decoding' => 'async',
            'sizes'    => '(max-width: 767px) 100vw, (max-width: 1199px) 92vw, 40vw',
            'alt'      => trim((string) get_post_meta(get_post_thumbnail_id(get_the_ID()), '_wp_attachment_image_alt', true)) ?: get_the_title(),
        ]);
    }
    ?>
    <article class="trust-shell page-shell page-shell--<?php echo esc_attr($page_slug); ?>">
        <header class="trust-shell__hero">
            <?php kuchnia_twist_render_breadcrumbs(get_post()); ?>
            <div class="trust-shell__hero-copy">
                <?php if ($profile && !empty($profile['eyebrow'])) : ?>
                    <span class="eyebrow"><?php echo esc_html($profile['eyebrow']); ?></span>
                <?php endif; ?>
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php elseif ($profile && !empty($profile['intro'])) : ?>
                    <p><?php echo esc_html($profile['intro']); ?></p>
                <?php endif; ?>
                <?php if ($profile && !empty($profile['highlights'])) : ?>
                    <div class="chip-links">
                        <?php foreach ($profile['highlights'] as $highlight) : ?>
                            <span class="chip-link chip-link--static"><?php echo esc_html($highlight); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($page_art !== '') : ?>
                <div class="trust-shell__hero-media">
                    <?php echo $page_art; ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($has_body || $has_profile_body) : ?>
            <div class="prose trust-shell__prose">
                <?php if ($has_body) : ?>
                    <?php the_content(); ?>
                <?php else : ?>
                    <?php foreach ($profile_body as $paragraph) : ?>
                        <p><?php echo wp_kses_post($paragraph); ?></p>
                    <?php endforeach; ?>
                    <?php if (!empty($profile_body_list)) : ?>
                        <ul>
                            <?php foreach ($profile_body_list as $item) : ?>
                                <li><?php echo wp_kses_post($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($profile && !empty($profile['sections'])) : ?>
            <section class="trust-shell__sections">
                <?php foreach ($profile['sections'] as $section) : ?>
                    <article class="trust-shell__card">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <p><?php echo wp_kses_post($section['body']); ?></p>
                        <?php if (!empty($section['items'])) : ?>
                            <ul>
                                <?php foreach ($section['items'] as $item) : ?>
                                    <li><?php echo wp_kses_post($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($page_slug === 'about' && !empty($editor_profile['name'])) : ?>
            <section class="trust-shell__author">
                <div class="author-card author-card--page">
                    <div class="author-card__avatar">
                        <?php kuchnia_twist_render_editor_portrait((string) ($editor_profile['name'] ?? ''), ['class' => 'author-card__image', 'loading' => 'lazy', 'decoding' => 'async']); ?>
                    </div>
                    <div class="author-card__body">
                        <span class="eyebrow"><?php esc_html_e('Editor', 'kuchnia-twist'); ?></span>
                        <h2><?php echo esc_html($editor_profile['name']); ?></h2>
                        <?php if (!empty($editor_profile['role'])) : ?>
                            <p class="author-card__role"><?php echo esc_html($editor_profile['role']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($editor_profile['bio'])) : ?>
                            <p><?php echo esc_html($editor_profile['bio']); ?></p>
                        <?php endif; ?>
                        <?php if ($public_email !== '' || $business_email !== '') : ?>
                            <div class="author-card__contacts">
                                <?php if ($public_email !== '') : ?>
                                    <a class="author-card__contact" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>">
                                        <span class="author-card__contact-label"><?php esc_html_e('Editorial email', 'kuchnia-twist'); ?></span>
                                        <span class="author-card__contact-value"><?php echo esc_html(antispambot($public_email)); ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($business_email !== '') : ?>
                                    <a class="author-card__contact" href="mailto:<?php echo esc_attr(antispambot($business_email)); ?>">
                                        <span class="author-card__contact-label"><?php esc_html_e('Business email', 'kuchnia-twist'); ?></span>
                                        <span class="author-card__contact-value"><?php echo esc_html(antispambot($business_email)); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php
get_footer();
