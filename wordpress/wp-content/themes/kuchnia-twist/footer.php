<?php

defined('ABSPATH') || exit;

$pillar_nav     = kuchnia_twist_pillar_nav_items();
$trust_nav      = kuchnia_twist_trust_nav_items();
$editor_profile = kuchnia_twist_editor_profile();
$public_email   = sanitize_email((string) ($editor_profile['public_email'] ?? ''));
$business_email = sanitize_email((string) ($editor_profile['business_email'] ?? ''));
$browse_label   = 'site-footer-browse-label';
$journal_label  = 'site-footer-journal-label';
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__lead">
                <div class="site-footer__brand">
                    <?php $brand_mark = kuchnia_twist_brand_mark_url(); ?>
                    <div class="site-footer__brand-copy">
                        <div class="site-footer__brand-heading">
                            <?php if ($brand_mark !== '') : ?>
                                <span class="site-footer__logo">
                                    <img class="site-footer__logo-image" src="<?php echo esc_url($brand_mark); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="28" height="28" loading="lazy" decoding="async">
                                </span>
                            <?php endif; ?>
                            <h2><?php bloginfo('name'); ?></h2>
                        </div>
                        <p class="site-footer__summary"><?php echo esc_html(kuchnia_twist_site_summary()); ?></p>
                        <?php $footer_email = $public_email !== '' ? $public_email : $business_email; ?>
                        <?php if ($footer_email !== '') : ?>
                            <div class="site-footer__contacts">
                                <a class="site-footer__contact" href="mailto:<?php echo esc_attr(antispambot($footer_email)); ?>">
                                    <span class="author-card__contact-label"><?php esc_html_e('Editorial email', 'kuchnia-twist'); ?></span>
                                    <span class="author-card__contact-value"><?php echo esc_html(antispambot($footer_email)); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (kuchnia_twist_has_social_profiles()) : ?>
                    <div class="site-footer__follow">
                        <span class="eyebrow"><?php esc_html_e('Follow', 'kuchnia-twist'); ?></span>
                        <p class="site-footer__follow-copy"><?php echo esc_html(kuchnia_twist_social_follow_label()); ?></p>
                        <?php kuchnia_twist_render_social_links('social-links--rail', true); ?>
                    </div>
                <?php endif; ?>
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
