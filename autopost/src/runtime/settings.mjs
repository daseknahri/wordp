import { CHANNEL_REGISTRY } from "../channels/adapter-profiles.mjs";
import { ACTIVE_CONTENT_TYPE_KEYS, CONTENT_TYPE_REGISTRY } from "../content/type-profiles.mjs";

export function createSettingsHelpers(deps) {
  const {
    cleanMultilineText,
    cleanText,
    config,
    isPlainObject,
    normalizePreset,
    normalizeFacebookPages,
    resolveTypedGuidance,
    trimTrailingSlash,
    PROMPT_VERSION,
  } = deps;

  function resolveContentMachine(raw) {
    const normalizedRaw = isPlainObject(raw) ? raw : {};
    const provided = isPlainObject(normalizedRaw.content_machine) ? normalizedRaw.content_machine : {};
    const publicationProfile = isPlainObject(provided.publication_profile) ? provided.publication_profile : {};
    const contentPresets = isPlainObject(provided.content_presets) ? provided.content_presets : {};
    const channelPresets = isPlainObject(provided.channel_presets) ? provided.channel_presets : {};
    const imagePlatformPresets = isPlainObject(channelPresets.image?.platforms) ? channelPresets.image.platforms : {};
    const cadence = isPlainObject(provided.cadence) ? provided.cadence : {};
    const models = isPlainObject(provided.models) ? provided.models : {};
    const contracts = isPlainObject(provided.contracts) ? provided.contracts : {};
    const sitePolicy = isPlainObject(provided.site_policy)
      ? provided.site_policy
      : (isPlainObject(provided.sitePolicy) ? provided.sitePolicy : {});
    const platformPolicy = isPlainObject(provided.platform_policy)
      ? provided.platform_policy
      : (isPlainObject(provided.platformPolicy) ? provided.platformPolicy : {});
    const postingPolicy = isPlainObject(provided.posting_policy)
      ? provided.posting_policy
      : (isPlainObject(provided.postingPolicy) ? provided.postingPolicy : {});
    const defaultCtas = isPlainObject(provided.default_ctas) ? provided.default_ctas : {};
    const facebookPostTeaserCta = cleanText(
      defaultCtas.facebook_post_teaser ||
      provided.facebook_post_teaser_cta ||
      normalizedRaw.facebook_post_teaser_cta ||
      "\u{1F447} Full article in the first comment below.",
    );
    const facebookCommentLinkCta = cleanText(
      defaultCtas.facebook_comment_link ||
      provided.facebook_comment_link_cta ||
      normalizedRaw.facebook_comment_link_cta ||
      provided.default_cta ||
      normalizedRaw.default_cta ||
      "Read the full article on the blog.",
    );

    return {
      promptVersion: String(provided.prompt_version || PROMPT_VERSION),
      publicationProfile: {
        id: String(publicationProfile.id || "default"),
        name: String(publicationProfile.name || normalizedRaw.site_name || config.fallbackSiteName),
        role: cleanMultilineText(
          publicationProfile.role ||
            normalizedRaw.publication_role ||
            `You are the lead editorial writer for ${String(publicationProfile.name || normalizedRaw.site_name || config.fallbackSiteName)}, producing recipe articles and food explainers that are sharp enough to win the click, useful enough to justify it, and strong enough to earn the next visit.`,
        ),
        voice_brief: cleanText(publicationProfile.voice_brief || normalizedRaw.brand_voice || config.fallbackBrandVoice),
        guardrails: cleanMultilineText(
          publicationProfile.guardrails ||
            normalizedRaw.global_guardrails ||
            [publicationProfile.do_guidance, publicationProfile.dont_guidance, publicationProfile.banned_claims]
              .filter(Boolean)
              .join("\n") ||
            "No fake personal stories, invented reporting, fabricated authority, or made-up facts. No filler SEO intros, generic throat-clearing, or padded explanations. No spammy clickbait, fake cliffhangers, or hollow viral language. No medical or nutrition claims beyond ordinary kitchen guidance. Keep paragraphs short, specific, and human. Avoid generic openers like 'when it comes to' or 'in today's busy world.' Never burn trust for the sake of a social click.",
        ),
      },
      contentTypeRegistry: CONTENT_TYPE_REGISTRY,
      activeContentTypes: ACTIVE_CONTENT_TYPE_KEYS,
      contentPresets: {
        recipe: normalizePreset(contentPresets.recipe, "Create dependable, craveable, and realistic home-cooking recipes with believable timings, coherent ingredient amounts, repeatable results, and enough practical detail to justify the click and earn a repeat visit.", 1200),
        food_fact: normalizePreset(contentPresets.food_fact, "Treat the entered title as a working topic, answer it directly, correct confusion, and finish with a practical takeaway worth the click and memorable enough to bring the reader back.", 1100),
      },
      channelRegistry: CHANNEL_REGISTRY,
      channelPresets: {
        recipe_master: {
          guidance: cleanMultilineText(
            channelPresets.recipe_master?.guidance ||
              normalizedRaw.recipe_master_prompt ||
              "Turn a dish name into a publishable multi-page recipe article package with a strong title, excerpt, SEO description, recipe-card readiness, and image direction."
          ),
        },
        article: {
          recipe: {
            guidance: cleanMultilineText(channelPresets.article?.recipe?.guidance || channelPresets.article?.guidance || normalizedRaw.article_prompt || "Open with appetite and concrete payoff, build 2 to 3 intentional pages, keep the recipe practical and credible, and make page 1 strong enough for social visitors while the full piece rewards repeat readers."),
          },
          food_fact: {
            guidance: cleanMultilineText(channelPresets.article?.food_fact?.guidance || normalizedRaw.food_fact_article_prompt || "Treat the entered title as a working topic, answer it fast, explain what people get wrong, and land a practical takeaway without drifting into recipe structure. Reward social visitors quickly and give returning readers a sharper kitchen insight."),
          },
        },
        facebook_caption: {
          recipe: {
            guidance: cleanMultilineText(channelPresets.facebook_caption?.recipe?.guidance || channelPresets.facebook_caption?.guidance || normalizedRaw.facebook_caption_guidance || "Generate a strong pool of recipe Facebook candidates with short hooks, 2 to 5 short caption lines, distinct angles, no title echo, no repeated hook-as-caption opener, and no links or hashtags. Pull the right reader onto the blog with an honest concrete payoff."),
          },
          food_fact: {
            guidance: cleanMultilineText(channelPresets.facebook_caption?.food_fact?.guidance || normalizedRaw.food_fact_facebook_caption_guidance || "Generate a strong pool of food-fact Facebook candidates with myth-busting, surprising-truth, or kitchen-mistake angles. No title echo, no links, no hashtags, and no empty hype. Make the click feel worth it and trustworthy."),
          },
        },
        facebook_groups: {
          guidance: cleanMultilineText(
            channelPresets.facebook_groups?.guidance
            || normalizedRaw.group_share_guidance
            || "Write a useful manual-share blurb for food groups that feels natural, highlights the practical payoff, and leaves the tracked link to the operator or follow-up."
          ),
        },
        pinterest: {
          guidance: cleanMultilineText(
            channelPresets.pinterest?.guidance
            || normalizedRaw.pinterest_guidance
            || "Draft a Pinterest-ready package with a keyword-clear title, a useful description, concise save-intent keywords, and vertical image direction anchored in the article's real payoff."
          ),
        },
        image: {
          guidance: cleanMultilineText(channelPresets.image?.guidance || normalizedRaw.image_style || "Use realistic, appetizing food photography with natural light, clean composition, believable texture, and no text overlays. For recipes, bias toward finished-dish hero imagery. For food explainers, use the most useful food subject for the article."),
          platforms: {
            blog: cleanMultilineText(imagePlatformPresets.blog || channelPresets.image?.blog || normalizedRaw.image_blog_guidance || ""),
            facebook: cleanMultilineText(imagePlatformPresets.facebook || channelPresets.image?.facebook || normalizedRaw.image_facebook_guidance || ""),
            pinterest: cleanMultilineText(imagePlatformPresets.pinterest || channelPresets.image?.pinterest || normalizedRaw.image_pinterest_guidance || ""),
          },
        },
      },
      cadence: {
        mode: String(cadence.mode || "manual_recipe_publish_at"),
        timezone: String(cadence.timezone || "UTC"),
      },
      models: {
        text_model: String(models.text_model || normalizedRaw.openai_model || config.fallbackTextModel),
        image_model: String(models.image_model || normalizedRaw.openai_image_model || "gpt-image-1.5"),
        repair_enabled: Boolean(models.repair_enabled ?? true),
        repair_attempts: Math.max(0, Number(models.repair_attempts ?? 1)),
      },
      contracts: {
        strict_contract_mode: Boolean(contracts.strict_contract_mode ?? normalizedRaw.strict_contract_mode ?? config.strictContractMode),
      },
      sitePolicy,
      platformPolicy,
      postingPolicy,
      defaultCtas: {
        facebook_post_teaser: facebookPostTeaserCta,
        facebook_comment_link: facebookCommentLinkCta,
      },
      facebookPostTeaserCta,
      facebookCommentLinkCta,
      defaultCta: facebookCommentLinkCta,
    };
  }

  function mergeSettings(raw) {
    const normalizedRaw = isPlainObject(raw) ? raw : {};
    const contentMachine = resolveContentMachine(normalizedRaw);
    const platformDelivery = isPlainObject(contentMachine.platformPolicy?.delivery) ? contentMachine.platformPolicy.delivery : {};
    const siteDelivery = isPlainObject(contentMachine.sitePolicy?.delivery) ? contentMachine.sitePolicy.delivery : {};
    const facebookPostTeaserCta =
      normalizedRaw.facebook_post_teaser_cta ||
      contentMachine.facebookPostTeaserCta ||
      "\u{1F447} Full article in the first comment below.";
    const facebookCommentLinkCta =
      normalizedRaw.facebook_comment_link_cta ||
      contentMachine.facebookCommentLinkCta ||
      contentMachine.defaultCta ||
      "Read the full article on the blog.";

    return {
      siteName: normalizedRaw.site_name || config.fallbackSiteName,
      siteUrl: trimTrailingSlash(normalizedRaw.site_url || config.publicWordPressUrl || config.internalWordPressUrl),
      brandVoice: contentMachine.publicationProfile.voice_brief || normalizedRaw.brand_voice || config.fallbackBrandVoice,
      articlePrompt: resolveTypedGuidance({ contentMachine }, "article", "recipe", normalizedRaw.article_prompt || "Open with appetite and payoff, use useful H2 sections, and keep the recipe practical, cookable, and worth the click."),
      facebookPostTeaserCta,
      facebookCommentLinkCta,
      defaultCta: facebookCommentLinkCta,
      imageStyle: contentMachine.channelPresets.image.guidance || normalizedRaw.image_style || "Realistic, appetizing food photography with natural light, clean plating, believable texture, and no text overlays.",
      imageGenerationMode: normalizedRaw.image_generation_mode || "uploaded_first_generate_missing",
      facebookGraphVersion: normalizedRaw.facebook_graph_version || "v22.0",
      facebookPageId: normalizedRaw.facebook_page_id || "",
      facebookPageAccessToken: normalizedRaw.facebook_page_access_token || "",
      facebookPages: normalizeFacebookPages(normalizedRaw.facebook_pages, normalizedRaw.facebook_page_id || "", normalizedRaw.facebook_page_access_token || ""),
      openaiModel: contentMachine.models.text_model || normalizedRaw.openai_model || config.fallbackTextModel,
      openaiImageModel: contentMachine.models.image_model || normalizedRaw.openai_image_model || "gpt-image-1.5",
      openaiApiKey: normalizedRaw.openai_api_key || config.fallbackOpenAiKey,
      openaiBaseUrl: trimTrailingSlash(normalizedRaw.openai_base_url || config.fallbackOpenAiBaseUrl),
      utmSource: normalizedRaw.utm_source || platformDelivery.utm_source || siteDelivery.utm_source || config.fallbackUtmSource,
      utmCampaignPrefix: normalizedRaw.utm_campaign_prefix || platformDelivery.utm_campaign_prefix || siteDelivery.utm_campaign_prefix || config.fallbackUtmCampaignPrefix,
      contentMachine,
    };
  }

  return {
    mergeSettings,
    resolveContentMachine,
  };
}
