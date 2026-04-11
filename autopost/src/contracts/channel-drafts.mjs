export function createChannelDraftHelpers(deps) {
  const {
    cleanMultilineText,
    cleanText,
    isPlainObject,
    trimText,
  } = deps;

  function buildPinterestDraft(contentPackage, existing = {}) {
    const articleSignals = isPlainObject(contentPackage?.article_signals) ? contentPackage.article_signals : {};
    const contentType = cleanText(contentPackage?.content_type || "recipe");
    const rawKeywords = [
      contentType === "recipe" ? "recipe" : "food facts",
      cleanText(articleSignals.heading_topic || ""),
      cleanText(articleSignals.ingredient_focus || ""),
      cleanText(articleSignals.meta_line || ""),
    ]
      .join(" ")
      .toLowerCase()
      .replace(/[^a-z0-9\s,]/g, " ")
      .split(/[\s,]+/)
      .map((token) => token.trim())
      .filter((token) => token && token.length > 2);
    const pinKeywords = Array.from(new Set(rawKeywords)).slice(0, 8);
    const fallbackTitle = trimText(cleanText(contentPackage?.title || ""), 90);
    const fallbackDescription = trimText(
      cleanText(contentPackage?.excerpt || articleSignals?.summary_line || articleSignals?.payoff_line || ""),
      180,
    );

    return {
      pin_title: trimText(cleanText(existing?.pin_title || fallbackTitle), 100),
      pin_description: trimText(cleanText(existing?.pin_description || fallbackDescription), 300),
      pin_keywords: Array.isArray(existing?.pin_keywords)
        ? existing.pin_keywords.map((keyword) => cleanText(keyword)).filter(Boolean).slice(0, 12)
        : pinKeywords,
      image_prompt_override: cleanMultilineText(
        existing?.image_prompt_override
        || `${contentPackage?.image_prompt || ""}\nVertical Pinterest pin composition, 2:3 aspect ratio, clean focal hierarchy.`,
      ),
      image_format_hint: cleanText(existing?.image_format_hint || "1000x1500 vertical pin"),
      overlay_text: trimText(cleanText(existing?.overlay_text || fallbackTitle), 70),
      guidance: cleanMultilineText(existing?.guidance || ""),
    };
  }

  function buildFacebookGroupsDraft(contentPackage, existing = {}, fallbackShareKit = "", fallbackGuidance = "") {
    const articleSignals = isPlainObject(contentPackage?.article_signals) ? contentPackage.article_signals : {};
    const fallbackBlurb = cleanMultilineText(
      fallbackShareKit
      || `${cleanText(articleSignals.pain_line || articleSignals.summary_line || "")}\n${cleanText(articleSignals.payoff_line || contentPackage?.excerpt || "")}`.trim(),
    );

    return {
      share_blurb: cleanMultilineText(existing?.share_blurb || existing?.group_share_kit || fallbackBlurb),
      sharing_mode: cleanText(existing?.sharing_mode || "manual_operator_share") || "manual_operator_share",
      input_package: cleanText(existing?.input_package || "content_package") || "content_package",
      guidance: cleanMultilineText(existing?.guidance || existing?.share_guidance || fallbackGuidance),
    };
  }

  return {
    buildFacebookGroupsDraft,
    buildPinterestDraft,
  };
}
