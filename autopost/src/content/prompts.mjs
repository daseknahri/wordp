export function resolvePublicationProfile(settings) {
  return settings.contentMachine.publicationProfile || {};
}

export function resolveContentPreset(settings, contentType) {
  const presets = settings.contentMachine.contentPresets || {};
  return presets[contentType] || presets.recipe || {};
}

export function createPromptBuilders(deps) {
  const {
    resolveTypedGuidance,
    internalLinkTargetsForJob,
    buildArticleSocialSignals,
    cleanText,
    cleanMultilineText,
    trimText,
    normalizeSocialLineFingerprint,
    buildPageAnglePlan,
    angleDefinitionsForType,
    config,
  } = deps;

  const buildPublicationInvariantLines = (settings) => {
    const profile = resolvePublicationProfile(settings);

    return [
      `Publication profile: ${profile.name || settings.siteName}`,
      `Publication role: ${profile.role || "You are the lead editorial writer for the publication."}`,
      `Voice brief: ${profile.voice_brief || settings.brandVoice}`,
      `Hard guardrails: ${profile.guardrails || "No fake personal stories, no filler SEO intros, no spammy clickbait, no generic opening filler, and no unsupported health or nutrition claims."}`,
      "Output discipline: stay publish-ready, specific, and honest. Do not drift into filler, fake memoir, or schema mistakes.",
    ];
  };

  const buildSocialCreativeBrief = (article, contentType = "recipe") => {
    const signals = buildArticleSocialSignals(article, contentType);
    const lines = [];

    const pushLine = (label, value) => {
      const text = trimText(cleanText(value || ""), 150);
      if (!text) {
        return;
      }

      const fingerprint = normalizeSocialLineFingerprint(text);
      const alreadyUsed = lines
        .map((entry) => normalizeSocialLineFingerprint(entry.split(":").slice(1).join(":") || entry))
        .filter(Boolean);
      if (!fingerprint || alreadyUsed.includes(fingerprint)) {
        return;
      }

      lines.push(`${label}: ${text}`);
    };

    pushLine("Core payoff", signals.summary_line);
    pushLine("Pain point", signals.pain_line);
    pushLine("Reader recognition", signals.pain_line || signals.consequence_line);
    pushLine("Smarter move", [signals.detail_line, signals.payoff_line || signals.proof_line].filter(Boolean).join(" "));
    pushLine("Identity shift", [signals.consequence_line, signals.payoff_line || signals.detail_line].filter(Boolean).join(" "));
    pushLine("Why it matters now", signals.consequence_line);
    pushLine("Concrete payoff", signals.payoff_line);
    pushLine("Habit shift", [signals.consequence_line, signals.payoff_line].filter(Boolean).join(" "));
    pushLine("Specific proof", signals.proof_line);
    pushLine("Useful detail", signals.detail_line);
    pushLine("Later-page tease", signals.page_signal_line);
    pushLine("Final reward", signals.final_reward_line);
    pushLine("Topic focus", signals.heading_topic);
    pushLine("Ingredient focus", signals.ingredient_focus);
    pushLine("Meta", signals.meta_line);

    return lines;
  };

  const buildCoreArticlePrompt = (job, settings, repairNote = "") => {
    const preset = resolveContentPreset(settings, job.content_type);
    const articleGuidance = resolveTypedGuidance(settings, "article", job.content_type, preset.guidance || "");
    const presetGuidance = cleanMultilineText(preset.guidance || "");
    const normalizedArticleGuidance = cleanMultilineText(articleGuidance || "");
    const internalLinkLibrary = internalLinkTargetsForJob(job)
      .map((item) => `- ${item.label}: [kuchnia_twist_link slug="${item.slug}"]${item.label}[/kuchnia_twist_link]`)
      .join("\n");

    const contentTypeNotes = {
      recipe: [
        "Write a recipe-led article with strong sensory writing and practical kitchen guidance.",
        "Do not place the full ingredients and method inside the article body; the structured recipe box will render them separately.",
        "Use H2 sections for: why this works, ingredient notes, practical cooking method, and serving or storage guidance.",
        "Page flow: page 1 should hook the reader and establish the payoff, page 2 should deepen the method and value, and page 3, if used, should feel earned and lead naturally into the recipe card on the final page.",
        "The recipe object must include prep_time, cook_time, total_time, yield, ingredients[], and instructions[].",
      ],
      food_fact: [
        "Write a fact-led article that answers the kitchen question directly, corrects confusion, explains why it matters, and gives a practical takeaway.",
        "Use H2 sections for: the direct answer, what is happening, a common mistake, and a practical takeaway.",
        "Page flow: page 1 should deliver the direct answer fast, page 2 should explain the mechanism or mistake, and page 3, if used, should land the practical takeaway cleanly.",
        "The recipe object must contain empty strings and empty arrays.",
      ],
      food_story: [
        "Write a publication-voice kitchen essay with a clear narrative arc, practical food insight, and a reflective close.",
        "Do not write fabricated first-person memories, invented reporting, or autobiography.",
        "Use H2 sections for: the central observation, practical meaning in home cooking, and a reflective close.",
        "Page flow: each page should earn its place and move the essay forward instead of feeling artificially split.",
        "The recipe object must contain empty strings and empty arrays.",
      ],
    };

    return [
      ...buildPublicationInvariantLines(settings),
      `Topic: ${job.topic}`,
      `Content type: ${job.content_type}`,
      presetGuidance ? `Content standard: ${presetGuidance}` : "",
      normalizedArticleGuidance && normalizedArticleGuidance !== presetGuidance ? `Article structure guidance: ${normalizedArticleGuidance}` : "",
      `Length target: ${preset.min_words || config.minWords}-${config.maxWords} words for the main article body.`,
      `Image style guidance: ${settings.contentMachine.channelPresets.image.guidance}`,
      job.title_override ? `Use this exact article title: ${job.title_override}` : "Generate a strong, editorial article title.",
      "Article rules:",
      "- Write original, useful, human-sounding content.",
      "- Use a polished magazine tone, not generic SEO filler or padded introductions.",
      "- Do not mention AI or say the article was generated.",
      "- Prefer clarity over cleverness in the title. Make the title signal a concrete payoff, mistake, shortcut, question answered, or useful outcome.",
      "- Strong titles and openings can use honest contrast: common assumption versus useful truth, effort versus payoff, or mistake versus fix. Keep that contrast concrete, not sensational.",
      "- Front-load the useful words. In the first few words of the title, excerpt, SEO description, and opening paragraph, name the concrete problem, payoff, shortcut, question, or outcome whenever possible.",
      "- Title, excerpt, SEO description, and the opening paragraph must each do a different job instead of repeating each other.",
      "- Each page must be clean WordPress-ready HTML using paragraphs, h2 headings, lists, and blockquotes only.",
      "- The opening page must begin with a concrete introduction paragraph.",
      "- The opening paragraph must be concrete and specific, not generic throat-clearing.",
      "- Page 1 should cash the promise of the title quickly instead of delaying the real payoff.",
      "- excerpt should feel distinct, specific, and useful, not like a restatement of the title. Front-load one concrete detail, problem, or payoff.",
      "- seo_description should sound like a natural search snippet that adds one clear concrete reason to click. Front-load that reason instead of burying it.",
      "- seo_description should stay under 155 characters.",
      "- Return image_prompt and image_alt for the article hero image even if real images are already uploaded.",
      "- Include at least three internal Kuchnia Twist links across content_pages.",
      "- Avoid copy like 'when it comes to', 'in today's world', 'this article explores', or other generic filler openings.",
      "Pagination rules:",
      "- Return 2 or 3 article pages in content_pages. Use 2 pages for tighter topics and 3 only when every page earns its place.",
      "- Keep the pages in natural reading order. Do not include <!--nextpage--> markers yourself.",
      "- Every page must have one dominant job and one clear takeaway. Do not create a filler bridge page.",
      "- Prefer natural H2-led breakpoints that can survive same-post pagination cleanly.",
      "- When pages 2 or 3 open with H2 headings, make those headings short, specific, and strong enough to work as page labels.",
      "- If you use 3 pages, page 2 should be the deepest or most useful page, not a bridge.",
      "- If you use 3 pages, page 3 must still feel earned with a real section and enough substance to reward the click.",
      "- Do not dump a few leftover notes onto a thin final page.",
      "- Pages 2 and 3 should open with a strong H2 or a distinct section lead so the page labels feel intentional.",
      "- Return page_flow with one item per page. Each item needs a short label and a one-sentence summary that make the next click feel worth it.",
      "- page_flow labels should read like editorial chapter names, not generic copy like 'Page 2', 'Continue', or 'Next page'.",
      "- page_flow summaries should preview concrete value or curiosity from the page instead of restating the label.",
      ...(contentTypeNotes[job.content_type] || contentTypeNotes.recipe),
      "Preferred internal link library:",
      internalLinkLibrary,
      repairNote ? `Previous attempt failed validation: ${repairNote}` : "",
      "JSON contract:",
      "{",
      '  "title": "string",',
      '  "slug": "kebab-case-string",',
      '  "excerpt": "string",',
      '  "seo_description": "string",',
      '  "content_pages": ["html-string"],',
      '  "page_flow": [{"label":"string","summary":"string"}],',
      '  "image_prompt": "string",',
      '  "image_alt": "string",',
      '  "recipe": {',
      '    "prep_time": "string",',
      '    "cook_time": "string",',
      '    "total_time": "string",',
      '    "yield": "string",',
      '    "ingredients": ["string"],',
      '    "instructions": ["string"]',
      "  }",
      "}",
    ]
      .filter(Boolean)
      .join("\n");
  };

  const buildSocialCandidatePrompt = (job, settings, article, selectedPages, preferredAngle = "", repairNote = "") => {
    const preset = resolveContentPreset(settings, job.content_type);
    const socialGuidance = resolveTypedGuidance(settings, "facebook_caption", job.content_type, "");
    const anglePlan = buildPageAnglePlan(selectedPages, job.content_type || "recipe", preferredAngle);
    const candidateCount = Math.max(8, Math.min(12, Math.max(selectedPages.length * 2, 8)));
    const socialBrief = buildSocialCreativeBrief(article, job.content_type || "recipe");

    return [
      ...buildPublicationInvariantLines(settings),
      `Content type: ${job.content_type}`,
      `Content standard: ${preset.guidance || ""}`,
      `Facebook caption guidance: ${socialGuidance}`,
      `Article title: ${article.title}`,
      `Excerpt: ${article.excerpt}`,
      ...socialBrief,
      `Generate ${candidateCount} social_candidates and make them meaningfully distinct.`,
      "Use a mix of creativity modes across the pool: direct payoff, sharp correction, practical shortcut, and emotionally clear curiosity.",
      "Use a wider mix of hook shapes across the pool too: direct statement, clean correction, contrast, useful question, and numbered form only when it genuinely fits.",
      "Include some candidates that use clean contrast such as expectation versus reality, mistake versus fix, or effort versus payoff without sounding gimmicky.",
      "Use concrete article details so the strongest candidates do not read like title-only hooks.",
      "Prefer hooks that name a real payoff, mistake, shortcut, timing, texture, ingredient, or outcome instead of vague emotional filler.",
      "Name the actual dish, ingredient, method, mistake, or topic often enough that the pool does not lean on vague 'this' or 'it' framing.",
      "Make sure some candidates feel instantly self-recognizable, like a real kitchen moment, shopping moment, weeknight problem, or home-cook decision the reader has actually lived.",
      "Make some candidates feel naturally discussable or tag-worthy because they touch a real household habit, shopping split, or recognizable choice, not because they ask for comments or tags.",
      "Let some candidates carry a small believable proof or clue early, like timing, texture, ingredient job, shopping detail, or a before-versus-after result.",
      "Make some candidates feel immediately usable, with a clear next move, kitchen decision, or practical thing the reader can do on the next cook or shop.",
      "Make some candidates feel relevant right now, tied to tonight, this week, the next grocery run, or the reader's next cook, shop, order, or storage decision.",
      "Make some candidates show consequence honestly: what gets wasted, repeated, ruined, overcomplicated, or missed if the reader keeps following the usual habit.",
      "Keep each candidate built around one dominant promise. Do not cram too many separate benefits, mistakes, textures, and outcomes into one hook-caption pair.",
      "Make some candidates line up tightly with the article title and page-one promise, but do it by cashing the same core problem or payoff, not by repeating the headline.",
      "Keep the caption visually easy to scan. Favor short distinct lines over dense lines that all say nearly the same thing.",
      "Make caption line 1 and line 2 do different jobs. Let line 1 give the clue, problem, proof, or sharp correction, and let line 2 sharpen the payoff, use, or result instead of repeating line 1 in softer words.",
      "Make several candidates front-load a pain point, mistake, or misunderstanding, and make several others front-load a clear payoff or result.",
      "In the first 4 to 6 words of the hook, try to surface the concrete problem, payoff, shortcut, or surprise instead of warming up with vague filler.",
      "Use the page-flow signals when useful so stronger candidates can hint at later-page payoff without mentioning pagination or page numbers.",
      "Make some candidates create a real habit-shift snap: name the usual move, assumption, or kitchen habit and make the better result feel concrete without sounding preachy or dramatic.",
      "Make some candidates create instant self-recognition by naming the repeated flat result, mistake, or kitchen symptom the reader already knows from experience.",
      "Make some candidates feel like the reader is about to make a smarter kitchen or shopping move, but keep it grounded, practical, and never smug or superior.",
      "Make some candidates feel like the reader is leaving behind the old default move for a cleaner better one, but keep it honest and never shame the reader.",
      "If a hook opens a question, contradiction, or curiosity gap, the caption must resolve part of it by line 1 or 2.",
      "Never withhold the core fact for pure suspense.",
      "Angle library for this content type:",
      ...angleDefinitionsForType(job.content_type || "recipe").map((angle) => `- ${angle.key}: ${angle.instruction}`),
      anglePlan.length ? "Selected page plan:" : "",
      ...anglePlan.map((item) => `- ${item.page_label}: prefer ${item.angle_key}`),
      repairNote ? `Previous social pool failed local review: ${repairNote}` : "",
      "Return only this JSON contract:",
      "{",
      '  "social_candidates": [{"angle_key":"string","hook":"string","caption":"string","cta_hint":"string","post_message":"string"}]',
      "}",
      "Each candidate must be honest, specific, short, and hook-led.",
      "At least half the pool should feel sharper than a generic social post while still staying honest and specific.",
      "At least two thirds of the pool should carry a tangible reason to care such as payoff, shortcut, texture, timing, mistake, or outcome.",
      "No links, hashtags, or title-echo hooks.",
      "Do not mention pagination, page numbers, 'next page', or 'keep reading' inside the social copy.",
      "Avoid generic hooks like 'you need to try this', 'you should', 'this is', 'this one', 'so good', 'best ever', or 'game changer'.",
      "Avoid cheap suspense like 'what happens next', 'nobody tells you', 'the secret', or 'you'll never guess'.",
      "Do not repeat the hook as the first caption line.",
    ]
      .filter(Boolean)
      .join("\n");
  };

  return {
    buildPublicationInvariantLines,
    buildSocialCreativeBrief,
    buildCoreArticlePrompt,
    buildSocialCandidatePrompt,
  };
}
