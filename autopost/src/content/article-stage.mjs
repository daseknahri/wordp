import { resolveContentSitePolicy } from "./site-policy.mjs";

export async function generateCoreArticlePackage({
  job,
  settings,
  requestOpenAiChat,
  parseJsonObject,
  buildCoreArticlePrompt,
  normalizeGeneratedPayload,
  validateGeneratedPayload,
  summarizeArticleStage,
  formatError,
}) {
  const normalizedSettings = settings && typeof settings === "object" ? settings : {};
  const models = normalizedSettings.contentMachine?.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Number(models.repair_attempts || 0)) : 0;
  const contentType = job.content_type || "recipe";
  const articleContract = contentType === "recipe"
    ? "The JSON must contain: title, slug, excerpt, seo_description, content_pages, page_flow, image_prompt, image_alt, and recipe. content_pages must be an array of 2 or 3 clean publish-ready HTML strings using paragraphs, headings, lists, and blockquotes only. page_flow must be an array with one item per page containing label and summary. Labels must be short, specific, and non-generic. Summaries must preview the payoff of the page instead of repeating the label."
    : "The JSON must contain: title, slug, excerpt, seo_description, content_pages, page_flow, image_prompt, and image_alt. Do not include recipe-only metadata for non-recipe content. content_pages must be an array of 2 or 3 clean publish-ready HTML strings using paragraphs, headings, lists, and blockquotes only. page_flow must be an array with one item per page containing label and summary. Labels must be short, specific, and non-generic. Summaries must preview the payoff of the page instead of repeating the label.";
  let lastErrorMessage = "";
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    try {
      const content = await requestOpenAiChat(settings, [
        {
          role: "system",
          content:
            `You are the article engine for a premium food publication. Return strict JSON only with no markdown fences. Be publish-ready, specific, and useful. No filler openings, no fake personal stories, no generic SEO padding, and no schema drift. ${articleContract}`,
        },
        {
          role: "user",
          content: buildCoreArticlePrompt(job, normalizedSettings, lastValidationError),
        },
      ]);

      if (!content || typeof content !== "string") {
        throw new Error("OpenAI did not return article content.");
      }

      const normalized = normalizeGeneratedPayload(parseJsonObject(content), job);
      const validated = validateGeneratedPayload(normalized, job);
      const articleStageSummary = summarizeArticleStage(validated, job);
      if (articleStageSummary.checks.length) {
        lastErrorMessage = `Article stage quality needs repair: ${articleStageSummary.checks.join(", ")}`;
        if (attempt >= repairAttemptsAllowed) {
          return {
            article: buildArticleOutput(validated),
            validatorSummary: buildArticleValidatorSummary(articleStageSummary, attempt, true, lastErrorMessage, lastValidationError),
          };
        }

        lastValidationError = buildArticleStageRepairNote(articleStageSummary, job, normalizedSettings);
        continue;
      }

      return {
        article: buildArticleOutput(validated),
        validatorSummary: buildArticleValidatorSummary(articleStageSummary, attempt, false, lastErrorMessage, lastValidationError),
      };
    } catch (error) {
      lastErrorMessage = formatError(error);
      lastValidationError = buildArticleRepairNote(lastErrorMessage, job);
      if (attempt >= repairAttemptsAllowed) {
        throw error;
      }
    }
  }

  throw new Error("Core article generation failed unexpectedly.");
}

function buildArticleOutput(validated) {
  return {
    title: validated.title,
    slug: validated.slug,
    excerpt: validated.excerpt,
    seo_description: validated.seo_description,
    content_pages: Array.isArray(validated.content_pages) ? validated.content_pages : [],
    page_flow: Array.isArray(validated.page_flow) ? validated.page_flow : [],
    content_html: validated.content_html,
    image_prompt: validated.image_prompt,
    image_alt: validated.image_alt,
    recipe: validated.recipe,
  };
}

function buildArticleValidatorSummary(articleStageSummary, attempt, warned, lastErrorMessage, lastValidationError) {
  return {
    repair_attempts: attempt,
    repaired: attempt > 0,
    last_validation_error: lastErrorMessage || lastValidationError,
    article_stage_quality_status: warned ? "warn" : "pass",
    article_stage_checks: warned ? articleStageSummary.checks : [],
    article_title_score: articleStageSummary.metrics.title_score,
    article_title_strong: articleStageSummary.metrics.title_strong,
    article_title_front_load_score: articleStageSummary.metrics.title_front_load_score,
    article_opening_alignment_score: articleStageSummary.metrics.opening_alignment_score,
    article_excerpt_signal_score: articleStageSummary.metrics.excerpt_signal_score,
    article_excerpt_front_load_score: articleStageSummary.metrics.excerpt_front_load_score,
    article_seo_signal_score: articleStageSummary.metrics.seo_signal_score,
    article_seo_front_load_score: articleStageSummary.metrics.seo_front_load_score,
    article_excerpt_adds_value: articleStageSummary.metrics.excerpt_adds_value,
    article_opening_adds_value: articleStageSummary.metrics.opening_adds_value,
    article_opening_front_load_score: articleStageSummary.metrics.opening_front_load_score,
    article_page_one_internal_links: articleStageSummary.metrics.page_one_internal_links,
  };
}

function buildArticleStageRepairNote(summary, job, settings = {}) {
  const checks = Array.isArray(summary?.checks) ? summary.checks : [];
  const metrics = summary?.metrics && typeof summary.metrics === "object" ? summary.metrics : {};
  const sitePolicy = resolveContentSitePolicy(settings, job);
  const publicationName = sitePolicy.publicationName || "this publication";
  const internalLinkMinimum = Math.max(0, Number(sitePolicy.internalLinks.minimumCount || 0));
  const fixes = [];

  if (checks.includes("missing_core_fields")) {
    fixes.push("Fill title, slug, excerpt, seo_description, content_pages, page_flow, image fields, and recipe data correctly.");
  }
  if (checks.includes("missing_recipe")) {
    fixes.push("For recipe output, fill recipe.ingredients[] and recipe.instructions[] completely.");
  }
  if (checks.includes("thin_content")) {
    fixes.push(`Increase article depth so the body clears roughly ${Number(metrics.minimum_words || 0)} words without filler.`);
  }
  if (checks.includes("weak_excerpt")) {
    fixes.push("Write a stronger, more specific excerpt that adds new value beyond the title and front-loads one concrete detail, pain point, or payoff.");
  }
  if (checks.includes("weak_seo")) {
    fixes.push("Write a fuller natural SEO description that front-loads one concrete reason to click instead of repeating the title or excerpt.");
  }
  if (checks.includes("weak_title")) {
    fixes.push("Write a clearer, more specific title that signals a real payoff, mistake, shortcut, or useful outcome instead of sounding generic. Honest contrast can help when it stays concrete.");
  }
  if (checks.includes("weak_title_alignment")) {
    fixes.push("Make page 1 cash the promise of the title immediately with a stronger opening paragraph whose first sentence front-loads the answer, payoff, or problem being solved.");
  }
  if (checks.includes("weak_pagination")) {
    fixes.push("Return 2 or 3 strong article pages with clean same-post flow.");
  }
  if (checks.includes("weak_page_balance")) {
    fixes.push("Rebalance the pages so no page feels thin or leftover.");
  }
  if (checks.includes("weak_page_openings")) {
    fixes.push("Make every page open with a stronger section lead or H2-led start.");
  }
  if (checks.includes("weak_page_flow")) {
    fixes.push("Fill page_flow completely with one strong label and summary per page.");
  }
  if (checks.includes("weak_page_labels")) {
    fixes.push("Use stronger editorial page labels instead of generic navigation-like labels.");
  }
  if (checks.includes("repetitive_page_labels")) {
    fixes.push("Make the page labels more distinct from one another.");
  }
  if (checks.includes("weak_page_summaries")) {
    fixes.push("Make page summaries preview concrete payoff instead of thin restatements.");
  }
  if (checks.includes("weak_reader_path")) {
    fixes.push(`Include at least one natural internal ${publicationName} link on page 1 so a social visitor has a clear next read beyond the current story.`);
  }
  if (checks.includes("weak_structure")) {
    fixes.push("Add clearer H2 structure so the article scans naturally.");
  }
  if (checks.includes("missing_internal_links")) {
    fixes.push(`Include at least ${internalLinkMinimum} natural internal ${publicationName} links across the article pages.`);
  }
  if ((job?.content_type || "") === "food_fact") {
    fixes.push("Stay in editorial explainer territory and avoid recipe-style metadata.");
  }

  return [
    `Previous article attempt was too weak for ${job?.content_type || "article"} output.`,
    "Fix these constraints:",
    ...Array.from(new Set(fixes)).map((fix) => `- ${fix}`),
  ].join("\n");
}

function buildArticleRepairNote(errorMessage, job) {
  const message = String(errorMessage || "").trim();
  const contentType = job?.content_type || "recipe";
  const fixes = [];

  if (/not valid json/i.test(message)) {
    fixes.push("Return one valid JSON object only with no markdown, arrays, or wrapper prose.");
  }
  if (/article body was empty/i.test(message)) {
    fixes.push("Return non-empty content_pages with real WordPress-ready HTML. Do not return [], null, or empty wrappers.");
  }
  if (/missing ingredients or instructions/i.test(message)) {
    fixes.push("For recipe output, fill recipe.ingredients[] and recipe.instructions[] completely.");
  }
  if (/blocked generic opening phrase/i.test(message)) {
    fixes.push("Replace the opening with a concrete, human paragraph. Do not use generic filler or AI-style disclaimers.");
  }
  if (/first-person voice/i.test(message)) {
    fixes.push("Avoid first-person voice and memoir framing. Use the publication voice instead.");
  }

  if (!fixes.length) {
    fixes.push("Return a complete, publish-ready article package that matches the JSON contract exactly.");
    fixes.push("Keep the title, excerpt, seo_description, content_pages, page_flow, image fields, and recipe object filled correctly.");
  }

  if (contentType === "recipe" && !fixes.some((fix) => /recipe\.ingredients|recipe\.instructions/i.test(fix))) {
    fixes.push("Keep the recipe practical, cookable, and fully structured.");
  }
  if (contentType === "food_fact") {
    fixes.push("Stay in editorial explainer territory only and avoid recipe-style metadata.");
  }

  return [
    `Previous article attempt failed for ${contentType}.`,
    "Fix these constraints:",
    ...fixes.map((fix) => `- ${fix}`),
  ].join("\n");
}
