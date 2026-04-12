<?php

defined('ABSPATH') || exit;

$editor_profile = kuchnia_twist_editor_profile();
$public_email   = kuchnia_twist_public_contact_email();
$follow_label   = kuchnia_twist_social_follow_label();
$pillar_nav     = kuchnia_twist_pillar_nav_items();
$trust_nav      = kuchnia_twist_trust_nav_items();
$has_social     = kuchnia_twist_has_social_profiles();
$browse_label   = 'site-footer-browse-label';
$journal_label  = 'site-footer-journal-label';
$follow_label_id = 'site-footer-follow-label';
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__lead">
                <div class="site-footer__brand">
                    <span class="site-footer__symbol">
                        <?php
                        if (function_exists('the_custom_logo') && has_custom_logo()) {
                            $logo_id = (int) get_theme_mod('custom_logo');
                            echo wp_get_attachment_image($logo_id, 'medium', false, [
                                'class' => 'site-footer__symbol-image',
                                'loading' => 'lazy',
                                'decoding' => 'async',
                                'alt' => get_bloginfo('name'),
                            ]);
                        } else {
                            echo '<img src="' . esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')) . '" alt="" width="38" height="38" loading="lazy" decoding="async">';
                        }
                        ?>
                    </span>
                    <div>
                        <h2><?php bloginfo('name'); ?></h2>
                        <p class="site-footer__summary"><?php esc_html_e('Independent home-cooking journal with recipes, food facts, and stories.', 'kuchnia-twist'); ?></p>
                    </div>
                </div>
                <div class="site-footer__lead-actions">
                    <?php if (!empty($trust_nav[0]['url']) && !empty($trust_nav[0]['label'])) : ?>
                        <a class="site-footer__lead-link site-footer__lead-link--muted" href="<?php echo esc_url($trust_nav[0]['url']); ?>"><?php echo esc_html($trust_nav[0]['label']); ?></a>
                    <?php endif; ?>
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
                <span><?php esc_html_e('Independent home-cooking journal with recipes, explainers, and visible editorial standards.', 'kuchnia-twist'); ?></span>
                <?php if ($trust_nav) : ?>
                    <nav class="site-footer__micro-nav" aria-label="<?php esc_attr_e('Site standards and policy links', 'kuchnia-twist'); ?>">
                        <?php foreach ($trust_nav as $item) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
