<?php

defined('ABSPATH') || exit;

$context = get_query_var('kt_recipe_card');
$recipe_data = is_array($context) ? ($context['recipe_data'] ?? []) : [];

if (empty($recipe_data)) {
    return;
}
?>

<section class="recipe-panel" id="recipe-card">
    <div class="recipe-panel__top">
        <span class="eyebrow"><?php esc_html_e('Recipe card', 'kuchnia-twist'); ?></span>
        <div class="recipe-panel__stats">
            <?php if (!empty($recipe_data['prep_time'])) : ?><span><?php echo esc_html($recipe_data['prep_time']); ?></span><?php endif; ?>
            <?php if (!empty($recipe_data['cook_time'])) : ?><span><?php echo esc_html($recipe_data['cook_time']); ?></span><?php endif; ?>
            <?php if (!empty($recipe_data['yield'])) : ?><span><?php echo esc_html($recipe_data['yield']); ?></span><?php endif; ?>
        </div>
    </div>
    <div class="recipe-panel__grid">
        <div>
            <h2><?php esc_html_e('Ingredients', 'kuchnia-twist'); ?></h2>
            <ul>
                <?php foreach (($recipe_data['ingredients'] ?? []) as $ingredient) : ?>
                    <li><?php echo esc_html($ingredient); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h2><?php esc_html_e('Method', 'kuchnia-twist'); ?></h2>
            <ol>
                <?php foreach (($recipe_data['instructions'] ?? []) as $step) : ?>
                    <li><?php echo esc_html($step); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</section>
