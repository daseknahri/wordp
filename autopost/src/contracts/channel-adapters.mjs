export function createChannelAdapterHelpers(deps) {
  const {
    buildArticleSocialSignals,
    buildChannelProfile,
    buildContentPackageQualitySummary,
    buildContentTypeProfile,
    buildDormantChannelQualitySummary,
    buildFacebookChannelQualitySummary,
    buildFacebookGroupsDraft,
    buildPinterestDraft,
    buildFacebookPostMessage,
    CHANNEL_ADAPTER_CONTRACT_VERSION,
    cleanMultilineText,
    cleanText,
    CONTENT_PACKAGE_CONTRACT_VERSION,
    isPlainObject,
    mergeContentPagesIntoHtml,
    normalizeFacebookDistribution,
    normalizeGeneratedPageFlow,
    normalizeRecipe,
    normalizeSlug,
    normalizeSocialPack,
    splitHtmlIntoPages,
    stabilizeGeneratedContentPages,
    trimText,
  } = deps;

  function resolveFacebookDefaultCtas(generated, job = null) {
    const generatedContentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
    const requestPayload = isPlainObject(job?.request_payload) ? job.request_payload : {};
    const requestContentMachine = isPlainObject(requestPayload.content_machine) ? requestPayload.content_machine : {};
    const generatedDefaultCtas = isPlainObject(generatedContentMachine.default_ctas) ? generatedContentMachine.default_ctas : {};
    const requestDefaultCtas = isPlainObject(requestContentMachine.default_ctas) ? requestContentMachine.default_ctas : {};

    const facebookPostTeaserCta = cleanText(
      generated?.facebook_post_teaser_cta ||
      requestPayload.facebook_post_teaser_cta ||
      generatedDefaultCtas.facebook_post_teaser ||
      requestDefaultCtas.facebook_post_teaser ||
      "\u{1F447} Full article in the first comment below.",
    );
    const facebookCommentLinkCta = cleanText(
      generated?.facebook_comment_link_cta ||
      requestPayload.facebook_comment_link_cta ||
      generatedDefaultCtas.facebook_comment_link ||
      requestDefaultCtas.facebook_comment_link ||
      generatedContentMachine.default_cta ||
      requestContentMachine.default_cta ||
      generated?.default_cta ||
      requestPayload.default_cta ||
      "Read the full article on the blog.",
    );

    return {
      facebookPostTeaserCta,
      facebookCommentLinkCta,
    };
  }

  function resolveCanonicalContentPackage(generated, job = null) {
    const existingPackage = isPlainObject(generated?.content_package) ? generated.content_package : {};
    const contentType = cleanText(existingPackage.content_type || generated?.content_type || job?.content_type || "recipe") || "recipe";
    const topicSeed = cleanText(existingPackage.topic_seed || generated?.topic_seed || job?.topic || "");
    const title = cleanText(existingPackage.title || generated?.title || job?.title_override || job?.topic || "");
    const slug = normalizeSlug(existingPackage.slug || generated?.slug || title);
    const excerpt = trimText(cleanText(existingPackage.excerpt || generated?.excerpt || ""), 220);
    const seoDescription = trimText(cleanText(existingPackage.seo_description || generated?.seo_description || ""), 155);
    const contentPages = Array.isArray(existingPackage.content_pages) && existingPackage.content_pages.length
      ? existingPackage.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "")
      : (Array.isArray(generated?.content_pages) ? generated.content_pages.map((page) => String(page || "")).filter((page) => cleanText(page.replace(/<[^>]+>/g, " ")) !== "") : []);
    const contentHtml = cleanMultilineText(existingPackage.content_html || generated?.content_html || "") || mergeContentPagesIntoHtml(contentPages);
    const stabilizedPages = contentPages.length
      ? stabilizeGeneratedContentPages(contentPages, contentHtml, contentType)
      : splitHtmlIntoPages(contentHtml, contentType).slice(0, 3);
    const pageFlow = normalizeGeneratedPageFlow(
      Array.isArray(existingPackage.page_flow) ? existingPackage.page_flow : (Array.isArray(generated?.page_flow) ? generated.page_flow : []),
      stabilizedPages,
    );
    const imagePrompt = cleanMultilineText(existingPackage.image_prompt || generated?.image_prompt || "");
    const imageAlt = cleanText(existingPackage.image_alt || generated?.image_alt || title);
    const recipe = contentType === "recipe"
      ? normalizeRecipe(isPlainObject(existingPackage.recipe) ? existingPackage.recipe : (isPlainObject(generated?.recipe) ? generated.recipe : {}), contentType)
      : undefined;
    const articleBase = {
      contract_version: cleanText(existingPackage.contract_version || "") || CONTENT_PACKAGE_CONTRACT_VERSION,
      package_shape: cleanText(existingPackage.package_shape || "") || "canonical_content_package",
      source_layer: cleanText(existingPackage.source_layer || "") || "article_engine",
      content_type: contentType,
      topic_seed: topicSeed,
      title,
      slug,
      excerpt,
      seo_description: seoDescription,
      content_html: contentHtml,
      content_pages: stabilizedPages,
      page_flow: pageFlow,
      image_prompt: imagePrompt,
      image_alt: imageAlt,
      ...(recipe ? { recipe } : {}),
    };

    return {
      ...articleBase,
      profile: isPlainObject(existingPackage.profile)
        ? { ...buildContentTypeProfile(contentType), ...existingPackage.profile }
        : buildContentTypeProfile(contentType),
      article_signals: isPlainObject(existingPackage.article_signals) && Object.keys(existingPackage.article_signals).length
        ? existingPackage.article_signals
        : buildArticleSocialSignals(articleBase, contentType),
      quality_summary: {
        ...(isPlainObject(existingPackage.quality_summary) ? existingPackage.quality_summary : {}),
        ...buildContentPackageQualitySummary(generated),
      },
    };
  }

  function resolveFacebookChannelAdapter(generated, job = null) {
    const existingChannels = isPlainObject(generated?.channels) ? generated.channels : {};
    const existingChannel = isPlainObject(existingChannels.facebook) ? existingChannels.facebook : {};
    const contentType = cleanText(generated?.content_type || job?.content_type || "recipe") || "recipe";
    const candidates = normalizeSocialPack(
      Array.isArray(existingChannel.candidates) ? existingChannel.candidates
        : (Array.isArray(existingChannel.social_candidates) ? existingChannel.social_candidates : generated?.social_candidates),
      contentType,
    );
    const selected = normalizeSocialPack(
      Array.isArray(existingChannel.selected) ? existingChannel.selected
        : (Array.isArray(existingChannel.social_pack) ? existingChannel.social_pack : generated?.social_pack),
      contentType,
    );
    const distribution = normalizeFacebookDistribution(
      isPlainObject(existingChannel.distribution) ? existingChannel.distribution
        : (isPlainObject(existingChannel.facebook_distribution) ? existingChannel.facebook_distribution : generated?.facebook_distribution),
      contentType,
    );

    return {
      channel: "facebook",
      contract_version: cleanText(existingChannel.contract_version || "") || CHANNEL_ADAPTER_CONTRACT_VERSION,
      live: true,
      profile: isPlainObject(existingChannel.profile)
        ? { ...buildChannelProfile("facebook"), ...existingChannel.profile }
        : buildChannelProfile("facebook"),
      input_package: cleanText(existingChannel.input_package || "") || "content_package",
      candidates,
      selected,
      distribution,
      quality_summary: {
        ...(isPlainObject(existingChannel.quality_summary) ? existingChannel.quality_summary : {}),
        ...buildFacebookChannelQualitySummary({
          ...generated,
          content_type: contentType,
          social_candidates: candidates,
          social_pack: selected,
          facebook_distribution: distribution,
        }),
      },
    };
  }

  function resolvePinterestChannelAdapter(generated, job = null) {
    const existingChannels = isPlainObject(generated?.channels) ? generated.channels : {};
    const existingChannel = isPlainObject(existingChannels.pinterest) ? existingChannels.pinterest : {};
    const contentPackage = resolveCanonicalContentPackage(generated, job);

    return {
      channel: "pinterest",
      contract_version: cleanText(existingChannel.contract_version || "") || CHANNEL_ADAPTER_CONTRACT_VERSION,
      live: false,
      profile: isPlainObject(existingChannel.profile)
        ? { ...buildChannelProfile("pinterest"), ...existingChannel.profile }
        : buildChannelProfile("pinterest"),
      input_package: cleanText(existingChannel.input_package || "") || "content_package",
      draft: buildPinterestDraft(contentPackage, isPlainObject(existingChannel.draft) ? existingChannel.draft : {}),
      quality_summary: {
        ...(isPlainObject(existingChannel.quality_summary) ? existingChannel.quality_summary : {}),
        ...buildDormantChannelQualitySummary(generated, "pinterest"),
      },
    };
  }

  function resolveFacebookGroupsChannelAdapter(generated, job = null) {
    const existingChannels = isPlainObject(generated?.channels) ? generated.channels : {};
    const existingChannel = isPlainObject(existingChannels.facebook_groups) ? existingChannels.facebook_groups : {};
    const contentPackage = resolveCanonicalContentPackage(generated, job);

    return {
      channel: "facebook_groups",
      contract_version: cleanText(existingChannel.contract_version || "") || CHANNEL_ADAPTER_CONTRACT_VERSION,
      live: false,
      profile: isPlainObject(existingChannel.profile)
        ? { ...buildChannelProfile("facebook_groups"), ...existingChannel.profile }
        : buildChannelProfile("facebook_groups"),
      input_package: cleanText(existingChannel.input_package || "") || "content_package",
      draft: buildFacebookGroupsDraft(
        contentPackage,
        isPlainObject(existingChannel.draft) ? existingChannel.draft : {},
        cleanMultilineText(generated?.group_share_kit || ""),
      ),
      quality_summary: {
        ...(isPlainObject(existingChannel.quality_summary) ? existingChannel.quality_summary : {}),
        ...buildDormantChannelQualitySummary(generated, "facebook_groups"),
      },
    };
  }

  function deriveLegacyFacebookCaptionMirror(facebookChannel, fallbackCaption = "", teaserCta = "") {
    const selected = Array.isArray(facebookChannel?.selected) ? facebookChannel.selected : [];
    const firstSelected = selected.find((variant) => isPlainObject(variant));
    if (firstSelected) {
      return buildFacebookPostMessage(firstSelected, cleanMultilineText(firstSelected.caption || fallbackCaption || ""), teaserCta);
    }

    const distributionPages = isPlainObject(facebookChannel?.distribution?.pages)
      ? Object.values(facebookChannel.distribution.pages)
      : [];
    const firstPage = distributionPages.find((page) => isPlainObject(page));
    if (firstPage) {
      return buildFacebookPostMessage(firstPage, cleanMultilineText(firstPage.caption || fallbackCaption || ""), teaserCta);
    }

    return cleanMultilineText(fallbackCaption || "");
  }

  function deriveLegacyGroupShareKitMirror(generated, job = null) {
    const groupChannel = resolveFacebookGroupsChannelAdapter(generated, job);
    return cleanMultilineText(groupChannel?.draft?.share_blurb || generated?.group_share_kit || "");
  }

  function syncGeneratedContractContainers(generated, job = null) {
    const contentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
    const contracts = isPlainObject(contentMachine.contracts) ? contentMachine.contracts : {};
    const hasLegacyFlag = typeof contracts.legacy_job === "boolean";
    const hasTypedFlag = typeof contracts.typed_contract_job === "boolean";
    let legacyJob = hasLegacyFlag ? Boolean(contracts.legacy_job) : null;
    let typedJob = hasTypedFlag ? Boolean(contracts.typed_contract_job) : null;
    if (!hasLegacyFlag && !hasTypedFlag) {
      legacyJob = true;
      typedJob = false;
    } else if (legacyJob === null) {
      legacyJob = !typedJob;
    } else if (typedJob === null) {
      typedJob = !legacyJob;
    }
    const rawChannels = isPlainObject(generated?.channels) ? generated.channels : {};
    const fallbacks = {
      content_package: !isPlainObject(generated?.content_package) || !Object.keys(generated.content_package || {}).length,
      facebook: !isPlainObject(rawChannels.facebook) || !Object.keys(rawChannels.facebook || {}).length,
      facebook_groups: !isPlainObject(rawChannels.facebook_groups) || !Object.keys(rawChannels.facebook_groups || {}).length,
      pinterest: !isPlainObject(rawChannels.pinterest) || !Object.keys(rawChannels.pinterest || {}).length,
    };
    const contentPackage = resolveCanonicalContentPackage(generated, job);
    const facebookChannel = resolveFacebookChannelAdapter({ ...generated, content_package: contentPackage }, job);
    const pinterestChannel = resolvePinterestChannelAdapter({ ...generated, content_package: contentPackage }, job);
    const facebookGroupsChannel = resolveFacebookGroupsChannelAdapter({ ...generated, content_package: contentPackage }, job);
    const defaultCtas = resolveFacebookDefaultCtas(generated, job);
    const legacyFacebookCaption = deriveLegacyFacebookCaptionMirror(
      facebookChannel,
      cleanMultilineText(generated?.facebook_caption || ""),
      defaultCtas.facebookPostTeaserCta,
    );
    const topLevelRecipe = contentPackage.content_type === "recipe"
      ? normalizeRecipe(contentPackage.recipe || generated?.recipe || {}, contentPackage.content_type)
      : normalizeRecipe({}, contentPackage.content_type);

    return {
      ...generated,
      content_type: contentPackage.content_type,
      topic_seed: contentPackage.topic_seed,
      title: contentPackage.title,
      slug: contentPackage.slug,
      excerpt: contentPackage.excerpt,
      seo_description: contentPackage.seo_description,
      content_html: contentPackage.content_html,
      content_pages: contentPackage.content_pages,
      page_flow: contentPackage.page_flow,
      image_prompt: contentPackage.image_prompt,
      image_alt: contentPackage.image_alt,
      recipe: topLevelRecipe,
      social_candidates: facebookChannel.candidates,
      social_pack: facebookChannel.selected,
      facebook_distribution: facebookChannel.distribution,
      facebook_caption: legacyFacebookCaption,
      group_share_kit: deriveLegacyGroupShareKitMirror({ ...generated, channels: { ...(isPlainObject(generated?.channels) ? generated.channels : {}), facebook_groups: facebookGroupsChannel } }, job),
      content_machine: {
        ...contentMachine,
        default_ctas: {
          facebook_post_teaser: defaultCtas.facebookPostTeaserCta,
          facebook_comment_link: defaultCtas.facebookCommentLinkCta,
        },
        default_cta: defaultCtas.facebookCommentLinkCta,
        contracts: {
          ...contracts,
          legacy_job: legacyJob,
          typed_contract_job: typedJob,
          fallbacks,
        },
      },
      content_package: contentPackage,
      channels: {
        facebook: facebookChannel,
        facebook_groups: facebookGroupsChannel,
        pinterest: pinterestChannel,
      },
    };
  }

  return {
    deriveLegacyFacebookCaptionMirror,
    deriveLegacyGroupShareKitMirror,
    resolveCanonicalContentPackage,
    resolveFacebookDefaultCtas,
    resolveFacebookChannelAdapter,
    resolveFacebookGroupsChannelAdapter,
    resolvePinterestChannelAdapter,
    syncGeneratedContractContainers,
  };
}
