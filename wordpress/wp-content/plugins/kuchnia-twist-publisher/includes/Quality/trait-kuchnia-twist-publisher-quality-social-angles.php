<?php

defined('ABSPATH') || exit;

trait Kuchnia_Twist_Publisher_Quality_Social_Angles_Trait
{
    private function social_angle_presets(string $content_type = 'recipe'): array
    {
        $presets = [
            'recipe' => [
                'quick_dinner' => [
                    'label'       => __('Quick Dinner', 'kuchnia-twist'),
                    'instruction' => __('Lead with speed, ease, and a real weeknight payoff.', 'kuchnia-twist'),
                ],
                'comfort_food' => [
                    'label'       => __('Comfort Food', 'kuchnia-twist'),
                    'instruction' => __('Lean into warmth, cozy payoff, texture, and repeat-cook appeal.', 'kuchnia-twist'),
                ],
                'budget_friendly' => [
                    'label'       => __('Budget Friendly', 'kuchnia-twist'),
                    'instruction' => __('Emphasize value, pantry practicality, and generous payoff without sounding cheap.', 'kuchnia-twist'),
                ],
                'beginner_friendly' => [
                    'label'       => __('Beginner Friendly', 'kuchnia-twist'),
                    'instruction' => __('Make the hook feel approachable, low-stress, and confidence-building.', 'kuchnia-twist'),
                ],
                'crowd_pleaser' => [
                    'label'       => __('Crowd Pleaser', 'kuchnia-twist'),
                    'instruction' => __('Frame the recipe as dependable, family-friendly, and easy to serve again.', 'kuchnia-twist'),
                ],
                'better_than_takeout' => [
                    'label'       => __('Better Than Takeout', 'kuchnia-twist'),
                    'instruction' => __('Focus on restaurant-style payoff with simpler home-kitchen control.', 'kuchnia-twist'),
                ],
            ],
            'food_fact' => [
                'myth_busting' => [
                    'label'       => __('Myth Busting', 'kuchnia-twist'),
                    'instruction' => __('Lead with a correction to something many cooks casually believe.', 'kuchnia-twist'),
                ],
                'surprising_truth' => [
                    'label'       => __('Surprising Truth', 'kuchnia-twist'),
                    'instruction' => __('Frame the post around a specific surprise that changes how the reader sees the topic.', 'kuchnia-twist'),
                ],
                'kitchen_mistake' => [
                    'label'       => __('Kitchen Mistake', 'kuchnia-twist'),
                    'instruction' => __('Focus on a common mistake, why it happens, and what to do instead.', 'kuchnia-twist'),
                ],
                'smarter_shortcut' => [
                    'label'       => __('Smarter Shortcut', 'kuchnia-twist'),
                    'instruction' => __('Offer a clearer, simpler, or smarter way to handle the topic in a home kitchen.', 'kuchnia-twist'),
                ],
                'what_most_people_get_wrong' => [
                    'label'       => __('What Most People Get Wrong', 'kuchnia-twist'),
                    'instruction' => __('Make the angle about the exact misunderstanding most readers carry into the kitchen.', 'kuchnia-twist'),
                ],
                'ingredient_truth' => [
                    'label'       => __('Ingredient Truth', 'kuchnia-twist'),
                    'instruction' => __('Explain what an ingredient really does and why that matters in practice.', 'kuchnia-twist'),
                ],
                'changes_how_you_cook_it' => [
                    'label'       => __('Changes How You Cook It', 'kuchnia-twist'),
                    'instruction' => __('Make the payoff feel like a concrete shift in how the reader will cook after learning this.', 'kuchnia-twist'),
                ],
                'restaurant_vs_home' => [
                    'label'       => __('Restaurant vs Home', 'kuchnia-twist'),
                    'instruction' => __('Contrast restaurant assumptions with what really works in a normal home kitchen.', 'kuchnia-twist'),
                ],
            ],
        ];

        return $presets[$content_type] ?? $presets['recipe'];
    }

    private function all_social_angle_presets(): array
    {
        return array_merge(
            $this->social_angle_presets('recipe'),
            $this->social_angle_presets('food_fact')
        );
    }

    private function normalize_hook_angle_key(string $value, string $content_type = ''): string
    {
        $value = sanitize_key($value);
        $presets = $content_type !== '' ? $this->social_angle_presets($content_type) : $this->all_social_angle_presets();
        return isset($presets[$value]) ? $value : '';
    }

    private function hook_angle_label(string $angle_key, string $content_type = 'recipe'): string
    {
        $angle_key = $this->normalize_hook_angle_key($angle_key, $content_type);
        $presets = $content_type !== '' ? $this->social_angle_presets($content_type) : $this->all_social_angle_presets();
        return $angle_key !== '' && !empty($presets[$angle_key]['label'])
            ? (string) $presets[$angle_key]['label']
            : __('Auto rotate', 'kuchnia-twist');
    }
}
