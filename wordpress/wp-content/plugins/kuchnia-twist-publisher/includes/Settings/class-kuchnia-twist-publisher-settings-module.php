<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-settings-save.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-settings-registry.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-settings-defaults.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-settings-read.php';

final class Kuchnia_Twist_Publisher_Settings_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Settings_Save_Trait;
    use Kuchnia_Twist_Publisher_Settings_Registry_Trait;
    use Kuchnia_Twist_Publisher_Settings_Defaults_Trait;
    use Kuchnia_Twist_Publisher_Settings_Read_Trait;
}
