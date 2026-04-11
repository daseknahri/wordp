<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-review.php';

final class Kuchnia_Twist_Publisher_Quality_Review_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Quality_Review_Trait;
}
