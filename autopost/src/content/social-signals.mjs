export function createSocialSignalHelpers(deps) {
  const {
    cleanText,
    trimText,
    trimWords,
    ensureStringArray,
    joinNaturalList,
    sharedWordsRatio,
    sentenceCase,
    firstSentence,
    normalizeGeneratedPageFlow,
    splitHtmlIntoPages,
    isPlainObject,
  } = deps;

  const extractArticleHeadings = (article, limit = 5) => {
    const html = Array.isArray(article?.content_pages) && article.content_pages.length
      ? article.content_pages.join("\n")
      : String(article?.content_html || "");

    return Array.from(html.matchAll(/<h2\b[^>]*>(.*?)<\/h2>/gi))
      .map((match) => cleanText(String(match[1] || "").replace(/<[^>]+>/g, " ")))
      .filter(Boolean)
      .slice(0, limit);
  };

  const extractArticlePlainText = (article, maxLength = 900) => {
    const html = Array.isArray(article?.content_pages) && article.content_pages.length
      ? article.content_pages.join(" ")
      : String(article?.content_html || "");

    return trimText(cleanText(html.replace(/<[^>]+>/g, " ")), maxLength);
  };

  const cleanHeadingTopic = (value) => trimText(
    cleanText(
      String(value || "")
        .replace(/^[0-9]+\s*[:.)-]?\s*/, "")
        .replace(/[.!?]+$/g, ""),
    ),
    90,
  );

  const buildSocialHookTopic = (title, contentType = "recipe") => {
    const source = contentType === "recipe"
      ? cleanText(title)
      : cleanText(String(title || "").replace(/^\d+\s+/, ""));

    return trimWords(cleanHeadingTopic(source) || cleanText(title), contentType === "recipe" ? 8 : 7);
  };

  const findSpecificArticleHeading = (headings, contentType = "recipe") => {
    const genericPatterns = contentType === "recipe"
      ? [
          /^why this recipe works$/i,
          /^what you'?ll need$/i,
          /^ingredient notes?$/i,
          /^ingredients?$/i,
          /^instructions?$/i,
          /^how to make\b/i,
          /^serving(?: and storage)?$/i,
          /^storage(?: and reheating)?$/i,
          /^recipe notes?$/i,
          /^tips?$/i,
        ]
      : [
          /^why it matters$/i,
          /^the takeaway$/i,
          /^bottom line$/i,
          /^what this means$/i,
          /^final thoughts?$/i,
        ];

    return ensureStringArray(headings).find((heading) => !genericPatterns.some((pattern) => pattern.test(heading))) || ensureStringArray(headings)[0] || "";
  };

  const extractRecipeIngredientFocus = (ingredients, limit = 3) => {
    const ignoreTokens = new Set([
      "cup", "cups", "tablespoon", "tablespoons", "tbsp", "teaspoon", "teaspoons", "tsp",
      "gram", "grams", "g", "kg", "kilogram", "kilograms", "ml", "l", "oz", "ounce", "ounces",
      "lb", "lbs", "pound", "pounds", "pinch", "dash", "can", "cans", "package", "packages",
      "clove", "cloves", "slice", "slices", "small", "medium", "large", "extra", "freshly",
      "ground", "optional", "divided", "plus", "more", "less", "to", "taste", "boneless",
      "skinless", "chopped", "diced", "minced", "shredded", "grated",
    ]);
    const lowValuePhrases = new Set(["salt", "black pepper", "pepper", "water"]);
    const focus = [];
    const seen = new Set();

    for (const ingredient of ensureStringArray(ingredients)) {
      const cleaned = cleanText(
        ingredient
          .toLowerCase()
          .replace(/\([^)]*\)/g, " ")
          .replace(/^[0-9\/.,\-\s]+/, " ")
          .replace(/[^a-z0-9\s]/g, " "),
      );
      const tokens = cleaned
        .split(/\s+/)
        .map((token) => token.trim())
        .filter((token) => token && !ignoreTokens.has(token) && !/^\d/.test(token));
      const phrase = tokens.slice(0, 2).join(" ").trim();

      if (!phrase || lowValuePhrases.has(phrase) || seen.has(phrase)) {
        continue;
      }

      seen.add(phrase);
      focus.push(phrase);

      if (focus.length >= limit) {
        break;
      }
    }

    return joinNaturalList(focus);
  };

  const resolveArticlePageFlow = (article, contentType = "recipe") => {
    const contentPages = Array.isArray(article?.content_pages) && article.content_pages.length
      ? article.content_pages
      : splitHtmlIntoPages(String(article?.content_html || ""), contentType).slice(0, 3);

    return normalizeGeneratedPageFlow(Array.isArray(article?.page_flow) ? article.page_flow : [], contentPages);
  };

  const buildArticleSocialSignals = (article, contentType = "recipe") => {
    const title = cleanText(article?.title || "");
    const headings = extractArticleHeadings(article, 5);
    const headingTopic = cleanHeadingTopic(findSpecificArticleHeading(headings, contentType));
    const pageFlow = resolveArticlePageFlow(article, contentType);
    const excerptSentence = firstSentence(article?.excerpt || "", 150);
    const bodySentence = firstSentence(extractArticlePlainText(article, 500), 150);
    const summaryLine = [excerptSentence, bodySentence].find((line) => line && sharedWordsRatio(line, title) < 0.85) || excerptSentence || bodySentence || "";
    const pageSignalLine = trimText(
      pageFlow
        .map((page) => cleanText([page?.label || "", page?.summary || ""].filter(Boolean).join(". ")))
        .find((line) => line && sharedWordsRatio(line, title) < 0.85 && sharedWordsRatio(line, summaryLine || title) < 0.9) || "",
      150,
    );
    const finalPage = pageFlow.length ? pageFlow[pageFlow.length - 1] : null;
    const finalRewardLine = trimText(cleanText([finalPage?.label || "", finalPage?.summary || ""].filter(Boolean).join(". ")), 150);
    const recipe = isPlainObject(article?.recipe) ? article.recipe : {};
    const ingredientFocus = contentType === "recipe" ? extractRecipeIngredientFocus(recipe.ingredients || [], 3) : "";
    const metaBits = [];

    if (contentType === "recipe") {
      if (cleanText(recipe.total_time || "")) {
        metaBits.push(`${cleanText(recipe.total_time)} total`);
      }
      if (cleanText(recipe.yield || "")) {
        metaBits.push(`makes ${cleanText(recipe.yield)}`);
      }
    }

    const metaLine = metaBits.length ? sentenceCase(metaBits.join(" and ")) : "";
    const proofLine = trimText(
      contentType === "recipe"
        ? (
            ingredientFocus
              ? `The payoff leans on ${ingredientFocus} without turning dinner into a project.`
              : (metaLine
                  ? `${metaLine} with a method that stays clear and repeatable.`
                  : (headingTopic
                      ? `It leans into ${headingTopic} without overcomplicating dinner.`
                      : "It keeps the payoff high without making the method drag."))
          )
        : (
            headingTopic
              ? `It gets specific about ${headingTopic} instead of staying vague.`
              : "It moves past the fuzzy version and gives the useful kitchen answer."
          ),
      150,
    );
    const detailLine = trimText(
      contentType === "recipe"
        ? (
            pageSignalLine && sharedWordsRatio(pageSignalLine, summaryLine || title) < 0.9 && sharedWordsRatio(pageSignalLine, proofLine || title) < 0.9
              ? pageSignalLine
              : (
                headingTopic && !proofLine.toLowerCase().includes(headingTopic.toLowerCase())
              ? `It also leans into ${headingTopic} so the article earns the click.`
              : (metaLine && !proofLine.toLowerCase().includes(metaLine.toLowerCase())
                  ? `${metaLine} without padding the method.`
                  : (finalRewardLine && sharedWordsRatio(finalRewardLine, proofLine || title) < 0.9 ? finalRewardLine : ""))
              )
          )
        : (
            pageSignalLine && sharedWordsRatio(pageSignalLine, summaryLine || title) < 0.9
              ? pageSignalLine
              : (
                bodySentence && bodySentence !== summaryLine && sharedWordsRatio(bodySentence, title) < 0.9
              ? bodySentence
              : (headingTopic ? `The useful part is what ${headingTopic} changes in a real kitchen.` : (finalRewardLine || ""))
              )
          ),
      150,
    );
    const painLine = trimText(
      contentType === "recipe"
        ? (
            headingTopic
              ? `It solves the usual ${headingTopic.toLowerCase()} problem without turning dinner into a project.`
              : (metaLine
                  ? `It solves the \"too much effort for a satisfying meal\" problem while staying practical.`
                  : "It solves the \"what can I make that still feels worth it\" problem without making the method drag."))
        : (
            headingTopic
              ? `It fixes what most people get wrong about ${headingTopic}.`
              : "It clears up a kitchen belief that wastes time, creates confusion, or leads to worse results."
          ),
      150,
    );
    const consequenceLine = trimText(
      contentType === "recipe"
        ? (
            headingTopic
              ? `Miss that ${headingTopic.toLowerCase()} detail and dinner starts feeling like more effort for less payoff.`
              : (ingredientFocus
                  ? `Miss the ${ingredientFocus} detail and the result starts feeling less worth the effort.`
                  : "Miss the useful detail and dinner slips back into more effort, less payoff, or another flat repeat."))
        : (
            headingTopic
              ? `Miss that ${headingTopic.toLowerCase()} detail and the same fuzzy kitchen decision keeps repeating.`
              : "Miss the useful detail and the same bad kitchen assumption keeps costing time, clarity, or better results."
          ),
      150,
    );
    const payoffLine = trimText(
      finalRewardLine || detailLine || proofLine || summaryLine,
      150,
    );

    return {
      hook_topic: buildSocialHookTopic(title, contentType) || title,
      heading_topic: headingTopic,
      ingredient_focus: ingredientFocus,
      meta_line: metaLine,
      summary_line: trimText(summaryLine, 150),
      pain_line: painLine,
      consequence_line: consequenceLine,
      payoff_line: payoffLine,
      proof_line: proofLine,
      detail_line: detailLine,
      page_signal_line: pageSignalLine,
      final_reward_line: finalRewardLine,
      page_flow_text: cleanText(pageFlow.map((page) => [page?.label || "", page?.summary || ""].filter(Boolean).join(". ")).join(" ")),
      context_text: cleanText([summaryLine, painLine, consequenceLine, payoffLine, proofLine, detailLine, pageSignalLine, finalRewardLine, headingTopic, ingredientFocus, metaLine].join(" ")),
    };
  };

  return {
    buildArticleSocialSignals,
    cleanHeadingTopic,
  };
}
