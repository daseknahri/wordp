<?php

defined('ABSPATH') || exit;
?>
    </main>
    <footer class="site-footer">
        <?php $public_email = kuchnia_twist_public_contact_email(); ?>
        <div class="site-footer__grid">
            <div>
                <div class="site-footer__brand">
                    <img class="site-footer__mark" src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-seal.svg')); ?>" alt="">
                    <img class="site-footer__wordmark" src="<?php echo esc_url(kuchnia_twist_asset_url('assets/brand-wordmark.svg')); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </div>
                <p class="site-footer__eyebrow"><?php esc_html_e('Kuchnia Twist', 'kuchnia-twist'); ?></p>
                <h2><?php esc_html_e('Food writing shaped for curiosity, appetite, and trust.', 'kuchnia-twist'); ?></h2>
                <p><?php esc_html_e('Recipes, food facts, and kitchen stories built to feel useful before they ever try to convert.', 'kuchnia-twist'); ?></p>
            </div>
            <div class="site-footer__links">
                <p class="site-footer__eyebrow"><?php esc_html_e('Browse the journal', 'kuchnia-twist'); ?></p>
                <?php kuchnia_twist_pillar_links(); ?>
                <p class="site-footer__eyebrow site-footer__eyebrow--sub"><?php esc_html_e('Trust pages', 'kuchnia-twist'); ?></p>
                <?php kuchnia_twist_policy_links(); ?>
            </div>
        </div>
        <div class="site-footer__notice">
            <div>
                <p class="site-footer__eyebrow"><?php esc_html_e('Publication note', 'kuchnia-twist'); ?></p>
                <p><?php esc_html_e('This is an independent editorial food site built around original recipes, practical explainers, and story-led kitchen writing.', 'kuchnia-twist'); ?></p>
            </div>
            <div class="site-footer__notice-meta">
                <span><?php esc_html_e('Clear policies and contact details are always kept visible.', 'kuchnia-twist'); ?></span>
                <?php if ($public_email) : ?>
                    <a href="mailto:<?php echo esc_attr(antispambot($public_email)); ?>"><?php echo esc_html(antispambot($public_email)); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span><?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?></span>
            <span><?php esc_html_e('Made for thoughtful publishing and steady growth.', 'kuchnia-twist'); ?></span>
        </div>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
