import { CONTENT_PACKAGE_CONTRACT_VERSION } from "../runtime/config.mjs";

export const CONTENT_TYPE_REGISTRY = {
  recipe: {
    key: "recipe",
    label: "Recipe",
    active: true,
    legacyReadOnly: false,
    input_mode: "dish_name",
    validation_mode: "recipe_article",
    rendering_mode: "recipe_multipage",
    min_words: 1200,
    recipe_required: true,
    settings_keys: {
      content_preset_guidance: "recipe_preset_guidance",
      article_guidance: "article_prompt",
      social_guidance: "facebook_caption_guidance",
    },
  },
  food_fact: {
    key: "food_fact",
    label: "Food Fact",
    active: true,
    legacyReadOnly: false,
    input_mode: "working_title",
    validation_mode: "editorial_article",
    rendering_mode: "editorial_multipage",
    min_words: 1100,
    recipe_required: false,
    settings_keys: {
      content_preset_guidance: "food_fact_preset_guidance",
      article_guidance: "food_fact_article_prompt",
      social_guidance: "food_fact_facebook_caption_guidance",
    },
  },
  food_story: {
    key: "food_story",
    label: "Food Story",
    active: false,
    legacyReadOnly: true,
    input_mode: "working_title",
    validation_mode: "editorial_article",
    rendering_mode: "editorial_multipage",
    min_words: 1100,
    recipe_required: false,
    settings_keys: {
      content_preset_guidance: "food_story_preset_guidance",
      article_guidance: "food_story_article_prompt",
      social_guidance: "food_story_facebook_caption_guidance",
    },
  },
};

export const ACTIVE_CONTENT_TYPE_KEYS = Object.keys(CONTENT_TYPE_REGISTRY).filter(
  (key) => Boolean(CONTENT_TYPE_REGISTRY[key]?.active),
);

export function resolveContentTypeRegistryEntry(contentType = "recipe") {
  const key = String(contentType || "recipe");
  return CONTENT_TYPE_REGISTRY[key] || CONTENT_TYPE_REGISTRY.recipe;
}

export function buildContentTypeProfile(contentType = "recipe") {
  const definition = resolveContentTypeRegistryEntry(contentType);
  const key = definition.key;

  return {
    key,
    contract_version: CONTENT_PACKAGE_CONTRACT_VERSION,
    package_shape: "canonical_content_package",
    input_mode: definition.input_mode,
    article_stage: `${key}_article`,
    validation_mode: definition.validation_mode,
    rendering_mode: definition.rendering_mode,
    recipe_required: Boolean(definition.recipe_required),
  };
}
