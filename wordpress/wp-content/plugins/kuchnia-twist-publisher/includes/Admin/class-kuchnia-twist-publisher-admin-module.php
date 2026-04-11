<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-review.php';

final class Kuchnia_Twist_Publisher_Admin_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Admin_Review_Trait;
}
