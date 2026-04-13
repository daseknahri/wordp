<?php

defined('ABSPATH') || exit;

$pillar_nav     = kuchnia_twist_pillar_nav_items();
$trust_nav      = kuchnia_twist_trust_nav_items();
$browse_label   = 'site-footer-browse-label';
$journal_label  = 'site-footer-journal-label';
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__lead">
                <div class="site-footer__brand">
                    <?php $site_icon = function_exists('get_site_icon_url') ? get_site_icon_url(64) : ''; ?>
                    <?php if (is_string($site_icon) && $site_icon !== '') : ?>
                        <span class="site-footer__logo">
                            <img class="site-footer__logo-image" src="<?php echo esc_url($site_icon); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="28" height="28" loading="lazy" decoding="async">
                        </span>
                    <?php endif; ?>
                    <div>
                        <h2><?php bloginfo('name'); ?></h2>
                        <p class="site-footer__summary"><?php echo esc_html(kuchnia_twist_site_summary()); ?></p>
                    </div>
                </div>
            </div>

            <div class="site-footer__grid">
                <details class="site-footer__section" open>
                    <summary id="<?php echo esc_attr($browse_label); ?>"><?php esc_html_e('Browse', 'kuchnia-twist'); ?></summary>
                    <nav class="site-footer__links" aria-labelledby="<?php echo esc_attr($browse_label); ?>">
                        <?php foreach ($pillar_nav as $item) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </details>

                <details class="site-footer__section" open>
                    <summary id="<?php echo esc_attr($journal_label); ?>"><?php esc_html_e('Journal', 'kuchnia-twist'); ?></summary>
                    <nav class="site-footer__links" aria-labelledby="<?php echo esc_attr($journal_label); ?>">
                        <?php foreach ($trust_nav as $item) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </details>

            </div>

            <div class="site-footer__bottom">
                <span><?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?></span>
                <span><?php esc_html_e('Independent home-cooking journal with visible editorial standards and practical kitchen guidance.', 'kuchnia-twist'); ?></span>
            </div>
        </div>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
