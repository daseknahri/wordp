<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_single_type');
$recipe_data = is_array($context) ? ($context['recipe_data'] ?? []) : [];
$is_final_page = !empty($context['is_final_page']);
$is_recipe = !empty($context['is_recipe']);

if (!$is_recipe || !$is_final_page || empty($recipe_data)) {
    return;
}

set_query_var('kt_recipe_card', [
    'recipe_data' => $recipe_data,
]);

get_template_part('template-parts/single/recipe-card');
