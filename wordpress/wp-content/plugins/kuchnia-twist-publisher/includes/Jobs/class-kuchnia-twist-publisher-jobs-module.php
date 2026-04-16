<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-jobs.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-events.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-action-support.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-listing.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-payloads.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-records.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-job-scheduling.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-recipe-ideas.php';

final class Kuchnia_Twist_Publisher_Jobs_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Jobs_Trait;
    use Kuchnia_Twist_Publisher_Job_Events_Trait;
    use Kuchnia_Twist_Publisher_Job_Action_Support_Trait;
    use Kuchnia_Twist_Publisher_Job_Listing_Trait;
    use Kuchnia_Twist_Publisher_Job_Payloads_Trait;
    use Kuchnia_Twist_Publisher_Job_Records_Trait;
    use Kuchnia_Twist_Publisher_Job_Scheduling_Trait;
    use Kuchnia_Twist_Publisher_Recipe_Ideas_Trait;
}

