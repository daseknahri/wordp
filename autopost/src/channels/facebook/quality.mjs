export function desiredSocialSignalTargets(totalCount) {
  const minimum = totalCount > 1 ? 1 : 0;

  return {
    hookFormTarget: totalCount > 1 ? Math.max(2, Math.min(3, totalCount || 1)) : 1,
    frontLoadedMin: Math.max(1, Math.min(totalCount || 1, 2)),
    noveltyMin: 1,
    relatabilityMin: totalCount > 1 ? 1 : 0,
    recognitionMin: totalCount > 1 ? 1 : 0,
    conversationMin: totalCount > 1 ? 1 : 0,
    savvyMin: totalCount > 1 ? 1 : 0,
    identityShiftMin: totalCount > 1 ? 1 : 0,
    proofMin: totalCount > 1 ? 1 : 0,
    actionabilityMin: totalCount > 1 ? 1 : 0,
    immediacyMin: totalCount > 1 ? 1 : 0,
    consequenceMin: totalCount > 1 ? 1 : 0,
    habitShiftMin: totalCount > 1 ? 1 : 0,
    focusMin: totalCount > 1 ? 1 : 0,
    promiseSyncMin: totalCount > 0 ? 1 : 0,
    scannableMin: totalCount > 1 ? 1 : 0,
    twoStepMin: totalCount > 1 ? 1 : 0,
    curiosityMin: totalCount > 1 ? 1 : 0,
    resolutionMin: totalCount > 1 ? 1 : 0,
    contrastMin: totalCount > 1 ? 1 : 0,
    painPointMin: minimum,
    payoffMin: minimum,
  };
}

export function summarizeSocialCandidatePool({
  candidates,
  article,
  desiredCount,
  contentType = "recipe",
  deps,
}) {
  const {
    angleDefinitionsForType,
    buildArticleSocialSignals,
    classifySocialHookForm,
    cleanText,
    frontLoadedClickSignalScore,
    normalizeAngleKey,
    normalizeCaptionOpeningFingerprint,
    normalizeHookFingerprint,
    normalizeSocialFingerprint,
    normalizeSocialPack,
    scoreSocialVariant,
    socialVariantActionabilitySignal,
    socialVariantAnchorSignal,
    socialVariantConsequenceSignal,
    socialVariantContrastSignal,
    socialVariantConversationSignal,
    socialVariantCuriositySignal,
    socialVariantHabitShiftSignal,
    socialVariantIdentityShiftSignal,
    socialVariantImmediacySignal,
    socialVariantLooksWeak,
    socialVariantNoveltyScore,
    socialVariantPainPointSignal,
    socialVariantPayoffSignal,
    socialVariantPromiseFocusSignal,
    socialVariantPromiseSyncSignal,
    socialVariantProofSignal,
    socialVariantRelatabilitySignal,
    socialVariantResolvesEarly,
    socialVariantSavvySignal,
    socialVariantScannabilitySignal,
    socialVariantSelfRecognitionSignal,
    socialVariantSpecificityScore,
    socialVariantTwoStepSignal,
  } = deps;
  const normalized = normalizeSocialPack(candidates, contentType);
  const angleLimit = angleDefinitionsForType(contentType).length;
  const poolTarget = Math.max(6, Math.min(10, desiredCount + 3));
  const strongTarget = Math.max(desiredCount, Math.min(4, poolTarget));
  const distinctVariantTarget = Math.max(1, Math.min(normalized.length, Math.max(desiredCount, 4)));
  const distinctHookTarget = Math.max(1, Math.min(normalized.length, Math.max(desiredCount, 3)));
  const distinctOpeningTarget = desiredCount > 1 ? Math.max(1, Math.min(normalized.length, desiredCount)) : 1;
  const distinctAngleTarget = desiredCount > 1 ? Math.max(2, Math.min(angleLimit, desiredCount)) : 1;
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  const fingerprints = new Set(normalized.map((variant) => normalizeSocialFingerprint(variant)).filter(Boolean));
  const hookFingerprints = new Set(normalized.map((variant) => normalizeHookFingerprint(variant)).filter(Boolean));
  const openingFingerprints = new Set(normalized.map((variant) => normalizeCaptionOpeningFingerprint(variant)).filter(Boolean));
  const angleKeys = new Set(normalized.map((variant) => normalizeAngleKey(variant?.angle_key || "", contentType)).filter(Boolean));
  const hookForms = new Set(normalized.map((variant) => classifySocialHookForm(variant)).filter(Boolean));
  const strongCandidates = normalized.filter((variant) => !socialVariantLooksWeak(variant, articleTitle, contentType, articleSignals)).length;
  const specificCandidates = normalized.filter((variant) => socialVariantSpecificityScore(variant, articleSignals) >= 2).length;
  const noveltyCandidates = normalized.filter((variant) => socialVariantNoveltyScore(variant, articleTitle, articleSignals) >= 2).length;
  const anchorCandidates = normalized.filter((variant) => socialVariantAnchorSignal(variant, articleSignals)).length;
  const relatableCandidates = normalized.filter((variant) => socialVariantRelatabilitySignal(variant, articleSignals, contentType)).length;
  const recognitionCandidates = normalized.filter((variant) => socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)).length;
  const conversationCandidates = normalized.filter((variant) => socialVariantConversationSignal(variant, articleSignals, contentType)).length;
  const savvyCandidates = normalized.filter((variant) => socialVariantSavvySignal(variant, articleSignals, contentType)).length;
  const identityShiftCandidates = normalized.filter((variant) => socialVariantIdentityShiftSignal(variant, articleSignals, contentType)).length;
  const painPointCandidates = normalized.filter((variant) => socialVariantPainPointSignal(variant, articleSignals)).length;
  const payoffCandidates = normalized.filter((variant) => socialVariantPayoffSignal(variant, articleSignals)).length;
  const proofCandidates = normalized.filter((variant) => socialVariantProofSignal(variant, articleSignals, contentType)).length;
  const actionableCandidates = normalized.filter((variant) => socialVariantActionabilitySignal(variant, articleSignals, contentType)).length;
  const immediacyCandidates = normalized.filter((variant) => socialVariantImmediacySignal(variant, articleSignals, contentType)).length;
  const consequenceCandidates = normalized.filter((variant) => socialVariantConsequenceSignal(variant, articleSignals, contentType)).length;
  const habitShiftCandidates = normalized.filter((variant) => socialVariantHabitShiftSignal(variant, articleSignals, contentType)).length;
  const focusedCandidates = normalized.filter((variant) => socialVariantPromiseFocusSignal(variant, articleSignals, contentType)).length;
  const promiseSyncCandidates = normalized.filter((variant) => socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals, contentType)).length;
  const scannableCandidates = normalized.filter((variant) => socialVariantScannabilitySignal(variant, contentType)).length;
  const twoStepCandidates = normalized.filter((variant) => socialVariantTwoStepSignal(variant, articleSignals, contentType)).length;
  const curiosityCandidates = normalized.filter((variant) => socialVariantCuriositySignal(variant, articleSignals)).length;
  const contrastCandidates = normalized.filter((variant) => socialVariantContrastSignal(variant, articleSignals)).length;
  const resolutionCandidates = normalized.filter((variant) => socialVariantResolvesEarly(variant, articleSignals, contentType)).length;
  const frontLoadedCandidates = normalized.filter((variant) => frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0).length;
  const highScoringCandidates = normalized.filter((variant) => scoreSocialVariant(variant, articleTitle, contentType, articleContext, articleSignals) >= 18).length;
  const issues = [];

  if (normalized.length < poolTarget) issues.push(`The social candidate pool is too small (${normalized.length}/${poolTarget}).`);
  if (strongCandidates < strongTarget) issues.push(`Too few strong social candidates survived local checks (${strongCandidates}/${strongTarget}).`);
  if (specificCandidates < Math.max(desiredCount, Math.min(4, poolTarget))) issues.push(`Too few specific social candidates anchor the pool in real article payoff (${specificCandidates}/${Math.max(desiredCount, Math.min(4, poolTarget))}).`);
  if (noveltyCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates add a concrete new detail beyond the title (${noveltyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (anchorCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates name a concrete article anchor instead of relying on vague pronouns (${anchorCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (relatableCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates frame a recognizable real-life kitchen moment (${relatableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (recognitionCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates create a direct self-recognition moment around a repeated kitchen result or mistake (${recognitionCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (conversationCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates feel socially discussable through a real household habit, shopping split, or recognizable choice (${conversationCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (savvyCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates make the reader feel they are about to make a smarter kitchen or shopping move (${savvyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (identityShiftCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates create a clean break from the reader's old default behavior (${identityShiftCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (painPointCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates frame a concrete problem, mistake, or shortcut (${painPointCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (payoffCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates frame a clear result, payoff, or reason to care (${payoffCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (proofCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates carry a small believable proof or concrete clue (${proofCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (actionableCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates make the next move or practical use feel obvious (${actionableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (immediacyCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates make the relevance feel immediate to the reader's next cook, shop, or order (${immediacyCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (consequenceCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates make the cost, waste, or repeated mistake feel concrete (${consequenceCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (habitShiftCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates create a clear old-habit-vs-better-result shift (${habitShiftCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (focusedCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates stay centered on one clean dominant promise instead of stacking too many claims (${focusedCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (promiseSyncCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates stay aligned with the article's title-and-opening promise without simply echoing the headline (${promiseSyncCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (scannableCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates stay easy to scan in short distinct caption lines (${scannableCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (twoStepCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates use caption line 1 and line 2 for distinct useful jobs instead of repeating the same idea (${twoStepCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (curiosityCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates create honest curiosity with a concrete clue (${curiosityCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (resolutionCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates resolve the hook with a concrete clue in the first caption lines (${resolutionCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (contrastCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates use a clean expectation-vs-reality or mistake-vs-fix contrast (${contrastCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (frontLoadedCandidates < Math.max(1, Math.min(desiredCount, 2))) issues.push(`Too few candidates lead with a concrete problem, payoff, or surprise in the first words (${frontLoadedCandidates}/${Math.max(1, Math.min(desiredCount, 2))}).`);
  if (highScoringCandidates < Math.max(desiredCount, Math.min(4, poolTarget))) issues.push(`Too few candidates score high on specificity and click promise (${highScoringCandidates}/${Math.max(desiredCount, Math.min(4, poolTarget))}).`);
  if (fingerprints.size < distinctVariantTarget) issues.push(`The social candidates are too repetitive overall (${fingerprints.size} distinct full variants).`);
  if (hookFingerprints.size < distinctHookTarget) issues.push(`The hooks are too repetitive (${hookFingerprints.size} distinct hooks).`);
  if (desiredCount > 1 && openingFingerprints.size < distinctOpeningTarget) issues.push(`The caption openings are too repetitive (${openingFingerprints.size} distinct openings).`);
  if (desiredCount > 1 && angleKeys.size < distinctAngleTarget) issues.push(`The angle mix is too narrow (${angleKeys.size} distinct angles).`);
  if (desiredCount > 1 && hookForms.size < Math.max(2, Math.min(3, desiredCount))) issues.push(`The hook shapes are too narrow (${hookForms.size} distinct hook forms).`);

  return {
    issues,
    metrics: {
      pool_size: normalized.length,
      strong_candidates: strongCandidates,
      specific_candidates: specificCandidates,
      novelty_candidates: noveltyCandidates,
      anchor_candidates: anchorCandidates,
      relatable_candidates: relatableCandidates,
      recognition_candidates: recognitionCandidates,
      conversation_candidates: conversationCandidates,
      savvy_candidates: savvyCandidates,
      identity_shift_candidates: identityShiftCandidates,
      pain_point_candidates: painPointCandidates,
      payoff_candidates: payoffCandidates,
      proof_candidates: proofCandidates,
      actionable_candidates: actionableCandidates,
      immediacy_candidates: immediacyCandidates,
      consequence_candidates: consequenceCandidates,
      habit_shift_candidates: habitShiftCandidates,
      focused_candidates: focusedCandidates,
      promise_sync_candidates: promiseSyncCandidates,
      scannable_candidates: scannableCandidates,
      two_step_candidates: twoStepCandidates,
      curiosity_candidates: curiosityCandidates,
      resolution_candidates: resolutionCandidates,
      contrast_candidates: contrastCandidates,
      front_loaded_candidates: frontLoadedCandidates,
      high_scoring_candidates: highScoringCandidates,
      unique_variants: fingerprints.size,
      unique_hooks: hookFingerprints.size,
      unique_openings: openingFingerprints.size,
      unique_angles: angleKeys.size,
      unique_hook_forms: hookForms.size,
    },
  };
}

export function summarizeSelectedSocialPack({
  socialPack,
  article,
  contentType = "recipe",
  deps,
}) {
  const {
    buildArticleSocialSignals,
    classifySocialHookForm,
    cleanText,
    frontLoadedClickSignalScore,
    normalizeSocialPack,
    scoreSocialVariant,
    socialVariantActionabilitySignal,
    socialVariantAnchorSignal,
    socialVariantConsequenceSignal,
    socialVariantContrastSignal,
    socialVariantConversationSignal,
    socialVariantCuriositySignal,
    socialVariantHabitShiftSignal,
    socialVariantIdentityShiftSignal,
    socialVariantImmediacySignal,
    socialVariantNoveltyScore,
    socialVariantPainPointSignal,
    socialVariantPayoffSignal,
    socialVariantPromiseFocusSignal,
    socialVariantPromiseSyncSignal,
    socialVariantProofSignal,
    socialVariantRelatabilitySignal,
    socialVariantResolvesEarly,
    socialVariantSavvySignal,
    socialVariantScannabilitySignal,
    socialVariantSelfRecognitionSignal,
    socialVariantSpecificityScore,
    socialVariantTwoStepSignal,
  } = deps;
  const normalized = normalizeSocialPack(socialPack, contentType);
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  const scores = normalized
    .map((variant) => scoreSocialVariant(variant, articleTitle, contentType, articleContext, articleSignals))
    .filter((score) => Number.isFinite(score));
  const specificVariants = normalized.filter((variant) => socialVariantSpecificityScore(variant, articleSignals) >= 2).length;
  const noveltyVariants = normalized.filter((variant) => socialVariantNoveltyScore(variant, articleTitle, articleSignals) >= 2).length;
  const anchorVariants = normalized.filter((variant) => socialVariantAnchorSignal(variant, articleSignals)).length;
  const relatableVariants = normalized.filter((variant) => socialVariantRelatabilitySignal(variant, articleSignals, contentType)).length;
  const recognitionVariants = normalized.filter((variant) => socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)).length;
  const conversationVariants = normalized.filter((variant) => socialVariantConversationSignal(variant, articleSignals, contentType)).length;
  const savvyVariants = normalized.filter((variant) => socialVariantSavvySignal(variant, articleSignals, contentType)).length;
  const identityShiftVariants = normalized.filter((variant) => socialVariantIdentityShiftSignal(variant, articleSignals, contentType)).length;
  const painPointVariants = normalized.filter((variant) => socialVariantPainPointSignal(variant, articleSignals)).length;
  const payoffVariants = normalized.filter((variant) => socialVariantPayoffSignal(variant, articleSignals)).length;
  const proofVariants = normalized.filter((variant) => socialVariantProofSignal(variant, articleSignals, contentType)).length;
  const actionableVariants = normalized.filter((variant) => socialVariantActionabilitySignal(variant, articleSignals, contentType)).length;
  const immediacyVariants = normalized.filter((variant) => socialVariantImmediacySignal(variant, articleSignals, contentType)).length;
  const consequenceVariants = normalized.filter((variant) => socialVariantConsequenceSignal(variant, articleSignals, contentType)).length;
  const habitShiftVariants = normalized.filter((variant) => socialVariantHabitShiftSignal(variant, articleSignals, contentType)).length;
  const focusedVariants = normalized.filter((variant) => socialVariantPromiseFocusSignal(variant, articleSignals, contentType)).length;
  const promiseSyncVariants = normalized.filter((variant) => socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals, contentType)).length;
  const scannableVariants = normalized.filter((variant) => socialVariantScannabilitySignal(variant, contentType)).length;
  const twoStepVariants = normalized.filter((variant) => socialVariantTwoStepSignal(variant, articleSignals, contentType)).length;
  const curiosityVariants = normalized.filter((variant) => socialVariantCuriositySignal(variant, articleSignals)).length;
  const contrastVariants = normalized.filter((variant) => socialVariantContrastSignal(variant, articleSignals)).length;
  const resolutionVariants = normalized.filter((variant) => socialVariantResolvesEarly(variant, articleSignals, contentType)).length;
  const frontLoadedVariants = normalized.filter((variant) => frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0).length;
  const leadVariant = normalized[0] || null;
  const hookForms = new Set(normalized.map((variant) => classifySocialHookForm(variant)).filter(Boolean));
  const leadHookForm = leadVariant ? classifySocialHookForm(leadVariant) : "";
  const leadScore = leadVariant ? scoreSocialVariant(leadVariant, articleTitle, contentType, articleContext, articleSignals) : 0;
  const leadSpecific = leadVariant ? socialVariantSpecificityScore(leadVariant, articleSignals) >= 2 : false;
  const leadNovelty = leadVariant ? socialVariantNoveltyScore(leadVariant, articleTitle, articleSignals) >= 2 : false;
  const leadAnchored = leadVariant ? socialVariantAnchorSignal(leadVariant, articleSignals) : false;
  const leadRelatable = leadVariant ? socialVariantRelatabilitySignal(leadVariant, articleSignals, contentType) : false;
  const leadRecognition = leadVariant ? socialVariantSelfRecognitionSignal(leadVariant, articleSignals, contentType) : false;
  const leadConversation = leadVariant ? socialVariantConversationSignal(leadVariant, articleSignals, contentType) : false;
  const leadSavvy = leadVariant ? socialVariantSavvySignal(leadVariant, articleSignals, contentType) : false;
  const leadIdentityShift = leadVariant ? socialVariantIdentityShiftSignal(leadVariant, articleSignals, contentType) : false;
  const leadPainPoint = leadVariant ? socialVariantPainPointSignal(leadVariant, articleSignals) : false;
  const leadPayoff = leadVariant ? socialVariantPayoffSignal(leadVariant, articleSignals) : false;
  const leadProof = leadVariant ? socialVariantProofSignal(leadVariant, articleSignals, contentType) : false;
  const leadActionable = leadVariant ? socialVariantActionabilitySignal(leadVariant, articleSignals, contentType) : false;
  const leadImmediacy = leadVariant ? socialVariantImmediacySignal(leadVariant, articleSignals, contentType) : false;
  const leadConsequence = leadVariant ? socialVariantConsequenceSignal(leadVariant, articleSignals, contentType) : false;
  const leadHabitShift = leadVariant ? socialVariantHabitShiftSignal(leadVariant, articleSignals, contentType) : false;
  const leadFocused = leadVariant ? socialVariantPromiseFocusSignal(leadVariant, articleSignals, contentType) : false;
  const leadPromiseSync = leadVariant ? socialVariantPromiseSyncSignal(leadVariant, articleTitle, articleSignals, contentType) : false;
  const leadScannable = leadVariant ? socialVariantScannabilitySignal(leadVariant, contentType) : false;
  const leadTwoStep = leadVariant ? socialVariantTwoStepSignal(leadVariant, articleSignals, contentType) : false;
  const leadCuriosity = leadVariant ? socialVariantCuriositySignal(leadVariant, articleSignals) : false;
  const leadContrast = leadVariant ? socialVariantContrastSignal(leadVariant, articleSignals) : false;
  const leadResolved = leadVariant ? socialVariantResolvesEarly(leadVariant, articleSignals, contentType) : false;
  const leadFrontLoaded = leadVariant ? frontLoadedClickSignalScore(leadVariant?.hook || "", contentType) > 0 : false;
  const averageScore = scores.length ? Number((scores.reduce((sum, value) => sum + value, 0) / scores.length).toFixed(1)) : 0;

  return {
    selected_social_average_score: averageScore,
    specific_social_variants: specificVariants,
    novelty_variants: noveltyVariants,
    anchored_variants: anchorVariants,
    relatable_variants: relatableVariants,
    recognition_variants: recognitionVariants,
    conversation_variants: conversationVariants,
    savvy_variants: savvyVariants,
    identity_shift_variants: identityShiftVariants,
    pain_point_variants: painPointVariants,
    payoff_variants: payoffVariants,
    proof_variants: proofVariants,
    actionable_variants: actionableVariants,
    immediacy_variants: immediacyVariants,
    consequence_variants: consequenceVariants,
    habit_shift_variants: habitShiftVariants,
    focused_variants: focusedVariants,
    promise_sync_variants: promiseSyncVariants,
    scannable_variants: scannableVariants,
    two_step_variants: twoStepVariants,
    curiosity_variants: curiosityVariants,
    resolution_variants: resolutionVariants,
    contrast_variants: contrastVariants,
    front_loaded_social_variants: frontLoadedVariants,
    unique_hook_forms: hookForms.size,
    lead_social_score: leadScore,
    lead_social_specific: leadSpecific,
    lead_social_novelty: leadNovelty,
    lead_social_anchored: leadAnchored,
    lead_social_relatable: leadRelatable,
    lead_social_recognition: leadRecognition,
    lead_social_conversation: leadConversation,
    lead_social_savvy: leadSavvy,
    lead_social_identity_shift: leadIdentityShift,
    lead_social_proof: leadProof,
    lead_social_actionable: leadActionable,
    lead_social_immediacy: leadImmediacy,
    lead_social_consequence: leadConsequence,
    lead_social_habit_shift: leadHabitShift,
    lead_social_focused: leadFocused,
    lead_social_promise_sync: leadPromiseSync,
    lead_social_scannable: leadScannable,
    lead_social_two_step: leadTwoStep,
    lead_social_curiosity: leadCuriosity,
    lead_social_resolved: leadResolved,
    lead_social_contrast: leadContrast,
    lead_social_pain_point: leadPainPoint,
    lead_social_payoff: leadPayoff,
    lead_social_front_loaded: leadFrontLoaded,
    lead_social_hook_form: leadHookForm,
  };
}

export function findBestSocialCandidateIndex({
  candidates,
  article,
  contentType,
  desiredAngle,
  usedFingerprints,
  usedHookFingerprints,
  usedCaptionOpenings,
  selectionState = null,
  deps,
}) {
  const {
    buildArticleSocialSignals,
    classifySocialHookForm,
    cleanText,
    frontLoadedClickSignalScore,
    normalizeAngleKey,
    normalizeCaptionOpeningFingerprint,
    normalizeHookFingerprint,
    normalizeSocialFingerprint,
    scoreSocialVariant,
    socialVariantActionabilitySignal,
    socialVariantConsequenceSignal,
    socialVariantContrastSignal,
    socialVariantConversationSignal,
    socialVariantCuriositySignal,
    socialVariantHabitShiftSignal,
    socialVariantIdentityShiftSignal,
    socialVariantImmediacySignal,
    socialVariantNoveltyScore,
    socialVariantPainPointSignal,
    socialVariantPayoffSignal,
    socialVariantPromiseFocusSignal,
    socialVariantPromiseSyncSignal,
    socialVariantProofSignal,
    socialVariantRelatabilitySignal,
    socialVariantResolvesEarly,
    socialVariantSavvySignal,
    socialVariantScannabilitySignal,
    socialVariantSelfRecognitionSignal,
    socialVariantSpecificityScore,
    socialVariantTwoStepSignal,
  } = deps;
  const normalizedDesiredAngle = normalizeAngleKey(desiredAngle || "", contentType);
  const articleTitle = cleanText(article?.title || "");
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const articleContext = articleSignals.context_text;
  let bestIndex = -1;
  let bestScore = -Infinity;

  for (let index = 0; index < candidates.length; index += 1) {
    const candidate = candidates[index];
    if (!candidate) continue;

    const fingerprint = normalizeSocialFingerprint(candidate);
    const hookFingerprint = normalizeHookFingerprint(candidate);
    const captionOpeningFingerprint = normalizeCaptionOpeningFingerprint(candidate);
    let score = scoreSocialVariant(candidate, articleTitle, contentType, articleContext, articleSignals);
    const candidateSpecificity = socialVariantSpecificityScore(candidate, articleSignals);
    const slotIndex = Math.max(0, Number(selectionState?.slotIndex || 0));
    const remainingSlots = Math.max(1, Number(selectionState?.remainingSlots || 1));

    if (!fingerprint) score -= 50;
    if (fingerprint && usedFingerprints.has(fingerprint)) score -= 25;
    if (hookFingerprint && usedHookFingerprints.has(hookFingerprint)) score -= 18;
    if (captionOpeningFingerprint && usedCaptionOpenings.has(captionOpeningFingerprint)) score -= 14;

    const candidateHookForm = classifySocialHookForm(candidate);
    const usedHookForms = selectionState?.usedHookForms instanceof Set ? selectionState.usedHookForms : new Set();
    const hookFormTarget = Math.max(1, Number(selectionState?.targets?.hookFormTarget || 1));

    const candidateAngle = normalizeAngleKey(candidate?.angle_key || "", contentType);
    if (normalizedDesiredAngle && candidateAngle === normalizedDesiredAngle) {
      score += slotIndex === 0 ? 5 : 8;
    } else if (normalizedDesiredAngle && candidateAngle !== "") {
      score -= slotIndex === 0 ? 1 : 3;
    }

    const candidatePain = socialVariantPainPointSignal(candidate, articleSignals);
    const candidatePayoff = socialVariantPayoffSignal(candidate, articleSignals);
    const candidateCuriosity = socialVariantCuriositySignal(candidate, articleSignals);
    const candidateResolved = socialVariantResolvesEarly(candidate, articleSignals, contentType);
    const candidateContrast = socialVariantContrastSignal(candidate, articleSignals);
    const candidateNovelty = socialVariantNoveltyScore(candidate, articleTitle, articleSignals) >= 2;
    const candidateRelatable = socialVariantRelatabilitySignal(candidate, articleSignals, contentType);
    const candidateRecognition = socialVariantSelfRecognitionSignal(candidate, articleSignals, contentType);
    const candidateConversation = socialVariantConversationSignal(candidate, articleSignals, contentType);
    const candidateSavvy = socialVariantSavvySignal(candidate, articleSignals, contentType);
    const candidateIdentityShift = socialVariantIdentityShiftSignal(candidate, articleSignals, contentType);
    const candidateProof = socialVariantProofSignal(candidate, articleSignals, contentType);
    const candidateActionable = socialVariantActionabilitySignal(candidate, articleSignals, contentType);
    const candidateImmediacy = socialVariantImmediacySignal(candidate, articleSignals, contentType);
    const candidateConsequence = socialVariantConsequenceSignal(candidate, articleSignals, contentType);
    const candidateHabitShift = socialVariantHabitShiftSignal(candidate, articleSignals, contentType);
    const candidateFocused = socialVariantPromiseFocusSignal(candidate, articleSignals, contentType);
    const candidatePromiseSync = socialVariantPromiseSyncSignal(candidate, articleTitle, articleSignals, contentType);
    const candidateScannable = socialVariantScannabilitySignal(candidate, contentType);
    const candidateTwoStep = socialVariantTwoStepSignal(candidate, articleSignals, contentType);
    const candidateFrontLoaded = frontLoadedClickSignalScore(candidate?.hook || "", contentType) > 0;
    const frontLoadedNeeded = Math.max(0, Number(selectionState?.targets?.frontLoadedMin || 0) - Number(selectionState?.frontLoadedCount || 0));
    const noveltyNeeded = Math.max(0, Number(selectionState?.targets?.noveltyMin || 0) - Number(selectionState?.noveltyCount || 0));
    const relatabilityNeeded = Math.max(0, Number(selectionState?.targets?.relatabilityMin || 0) - Number(selectionState?.relatableCount || 0));
    const recognitionNeeded = Math.max(0, Number(selectionState?.targets?.recognitionMin || 0) - Number(selectionState?.recognitionCount || 0));
    const conversationNeeded = Math.max(0, Number(selectionState?.targets?.conversationMin || 0) - Number(selectionState?.conversationCount || 0));
    const savvyNeeded = Math.max(0, Number(selectionState?.targets?.savvyMin || 0) - Number(selectionState?.savvyCount || 0));
    const identityShiftNeeded = Math.max(0, Number(selectionState?.targets?.identityShiftMin || 0) - Number(selectionState?.identityShiftCount || 0));
    const proofNeeded = Math.max(0, Number(selectionState?.targets?.proofMin || 0) - Number(selectionState?.proofCount || 0));
    const actionabilityNeeded = Math.max(0, Number(selectionState?.targets?.actionabilityMin || 0) - Number(selectionState?.actionableCount || 0));
    const immediacyNeeded = Math.max(0, Number(selectionState?.targets?.immediacyMin || 0) - Number(selectionState?.immediacyCount || 0));
    const consequenceNeeded = Math.max(0, Number(selectionState?.targets?.consequenceMin || 0) - Number(selectionState?.consequenceCount || 0));
    const habitShiftNeeded = Math.max(0, Number(selectionState?.targets?.habitShiftMin || 0) - Number(selectionState?.habitShiftCount || 0));
    const focusNeeded = Math.max(0, Number(selectionState?.targets?.focusMin || 0) - Number(selectionState?.focusedCount || 0));
    const promiseSyncNeeded = Math.max(0, Number(selectionState?.targets?.promiseSyncMin || 0) - Number(selectionState?.promiseSyncCount || 0));
    const scannableNeeded = Math.max(0, Number(selectionState?.targets?.scannableMin || 0) - Number(selectionState?.scannableCount || 0));
    const twoStepNeeded = Math.max(0, Number(selectionState?.targets?.twoStepMin || 0) - Number(selectionState?.twoStepCount || 0));
    const curiosityNeeded = Math.max(0, Number(selectionState?.targets?.curiosityMin || 0) - Number(selectionState?.curiosityCount || 0));
    const resolutionNeeded = Math.max(0, Number(selectionState?.targets?.resolutionMin || 0) - Number(selectionState?.resolutionCount || 0));
    const contrastNeeded = Math.max(0, Number(selectionState?.targets?.contrastMin || 0) - Number(selectionState?.contrastCount || 0));
    const painNeeded = Math.max(0, Number(selectionState?.targets?.painPointMin || 0) - Number(selectionState?.painPointCount || 0));
    const payoffNeeded = Math.max(0, Number(selectionState?.targets?.payoffMin || 0) - Number(selectionState?.payoffCount || 0));

    if (frontLoadedNeeded > 0) score += candidateFrontLoaded ? (remainingSlots <= frontLoadedNeeded ? 8 : 4) : (remainingSlots <= frontLoadedNeeded ? -8 : 0);
    if (curiosityNeeded > 0) score += candidateCuriosity ? (remainingSlots <= curiosityNeeded ? 6 : 3) : (remainingSlots <= curiosityNeeded ? -6 : 0);
    if (resolutionNeeded > 0) score += candidateResolved ? (remainingSlots <= resolutionNeeded ? 7 : 3) : (remainingSlots <= resolutionNeeded ? -7 : 0);
    if (noveltyNeeded > 0) score += candidateNovelty ? (remainingSlots <= noveltyNeeded ? 7 : 3) : (remainingSlots <= noveltyNeeded ? -7 : 0);
    if (relatabilityNeeded > 0) score += candidateRelatable ? (remainingSlots <= relatabilityNeeded ? 6 : 3) : (remainingSlots <= relatabilityNeeded ? -6 : 0);
    if (recognitionNeeded > 0) score += candidateRecognition ? (remainingSlots <= recognitionNeeded ? 7 : 3) : (remainingSlots <= recognitionNeeded ? -7 : 0);
    if (conversationNeeded > 0) score += candidateConversation ? (remainingSlots <= conversationNeeded ? 6 : 3) : (remainingSlots <= conversationNeeded ? -6 : 0);
    if (savvyNeeded > 0) score += candidateSavvy ? (remainingSlots <= savvyNeeded ? 6 : 3) : (remainingSlots <= savvyNeeded ? -6 : 0);
    if (identityShiftNeeded > 0) score += candidateIdentityShift ? (remainingSlots <= identityShiftNeeded ? 6 : 3) : (remainingSlots <= identityShiftNeeded ? -6 : 0);
    if (proofNeeded > 0) score += candidateProof ? (remainingSlots <= proofNeeded ? 6 : 3) : (remainingSlots <= proofNeeded ? -6 : 0);
    if (actionabilityNeeded > 0) score += candidateActionable ? (remainingSlots <= actionabilityNeeded ? 6 : 3) : (remainingSlots <= actionabilityNeeded ? -6 : 0);
    if (immediacyNeeded > 0) score += candidateImmediacy ? (remainingSlots <= immediacyNeeded ? 6 : 3) : (remainingSlots <= immediacyNeeded ? -6 : 0);
    if (consequenceNeeded > 0) score += candidateConsequence ? (remainingSlots <= consequenceNeeded ? 6 : 3) : (remainingSlots <= consequenceNeeded ? -6 : 0);
    if (habitShiftNeeded > 0) score += candidateHabitShift ? (remainingSlots <= habitShiftNeeded ? 7 : 3) : (remainingSlots <= habitShiftNeeded ? -7 : 0);
    if (focusNeeded > 0) score += candidateFocused ? (remainingSlots <= focusNeeded ? 6 : 3) : (remainingSlots <= focusNeeded ? -6 : 0);
    if (promiseSyncNeeded > 0) score += candidatePromiseSync ? (remainingSlots <= promiseSyncNeeded ? 7 : 3) : (remainingSlots <= promiseSyncNeeded ? -7 : 0);
    if (scannableNeeded > 0) score += candidateScannable ? (remainingSlots <= scannableNeeded ? 6 : 3) : (remainingSlots <= scannableNeeded ? -6 : 0);
    if (twoStepNeeded > 0) score += candidateTwoStep ? (remainingSlots <= twoStepNeeded ? 6 : 3) : (remainingSlots <= twoStepNeeded ? -6 : 0);
    if (contrastNeeded > 0) score += candidateContrast ? (remainingSlots <= contrastNeeded ? 6 : 3) : (remainingSlots <= contrastNeeded ? -6 : 0);
    if (painNeeded > 0) score += candidatePain ? (remainingSlots <= painNeeded ? 9 : 4) : (remainingSlots <= painNeeded ? -8 : 0);
    if (payoffNeeded > 0) score += candidatePayoff ? (remainingSlots <= payoffNeeded ? 9 : 4) : (remainingSlots <= payoffNeeded ? -8 : 0);

    if (candidatePain && candidatePayoff && (painNeeded > 0 || payoffNeeded > 0)) score += 1;
    if ((candidateCuriosity || candidateContrast) && !candidateResolved) score -= 4;

    if (slotIndex > 0 && painNeeded === 0 && payoffNeeded === 0) {
      const painLead = Number(selectionState?.painPointCount || 0) - Number(selectionState?.payoffCount || 0);
      const payoffLead = Number(selectionState?.payoffCount || 0) - Number(selectionState?.painPointCount || 0);
      if (candidatePain && painLead >= 1 && !candidatePayoff) score -= 3;
      if (candidatePayoff && payoffLead >= 1 && !candidatePain) score -= 3;
    }

    if (slotIndex > 0 && candidateCuriosity && Number(selectionState?.curiosityCount || 0) >= Math.max(1, Math.floor((slotIndex + 1) / 2))) score -= 2;

    if (candidateHookForm) {
      if (!usedHookForms.has(candidateHookForm) && usedHookForms.size < hookFormTarget) {
        score += 4;
      } else if (usedHookForms.has(candidateHookForm) && usedHookForms.size < hookFormTarget && remainingSlots <= Math.max(1, hookFormTarget - usedHookForms.size)) {
        score -= 5;
      } else if (slotIndex > 0 && usedHookForms.has(candidateHookForm)) {
        score -= 1;
      }
    }

    if (slotIndex === 0) {
      if (score >= 18) score += 6;
      else if (score >= 14) score += 3;
      if (candidateSpecificity >= 3) score += 2;
      else if (candidateSpecificity < 2) score -= 5;
      if (candidateFrontLoaded) score += 2;
      if ((candidateCuriosity || candidateContrast) && candidateResolved) score += 2;
      if (!candidatePain && !candidatePayoff) score -= 6;
      if (!candidateConsequence && !candidateProof && !candidateActionable) score -= 3;
      if (!candidateHabitShift && !candidateContrast) score -= 2;
      if (!candidateFocused) score -= 4;
      if (candidateScannable) score += 2;
      else score -= 3;
      if (candidateRecognition) score += 2;
      if (candidateSavvy) score += 2;
      if (candidateIdentityShift) score += 2;
      if (candidateTwoStep) score += 2;
      else score -= 3;
    }

    if (score > bestScore) {
      bestScore = score;
      bestIndex = index;
    }
  }

  return bestIndex;
}

export function buildFallbackSocialPack({
  article,
  pages,
  settings,
  contentType = "recipe",
  preferredAngle = "",
  deps,
}) {
  const {
    angleDefinition,
    angleDefinitionsForType,
    buildAngleSequence,
    buildArticleSocialSignals,
    buildFacebookPostMessage,
    buildFallbackCaption,
    cleanMultilineText,
    sharedWordsRatio,
    socialVariantSpecificityScore,
  } = deps;
  const count = Math.max(1, pages.length || 1);
  const angles = buildAngleSequence(count, contentType, preferredAngle);
  const definitions = angleDefinitionsForType(contentType);
  const signals = buildArticleSocialSignals(article, contentType);
  const closers = {
    recipe: {
      quick_dinner: ["Would you make this tonight?", "Save this for a busy evening.", "This one is built for the weeknight rotation."],
      comfort_food: ["Save this for a comfort-food night.", "This is the kind of dinner you come back to.", "Would this hit the spot for you tonight?"],
      budget_friendly: ["Would you try it for a family meal?", "This is a strong one to keep in the low-stress rotation.", "Save this for a practical dinner that still feels good."],
      beginner_friendly: ["Would you cook this as a starter dinner?", "This is a good recipe to build confidence with.", "Save this if you want an easy kitchen win."],
      crowd_pleaser: ["Who would you make this for?", "This one is built for repeat requests.", "Save this for the next easy family dinner."],
      better_than_takeout: ["Would you skip takeout for this?", "This is the kind of fakeout takeaway people repeat.", "Save this for the night you want the payoff without delivery."],
    },
    food_fact: {
      myth_busting: ["Would this change how you think about it?", "Save this if you want the cleaner answer.", "This is the kind of kitchen truth worth keeping."],
      surprising_truth: ["Did you expect that?", "Save this for the next time it comes up in the kitchen.", "This changes the way the topic lands."],
      kitchen_mistake: ["Have you been doing this too?", "Save this so the mistake stops repeating.", "This one catches more cooks than it should."],
      smarter_shortcut: ["Would you use the simpler move instead?", "Save this for the next low-friction kitchen fix.", "This is a better shortcut than most people use."],
      what_most_people_get_wrong: ["Most people miss this part.", "Save this if you want the clearer version.", "This is the mistake worth fixing first."],
      ingredient_truth: ["This changes how the ingredient makes sense.", "Save this if you want the useful version, not the fuzzy one.", "This one explains more than the label ever does."],
      changes_how_you_cook_it: ["This changes the next time you cook it.", "Save this before your next kitchen round.", "This one earns a place in the mental file."],
      restaurant_vs_home: ["Home cooking works differently here.", "Save this if you want the realistic home-kitchen answer.", "This is where restaurant logic throws people off."],
    },
  };
  const recipeTemplates = {
    quick_dinner: (title, index, detail) => ({
      hook: `Busy nights need ${detail.hook_topic || title} instead of more drag.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Fast enough for a real weeknight.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Big payoff, clear steps, and no unnecessary drag.",
        closers.recipe.quick_dinner[index % closers.recipe.quick_dinner.length],
      ),
    }),
    comfort_food: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} brings comfort-food payoff instead of an all-night project.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Cozy, rich, and built for the kind of dinner you actually want.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It feels indulgent without making the method harder.",
        closers.recipe.comfort_food[index % closers.recipe.comfort_food.length],
      ),
    }),
    budget_friendly: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} keeps dinner practical rather than feeling cheap.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Simple ingredients, strong flavor, and no unnecessary extras.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "This one feels generous without making the grocery list harder.",
        closers.recipe.budget_friendly[index % closers.recipe.budget_friendly.length],
      ),
    }),
    beginner_friendly: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} is easier than it looks and still worth repeating.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "Approachable steps, clear detail, and a result that still feels impressive.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "This is the kind of recipe that builds confidence fast.",
        closers.recipe.beginner_friendly[index % closers.recipe.beginner_friendly.length],
      ),
    }),
    crowd_pleaser: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} is the kind of meal people ask for again.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Easy to serve, easy to repeat, and hard to complain about.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It works when you want a meal that lands with everyone.",
        closers.recipe.crowd_pleaser[index % closers.recipe.crowd_pleaser.length],
      ),
    }),
    better_than_takeout: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} gives takeout payoff instead of the delivery wait.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "Big payoff, better control, and a home-kitchen method that actually works.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It gives you the restaurant-style hit without the delivery wait.",
        closers.recipe.better_than_takeout[index % closers.recipe.better_than_takeout.length],
      ),
    }),
  };
  const factTemplates = {
    myth_busting: (title, index, detail) => ({
      hook: `Most advice on ${detail.hook_topic || title} misses the useful detail.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The common version of this advice is off.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The useful answer is simpler and more practical in a real kitchen.",
        closers.food_fact.myth_busting[index % closers.food_fact.myth_busting.length],
      ),
    }),
    surprising_truth: (title, index, detail) => ({
      hook: `One kitchen detail changes how ${detail.hook_topic || title} lands.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "There is one detail that changes the whole takeaway.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "Once you see it clearly, the kitchen decision gets easier.",
        closers.food_fact.surprising_truth[index % closers.food_fact.surprising_truth.length],
      ),
    }),
    kitchen_mistake: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} hides a mistake people repeat all the time.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The problem is common because the bad advice sounds reasonable.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The fix is easier once you know what is actually happening.",
        closers.food_fact.kitchen_mistake[index % closers.food_fact.kitchen_mistake.length],
      ),
    }),
    smarter_shortcut: (title, index, detail) => ({
      hook: `The simpler move with ${detail.hook_topic || title} is better than the usual advice.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "There is a cleaner shortcut here.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It saves effort without watering down the result.",
        closers.food_fact.smarter_shortcut[index % closers.food_fact.smarter_shortcut.length],
      ),
    }),
    what_most_people_get_wrong: (title, index, detail) => ({
      hook: `Most people start ${detail.hook_topic || title} from the wrong assumption.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "The confusion usually begins with one bad assumption.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Once that gets corrected, the rest of the topic makes more sense.",
        closers.food_fact.what_most_people_get_wrong[index % closers.food_fact.what_most_people_get_wrong.length],
      ),
    }),
    ingredient_truth: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} makes more sense when function matters more than hype.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "This is less about hype and more about function.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "The ingredient works a certain way, and that changes the result.",
        closers.food_fact.ingredient_truth[index % closers.food_fact.ingredient_truth.length],
      ),
    }),
    changes_how_you_cook_it: (title, index, detail) => ({
      hook: `One practical detail in ${detail.hook_topic || title} changes your next move instead of just the theory.`,
      caption: buildFallbackCaption(
        detail.payoff_line || detail.summary_line || "The useful part is not just knowing the fact.",
        detail.consequence_line || detail.detail_line,
        detail.proof_line || "It is knowing how that fact changes your next cooking move.",
        closers.food_fact.changes_how_you_cook_it[index % closers.food_fact.changes_how_you_cook_it.length],
      ),
    }),
    restaurant_vs_home: (title, index, detail) => ({
      hook: `${detail.hook_topic || title} works differently at home than people expect.`,
      caption: buildFallbackCaption(
        detail.pain_line || detail.summary_line || "A lot of the confusion comes from borrowing restaurant logic.",
        detail.consequence_line || detail.detail_line,
        detail.payoff_line || detail.proof_line || "Home kitchens need a more realistic answer.",
        closers.food_fact.restaurant_vs_home[index % closers.food_fact.restaurant_vs_home.length],
      ),
    }),
  };
  const templates = contentType === "recipe" ? recipeTemplates : factTemplates;

  return Array.from({ length: count }, (_, index) => {
    const page = pages[index] || null;
    const angleKey = angles[index] || definitions[index % definitions.length].key;
    const variant = (templates[angleKey] || Object.values(templates)[0])(article.title, index, signals);
    const angleLabel = angleDefinition(angleKey, contentType)?.label || angleKey.replace(/_/g, " ");
    const builtVariant = {
      id: `variant-${index + 1}`,
      angle_key: angleKey,
      hook: variant.hook,
      caption: variant.caption,
      cta_hint: page?.label ? `${angleLabel} angle on ${page.label}` : `General ${contentType} post`,
    };
    if (
      signals.detail_line &&
      socialVariantSpecificityScore(builtVariant, signals) < 2 &&
      sharedWordsRatio(`${builtVariant.hook} ${builtVariant.caption}`, signals.detail_line) < 0.1
    ) {
      const closerLine = cleanMultilineText(builtVariant.caption).split(/\r?\n/).filter(Boolean).slice(-1)[0] || "";
      builtVariant.caption = buildFallbackCaption(
        signals.detail_line,
        builtVariant.caption,
        signals.proof_line || signals.page_signal_line || signals.final_reward_line,
        closerLine,
      );
    }
    builtVariant.post_message = buildFacebookPostMessage(builtVariant, "");
    return builtVariant;
  });
}

export function ensureSocialPackCoverage({
  value,
  pages,
  article,
  settings,
  contentType = "recipe",
  preferredAngle = "",
  deps,
}) {
  const {
    buildAngleSequence,
    buildArticleSocialSignals,
    buildFacebookPostMessage,
    classifySocialHookForm,
    cleanMultilineText,
    cleanText,
    frontLoadedClickSignalScore,
    normalizeCaptionOpeningFingerprint,
    normalizeHookFingerprint,
    normalizeSocialFingerprint,
    normalizeSocialPack,
    socialVariantActionabilitySignal,
    socialVariantConsequenceSignal,
    socialVariantContrastSignal,
    socialVariantConversationSignal,
    socialVariantCuriositySignal,
    socialVariantHabitShiftSignal,
    socialVariantIdentityShiftSignal,
    socialVariantImmediacySignal,
    socialVariantLooksWeak,
    socialVariantNoveltyScore,
    socialVariantPainPointSignal,
    socialVariantPayoffSignal,
    socialVariantPromiseFocusSignal,
    socialVariantPromiseSyncSignal,
    socialVariantProofSignal,
    socialVariantRelatabilitySignal,
    socialVariantResolvesEarly,
    socialVariantSavvySignal,
    socialVariantScannabilitySignal,
    socialVariantSelfRecognitionSignal,
    socialVariantTwoStepSignal,
  } = deps;
  const desiredCount = Math.max(1, Array.isArray(pages) ? pages.length : 0);
  const normalized = normalizeSocialPack(value, contentType);
  const fallback = buildFallbackSocialPack({ article, pages, settings, contentType, preferredAngle, deps });
  const articleSignals = buildArticleSocialSignals(article, contentType);
  const angleSequence = buildAngleSequence(desiredCount, contentType, preferredAngle);
  const signalTargets = desiredSocialSignalTargets(desiredCount);
  const unusedCandidates = [...normalized];
  const usedFingerprints = new Set();
  const usedHookFingerprints = new Set();
  const usedCaptionOpenings = new Set();
  const usedHookForms = new Set();
  let frontLoadedCount = 0;
  let noveltyCount = 0;
  let relatableCount = 0;
  let recognitionCount = 0;
  let conversationCount = 0;
  let savvyCount = 0;
  let identityShiftCount = 0;
  let proofCount = 0;
  let actionableCount = 0;
  let immediacyCount = 0;
  let consequenceCount = 0;
  let habitShiftCount = 0;
  let focusedCount = 0;
  let promiseSyncCount = 0;
  let scannableCount = 0;
  let twoStepCount = 0;
  let curiosityCount = 0;
  let resolutionCount = 0;
  let contrastCount = 0;
  let painPointCount = 0;
  let payoffCount = 0;

  return Array.from({ length: desiredCount }, (_, index) => {
    const desiredAngle = angleSequence[index];
    const bestCandidateIndex = findBestSocialCandidateIndex({
      candidates: unusedCandidates,
      article,
      contentType,
      desiredAngle,
      usedFingerprints,
      usedHookFingerprints,
      usedCaptionOpenings,
      selectionState: {
        frontLoadedCount,
        noveltyCount,
        relatableCount,
        recognitionCount,
        conversationCount,
        savvyCount,
        identityShiftCount,
        proofCount,
        actionableCount,
        immediacyCount,
        consequenceCount,
        habitShiftCount,
        focusedCount,
        promiseSyncCount,
        scannableCount,
        twoStepCount,
        curiosityCount,
        resolutionCount,
        contrastCount,
        painPointCount,
        payoffCount,
        usedHookForms,
        targets: signalTargets,
        remainingSlots: desiredCount - index,
        slotIndex: index,
      },
      deps,
    });
    const selectedCandidate = bestCandidateIndex >= 0 ? unusedCandidates.splice(bestCandidateIndex, 1)[0] : null;
    const base = selectedCandidate || fallback[index] || fallback[fallback.length - 1];
    let variant = {
      ...base,
      angle_key: desiredAngle,
      cta_hint: cleanText(base?.cta_hint || (pages[index]?.label ? `Use on ${pages[index]?.label}` : "")),
      post_message: cleanMultilineText(base?.post_message || base?.postMessage || ""),
    };
    variant.post_message = variant.post_message || buildFacebookPostMessage(variant, "");

    const fingerprint = normalizeSocialFingerprint(variant);
    const hookFingerprint = normalizeHookFingerprint(variant);
    const captionOpeningFingerprint = normalizeCaptionOpeningFingerprint(variant);
    if (
      !fingerprint ||
      usedFingerprints.has(fingerprint) ||
      (hookFingerprint && usedHookFingerprints.has(hookFingerprint)) ||
      (captionOpeningFingerprint && usedCaptionOpenings.has(captionOpeningFingerprint)) ||
      socialVariantLooksWeak(variant, article.title || "", contentType, articleSignals)
    ) {
      variant = {
        ...(fallback[index] || fallback[fallback.length - 1]),
        angle_key: angleSequence[index],
      };
      variant.post_message = cleanMultilineText(variant.post_message || "") || buildFacebookPostMessage(variant, "");
    }

    usedFingerprints.add(normalizeSocialFingerprint(variant));
    if (normalizeHookFingerprint(variant)) usedHookFingerprints.add(normalizeHookFingerprint(variant));
    if (normalizeCaptionOpeningFingerprint(variant)) usedCaptionOpenings.add(normalizeCaptionOpeningFingerprint(variant));
    if (classifySocialHookForm(variant)) usedHookForms.add(classifySocialHookForm(variant));
    if (socialVariantPainPointSignal(variant, articleSignals)) painPointCount += 1;
    if (socialVariantPayoffSignal(variant, articleSignals)) payoffCount += 1;
    if (socialVariantCuriositySignal(variant, articleSignals)) curiosityCount += 1;
    if (socialVariantResolvesEarly(variant, articleSignals, contentType)) resolutionCount += 1;
    if (socialVariantNoveltyScore(variant, article.title || "", articleSignals) >= 2) noveltyCount += 1;
    if (socialVariantRelatabilitySignal(variant, articleSignals, contentType)) relatableCount += 1;
    if (socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)) recognitionCount += 1;
    if (socialVariantConversationSignal(variant, articleSignals, contentType)) conversationCount += 1;
    if (socialVariantSavvySignal(variant, articleSignals, contentType)) savvyCount += 1;
    if (socialVariantIdentityShiftSignal(variant, articleSignals, contentType)) identityShiftCount += 1;
    if (socialVariantProofSignal(variant, articleSignals, contentType)) proofCount += 1;
    if (socialVariantActionabilitySignal(variant, articleSignals, contentType)) actionableCount += 1;
    if (socialVariantImmediacySignal(variant, articleSignals, contentType)) immediacyCount += 1;
    if (socialVariantConsequenceSignal(variant, articleSignals, contentType)) consequenceCount += 1;
    if (socialVariantHabitShiftSignal(variant, articleSignals, contentType)) habitShiftCount += 1;
    if (socialVariantPromiseFocusSignal(variant, articleSignals, contentType)) focusedCount += 1;
    if (socialVariantPromiseSyncSignal(variant, article.title || "", articleSignals, contentType)) promiseSyncCount += 1;
    if (socialVariantScannabilitySignal(variant, contentType)) scannableCount += 1;
    if (socialVariantTwoStepSignal(variant, articleSignals, contentType)) twoStepCount += 1;
    if (socialVariantContrastSignal(variant, articleSignals)) contrastCount += 1;
    if (frontLoadedClickSignalScore(variant?.hook || "", contentType) > 0) frontLoadedCount += 1;
    return variant;
  }).filter(Boolean);
}
