<?php

defined('ABSPATH') || exit;

$editor_profile = kuchnia_twist_editor_profile();
$public_email   = kuchnia_twist_public_contact_email();
$follow_label   = kuchnia_twist_social_follow_label();
$pillar_nav     = kuchnia_twist_pillar_nav_items();
$trust_nav      = kuchnia_twist_trust_nav_items();
$has_social     = kuchnia_twist_has_social_profiles();
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__lead">
                <div class="site-footer__brand">
                    <span class="site-footer__symbol">
                        <img src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')); ?>" alt="">
                    </span>
                    <div>
                        <span class="eyebrow"><?php esc_html_e('Independent editorial food journal', 'kuchnia-twist'); ?></span>
                        <h2><?php esc_html_e('Built for fast scanning on mobile and deeper reading when dinner is decided.', 'kuchnia-twist'); ?></h2>
                    </div>
                </div>
                <p class="site-footer__summary">
                    <?php
                    printf(
                        esc_html__('%1$s edits Kuchnia Twist as a home-cooking publication shaped around recipes, food facts, and slower kitchen stories that stay useful before they try to convert.', 'kuchnia-twist'),
                        esc_html($editor_profile['name'])
                    );
                    ?>
                </p>
            </div>

            <div class="site-footer__grid">
                <details class="site-footer__section" open>
                    <summary><?php esc_html_e('Browse the journal', 'kuchnia-twist'); ?></summary>
                    <div class="site-footer__links">
                        <?php foreach ($pillar_nav as $item) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="site-footer__section" open>
                    <summary><?php esc_html_e('Publication', 'kuchnia-twist'); ?></summary>
                    <div class="site-footer__links">
                        <?php foreach ($trust_nav as $item) : ?>
                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>

                <?php if ($has_social || $public_email) : ?>
                    <details class="site-footer__section" open>
                        <summary><?php echo esc_html($follow_label); ?></summary>
                        <div class="site-footer__links site-footer__links--social">
                            <?php if ($has_social) : ?>
                                <?php kuchnia_twist_render_social_links('social-links--footer', true); ?>
                            <?php endif; ?>
                            <?php if ($public_email) : ?>
                                <a class="site-footer__email" href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <div class="site-footer__bottom">
                <span><?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?></span>
                <span><?php esc_html_e('Premium editorial shell, mobile-first reading rhythm.', 'kuchnia-twist'); ?></span>
            </div>
        </div>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
