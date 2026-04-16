<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-site-identity.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-site-shortcodes.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-site.php';

final class Kuchnia_Twist_Publisher_Site_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Site_Identity_Trait;
    use Kuchnia_Twist_Publisher_Site_Shortcodes_Trait;
    use Kuchnia_Twist_Publisher_Site_Trait;
}
