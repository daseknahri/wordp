export function createSocialVariantHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    countLines,
    countWords,
    frontLoadedClickSignalScore,
    normalizeAngleKey,
    normalizeSlug,
    normalizeSocialLineFingerprint,
    sharedWordsRatio,
    trimText,
  } = deps;

  function containsCheapSuspensePattern(text) {
    return /\b(what happens next|nobody tells you|no one tells you|what they don't tell you|the secret(?: to)?|finally revealed|you(?:'ll| will) never guess|hidden truth)\b/i.test(cleanText(text || ""));
  }

  function socialVariantGenericPenalty(variant) {
    const hook = cleanText(variant?.hook || "").toLowerCase();
    const caption = cleanMultilineText(variant?.caption || "").toLowerCase();
    const genericPatterns = [
      /\byou need to try\b/,
      /\byou should\b/,
      /\bmust try\b/,
      /\bthis is\b/,
      /\bthis one\b/,
      /\bthese are\b/,
      /\bhere'?s why\b/,
      /\bso good\b/,
      /\bbest ever\b/,
      /\byou won't believe\b/,
      /\bi'm obsessed\b/,
      /\bgame changer\b/,
      /\breal cooks\b/,
      /\bgood cooks know\b/,
      /\bsmart cooks\b/,
      /\bserious cooks\b/,
      /\bpeople who know better\b/,
      /\bif you know what you're doing\b/,
      /\bamateurs?\b/,
      /\brookie move\b/,
      /\blazy cooks\b/,
      /\bthis one is everything\b/,
      /\btotal winner\b/,
      /\bwhat happens next\b/,
      /\bnobody tells you\b/,
      /\bno one tells you\b/,
      /\bwhat they don't tell you\b/,
      /\bthe secret(?: to)?\b/,
      /\bfinally revealed\b/,
      /\byou(?:'ll| will) never guess\b/,
      /\bhidden truth\b/,
    ];

    let penalty = 0;
    for (const pattern of genericPatterns) {
      if (pattern.test(hook)) {
        penalty += 6;
      }
      if (pattern.test(caption)) {
        penalty += 4;
      }
    }

    return penalty;
  }

  function classifySocialHookForm(variant) {
    const hook = cleanText(variant?.hook || "").toLowerCase();
    if (!hook) {
      return "";
    }
    if (/^\d+\b/.test(hook)) {
      return "numbered";
    }
    if (/[?]/.test(hook) || /^(why|how|what|when|which)\b/.test(hook)) {
      return "question";
    }
    if (/\b(instead of|rather than|not just|not the|what most people|get wrong|vs\.?|versus)\b/.test(hook)) {
      return "contrast";
    }
    if (/^(stop|avoid|fix|skip|quit|never)\b/.test(hook) || /\b(mistake|wrong|avoid|fix)\b/.test(hook)) {
      return "correction";
    }
    if (/^(save|make|keep|use|try|cook|shop)\b/.test(hook)) {
      return "directive";
    }
    if (/\b(faster|easier|better|crispy|creamy|juicy|budget|weeknight|shortcut|payoff|result)\b/.test(hook)) {
      return "payoff";
    }
    if (/\b(problem|waste|stuck|mistake|harder|overpay|dry|soggy|flat)\b/.test(hook)) {
      return "problem";
    }
    return "statement";
  }

  function socialVariantNoveltyScore(variant, articleTitle = "", articleSignals = {}) {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (!combined) {
      return 0;
    }

    const titleOverlap = articleTitle ? sharedWordsRatio(combined, articleTitle) : 0;
    const noveltyTargets = [
      articleSignals.detail_line,
      articleSignals.proof_line,
      articleSignals.page_signal_line,
      articleSignals.final_reward_line,
    ].filter(Boolean);
    let bestOverlap = 0;

    for (const target of noveltyTargets) {
      bestOverlap = Math.max(bestOverlap, sharedWordsRatio(combined, target));
    }

    let score = 0;
    if (bestOverlap >= 0.18) {
      score += 2;
    } else if (bestOverlap >= 0.1) {
      score += 1;
    }
    if (titleOverlap > 0 && titleOverlap <= 0.58 && bestOverlap >= 0.08) {
      score += 1;
    }
    if (articleSignals?.page_signal_line && sharedWordsRatio(combined, articleSignals.page_signal_line) >= 0.12) {
      score += 1;
    }
    if (containsCheapSuspensePattern(hook) || containsCheapSuspensePattern(caption)) {
      score -= 1;
    }

    return Math.max(0, score);
  }

  function buildArticleAnchorPhrases(articleSignals = {}) {
    const rawPhrases = [
      articleSignals.hook_topic,
      articleSignals.heading_topic,
      articleSignals.ingredient_focus,
      articleSignals.detail_line,
      articleSignals.page_signal_line,
    ]
      .filter(Boolean)
      .flatMap((value) =>
        cleanText(value)
          .split(/\s*(?:,| and | with | without | or )\s*/i)
          .map((part) => trimText(cleanText(part), 60))
          .filter(Boolean),
      );
    const seen = new Set();

    return rawPhrases.filter((phrase) => {
      const fingerprint = normalizeSlug(phrase);
      if (!fingerprint || seen.has(fingerprint) || countWords(phrase) < 1) {
        return false;
      }
      seen.add(fingerprint);
      return true;
    });
  }

  function socialVariantAnchorSignal(variant, articleSignals = {}) {
    const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
    if (!combined) {
      return false;
    }

    return buildArticleAnchorPhrases(articleSignals).some((target) => {
      const overlap = sharedWordsRatio(combined, target);
      return overlap >= 0.18 || (countWords(target) <= 2 && overlap >= 0.12);
    });
  }

  function socialVariantSpecificityScore(variant, articleSignals = {}) {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = `${hook} ${caption}`.trim();
    const scoringTargets = [
      articleSignals.summary_line,
      articleSignals.pain_line,
      articleSignals.payoff_line,
      articleSignals.proof_line,
      articleSignals.detail_line,
      articleSignals.page_signal_line,
      articleSignals.final_reward_line,
    ].filter(Boolean);
    const focusTargets = [
      articleSignals.heading_topic,
      articleSignals.ingredient_focus,
      articleSignals.meta_line,
    ].filter(Boolean);
    let score = 0;

    if (/\b\d+\b/.test(hook)) {
      score += 1;
    }
    if (/\b(crispy|creamy|cheesy|garlicky|juicy|buttery|sticky|caramelized|faster|easier|mistake|shortcut|truth)\b/i.test(combined)) {
      score += 1;
    }

    for (const target of scoringTargets) {
      const overlap = sharedWordsRatio(combined, target);
      if (overlap >= 0.18) {
        score += 2;
        break;
      }
      if (overlap >= 0.1) {
        score += 1;
        break;
      }
    }

    for (const target of focusTargets) {
      const overlap = sharedWordsRatio(combined, target);
      if (overlap >= 0.2) {
        score += 1;
        break;
      }
    }

    return score;
  }

  function socialVariantPainPointSignal(variant, articleSignals = {}) {
    const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
    if (/\b(mistake|wrong|avoid|fix|shortcut|faster|easier|save|stop|problem|ruin|dry|bland|soggy|overcook|underseason|what most people get wrong)\b/i.test(combined)) {
      return true;
    }

    return Boolean(articleSignals?.pain_line) && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.18;
  }

  function socialVariantPayoffSignal(variant, articleSignals = {}) {
    const combined = cleanText(`${variant?.hook || ""} ${variant?.caption || ""}`);
    if (/\b(payoff|result|worth it|comfort|crispy|creamy|juicy|easier|faster|better|clearer|simpler|repeatable|useful|satisfying)\b/i.test(combined)) {
      return true;
    }

    if (Boolean(articleSignals?.payoff_line) && sharedWordsRatio(combined, articleSignals.payoff_line) >= 0.18) {
      return true;
    }

    return Boolean(articleSignals?.final_reward_line) && sharedWordsRatio(combined, articleSignals.final_reward_line) >= 0.18;
  }

  function socialVariantProofSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    if (/\b(\d+\s?(?:minute|min|minutes|mins|step|steps)|one[- ]pan|sheet pan|air fryer|skillet|oven|temperature|label|pantry|fridge|crispy|creamy|cheesy|garlicky|juicy|golden|without drying|without going soggy|that keeps|which keeps|because|so it stays|so you get)\b/i.test(combined)) {
      return true;
    }

    const clueTargets = [
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.page_signal_line,
      articleSignals?.final_reward_line,
    ].filter(Boolean);
    if (clueTargets.some((target) => sharedWordsRatio(combined, target) >= 0.18)) {
      return true;
    }

    return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
      && /\b(because|so|without|keeps|stays|means|difference|detail|result)\b/i.test(earlyCaption || hook)
      && socialVariantSpecificityScore(variant, articleSignals) >= 2;
  }

  function socialVariantCuriositySignal(variant, articleSignals = {}) {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const hookHasCue = /\b(why|how|turns out|actually|the difference|detail|changes|what most people|get wrong|mistake|truth|assumption)\b/i.test(hook);
    const clueOverlap = [
      articleSignals?.page_signal_line,
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.final_reward_line,
    ]
      .filter(Boolean)
      .some((target) => sharedWordsRatio(combined, target) >= 0.18);

    if (!hookHasCue || containsCheapSuspensePattern(hook) || containsCheapSuspensePattern(caption)) {
      return false;
    }

    return clueOverlap || socialVariantSpecificityScore(variant, articleSignals) >= 2;
  }

  function socialVariantContrastSignal(variant, articleSignals = {}) {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (/\b(instead of|rather than|not just|not the|more than|less about|without turning|but not|vs\.?|versus|the part that|what changes|what most people miss)\b/i.test(combined)) {
      return true;
    }

    const painOverlap = Boolean(articleSignals?.pain_line) ? sharedWordsRatio(combined, articleSignals.pain_line) : 0;
    const payoffOverlap = Boolean(articleSignals?.payoff_line) ? sharedWordsRatio(combined, articleSignals.payoff_line) : 0;
    const proofOverlap = Boolean(articleSignals?.proof_line) ? sharedWordsRatio(combined, articleSignals.proof_line) : 0;

    return (painOverlap >= 0.12 && payoffOverlap >= 0.12) || (painOverlap >= 0.12 && proofOverlap >= 0.12);
  }

  function socialVariantRelatabilitySignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (!combined) {
      return false;
    }

    const recipePattern = /\b(busy night|weeknight|after work|family dinner|home cook|at home|takeout night|budget dinner|feed everyone|tonight|make this tonight|fridge|pantry)\b/i;
    const factPattern = /\b(in your kitchen|at home|home cook|next time you cook|next time you buy|next time you store|next time you shop|your pantry|your fridge|the label|grocery aisle|home kitchen)\b/i;
    const pattern = contentType === "recipe" ? recipePattern : factPattern;
    if (pattern.test(combined)) {
      return true;
    }

    if (/\b(you|your)\b/i.test(combined) && articleSignals?.pain_line && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.16) {
      return true;
    }

    return Boolean(articleSignals?.summary_line) && sharedWordsRatio(combined, articleSignals.summary_line) >= 0.18 && /\b(you|your|home|kitchen|dinner|cook)\b/i.test(combined);
  }

  function socialVariantSelfRecognitionSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (!combined) {
      return false;
    }

    const recipePattern = /\b(if your|when your|if you keep|if dinner keeps|if this keeps|the reason your|why your|you know that moment when|you know the night when)\b/i;
    const factPattern = /\b(if your|when your|if you keep|if that label keeps|if this keeps|the reason your|why your|you know that moment when|you know the shopping moment when)\b/i;
    const pattern = contentType === "recipe" ? recipePattern : factPattern;
    const repeatedOutcomePattern = /\b(keeps getting|keeps turning|keeps ending up|still turns|still ends up|still feels|same mistake|same result|same flat|same soggy|same dry|same bland|same confusion|same waste)\b/i;
    const articlePainOverlap = Boolean(articleSignals?.pain_line) && sharedWordsRatio(combined, articleSignals.pain_line) >= 0.16;
    const articleConsequenceOverlap = Boolean(articleSignals?.consequence_line) && sharedWordsRatio(combined, articleSignals.consequence_line) >= 0.16;

    if (pattern.test(combined) && (repeatedOutcomePattern.test(combined) || articlePainOverlap || articleConsequenceOverlap)) {
      return true;
    }

    return /\b(your|you)\b/i.test(combined)
      && socialVariantRelatabilitySignal(variant, articleSignals, contentType)
      && (
        repeatedOutcomePattern.test(combined)
        || articlePainOverlap
        || articleConsequenceOverlap
        || socialVariantConsequenceSignal(variant, articleSignals, contentType)
      )
      && socialVariantAnchorSignal(variant, articleSignals);
  }

  function socialVariantConversationSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (!combined) {
      return false;
    }

    if (/\b(comment|tag|share|send this|drop a|tell me in the comments|let me know)\b/i.test(combined)) {
      return false;
    }

    const recipePattern = /\b(your house|your table|your family|in your family|the person who|the friend who|most home cooks|a lot of home cooks|everyone thinks|everyone assumes|if you always|the way you always|which one|debate|split)\b/i;
    const factPattern = /\b(your kitchen|your pantry|your fridge|your grocery cart|at the store|on the label|the version you always buy|what most people buy|most people think|a lot of people assume|if you always|which one|debate|split)\b/i;
    const pattern = contentType === "recipe" ? recipePattern : factPattern;
    if (pattern.test(combined)) {
      return true;
    }

    return socialVariantRelatabilitySignal(variant, articleSignals, contentType)
      && socialVariantAnchorSignal(variant, articleSignals)
      && (
        socialVariantContrastSignal(variant, articleSignals)
        || socialVariantPainPointSignal(variant, articleSignals)
        || /\b(people|everyone|most|house|family|table|friend|buy|shop|order)\b/i.test(combined)
      );
  }

  function socialVariantHabitShiftSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    if (!combined) {
      return false;
    }

    const explicitShiftPattern = contentType === "recipe"
      ? /\b(if you always|if you still|the way you always|usual move|usual dinner move|default dinner move|instead of|rather than|stop doing|stop treating|swap|trade|skip the|break the habit|usual habit|same dinner habit|keep doing)\b/i
      : /\b(if you always|if you still|the way you always|usual move|default move|instead of|rather than|stop doing|swap|trade|skip the|break the habit|usual habit|same shopping habit|same kitchen habit|keep doing)\b/i;
    const shiftWords = /\b(always|still|instead of|rather than|swap|trade|usual|default|habit|keep doing|stop doing|break the habit|same mistake)\b/i.test(combined);
    const betterResult =
      socialVariantContrastSignal(variant, articleSignals)
      || socialVariantConsequenceSignal(variant, articleSignals, contentType)
      || socialVariantPayoffSignal(variant, articleSignals)
      || socialVariantActionabilitySignal(variant, articleSignals, contentType);
    const grounded =
      socialVariantAnchorSignal(variant, articleSignals)
      || socialVariantSpecificityScore(variant, articleSignals) >= 2;
    const sociallyRecognizable =
      socialVariantRelatabilitySignal(variant, articleSignals, contentType)
      || socialVariantConversationSignal(variant, articleSignals, contentType);

    if (explicitShiftPattern.test(combined) && betterResult && grounded) {
      return true;
    }

    if (shiftWords && sociallyRecognizable && betterResult && grounded) {
      return true;
    }

    return Boolean(articleSignals?.consequence_line)
      && Boolean(articleSignals?.payoff_line)
      && betterResult
      && sharedWordsRatio(combined, `${articleSignals.consequence_line} ${articleSignals.payoff_line}`) >= 0.16;
  }

  function socialVariantSavvySignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    if (/\b(smart cooks|real cooks|good cooks know|bad cooks|lazy cooks|amateurs?|rookie move)\b/i.test(combined)) {
      return false;
    }

    const explicitPattern = contentType === "recipe"
      ? /\b(smarter move|smarter dinner move|better move|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the method that works|the version worth making|the version worth repeating|worth using|worth making)\b/i
      : /\b(smarter move|smarter buy|better buy|better pick|better choice|better call|better bet|cleaner move|smart swap|smarter swap|the move that works|the version worth buying|the version worth keeping|the detail worth knowing|worth checking|worth buying|worth using)\b/i;
    const smartChoiceWords = /\b(smarter|cleaner|better|worth|reliable|more reliable|better call|better bet|better pick|better choice|better move|good call)\b/i.test(hook || earlyCaption || combined);
    const grounded =
      socialVariantAnchorSignal(variant, articleSignals)
      || socialVariantSpecificityScore(variant, articleSignals) >= 2;
    const usefulSignal =
      socialVariantProofSignal(variant, articleSignals, contentType)
      || socialVariantActionabilitySignal(variant, articleSignals, contentType)
      || socialVariantPromiseSyncSignal(variant, articleSignals?.title || "", articleSignals, contentType)
      || socialVariantHabitShiftSignal(variant, articleSignals, contentType)
      || socialVariantConsequenceSignal(variant, articleSignals, contentType)
      || socialVariantPayoffSignal(variant, articleSignals);
    const overlapSignal = [
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.payoff_line,
      articleSignals?.page_signal_line,
    ]
      .filter(Boolean)
      .some((target) => sharedWordsRatio(combined, target) >= 0.16);

    if (explicitPattern.test(combined) && grounded && usefulSignal) {
      return true;
    }

    return smartChoiceWords && grounded && (usefulSignal || overlapSignal);
  }

  function socialVariantIdentityShiftSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    if (/\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you're doing|amateurs?|rookie move|lazy cooks)\b/i.test(combined)) {
      return false;
    }

    const explicitPattern = contentType === "recipe"
      ? /\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old weeknight move|past the usual dinner drag|no longer stuck with|graduate from)\b/i
      : /\b(done with|leave behind|move past|stop settling for|break out of|not your old default|not the old shopping move|past the usual confusion|no longer stuck with|graduate from)\b/i;
    const shiftWords = /\b(done with|leave behind|move past|past the usual|no longer stuck with|stop settling|old default|usual default|graduate from|break out of)\b/i.test(hook || earlyCaption || combined);
    const grounded =
      socialVariantAnchorSignal(variant, articleSignals)
      || socialVariantSpecificityScore(variant, articleSignals) >= 2;
    const practicalLift =
      socialVariantSavvySignal(variant, articleSignals, contentType)
      || socialVariantHabitShiftSignal(variant, articleSignals, contentType)
      || socialVariantConsequenceSignal(variant, articleSignals, contentType)
      || socialVariantActionabilitySignal(variant, articleSignals, contentType)
      || socialVariantPayoffSignal(variant, articleSignals);
    const recognition =
      socialVariantSelfRecognitionSignal(variant, articleSignals, contentType)
      || socialVariantRelatabilitySignal(variant, articleSignals, contentType)
      || socialVariantConversationSignal(variant, articleSignals, contentType);

    if (explicitPattern.test(combined) && grounded && practicalLift && recognition) {
      return true;
    }

    return shiftWords && grounded && practicalLift && recognition;
  }

  function socialVariantActionabilitySignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    if (/\b(next time you|before you|use this|skip the|start with|watch for|look for|keep it|swap in|swap out|do this|try this|store it|cook it|buy it|save this for|make this when)\b/i.test(combined)) {
      return true;
    }

    const guidanceTargets = [
      articleSignals?.detail_line,
      articleSignals?.proof_line,
      articleSignals?.pain_line,
      articleSignals?.payoff_line,
    ].filter(Boolean);
    if (guidanceTargets.some((target) => sharedWordsRatio(combined, target) >= 0.18) && /\b(you|your|next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i.test(earlyCaption || combined)) {
      return true;
    }

    return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
      && /\b(next|before|when|keep|skip|use|cook|store|buy|make|watch)\b/i.test(earlyCaption || combined)
      && socialVariantSpecificityScore(variant, articleSignals) >= 2;
  }

  function socialVariantImmediacySignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    const recipePattern = /\b(tonight|this week|this weekend|after work|before dinner|next grocery run|next shop|next time you cook|next time you shop|next time you make|weeknight|tomorrow night)\b/i;
    const factPattern = /\b(this week|this weekend|next grocery run|next time you buy|next time you shop|next time you cook|next time you order|next time you store|before you buy|before you cook|before you order)\b/i;
    const pattern = contentType === "recipe" ? recipePattern : factPattern;
    if (pattern.test(combined)) {
      return true;
    }

    const immediacyTargets = [
      articleSignals?.detail_line,
      articleSignals?.proof_line,
      articleSignals?.payoff_line,
      articleSignals?.page_signal_line,
      articleSignals?.final_reward_line,
    ].filter(Boolean);
    if (immediacyTargets.some((target) => sharedWordsRatio(combined, target) >= 0.16)
      && /\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i.test(earlyCaption || combined)) {
      return true;
    }

    return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
      && socialVariantSpecificityScore(variant, articleSignals) >= 2
      && /\b(tonight|this week|this weekend|next|before|after work|grocery run|when you cook|when you buy|when you order)\b/i.test(combined);
  }

  function socialVariantConsequenceSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const combined = cleanText(`${hook} ${caption}`);
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");

    if (!combined) {
      return false;
    }

    if (/\b(otherwise|or you keep|or it keeps|costs you|keeps costing|keeps wasting|wastes time|wastes money|ends up|turns dry|turns soggy|falls flat|miss the detail|miss that|without the detail|keep repeating|same mistake|less payoff|more effort|still paying for|still stuck with)\b/i.test(combined)) {
      return true;
    }

    if (Boolean(articleSignals?.consequence_line) && sharedWordsRatio(combined, articleSignals.consequence_line) >= 0.18) {
      return true;
    }

    return frontLoadedClickSignalScore(earlyCaption || hook, contentType) > 0
      && /\b(otherwise|miss|lose|cost|waste|repeat|stuck|flat|dry|soggy|harder|less payoff|more effort)\b/i.test(earlyCaption || combined)
      && socialVariantSpecificityScore(variant, articleSignals) >= 2;
  }

  function socialVariantPromiseFocusSignal(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");
    const leadWindow = cleanText(`${hook} ${earlyCaption}`);
    if (!leadWindow) {
      return false;
    }

    const separatorCount = (leadWindow.match(/,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/gi) || []).length;
    const promiseHitCount = [
      socialVariantPainPointSignal(variant, articleSignals),
      socialVariantPayoffSignal(variant, articleSignals),
      socialVariantProofSignal(variant, articleSignals, contentType),
      socialVariantActionabilitySignal(variant, articleSignals, contentType),
      socialVariantConsequenceSignal(variant, articleSignals, contentType),
      socialVariantCuriositySignal(variant, articleSignals),
      socialVariantContrastSignal(variant, articleSignals),
    ].filter(Boolean).length;
    const focusedOverlap = [
      articleSignals?.pain_line,
      articleSignals?.payoff_line,
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.consequence_line,
    ]
      .filter(Boolean)
      .some((target) => sharedWordsRatio(leadWindow, target) >= 0.18);

    return socialVariantSpecificityScore(variant, articleSignals) >= 2
      && frontLoadedClickSignalScore(hook, contentType) >= 0
      && countWords(hook) <= 13
      && countWords(earlyCaption || hook) <= 24
      && separatorCount <= 3
      && promiseHitCount <= 4
      && focusedOverlap;
  }

  function socialVariantTwoStepSignal(variant, articleSignals = {}, contentType = "recipe") {
    const captionLines = cleanMultilineText(variant?.caption || "")
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2);
    if (captionLines.length < 2) {
      return false;
    }

    const [line1, line2] = captionLines;
    const line1Words = countWords(line1);
    const line2Words = countWords(line2);
    const lineOverlap = Math.max(sharedWordsRatio(line1, line2), sharedWordsRatio(line2, line1));
    const line1Start = normalizeSlug(line1.split(/\s+/).slice(0, 2).join(" "));
    const line2Start = normalizeSlug(line2.split(/\s+/).slice(0, 2).join(" "));
    const genericLeadPattern = /^(this|it|that|these|they)\b|^(you should|this is|this one|these are|here'?s why)\b/i;
    const line1Variant = { hook: "", caption: line1 };
    const line2Variant = { hook: "", caption: line2 };
    const line1ProblemClue =
      socialVariantPainPointSignal(line1Variant, articleSignals)
      || socialVariantProofSignal(line1Variant, articleSignals, contentType)
      || socialVariantCuriositySignal(line1Variant, articleSignals)
      || socialVariantContrastSignal(line1Variant)
      || frontLoadedClickSignalScore(line1, contentType) > 0;
    const line1Payoff = socialVariantPayoffSignal(line1Variant, articleSignals);
    const line2UseOrResult =
      socialVariantPayoffSignal(line2Variant, articleSignals)
      || socialVariantActionabilitySignal(line2Variant, articleSignals, contentType)
      || socialVariantConsequenceSignal(line2Variant, articleSignals, contentType)
      || socialVariantProofSignal(line2Variant, articleSignals, contentType);
    const line2DistinctEnough =
      socialVariantSpecificityScore(line2Variant, articleSignals) >= 1
      || [
        articleSignals?.payoff_line,
        articleSignals?.proof_line,
        articleSignals?.detail_line,
        articleSignals?.consequence_line,
        articleSignals?.page_signal_line,
      ]
        .filter(Boolean)
        .some((target) => sharedWordsRatio(line2, target) >= 0.16);
    const complementaryFlow =
      (line1ProblemClue && line2UseOrResult)
      || (line1Payoff && (
        socialVariantProofSignal(line2Variant, articleSignals, contentType)
        || socialVariantActionabilitySignal(line2Variant, articleSignals, contentType)
        || socialVariantConsequenceSignal(line2Variant, articleSignals, contentType)
      ));

    return socialVariantSpecificityScore(variant, articleSignals) >= 2
      && line1Words >= 4
      && line1Words <= 14
      && line2Words >= 4
      && line2Words <= 16
      && !genericLeadPattern.test(line1)
      && !genericLeadPattern.test(line2)
      && lineOverlap <= 0.72
      && line1Start !== ""
      && line1Start !== line2Start
      && line2DistinctEnough
      && complementaryFlow;
  }

  function socialVariantScannabilitySignal(variant, contentType = "recipe") {
    const lines = cleanMultilineText(variant?.caption || "")
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 4);
    if (lines.length < 3) {
      return false;
    }

    const lineWordCounts = lines.map((line) => countWords(line));
    const shortLines = lineWordCounts.filter((count) => count >= 3 && count <= 12).length;
    const lineStarts = lines
      .map((line) => normalizeSlug(line.split(/\s+/).slice(0, 2).join(" ")))
      .filter(Boolean);
    const uniqueStarts = new Set(lineStarts);
    const repeatedAdjacent = lines.some((line, index) => {
      if (index === 0) {
        return false;
      }
      const previous = lines[index - 1] || "";
      return Math.max(sharedWordsRatio(line, previous), sharedWordsRatio(previous, line)) >= 0.72;
    });
    const overloadedLines = lines.filter((line) => /,|;|:|\/|\band\b|\bwhile\b|\bplus\b|\bwith\b|\bbut\b/gi.test(line)).length;
    const frontLoadedLines = lines.filter((line) => frontLoadedClickSignalScore(line, contentType) > 0).length;

    return shortLines >= 2
      && uniqueStarts.size >= Math.min(lines.length, 3)
      && !repeatedAdjacent
      && overloadedLines <= 1
      && frontLoadedLines >= 1;
  }

  function socialVariantResolvesEarly(variant, articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const captionLines = cleanMultilineText(variant?.caption || "")
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean);
    const earlyCaption = cleanText(captionLines.slice(0, 2).join(" "));
    const needsResolution = /[?]|\b(why|how|turns out|actually|the difference|changes|truth|mistake|what most people|get wrong|instead of|rather than|not just|more than|less about|vs\.?|versus)\b/i.test(hook);

    if (!earlyCaption) {
      return false;
    }

    const clueTargets = [
      articleSignals.pain_line,
      articleSignals.payoff_line,
      articleSignals.proof_line,
      articleSignals.detail_line,
      articleSignals.page_signal_line,
      articleSignals.final_reward_line,
    ].filter(Boolean);
    const overlapHit = clueTargets.some((target) => sharedWordsRatio(earlyCaption, target) >= 0.16);
    const frontLoadedHit = frontLoadedClickSignalScore(earlyCaption, contentType) > 0;
    const concreteHit = /\b(crispy|creamy|cheesy|garlicky|juicy|mistake|shortcut|truth|faster|easier|save|problem|result|payoff|difference|detail|reason|because|instead|clearer|better)\b/i.test(earlyCaption);

    if (!needsResolution) {
      return overlapHit || (frontLoadedHit && concreteHit);
    }

    return overlapHit || (frontLoadedHit && concreteHit);
  }

  function socialVariantPromiseSyncSignal(variant, articleTitle = "", articleSignals = {}, contentType = "recipe") {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const earlyCaption = caption
      .split(/\r?\n/)
      .map((line) => cleanText(line))
      .filter(Boolean)
      .slice(0, 2)
      .join(" ");
    const leadWindow = cleanText(`${hook} ${earlyCaption}`);
    if (!leadWindow) {
      return false;
    }

    const normalizedHook = normalizeSlug(hook);
    const normalizedTitle = normalizeSlug(articleTitle);
    const titleOverlap = articleTitle ? sharedWordsRatio(leadWindow, articleTitle) : 0;
    const signalTargets = [
      articleSignals?.summary_line,
      articleSignals?.pain_line,
      articleSignals?.payoff_line,
      articleSignals?.proof_line,
      articleSignals?.detail_line,
      articleSignals?.page_signal_line,
      articleSignals?.final_reward_line,
    ].filter(Boolean);
    const signalOverlap = signalTargets.reduce((max, target) => Math.max(max, sharedWordsRatio(leadWindow, target)), 0);
    const promiseHit =
      socialVariantPainPointSignal(variant, articleSignals)
      || socialVariantPayoffSignal(variant, articleSignals)
      || socialVariantProofSignal(variant, articleSignals, contentType)
      || socialVariantActionabilitySignal(variant, articleSignals, contentType)
      || socialVariantConsequenceSignal(variant, articleSignals, contentType);

    return socialVariantSpecificityScore(variant, articleSignals) >= 2
      && frontLoadedClickSignalScore(hook || earlyCaption, contentType) > 0
      && normalizedHook !== ""
      && normalizedHook !== normalizedTitle
      && (titleOverlap >= 0.12 || socialVariantAnchorSignal(variant, articleSignals))
      && (signalOverlap >= 0.14 || promiseHit);
  }

  function scoreSocialVariant(variant, articleTitle = "", contentType = "recipe", articleContext = "", articleSignals = null) {
    if (!variant || socialVariantLooksWeak(variant, articleTitle, contentType, articleSignals || null)) {
      return -100;
    }

    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const hookWords = countWords(hook);
    const captionWords = countWords(caption);
    const captionLines = countLines(caption);
    const normalizedHook = normalizeSlug(hook);
    const normalizedTitle = normalizeSlug(articleTitle);
    const angleKey = normalizeAngleKey(variant?.angle_key || "", contentType);
    const overlap = sharedWordsRatio(hook, articleTitle);
    const contextOverlap = articleContext ? sharedWordsRatio(`${hook} ${caption}`, articleContext) : 0;
    const specificityScore = socialVariantSpecificityScore(variant, articleSignals || {});
    const noveltyScore = socialVariantNoveltyScore(variant, articleTitle, articleSignals || {});
    const anchorScore = socialVariantAnchorSignal(variant, articleSignals || {}) ? 2 : 0;
    const painPointScore = socialVariantPainPointSignal(variant, articleSignals || {}) ? 2 : 0;
    const payoffScore = socialVariantPayoffSignal(variant, articleSignals || {}) ? 2 : 0;
    const proofScore = socialVariantProofSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const selfRecognitionScore = socialVariantSelfRecognitionSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const savvyScore = socialVariantSavvySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const identityShiftScore = socialVariantIdentityShiftSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const actionabilityScore = socialVariantActionabilitySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const immediacyScore = socialVariantImmediacySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const consequenceScore = socialVariantConsequenceSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const habitShiftScore = socialVariantHabitShiftSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const focusScore = socialVariantPromiseFocusSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const scannabilityScore = socialVariantScannabilitySignal(variant, contentType) ? 1 : 0;
    const twoStepScore = socialVariantTwoStepSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const curiosityScore = socialVariantCuriositySignal(variant, articleSignals || {}) ? 1 : 0;
    const contrastScore = socialVariantContrastSignal(variant, articleSignals || {}) ? 1 : 0;
    const relatabilityScore = socialVariantRelatabilitySignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const conversationScore = socialVariantConversationSignal(variant, articleSignals || {}, contentType) ? 1 : 0;
    const resolutionScore = socialVariantResolvesEarly(variant, articleSignals || {}, contentType) ? 1 : 0;
    const promiseSyncScore = socialVariantPromiseSyncSignal(variant, articleTitle, articleSignals || {}, contentType) ? 1 : 0;
    const hookFrontLoadScore = frontLoadedClickSignalScore(hook, contentType);
    const payoffOverlap = articleSignals?.payoff_line ? sharedWordsRatio(`${hook} ${caption}`, articleSignals.payoff_line) : 0;
    let score = 0;

    if (angleKey) {
      score += 4;
    }
    score += hookWords >= 6 && hookWords <= 11 ? 6 : 3;
    score += captionWords >= 22 && captionWords <= 55 ? 5 : 2;
    score += captionLines >= 3 && captionLines <= 4 ? 4 : 2;
    if (normalizedTitle !== "" && normalizedHook !== normalizedTitle) {
      score += 4;
    }
    if (overlap <= 0.45) {
      score += 3;
    } else if (overlap >= 0.8) {
      score -= 5;
    }
    if (stripHookEchoFromCaption(hook, caption) === caption) {
      score += 2;
    }
    if (articleContext) {
      if (contextOverlap >= 0.16 && contextOverlap <= 0.7) {
        score += 4;
      } else if (contextOverlap >= 0.08) {
        score += 2;
      } else if (contextOverlap === 0) {
        score -= 2;
      }
    }
    score += specificityScore;
    score += noveltyScore;
    score += anchorScore;
    score += painPointScore;
    score += payoffScore;
    score += proofScore;
    score += selfRecognitionScore;
    score += savvyScore;
    score += identityShiftScore;
    score += actionabilityScore;
    score += immediacyScore;
    score += consequenceScore;
    score += habitShiftScore;
    score += focusScore;
    score += scannabilityScore;
    score += twoStepScore;
    score += curiosityScore;
    score += contrastScore;
    score += relatabilityScore;
    score += conversationScore;
    score += resolutionScore;
    score += promiseSyncScore;
    score += hookFrontLoadScore;
    if (payoffOverlap >= 0.18) {
      score += 2;
    } else if (payoffOverlap >= 0.1) {
      score += 1;
    }

    return score - socialVariantGenericPenalty(variant);
  }

  function socialVariantLooksWeak(variant, articleTitle = "", contentType = "recipe", articleSignals = null) {
    const hook = cleanText(variant?.hook || "");
    const caption = cleanMultilineText(variant?.caption || "");
    const hookWords = countWords(hook);
    const captionWords = countWords(caption);
    const captionLines = countLines(caption);
    const normalizedHook = normalizeSlug(hook);
    const normalizedTitle = normalizeSlug(articleTitle);
    const hookFrontLoadScore = frontLoadedClickSignalScore(hook, contentType);
    const unanchoredPronounLead = /^(it|this|that|these|they)\b/i.test(hook) && articleSignals && !socialVariantAnchorSignal(variant, articleSignals);
    const superiorityBait = /\b(real cooks|good cooks know|smart cooks|serious cooks|people who know better|if you know what you're doing|amateurs?|rookie move|lazy cooks)\b/i.test(`${hook} ${caption}`);

    return (
      !hook ||
      hookWords < 4 ||
      hookWords > 18 ||
      !caption ||
      captionWords < 14 ||
      captionWords > 85 ||
      captionLines < 2 ||
      captionLines > 5 ||
      hookFrontLoadScore < 0 ||
      unanchoredPronounLead ||
      superiorityBait ||
      containsCheapSuspensePattern(hook) ||
      (normalizedTitle !== "" && normalizedHook === normalizedTitle) ||
      /(https?:\/\/|www\.)/i.test(caption) ||
      /(^|\s)#[a-z0-9_]+/i.test(caption)
    );
  }

  function stripHookEchoFromCaption(hook, caption) {
    const cleanHook = cleanText(hook || "");
    const lines = cleanMultilineText(caption || "")
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);

    if (!cleanHook || lines.length < 2) {
      return lines.join("\n");
    }

    const normalizedHook = normalizeSocialLineFingerprint(cleanHook);
    const firstLine = lines[0] || "";
    if (normalizedHook !== "" && normalizeSocialLineFingerprint(firstLine) === normalizedHook) {
      return lines.slice(1).join("\n");
    }

    return lines.join("\n");
  }

  return {
    classifySocialHookForm,
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
    stripHookEchoFromCaption,
  };
}
