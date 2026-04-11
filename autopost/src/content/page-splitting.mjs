export function createPageSplittingHelpers(deps) {
  const {
    cleanText,
    cleanMultilineText,
    countWords,
    escapeHtml,
    extractFirstHeading,
    isPlainObject,
    mergeContentPagesIntoHtml,
    normalizeContentPageItem,
    normalizeHtml,
    pageStartsWithExpectedLead,
    readGeneratedArray,
    readGeneratedString,
  } = deps;

  function buildContentHtmlFromSections(source) {
    const sectionSets = readGeneratedArray(source, [
      "sections",
      "article_sections",
      "articleSections",
      "content_sections",
      "contentSections",
      "body_sections",
      "bodySections",
      "outline_sections",
      "outlineSections",
      "content_blocks",
      "contentBlocks",
      "blocks",
    ]).filter(Boolean);

    if (!sectionSets.length) {
      return "";
    }

    return sectionSets
      .map((section) => {
        if (typeof section === "string") {
          return `<p>${escapeHtml(cleanText(section))}</p>`;
        }

        if (!isPlainObject(section)) {
          return "";
        }

        const heading = cleanText(section.heading || section.title || section.label);
        const body = cleanMultilineText(section.html || section.content_html || section.content || section.body || section.text || "");
        const bodyHtml = normalizeHtml(body);
        if (!heading && !bodyHtml) {
          return "";
        }

        return [heading ? `<h2>${escapeHtml(heading)}</h2>` : "", bodyHtml].filter(Boolean).join("\n");
      })
      .filter(Boolean)
      .join("\n");
  }

  function countHtmlWords(html) {
    return countWords(String(html || "").replace(/<[^>]+>/g, " "));
  }

  function pageWrapUpBonus(pageHtml, contentType = "recipe") {
    const heading = extractFirstHeading(pageHtml).toLowerCase();
    if (!heading) {
      return 0;
    }

    const recipePatterns = [
      /\bserv(?:e|ing)\b/,
      /\bstorage\b/,
      /\breheat/i,
      /\bnotes?\b/,
      /\btips?\b/,
      /\bvariations?\b/,
      /\bmake ahead\b/,
    ];
    const factPatterns = [
      /\bpractical takeaway\b/,
      /\bwhat to do\b/,
      /\bwhat this means\b/,
      /\bwhy it matters\b/,
      /\bbottom line\b/,
      /\bfinal takeaway\b/,
      /\bnext time\b/,
    ];
    const patterns = contentType === "recipe" ? recipePatterns : factPatterns;

    return patterns.some((pattern) => pattern.test(heading)) ? (contentType === "recipe" ? 8 : 7) : 0;
  }

  function enumeratePageLayouts(blocks, pageCount) {
    const count = Array.isArray(blocks) ? blocks.length : 0;
    if (count < pageCount || pageCount < 2 || pageCount > 3) {
      return [];
    }

    if (pageCount === 2) {
      return Array.from({ length: count - 1 }, (_, index) => [index + 1]);
    }

    const layouts = [];
    for (let first = 1; first <= count - 2; first += 1) {
      for (let second = first + 1; second <= count - 1; second += 1) {
        layouts.push([first, second]);
      }
    }
    return layouts;
  }

  function buildPagesFromBreakpoints(blocks, breakpoints, intro = "") {
    const normalizedBreakpoints = Array.isArray(breakpoints) ? breakpoints : [];
    const pages = [];
    let start = 0;

    for (const stop of [...normalizedBreakpoints, blocks.length]) {
      const chunk = blocks.slice(start, stop);
      start = stop;
      pages.push(chunk.join("\n").trim());
    }

    if (intro) {
      pages[0] = `${intro}\n${pages[0] || ""}`.trim();
    }

    return pages.map((page) => page.trim()).filter(Boolean);
  }

  function scorePageLayout(pages, contentType = "recipe") {
    const pageWordCounts = pages.map((page) => countHtmlWords(page));
    const totalWords = pageWordCounts.reduce((sum, count) => sum + count, 0);
    const targetWords = totalWords / Math.max(1, pages.length);
    const sectionCounts = pages.map((page) => (String(page).match(/<h2\b/gi) || []).length);
    let score = 0;

    pageWordCounts.forEach((count, index) => {
      const minWords = pages.length === 3
        ? (index === 0 ? 150 : 130)
        : (index === 0 ? 220 : 180);
      const deviation = targetWords > 0 ? Math.abs(count - targetWords) / targetWords : 0;

      score -= deviation * 24;
      if (count >= minWords) {
        score += 6;
      } else {
        score -= ((minWords - count) / Math.max(20, minWords)) * 20;
      }
      if (count < 100) {
        score -= 18;
      }
    });

    score += pages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length * 5;

    if (pages.length === 3) {
      if (pageWordCounts[1] >= pageWordCounts[0] * 0.85) {
        score += 4;
      }
      if (pageWordCounts[2] >= pageWordCounts[1] * 0.62) {
        score += 3;
      } else {
        score -= 8;
      }
      if (pageWordCounts[2] < 120) {
        score -= 8;
      }
    } else if (pages.length === 2 && pageWordCounts[1] < pageWordCounts[0] * 0.55) {
      score -= 6;
    }

    if (sectionCounts.slice(1).every((count) => count > 0)) {
      score += 4;
    }
    if (pages.length === 3 && sectionCounts[2] === 0) {
      score -= 6;
    }

    score += pageWrapUpBonus(pages[pages.length - 1], contentType);

    return score;
  }

  function selectBestPageLayout(blocks, intro, contentType = "recipe", allowedPageCounts = [2]) {
    let bestPages = [];
    let bestScore = -Infinity;

    for (const pageCount of allowedPageCounts) {
      for (const breakpoints of enumeratePageLayouts(blocks, pageCount)) {
        const pages = buildPagesFromBreakpoints(blocks, breakpoints, intro);
        if (pages.length !== pageCount) {
          continue;
        }

        let score = scorePageLayout(pages, contentType);
        if (pageCount === 3) {
          score += 2;
        }

        if (score > bestScore) {
          bestScore = score;
          bestPages = pages;
        }
      }
    }

    return bestPages;
  }

  function splitHtmlIntoPages(contentHtml, contentType = "recipe") {
    const normalized = normalizeHtml(contentHtml);
    if (!normalized) {
      return [];
    }

    const sections = normalized.split(/(?=<h2\b)/i).map((section) => section.trim()).filter(Boolean);
    const wordCount = countHtmlWords(normalized);

    if (sections.length >= 2) {
      const intro = sections[0] && !/^<h2\b/i.test(sections[0]) ? sections[0] : "";
      const remainingSections = intro ? sections.slice(1) : sections.slice();
      const allowThreePages = remainingSections.length >= 3
        && (
          contentType === "recipe"
            ? (remainingSections.length >= 5 || wordCount >= 1300)
            : (remainingSections.length >= 4 || wordCount >= 1150)
        );
      const pageCounts = allowThreePages ? [2, 3] : [2];
      const pages = selectBestPageLayout(remainingSections, intro, contentType, pageCounts);

      if (pages.length >= 2) {
        return pages.slice(0, 3);
      }
    }

    const paragraphs = normalized.match(/<(p|ul|ol|blockquote)\b[\s\S]*?<\/\1>/gi) || [];
    if (paragraphs.length >= 4) {
      const allowThreePages = paragraphs.length >= 8 && wordCount >= 1200;
      const pages = selectBestPageLayout(paragraphs, "", contentType, allowThreePages ? [2, 3] : [2]);

      if (pages.length >= 2) {
        return pages.slice(0, 3);
      }
    }

    return [normalized];
  }

  function stabilizeGeneratedContentPages(contentPages, fallbackHtml, contentType = "recipe") {
    const pages = Array.isArray(contentPages)
      ? contentPages.map((page) => normalizeHtml(page)).filter(Boolean)
      : [];
    const fallback = normalizeHtml(fallbackHtml || "");

    if (!pages.length) {
      return fallback ? splitHtmlIntoPages(fallback, contentType).slice(0, 3) : [];
    }

    const mergedCurrent = mergeContentPagesIntoHtml(pages);
    const currentScore = pages.length >= 2 && pages.length <= 3 ? scorePageLayout(pages, contentType) : -Infinity;
    const currentShortest = pages.length ? Math.min(...pages.map((page) => countHtmlWords(page))) : 0;
    const currentStrongOpens = pages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;

    const candidateSource = fallback || mergedCurrent;
    const candidatePages = candidateSource ? splitHtmlIntoPages(candidateSource, contentType).slice(0, 3) : [];
    const candidateScore = candidatePages.length >= 2 && candidatePages.length <= 3 ? scorePageLayout(candidatePages, contentType) : -Infinity;

    if (pages.length < 2 || pages.length > 3) {
      return candidatePages.length ? candidatePages : pages.slice(0, 3);
    }

    if (!candidatePages.length) {
      return pages;
    }

    const candidateShortest = Math.min(...candidatePages.map((page) => countHtmlWords(page)));
    const candidateStrongOpens = candidatePages.filter((page, index) => pageStartsWithExpectedLead(page, index)).length;
    const currentWeak = currentShortest < 110 || currentStrongOpens < pages.length;
    const candidateStronger =
      candidatePages.length >= 2 &&
      (candidateScore > currentScore + 4
        || (currentWeak && candidateScore >= currentScore)
        || (candidateShortest > currentShortest + 35)
        || (candidateStrongOpens > currentStrongOpens));

    return candidateStronger ? candidatePages : pages;
  }

  function resolveGeneratedContentPages(source, job) {
    const directPages = readGeneratedArray(source, [
      "content_pages",
      "contentPages",
      "article_pages",
      "articlePages",
      "pages",
      "page_chunks",
      "pageChunks",
    ])
      .map((item) => normalizeContentPageItem(item))
      .filter(Boolean);

    if (directPages.length) {
      if (directPages.length > 1) {
        return directPages;
      }

      return splitHtmlIntoPages(directPages[0], job?.content_type || "recipe").slice(0, 3);
    }

    const direct = readGeneratedString(source, [
      "content_html",
      "contentHtml",
      "article_html",
      "articleHtml",
      "body_html",
      "bodyHtml",
      "blog_html",
      "blogHtml",
      "article_body",
      "articleBody",
      "html",
      "body",
    ]);

    if (direct) {
      return splitHtmlIntoPages(direct, job?.content_type || "recipe").slice(0, 3);
    }

    const sectionHtml = buildContentHtmlFromSections(source);
    if (sectionHtml) {
      return splitHtmlIntoPages(sectionHtml, job?.content_type || "recipe").slice(0, 3);
    }

    const plaintext = readGeneratedString(source, ["content", "article", "post"]);
    if (plaintext && !isPlainObject(plaintext)) {
      return splitHtmlIntoPages(plaintext, job?.content_type || "recipe").slice(0, 3);
    }

    return [];
  }

  function resolveGeneratedContentHtml(source, job) {
    const pages = resolveGeneratedContentPages(source, job);
    if (pages.length) {
      return mergeContentPagesIntoHtml(pages);
    }

    const direct = readGeneratedString(source, [
      "content_html",
      "contentHtml",
      "article_html",
      "articleHtml",
      "body_html",
      "bodyHtml",
      "blog_html",
      "blogHtml",
      "article_body",
      "articleBody",
      "html",
      "body",
    ]);

    if (direct) {
      return normalizeHtml(direct);
    }

    const sectionHtml = buildContentHtmlFromSections(source);
    if (sectionHtml) {
      return normalizeHtml(sectionHtml);
    }

    const plaintext = readGeneratedString(source, ["content", "article", "post"]);
    if (plaintext && !isPlainObject(plaintext)) {
      return normalizeHtml(plaintext);
    }

    return "";
  }

  return {
    buildContentHtmlFromSections,
    resolveGeneratedContentHtml,
    resolveGeneratedContentPages,
    splitHtmlIntoPages,
    stabilizeGeneratedContentPages,
  };
}
