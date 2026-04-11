<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-rest-worker.php';

final class Kuchnia_Twist_Publisher_Rest_Worker_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Rest_Worker_Trait;
}
