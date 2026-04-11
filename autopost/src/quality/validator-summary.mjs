export function createValidatorSummaryHelpers(deps) {
  const {
    cleanText,
    isPlainObject,
    syncGeneratedContractContainers,
  } = deps;

  function mergeValidatorSummary(generated, updates) {
    const contentMachine = isPlainObject(generated?.content_machine) ? generated.content_machine : {};
    const validatorSummary = isPlainObject(contentMachine.validator_summary) ? contentMachine.validator_summary : {};

    return syncGeneratedContractContainers({
      ...generated,
      content_machine: {
        ...contentMachine,
        validator_summary: {
          ...validatorSummary,
          ...updates,
        },
      },
    });
  }

  function assertQualityGate(summary) {
    const blockingChecks = Array.isArray(summary?.blocking_checks)
      ? summary.blocking_checks
      : (Array.isArray(summary?.failed_checks) ? summary.failed_checks : []);
    if (!blockingChecks.length && String(summary?.quality_status || "") !== "block") {
      return;
    }

    const messages = {
      missing_core_fields: "The generated package is missing a title, slug, or article body.",
      missing_recipe: "The generated recipe is missing ingredients or instructions.",
      missing_manual_images: "Manual-only mode requires both blog and Facebook images before publish.",
      duplicate_conflict: "A duplicate title or slug conflict blocked this article before publish.",
      missing_target_pages: "At least one Facebook page must stay attached before publish.",
      thin_content: "The generated article body was too thin for the quality gate.",
      weak_title: "The generated title was too generic to carry a strong click promise.",
      weak_excerpt: "The generated excerpt was too weak, repetitive, or slow to surface a concrete reason to click.",
      weak_seo: "The generated SEO description was too weak, repetitive, or buried the concrete click reason too late.",
      weak_title_alignment: "The opening paragraph did not cash the headline promise quickly enough with a concrete answer, problem, or payoff.",
      weak_pagination: "The generated article should be split into 2 or 3 strong pages.",
      weak_page_balance: "One generated article page was too thin to feel intentional.",
      weak_page_openings: "One generated article page opens weakly instead of feeling like a deliberate page start.",
      weak_page_flow: "The generated page map is missing a clear label or summary for one of the article pages.",
      weak_page_labels: "The generated page labels are too generic to feel like real chapter navigation.",
      repetitive_page_labels: "The generated page labels feel repetitive instead of distinct.",
      weak_page_summaries: "The generated page summaries are too thin to make the next click feel worthwhile.",
      weak_structure: "The generated article needs more H2 structure before publish.",
      missing_internal_links: "The generated article did not include enough internal links.",
      social_pack_incomplete: "The generated social pack did not cover all selected Facebook pages.",
      social_pack_repetitive: "The generated social pack was too repetitive across selected Facebook pages.",
      social_hooks_repetitive: "The generated Facebook hooks were too repetitive across selected Facebook pages.",
      social_openings_repetitive: "The generated Facebook caption openings were too repetitive across selected Facebook pages.",
      social_angles_repetitive: "The generated social pack reused too many of the same angle types across selected Facebook pages.",
      social_hook_forms_thin: "The selected Facebook pack reused too many of the same hook shapes instead of varying the sentence pattern.",
      weak_social_copy: "The generated Facebook hooks or captions were too weak for publish.",
      weak_social_lead: "The lead Facebook variant was not strong, specific, concrete, or front-loaded enough to carry the first click opportunity.",
      social_specificity_thin: "Too few selected Facebook variants felt concrete and article-specific.",
      social_anchor_thin: "Too few selected Facebook variants named a concrete dish, ingredient, mistake, method, or topic.",
      social_relatability_thin: "Too few selected Facebook variants framed a recognizable real-life kitchen moment.",
      social_recognition_thin: "Too few selected Facebook variants created a direct self-recognition moment around a repeated kitchen result or mistake.",
      social_conversation_thin: "Too few selected Facebook variants felt naturally discussable through a real household habit, shopping split, or recognizable choice.",
      social_savvy_thin: "Too few selected Facebook variants hinted at a deeper kitchen skill or counterintuitive tip.",
      social_identity_shift_thin: "Too few selected Facebook variants promised a simple shift in how the reader sees an ingredient or method.",
      social_immediacy_thin: "Too few selected Facebook variants highlighted quick relief or near-term payoff.",
      social_habit_shift_thin: "Too few selected Facebook variants prompted a clear behavior swap or repeatable kitchen habit.",
      social_promise_sync_thin: "Too few selected Facebook variants echoed a clean promise that matched the article title.",
      social_scannability_thin: "Too few selected Facebook variants were scan-friendly enough for fast feeds.",
      social_two_step_thin: "Too few selected Facebook variants hinted at a two-step reveal or transformation.",
      social_front_loaded_thin: "Too few selected Facebook variants led with the concrete detail early enough.",
      social_curiosity_thin: "Too few selected Facebook variants created a sharp curiosity gap.",
      social_contrast_thin: "Too few selected Facebook variants created a clear contrast or before/after.",
      social_pain_point_thin: "Too few selected Facebook variants named a real kitchen frustration.",
      social_payoff_thin: "Too few selected Facebook variants named the outcome or payoff clearly enough.",
      social_pack_coverage_thin: "The selected social pack did not include enough strong variants for all Facebook pages.",
      social_pack_score_low: "The average social pack score was too low for publish.",
      blocked_opening_phrase: "The generated article used a blocked generic opening phrase.",
      blocked_first_person: "Food story content must remain in publication voice, not first person.",
      missing_social_summary: "The social candidate pool was too thin to confirm strong coverage.",
      social_variants_missing: "No social variants were generated for the selected Facebook pages.",
      social_candidates_too_few: "The social candidate pool was too small to select strong variants.",
      social_repair_failed: "The social repair pass did not recover enough strong variants.",
      social_repair_blocked: "The social repair attempt produced a blocked or invalid output.",
      social_candidate_parse_failed: "The social candidate output could not be parsed.",
      social_pack_selection_failed: "The social pack selection logic failed to produce the requested coverage.",
      social_pack_format_invalid: "The social pack output format was invalid.",
      social_pack_validation_failed: "The social pack failed validation.",
      social_pack_blocked: "The social pack failed the quality gate.",
    };

    const message = blockingChecks
      .map((check) => messages[check?.code] || cleanText(check?.message || "") || cleanText(check?.title || ""))
      .find(Boolean);

    throw new Error(message || "The generated package failed the quality gate.");
  }

  return {
    assertQualityGate,
    mergeValidatorSummary,
  };
}
