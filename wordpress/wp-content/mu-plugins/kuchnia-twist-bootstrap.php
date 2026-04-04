<?php
/**
 * Plugin Name: Kuchnia Twist Bootstrap
 * Description: Loads the Kuchnia Twist publisher plugin automatically.
 */

defined('ABSPATH') || exit;

$plugin_file = WPMU_PLUGIN_DIR . '/../plugins/kuchnia-twist-publisher/kuchnia-twist-publisher.php';

if (file_exists($plugin_file)) {
    require_once $plugin_file;
}
