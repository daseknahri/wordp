<?php

defined('ABSPATH') || exit;
?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__grid">
            <div>
                <p class="site-footer__eyebrow"><?php esc_html_e('Kuchnia Twist', 'kuchnia-twist'); ?></p>
                <h2><?php esc_html_e('Food writing shaped for curiosity, appetite, and trust.', 'kuchnia-twist'); ?></h2>
                <p><?php esc_html_e('Recipes, food facts, and kitchen stories built to feel useful before they ever try to convert.', 'kuchnia-twist'); ?></p>
            </div>
            <div class="site-footer__links">
                <?php kuchnia_twist_policy_links(); ?>
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
