<?php

defined('ABSPATH') || exit;

$field_id = wp_unique_id('site-search-field-');
?>
<form role="search" method="get" class="site-search" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Search the site', 'kuchnia-twist'); ?></label>
    <span class="site-search__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="M16 16 21 21"></path></svg>
    </span>
    <input
        id="<?php echo esc_attr($field_id); ?>"
        type="search"
        class="site-search__input"
        placeholder="<?php esc_attr_e('Search recipes, facts, and stories', 'kuchnia-twist'); ?>"
        autocomplete="off"
        autocapitalize="none"
        enterkeyhint="search"
        spellcheck="false"
        value="<?php echo esc_attr(get_search_query()); ?>"
        name="s"
    >
    <button type="submit" class="site-search__button"><?php esc_html_e('Search', 'kuchnia-twist'); ?></button>
</form>
