export function extractOpeningParagraphText(article, deps) {
  const { cleanText } = deps;
  const html = Array.isArray(article?.content_pages) && article.content_pages.length
    ? String(article.content_pages[0] || "")
    : String(article?.content_html || "");
  const match = html.match(/<p\b[^>]*>(.*?)<\/p>/i);
  return cleanText(String(match?.[1] || html).replace(/<[^>]+>/g, " "));
}
