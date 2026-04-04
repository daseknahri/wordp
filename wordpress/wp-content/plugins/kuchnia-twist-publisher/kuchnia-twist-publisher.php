<?php
/**
 * Plugin Name: Kuchnia Twist Publisher
 * Description: Topic-driven article and Facebook publishing workflow for Kuchnia Twist.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: kuchnia-twist
 */

defined('ABSPATH') || exit;

defined('KUCHNIA_TWIST_PUBLISHER_FILE') || define('KUCHNIA_TWIST_PUBLISHER_FILE', __FILE__);
defined('KUCHNIA_TWIST_PUBLISHER_DIR') || define('KUCHNIA_TWIST_PUBLISHER_DIR', plugin_dir_path(__FILE__));

require_once KUCHNIA_TWIST_PUBLISHER_DIR . 'includes/class-kuchnia-twist-publisher.php';

register_activation_hook(__FILE__, [Kuchnia_Twist_Publisher::class, 'activate']);
Kuchnia_Twist_Publisher::instance();
