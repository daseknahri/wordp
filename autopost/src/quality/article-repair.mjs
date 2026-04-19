export function createArticleRepairHelpers(deps) {
  const {
    cleanText,
    isPlainObject,
    resolveContentSitePolicy,
  } = deps;

  function buildArticleStageRepairNote(summary, job, settings = {}) {
    const checks = Array.isArray(summary?.checks) ? summary.checks : [];
    const metrics = isPlainObject(summary?.metrics) ? summary.metrics : {};
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

  return { buildArticleStageRepairNote };
}
