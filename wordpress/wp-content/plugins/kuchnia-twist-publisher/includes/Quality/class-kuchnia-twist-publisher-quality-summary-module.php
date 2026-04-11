<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-summary.php';

final class Kuchnia_Twist_Publisher_Quality_Summary_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Quality_Summary_Trait;
}
