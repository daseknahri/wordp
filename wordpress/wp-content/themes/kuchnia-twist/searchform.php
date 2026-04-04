<?php

defined('ABSPATH') || exit;
?>
<form role="search" method="get" class="site-search" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="site-search-field"><?php esc_html_e('Search the site', 'kuchnia-twist'); ?></label>
    <input
        id="site-search-field"
        type="search"
        class="site-search__input"
        placeholder="<?php esc_attr_e('Search recipes, facts, and stories', 'kuchnia-twist'); ?>"
        value="<?php echo esc_attr(get_search_query()); ?>"
        name="s"
    >
    <button type="submit" class="site-search__button"><?php esc_html_e('Search', 'kuchnia-twist'); ?></button>
</form>
