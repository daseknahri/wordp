<?php

defined('ABSPATH') || exit;

get_header();
?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $profile        = kuchnia_twist_page_profile(get_post());
    $has_body       = kuchnia_twist_page_has_meaningful_body(get_post());
    $action_links   = $profile ? kuchnia_twist_page_action_links(get_post_field('post_name', get_the_ID())) : [];
    $page_slug      = get_post_field('post_name', get_the_ID());
    $page_signals   = $profile ? kuchnia_twist_page_signal_cards(get_post()) : [];
    $trust_links    = $profile ? kuchnia_twist_trust_page_links($page_slug) : [];
    $reader_paths   = $profile ? kuchnia_twist_reader_paths() : [];
    $public_email   = kuchnia_twist_public_contact_email();
    $page_art       = in_array($page_slug, ['about', 'contact'], true)
        ? kuchnia_twist_fallback_media_url($page_slug)
        : kuchnia_twist_fallback_media_url('trust');
    ?>
    <article class="page-shell <?php echo $profile ? 'page-shell--trust' : ''; ?> page-shell--<?php echo esc_attr($page_slug); ?>">
        <?php kuchnia_twist_render_breadcrumbs(get_post()); ?>

        <header class="page-hero" data-reveal>
            <div class="page-hero__copy">
                <span class="eyebrow"><?php echo esc_html($profile['eyebrow'] ?? __('Page', 'kuchnia-twist')); ?></span>
                <h1><?php the_title(); ?></h1>
                <?php if (!empty($profile['intro'])) : ?>
                    <p class="page-hero__lede"><?php echo esc_html($profile['intro']); ?></p>
                <?php elseif (has_excerpt()) : ?>
                    <p class="page-hero__lede"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($profile['highlights'])) : ?>
                <aside class="page-highlights page-highlights--with-art" aria-label="<?php esc_attr_e('Page highlights', 'kuchnia-twist'); ?>">
                    <img class="page-highlights__art" src="<?php echo esc_url($page_art); ?>" alt="">
                    <?php foreach ($profile['highlights'] as $highlight) : ?>
                        <p><?php echo esc_html($highlight); ?></p>
                    <?php endforeach; ?>
                </aside>
            <?php endif; ?>
        </header>

        <?php if ($profile) : ?>
            <section class="page-signals" aria-label="<?php esc_attr_e('Publication signals', 'kuchnia-twist'); ?>" data-reveal>
                <div class="page-signals__intro">
                    <span class="eyebrow"><?php esc_html_e('Publication signals', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('These pages should feel visibly maintained, connected, and easy to verify.', 'kuchnia-twist'); ?></h2>
                    <p><?php esc_html_e('Readers often land here to check whether the publication feels real. Freshness, reachability, and a clear trust network do a lot of that work before a single article is opened.', 'kuchnia-twist'); ?></p>
                </div>
                <div class="page-signals__grid">
                    <?php foreach ($page_signals as $signal) : ?>
                        <article class="page-signal">
                            <span class="eyebrow"><?php echo esc_html($signal['label']); ?></span>
                            <strong class="page-signal__value"><?php echo esc_html($signal['value']); ?></strong>
                            <p><?php echo esc_html($signal['detail']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <aside class="page-network">
                    <span class="eyebrow"><?php esc_html_e('Trust network', 'kuchnia-twist'); ?></span>
                    <h3><?php esc_html_e('Readers should never have to hunt for the basics.', 'kuchnia-twist'); ?></h3>
                    <p><?php esc_html_e('About, contact, privacy, cookies, and editorial standards become more believable when they stay linked together and visibly current.', 'kuchnia-twist'); ?></p>
                    <?php if ($trust_links) : ?>
                        <div class="page-network__links">
                            <?php foreach ($trust_links as $trust_link) : ?>
                                <a href="<?php echo esc_url($trust_link['url']); ?>"><?php echo esc_html($trust_link['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($public_email) : ?>
                        <a class="page-network__mail" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                    <?php else : ?>
                        <p class="page-network__note"><?php esc_html_e('Set a public email in Settings > General so the publication stays reachable from the trust pages.', 'kuchnia-twist'); ?></p>
                    <?php endif; ?>
                </aside>
            </section>
        <?php endif; ?>

        <?php if ($has_body) : ?>
            <div class="prose page-prose" data-reveal>
                <?php the_content(); ?>
            </div>
        <?php endif; ?>

        <?php if ($profile && !empty($profile['sections'])) : ?>
            <section class="page-sections" aria-label="<?php esc_attr_e('Supporting details', 'kuchnia-twist'); ?>" data-reveal>
                <?php foreach ($profile['sections'] as $section) : ?>
                    <article class="page-section-card">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <p><?php echo esc_html($section['body']); ?></p>
                        <?php if (!empty($section['items'])) : ?>
                            <ul class="page-section-card__list">
                                <?php foreach ($section['items'] as $item) : ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($profile && $reader_paths) : ?>
            <section class="page-paths" aria-label="<?php esc_attr_e('Continue into the journal', 'kuchnia-twist'); ?>" data-reveal>
                <div class="page-paths__intro">
                    <span class="eyebrow"><?php esc_html_e('Continue into the journal', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('Trust pages work better when they still guide readers back into live editorial work.', 'kuchnia-twist'); ?></h2>
                    <p><?php esc_html_e('Once someone understands who is behind the site and how it is run, the clean next step is opening the archive path that matches their intent.', 'kuchnia-twist'); ?></p>
                </div>
                <div class="page-paths__grid">
                    <?php foreach ($reader_paths as $path) : ?>
                        <article class="page-path">
                            <span class="eyebrow"><?php echo esc_html($path['eyebrow']); ?></span>
                            <h3><?php echo esc_html($path['title']); ?></h3>
                            <p><?php echo esc_html($path['description']); ?></p>
                            <div class="page-path__meta"><?php echo esc_html($path['count_label']); ?></div>
                            <a class="text-link" href="<?php echo esc_url($path['url']); ?>"><?php esc_html_e('Open this reading path', 'kuchnia-twist'); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($action_links) : ?>
            <section class="page-callout" data-reveal>
                <div>
                    <span class="eyebrow"><?php esc_html_e('Next step', 'kuchnia-twist'); ?></span>
                    <h2><?php esc_html_e('Keep the trust pages connected, visible, and easy to scan.', 'kuchnia-twist'); ?></h2>
                </div>
                <div class="page-callout__links">
                    <?php foreach ($action_links as $action_link) : ?>
                        <a class="text-link" href="<?php echo esc_url($action_link['url']); ?>"><?php echo esc_html($action_link['label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php
get_footer();
