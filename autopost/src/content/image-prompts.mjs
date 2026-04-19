export function createImagePromptHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    resolveCanonicalContentPackage,
    trimText,
  } = deps;

  function isPlainObject(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function normalizeSignalLine(value, limit = 90) {
    const cleaned = cleanText(value);
    if (!cleaned) {
      return "";
    }

    return trimText(cleaned, limit);
  }

  function uniqueSignals(values) {
    const seen = new Set();
    const result = [];

    for (const value of values) {
      const cleaned = normalizeSignalLine(value);
      const fingerprint = cleaned.toLowerCase();
      if (!cleaned || seen.has(fingerprint)) {
        continue;
      }
      seen.add(fingerprint);
      result.push(cleaned);
    }

    return result;
  }

  function platformGuidance(platform) {
    switch (platform) {
      case "blog":
        return "Landscape editorial hero framing with clean negative space for headers.";
      case "facebook":
        return "Square crop, tight subject framing, and strong contrast for feed clarity.";
      case "pinterest":
        return "Vertical pin-friendly framing with a clean area for optional overlay text (do not add text).";
      default:
        return "";
    }
  }

  function resolvePlatformGuidance(settings, platform) {
    if (!platform) {
      return "";
    }

    const normalizedSettings = isPlainObject(settings) ? settings : {};
    const imagePresets = isPlainObject(normalizedSettings.contentMachine?.channelPresets?.image)
      ? normalizedSettings.contentMachine.channelPresets.image
      : {};
    const platformPresets = isPlainObject(imagePresets.platforms) ? imagePresets.platforms : {};
    const direct = cleanMultilineText(
      platformPresets[platform]
      || imagePresets[platform]
      || "",
    );

    return direct || platformGuidance(platform);
  }

  function contentTypeGuidance(contentType) {
    if (contentType === "food_fact") {
      return "Show the ingredient, tool, label, or kitchen moment that illustrates the fact. Avoid full plated recipe styling unless it is essential.";
    }
    if (contentType === "recipe") {
      return "Make the finished dish the clear hero with visible texture and ingredient cues.";
    }
    return "";
  }

  function resolveTopicFocus(contentPackage) {
    const signals = isPlainObject(contentPackage?.article_signals) ? contentPackage.article_signals : {};
    const candidates = uniqueSignals([
      signals.heading_topic,
      signals.ingredient_focus,
      signals.detail_line,
      signals.proof_line,
      signals.page_signal_line,
      signals.payoff_line,
      signals.final_reward_line,
      contentPackage.title,
    ]);

    if (!candidates.length) {
      return "";
    }

    return `Visual focus: ${candidates.slice(0, 2).join("; ")}.`;
  }

  function buildImagePrompt(generated, options = {}) {
    const contentPackage = resolveCanonicalContentPackage(generated);
    const normalizedOptions = typeof options === "string" ? { variantHint: options } : options;
    const variantHint = cleanText(normalizedOptions?.variantHint || normalizedOptions?.variant_hint || "");
    const platform = cleanText(normalizedOptions?.platform || normalizedOptions?.channel || "");
    const settings = normalizedOptions?.settings;
    const contentType = cleanText(contentPackage?.content_type || "recipe");
    const topicFocus = resolveTopicFocus(contentPackage);

    return [
      cleanText(contentPackage.image_prompt || ""),
      variantHint,
      topicFocus,
      contentTypeGuidance(contentType),
      resolvePlatformGuidance(settings, platform),
      "Keep the image realistic, editorial, craveable, and free of any text or logos.",
      "Use natural light, believable texture, and clean plating or surfaces.",
    ]
      .filter(Boolean)
      .join("\n");
  }

  return {
    buildImagePrompt,
  };
}
