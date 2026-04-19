export function createGeneratedPayloadHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    ensureInternalLinks,
    ensureInternalLinksOnPages,
    ensureStringArray,
    formatError,
    isPlainObject,
    mergeContentPagesIntoHtml,
    normalizeFacebookDistribution,
    normalizeGeneratedPageFlow,
    normalizeSlug,
    normalizeSocialPack,
    resolveGeneratedContentHtml,
    resolveGeneratedContentPages,
    splitHtmlIntoPages,
    stabilizeGeneratedContentPages,
    syncGeneratedContractContainers,
    trimText,
  } = deps;

  function resolveJobPublicationName(job) {
    const requestPayload = isPlainObject(job?.request_payload) ? job.request_payload : {};
    const contentMachine = isPlainObject(requestPayload.content_machine) ? requestPayload.content_machine : {};
    const sitePolicy = isPlainObject(contentMachine.site_policy)
      ? contentMachine.site_policy
      : (isPlainObject(contentMachine.sitePolicy) ? contentMachine.sitePolicy : {});

    return cleanText(
      sitePolicy.publication_name ||
        sitePolicy.publicationName ||
        requestPayload.site_name ||
        job?.site_name ||
        "",
    );
  }

  function coerceGeneratedPayload(value, depth = 0) {
    if (depth > 4) {
      return {};
    }

    if (isPlainObject(value)) {
      return value;
    }

    if (typeof value === "string") {
      const trimmed = value.trim();
      if (!trimmed) {
        return {};
      }

      if ((trimmed.startsWith("{") && trimmed.endsWith("}")) || (trimmed.startsWith("[") && trimmed.endsWith("]"))) {
        try {
          return coerceGeneratedPayload(JSON.parse(trimmed), depth + 1);
        } catch {
          return {};
        }
      }

      return {};
    }

    if (Array.isArray(value)) {
      if (value.length === 1) {
        return coerceGeneratedPayload(value[0], depth + 1);
      }

      const firstObject = value.find((item) => isPlainObject(item) || typeof item === "string" || Array.isArray(item));
      return firstObject ? coerceGeneratedPayload(firstObject, depth + 1) : {};
    }

    return {};
  }

  function generatedPayloadContainers(source) {
    const containers = [];
    const queue = [source];
    const seen = new Set();
    const nestedKeys = [
      "article",
      "post",
      "content",
      "data",
      "result",
      "output",
      "payload",
      "article_package",
      "articlePackage",
      "recipe_package",
      "recipePackage",
      "content_package",
      "contentPackage",
      "blog_post",
      "blogPost",
      "channels",
      "facebook",
      "pinterest",
    ];

    while (queue.length && containers.length < 16) {
      const current = queue.shift();
      if (!isPlainObject(current) || seen.has(current)) {
        continue;
      }

      seen.add(current);
      containers.push(current);

      for (const key of nestedKeys) {
        if (isPlainObject(current[key])) {
          queue.push(current[key]);
        }
      }
    }

    return containers;
  }

  function readGeneratedString(source, keys) {
    for (const container of generatedPayloadContainers(source)) {
      for (const key of keys) {
        const value = container[key];
        if (typeof value === "string" && cleanText(value)) {
          return value;
        }

        if (Array.isArray(value)) {
          const joined = cleanMultilineText(
            value
              .map((item) => {
                if (typeof item === "string") {
                  return item;
                }

                if (isPlainObject(item)) {
                  return cleanMultilineText(item.html || item.text || item.content || item.body || item.value || "");
                }

                return "";
              })
              .filter(Boolean)
              .join("\n\n"),
          );

          if (joined) {
            return joined;
          }
        }
      }
    }

    return "";
  }

  function readGeneratedObject(source, keys) {
    for (const container of generatedPayloadContainers(source)) {
      for (const key of keys) {
        if (isPlainObject(container[key])) {
          return container[key];
        }
      }
    }

    return null;
  }

  function readGeneratedArray(source, keys) {
    for (const container of generatedPayloadContainers(source)) {
      for (const key of keys) {
        if (Array.isArray(container[key])) {
          return container[key];
        }
      }
    }

    return [];
  }

  function describeGeneratedShape(source) {
    const keys = new Set();

    for (const container of generatedPayloadContainers(source)) {
      Object.keys(container).forEach((key) => keys.add(key));
    }

    return Array.from(keys).sort().join(", ") || "none";
  }

  function describeGeneratedType(value) {
    if (Array.isArray(value)) {
      return `array(${value.length})`;
    }

    if (value === null) {
      return "null";
    }

    return typeof value;
  }

  function previewGeneratedValue(value) {
    if (isPlainObject(value)) {
      return trimText(JSON.stringify(value).replace(/\s+/g, " "), 220) || "empty-object";
    }

    if (Array.isArray(value)) {
      return trimText(JSON.stringify(value).replace(/\s+/g, " "), 220) || "empty-array";
    }

    return trimText(String(value || "").replace(/\s+/g, " "), 220) || "empty";
  }

  function normalizeRecipe(value, contentType) {
    if (contentType !== "recipe") {
      return {
        prep_time: "",
        cook_time: "",
        total_time: "",
        yield: "",
        ingredients: [],
        instructions: [],
      };
    }

    const recipe = isPlainObject(value) ? value : {};
    return {
      prep_time: cleanText(recipe.prep_time),
      cook_time: cleanText(recipe.cook_time),
      total_time: cleanText(recipe.total_time),
      yield: cleanText(recipe.yield),
      ingredients: ensureStringArray(recipe.ingredients),
      instructions: ensureStringArray(recipe.instructions),
    };
  }

  function normalizeGeneratedPayload(raw, job) {
    const source = coerceGeneratedPayload(raw);
    const channelsSource = readGeneratedObject(source, ["channels", "channel_outputs", "channelOutputs"]) || {};
    const facebookSource = isPlainObject(channelsSource?.facebook) ? channelsSource.facebook : {};
    const titleOverride = cleanText(job?.title_override || "");
    const publicationName = resolveJobPublicationName(job) || "this publication";
    const title =
      titleOverride ||
      cleanText(readGeneratedString(source, ["title", "headline", "post_title", "postTitle", "name"])) ||
      cleanText(job?.topic) ||
      `Fresh from ${publicationName}`;
    const slug = normalizeSlug(readGeneratedString(source, ["slug", "post_slug", "postSlug"]) || title);
    let contentPages = resolveGeneratedContentPages(source, job);
    const sourceContentHtml = resolveGeneratedContentHtml(source, job);
    if (!contentPages.length) {
      contentPages = sourceContentHtml ? splitHtmlIntoPages(sourceContentHtml, job?.content_type || "recipe").slice(0, 3) : [];
    }
    contentPages = stabilizeGeneratedContentPages(contentPages, sourceContentHtml, job?.content_type || "recipe");
    contentPages = ensureInternalLinksOnPages(contentPages, job);
    const pageFlow = normalizeGeneratedPageFlow(
      readGeneratedArray(source, ["page_flow", "pageFlow", "content_page_flow", "contentPageFlow"]),
      contentPages,
    );
    const contentHtml = contentPages.length ? mergeContentPagesIntoHtml(contentPages, null, job) : ensureInternalLinks(sourceContentHtml, job, null);
    const sourceContentText = cleanText(String(contentHtml || "").replace(/<[^>]+>/g, " "));
    const fallbackExcerpt = trimText(sourceContentText.split(/(?<=[.!?])\s+/)[0] || sourceContentText, 220);
    const excerpt =
      trimText(cleanText(readGeneratedString(source, ["excerpt", "summary", "dek", "standfirst", "description"])), 220) ||
      fallbackExcerpt ||
      `${title} on ${publicationName}.`;
    const seoDescription =
      trimText(cleanText(readGeneratedString(source, ["seo_description", "seoDescription", "meta_description", "metaDescription", "search_description", "searchDescription"])), 155) ||
      trimText(excerpt, 155);
    const legacyFacebookCaption = cleanMultilineText(readGeneratedString(source, ["facebook_caption", "facebookCaption"]));
    const groupShareKit = cleanMultilineText(readGeneratedString(source, ["group_share_kit", "groupShareKit"]));
    const imagePrompt =
      cleanMultilineText(readGeneratedString(source, ["image_prompt", "imagePrompt", "hero_image_prompt", "heroImagePrompt"])) ||
      `Editorial food photography of ${title}, premium magazine lighting, appetizing detail, natural styling, no text overlay.`;
    const imageAlt = cleanText(readGeneratedString(source, ["image_alt", "imageAlt", "hero_image_alt", "heroImageAlt", "alt_text", "altText"])) || title;
    const recipe = normalizeRecipe(readGeneratedObject(source, ["recipe", "recipe_card", "recipeCard"]) || {}, job?.content_type || "recipe");
    const socialPack = normalizeSocialPack(
      Array.isArray(facebookSource.selected) ? facebookSource.selected
        : (Array.isArray(facebookSource.social_pack) ? facebookSource.social_pack : readGeneratedArray(source, ["social_pack", "socialPack", "facebook_variants", "facebookVariants"])),
      job?.content_type || "recipe",
    );
    const socialCandidates = normalizeSocialPack(
      Array.isArray(facebookSource.candidates) ? facebookSource.candidates
        : (Array.isArray(facebookSource.social_candidates) ? facebookSource.social_candidates : readGeneratedArray(source, ["social_candidates", "socialCandidates"])),
      job?.content_type || "recipe",
    );
    const facebookDistribution = normalizeFacebookDistribution(
      isPlainObject(facebookSource.distribution) ? facebookSource.distribution
        : (isPlainObject(facebookSource.facebook_distribution) ? facebookSource.facebook_distribution : (readGeneratedObject(source, ["facebook_distribution", "facebookDistribution"]) || {})),
      job?.content_type || "recipe",
    );

    if (!contentHtml) {
      throw new Error(
        `The generated article body was empty. Parsed type: ${describeGeneratedType(raw)}. Parsed keys: ${describeGeneratedShape(source)}. Raw preview: ${previewGeneratedValue(raw)}.`,
      );
    }

    if ((job?.content_type || "") === "recipe" && (!recipe.ingredients.length || !recipe.instructions.length)) {
      throw new Error("The generated recipe is missing ingredients or instructions.");
    }

    return syncGeneratedContractContainers({
      content_package: readGeneratedObject(source, ["content_package", "contentPackage"]) || {},
      channels: isPlainObject(channelsSource) ? channelsSource : {},
      title,
      slug,
      excerpt,
      seo_description: seoDescription,
      content_pages: contentPages.length ? contentPages : ensureInternalLinksOnPages(splitHtmlIntoPages(contentHtml, job?.content_type || "recipe").slice(0, 3), job),
      page_flow: pageFlow,
      content_html: contentHtml,
      facebook_caption: legacyFacebookCaption,
      group_share_kit: groupShareKit,
      image_prompt: imagePrompt,
      image_alt: imageAlt,
      recipe,
      social_pack: socialPack,
      social_candidates: socialCandidates,
      facebook_distribution: facebookDistribution,
      assets: readGeneratedObject(source, ["assets"]) || {},
      facebook_urls: readGeneratedObject(source, ["facebook_urls", "facebookUrls"]) || {},
      content_machine: readGeneratedObject(source, ["content_machine", "contentMachine"]) || {},
    }, job);
  }

  function emptyGeneratedPayload(job) {
    const title = cleanText(job?.title_override || job?.topic || "");

    return syncGeneratedContractContainers({
      title,
      slug: title ? normalizeSlug(title) : "",
      excerpt: "",
      seo_description: "",
      content_pages: [],
      page_flow: [],
      content_html: "",
      facebook_caption: "",
      group_share_kit: "",
      image_prompt: "",
      image_alt: title,
      recipe: normalizeRecipe({}, job?.content_type || "recipe"),
      social_pack: [],
      social_candidates: [],
      facebook_distribution: normalizeFacebookDistribution({}, job?.content_type || "recipe"),
      assets: {},
      facebook_urls: {},
      content_machine: {},
    }, job);
  }

  function hydrateStoredGeneratedPayload(raw, job) {
    const source = coerceGeneratedPayload(raw);
    if (!Object.keys(source).length) {
      return emptyGeneratedPayload(job);
    }

    try {
      return normalizeGeneratedPayload(source, job);
    } catch (error) {
      const message = formatError(error);
      if (/generated article body was empty|generated recipe is missing ingredients or instructions/i.test(message)) {
        return emptyGeneratedPayload(job);
      }
      throw error;
    }
  }

  return {
    coerceGeneratedPayload,
    describeGeneratedShape,
    describeGeneratedType,
    emptyGeneratedPayload,
    generatedPayloadContainers,
    hydrateStoredGeneratedPayload,
    normalizeGeneratedPayload,
    normalizeRecipe,
    previewGeneratedValue,
    readGeneratedArray,
    readGeneratedObject,
    readGeneratedString,
  };
}
