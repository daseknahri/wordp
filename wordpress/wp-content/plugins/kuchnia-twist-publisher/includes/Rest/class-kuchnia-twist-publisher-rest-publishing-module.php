<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-rest-publishing.php';

final class Kuchnia_Twist_Publisher_Rest_Publishing_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Rest_Publishing_Trait;
}
