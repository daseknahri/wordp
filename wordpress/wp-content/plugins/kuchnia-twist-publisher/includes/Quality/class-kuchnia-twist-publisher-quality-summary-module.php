<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-editorial-readiness.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-job-evaluator.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-page-flow.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-scoring.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-social-angles.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-social-variants.php';
require_once __DIR__ . '/trait-kuchnia-twist-publisher-quality-summary.php';

final class Kuchnia_Twist_Publisher_Quality_Summary_Module extends Kuchnia_Twist_Publisher_Module
{
    use Kuchnia_Twist_Publisher_Quality_Editorial_Readiness_Trait;
    use Kuchnia_Twist_Publisher_Quality_Job_Evaluator_Trait;
    use Kuchnia_Twist_Publisher_Quality_Page_Flow_Trait;
    use Kuchnia_Twist_Publisher_Quality_Scoring_Trait;
    use Kuchnia_Twist_Publisher_Quality_Social_Angles_Trait;
    use Kuchnia_Twist_Publisher_Quality_Social_Variants_Trait;
    use Kuchnia_Twist_Publisher_Quality_Summary_Trait;
}


