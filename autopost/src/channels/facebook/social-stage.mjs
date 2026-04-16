export async function generateSocialCandidatePool({
  job,
  settings,
  article,
  selectedPages,
  preferredAngle = "",
  requestOpenAiChat,
  parseJsonObject,
  buildSocialCandidatePrompt,
  normalizeSocialPack,
  summarizeSocialCandidatePool,
  angleDefinitionsForType,
}) {
  const models = settings.contentMachine.models || {};
  const repairAttemptsAllowed = models.repair_enabled ? Math.max(0, Math.min(1, Number(models.repair_attempts || 0))) : 0;
  const desiredCount = Math.max(1, Array.isArray(selectedPages) ? selectedPages.length : 0);
  let lastValidationError = "";

  for (let attempt = 0; attempt <= repairAttemptsAllowed; attempt += 1) {
    const content = await requestOpenAiChat(settings, [
      {
        role: "system",
        content:
          "You are the Facebook creative engine for a premium food publication. Return strict JSON only with no markdown fences. The JSON must contain social_candidates, an array of candidate variants. Each variant must contain angle_key, hook, caption, and cta_hint. Non-negotiables: no links, no hashtags, no pagination mentions, no title-echo hooks, no hook repeated as the first caption line, and no empty hype. Make the copy specific, honest, and scroll-stopping without sounding fake.",
      },
      {
        role: "user",
        content: buildSocialCandidatePrompt(job, settings, article, selectedPages, preferredAngle, lastValidationError),
      },
    ]);

    if (!content || typeof content !== "string") {
      throw new Error("OpenAI did not return social candidate content.");
    }

    const parsed = parseJsonObject(content);
    const normalized = normalizeSocialPack(parsed.social_candidates || parsed.socialCandidates || [], job.content_type || "recipe");
    const poolSummary = summarizeSocialCandidatePool(normalized, article, desiredCount, job.content_type || "recipe");
    if (!poolSummary.issues.length || attempt >= repairAttemptsAllowed) {
      return {
        candidates: normalized,
        validatorSummary: {
          social_repair_attempts: attempt,
          social_repaired: attempt > 0,
          last_social_validation_error: poolSummary.issues.length ? poolSummary.issues.join(" ") : lastValidationError,
          social_pool_quality_status: poolSummary.issues.length ? "warn" : "pass",
          social_pool_size: poolSummary.metrics.pool_size,
          strong_social_candidates: poolSummary.metrics.strong_candidates,
          specific_social_candidates: poolSummary.metrics.specific_candidates,
          conversation_social_candidates: poolSummary.metrics.conversation_candidates,
          scannable_social_candidates: poolSummary.metrics.scannable_candidates,
          anchored_social_candidates: poolSummary.metrics.anchor_candidates,
          relatable_social_candidates: poolSummary.metrics.relatable_candidates,
          recognition_social_candidates: poolSummary.metrics.recognition_candidates,
          proof_social_candidates: poolSummary.metrics.proof_candidates,
          actionable_social_candidates: poolSummary.metrics.actionable_candidates,
          immediacy_social_candidates: poolSummary.metrics.immediacy_candidates,
          consequence_social_candidates: poolSummary.metrics.consequence_candidates,
          habit_shift_social_candidates: poolSummary.metrics.habit_shift_candidates,
          focused_social_candidates: poolSummary.metrics.focused_candidates,
          promise_sync_candidates: poolSummary.metrics.promise_sync_candidates,
          two_step_social_candidates: poolSummary.metrics.two_step_candidates,
          novelty_social_candidates: poolSummary.metrics.novelty_candidates,
          front_loaded_social_candidates: poolSummary.metrics.front_loaded_candidates,
          curiosity_social_candidates: poolSummary.metrics.curiosity_candidates,
          resolution_social_candidates: poolSummary.metrics.resolution_candidates,
          contrast_social_candidates: poolSummary.metrics.contrast_candidates,
          pain_point_social_candidates: poolSummary.metrics.pain_point_candidates,
          payoff_social_candidates: poolSummary.metrics.payoff_candidates,
          high_scoring_social_candidates: poolSummary.metrics.high_scoring_candidates,
        },
      };
    }

    lastValidationError = buildSocialPoolRepairNote(
      poolSummary,
      desiredCount,
      Number(angleDefinitionsForType(job.content_type || "recipe")?.length || 0),
    );
  }

  throw new Error("Social candidate generation failed unexpectedly.");
}

function buildSocialPoolRepairNote(summary, desiredCount, angleCount = 0) {
  const metrics = summary?.metrics && typeof summary.metrics === "object" ? summary.metrics : {};
  const poolTarget = Math.max(6, Math.min(10, desiredCount + 3));
  const strongTarget = Math.max(desiredCount, Math.min(4, poolTarget));
  const distinctVariantTarget = Math.max(1, Math.max(desiredCount, 4));
  const distinctHookTarget = Math.max(1, Math.max(desiredCount, 3));
  const distinctOpeningTarget = desiredCount > 1 ? Math.max(1, desiredCount) : 1;
  const distinctAngleTarget = desiredCount > 1 ? Math.max(2, Math.min(Math.max(1, angleCount), desiredCount)) : 1;
  const fixes = [];

  if (Number(metrics.pool_size || 0) < poolTarget) fixes.push(`Return at least ${poolTarget} candidates.`);
  if (Number(metrics.strong_candidates || 0) < strongTarget) fixes.push("Make more candidates strong, specific, and publishable.");
  if (Number(metrics.specific_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) fixes.push("Anchor more hooks and captions in concrete article payoff, proof, ingredient focus, or useful detail.");
  if (Number(metrics.novelty_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates add a concrete new detail or clue instead of restating the title.");
  if (Number(metrics.anchor_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Name the actual dish, ingredient, mistake, method, or topic more often instead of leaning on vague 'this' or 'it' hooks.");
  if (Number(metrics.relatable_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Frame more candidates around a recognizable home-kitchen moment or use case the reader can see themselves in.");
  if (Number(metrics.recognition_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates create a direct 'that's me' moment by naming the repeated bad result, mistake, or kitchen symptom the reader already knows.");
  if (Number(metrics.conversation_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates feel socially discussable by naming a real household habit, shopping split, or recognizable choice without asking for comments or tags.");
  if (Number(metrics.savvy_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates feel like the reader is about to make a smarter kitchen or shopping move, but keep it grounded, practical, and never smug.");
  if (Number(metrics.identity_shift_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates feel like the reader is leaving behind the old default move, but keep it honest and non-judgmental.");
  if (Number(metrics.pain_point_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Include more candidates framed around a real mistake, problem, shortcut, or pain point.");
  if (Number(metrics.payoff_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Include more candidates that front-load a clear payoff, result, or useful reason to care.");
  if (Number(metrics.proof_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Give more candidates a small believable proof or concrete clue like timing, texture, ingredient job, label detail, or before-versus-after result.");
  if (Number(metrics.immediacy_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates feel relevant right now, tied to tonight, this week, the next grocery run, or the reader's next cook, shop, or order.");
  if (Number(metrics.consequence_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Show the consequence more often: what gets wasted, repeated, overcomplicated, or missed if the reader keeps doing the usual thing.");
  if (Number(metrics.habit_shift_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make more candidates create a clear old-habit-vs-better-result snap by naming the usual move and the better outcome without sounding preachy.");
  if (Number(metrics.focused_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Keep more candidates centered on one clean dominant promise instead of stacking too many benefits, claims, or angles into one post.");
  if (Number(metrics.promise_sync_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Keep more candidates aligned with the title and page-one promise by cashing the same core problem or payoff without echoing the headline.");
  if (Number(metrics.scannable_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Keep more candidates easy to scan. Use short caption lines with distinct jobs instead of dense lines that all feel the same.");
  if (Number(metrics.two_step_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Make caption line 1 and line 2 do distinct jobs instead of repeating the same idea.");
  if (Number(metrics.front_loaded_candidates || 0) < Math.max(1, Math.min(desiredCount, 2))) fixes.push("Lead more hooks with the concrete problem, payoff, shortcut, or surprise in the first few words.");
  if (Number(metrics.high_scoring_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) fixes.push("Raise the click quality so more candidates feel specific, useful, and worth tapping.");
  if (Number(metrics.strong_candidates || 0) < strongTarget || Number(metrics.high_scoring_candidates || 0) < Math.max(desiredCount, Math.min(4, poolTarget))) fixes.push("Make the first few words of more hooks carry the concrete problem, payoff, shortcut, or surprise instead of vague setup.");
  if (Number(metrics.unique_variants || 0) < distinctVariantTarget) fixes.push("Make the full variants feel less repetitive overall.");
  if (Number(metrics.unique_hooks || 0) < distinctHookTarget) fixes.push("Use more distinct hooks.");
  if (desiredCount > 1 && Number(metrics.unique_openings || 0) < distinctOpeningTarget) fixes.push("Use more distinct caption openings.");
  if (desiredCount > 1 && Number(metrics.unique_angles || 0) < distinctAngleTarget) fixes.push("Broaden the angle mix across the pool.");
  if (desiredCount > 1 && Number(metrics.unique_hook_forms || 0) < Math.max(2, Math.min(3, desiredCount))) fixes.push("Use a wider mix of hook shapes across the pool: not just one repeated sentence pattern.");

  return [
    `Previous social candidate pool was too weak for ${desiredCount} selected pages.`,
    "Fix these constraints:",
    ...fixes.map((fix) => `- ${fix}`),
    "- Keep the copy honest, specific, short, and hook-led.",
    "- If a hook opens a question or contradiction, resolve part of it by caption line 1 or 2.",
  ].filter(Boolean).join("\n");
}
