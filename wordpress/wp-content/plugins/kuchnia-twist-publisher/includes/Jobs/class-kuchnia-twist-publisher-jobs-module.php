<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-jobs.php';

final class Kuchnia_Twist_Publisher_Jobs_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Jobs_Trait;
}
