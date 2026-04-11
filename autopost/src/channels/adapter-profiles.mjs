import { CHANNEL_ADAPTER_CONTRACT_VERSION } from "../runtime/config.mjs";

export const RECIPE_HOOK_ANGLES = [
  {
    key: "quick_dinner",
    label: "Quick Dinner",
    instruction: "Lead with speed, weeknight relief, and a realistic payoff for busy cooks.",
  },
  {
    key: "comfort_food",
    label: "Comfort Food",
    instruction: "Lean into warmth, coziness, craveability, and repeat-cook appeal.",
  },
  {
    key: "budget_friendly",
    label: "Budget Friendly",
    instruction: "Emphasize value, pantry practicality, and generous payoff without sounding cheap.",
  },
  {
    key: "beginner_friendly",
    label: "Beginner Friendly",
    instruction: "Make the recipe feel approachable, confidence-building, and easy to follow.",
  },
  {
    key: "crowd_pleaser",
    label: "Crowd Pleaser",
    instruction: "Frame the dish as dependable, family-friendly, and easy to serve again.",
  },
  {
    key: "better_than_takeout",
    label: "Better Than Takeout",
    instruction: "Focus on restaurant-style payoff with simpler home-kitchen control.",
  },
];

export const FOOD_FACT_HOOK_ANGLES = [
  {
    key: "myth_busting",
    label: "Myth Busting",
    instruction: "Lead with a correction to something many cooks casually believe.",
  },
  {
    key: "surprising_truth",
    label: "Surprising Truth",
    instruction: "Frame the post around a specific surprise that changes how the reader sees the topic.",
  },
  {
    key: "kitchen_mistake",
    label: "Kitchen Mistake",
    instruction: "Focus on a common mistake, why it happens, and what to do instead.",
  },
  {
    key: "smarter_shortcut",
    label: "Smarter Shortcut",
    instruction: "Offer a clearer, simpler, or smarter way to handle the topic in a home kitchen.",
  },
  {
    key: "what_most_people_get_wrong",
    label: "What Most People Get Wrong",
    instruction: "Make the angle about the exact misunderstanding most readers carry into the kitchen.",
  },
  {
    key: "ingredient_truth",
    label: "Ingredient Truth",
    instruction: "Explain what an ingredient really does and why that matters in practice.",
  },
  {
    key: "changes_how_you_cook_it",
    label: "Changes How You Cook It",
    instruction: "Make the payoff feel like a concrete shift in how the reader will cook after learning this.",
  },
  {
    key: "restaurant_vs_home",
    label: "Restaurant vs Home",
    instruction: "Contrast restaurant assumptions with what really works in a normal home kitchen.",
  },
];

export const SOCIAL_ANGLE_LIBRARY = {
  recipe: RECIPE_HOOK_ANGLES,
  food_fact: FOOD_FACT_HOOK_ANGLES,
  food_story: FOOD_FACT_HOOK_ANGLES,
};

export const CHANNEL_REGISTRY = {
  facebook: {
    key: "facebook",
    label: "Facebook Pages",
    live: true,
    adapter: "page_distribution",
    output_shape: "social_pack",
    input_package: "content_package",
    request_target_shape: {
      enabled: false,
      page_ids: [],
      pages: [],
    },
    media_platform_hints: {
      platform: "facebook",
    },
  },
  facebook_groups: {
    key: "facebook_groups",
    label: "Facebook Groups",
    live: false,
    adapter: "manual_group_share",
    output_shape: "share_draft",
    input_package: "content_package",
    request_target_shape: {
      enabled: false,
      mode: "manual_draft",
    },
    media_platform_hints: {
      platform: "facebook_groups",
    },
  },
  pinterest: {
    key: "pinterest",
    label: "Pinterest",
    live: false,
    adapter: "draft_pin",
    output_shape: "pin_draft",
    input_package: "content_package",
    request_target_shape: {
      enabled: false,
      mode: "draft",
    },
    media_platform_hints: {
      platform: "pinterest",
    },
  },
};

export function buildChannelProfile(channel = "facebook") {
  const definition = CHANNEL_REGISTRY[channel] || CHANNEL_REGISTRY.facebook;

  return {
    key: definition.key,
    contract_version: CHANNEL_ADAPTER_CONTRACT_VERSION,
    live: Boolean(definition.live),
    adapter: definition.adapter,
    output_shape: definition.output_shape,
    input_package: definition.input_package,
  };
}
