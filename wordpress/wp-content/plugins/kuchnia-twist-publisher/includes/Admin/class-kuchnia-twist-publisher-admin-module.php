<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-screens.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-publisher-page.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-settings-page.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-job-summary.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-job-summary-generated.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-job-summary-panels.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-job-summary-support.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-review.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-admin-system-status.php';
require_once __DIR__ . '/../Quality/trait-kuchnia-twist-publisher-quality-page-flow.php';

final class Kuchnia_Twist_Publisher_Admin_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Admin_Screens_Trait;
    use Kuchnia_Twist_Publisher_Admin_Publisher_Page_Trait;
    use Kuchnia_Twist_Publisher_Admin_Settings_Page_Trait;
    use Kuchnia_Twist_Publisher_Admin_Job_Summary_Trait;
    use Kuchnia_Twist_Publisher_Admin_Job_Summary_Generated_Trait;
    use Kuchnia_Twist_Publisher_Admin_Job_Summary_Panels_Trait;
    use Kuchnia_Twist_Publisher_Admin_Job_Summary_Support_Trait;
    use Kuchnia_Twist_Publisher_Admin_Review_Trait;
    use Kuchnia_Twist_Publisher_Admin_System_Status_Trait;
    use Kuchnia_Twist_Publisher_Quality_Page_Flow_Trait;
}
