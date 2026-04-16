export function countInternalLinks(contentHtml) {
  const shortcodeCount = (String(contentHtml || "").match(/\[kuchnia_twist_link\s+slug=/gi) || []).length;
  const anchorCount = (String(contentHtml || "").match(/<a\s+[^>]*href=/gi) || []).length;
  return shortcodeCount + anchorCount;
}

export function internalLinkTargetsForJob(job) {
  const shared = [
    { slug: "recipes", label: "Recipes" },
    { slug: "food-facts", label: "Food Facts" },
    { slug: "fresh-garlic-vs-roasted-garlic-when-each-one-wins", label: "Fresh Garlic vs Roasted Garlic: When Each One Wins" },
  ];

  const byType = {
    recipe: [
      { slug: "recipes", label: "Recipes" },
      { slug: "food-facts", label: "Food Facts" },
      { slug: "why-onions-need-more-time-than-most-recipes-admit", label: "Why Onions Need More Time Than Most Recipes Admit" },
      { slug: "what-tomato-paste-actually-does-in-a-pan", label: "What Tomato Paste Actually Does in a Pan" },
      { slug: "tomato-butter-beans-on-toast-with-garlic-and-lemon", label: "Tomato Butter Beans on Toast with Garlic and Lemon" },
      { slug: "crispy-sheet-pan-chicken-with-caramelized-onions-and-potatoes", label: "Crispy Sheet-Pan Chicken with Caramelized Onions and Potatoes" },
    ],
    food_fact: [
      { slug: "food-facts", label: "Food Facts" },
      { slug: "recipes", label: "Recipes" },
      { slug: "fresh-garlic-vs-roasted-garlic-when-each-one-wins", label: "Fresh Garlic vs Roasted Garlic: When Each One Wins" },
      { slug: "why-onions-need-more-time-than-most-recipes-admit", label: "Why Onions Need More Time Than Most Recipes Admit" },
      { slug: "what-tomato-paste-actually-does-in-a-pan", label: "What Tomato Paste Actually Does in a Pan" },
      { slug: "crispy-sheet-pan-chicken-with-caramelized-onions-and-potatoes", label: "Crispy Sheet-Pan Chicken with Caramelized Onions and Potatoes" },
      { slug: "tomato-butter-beans-on-toast-with-garlic-and-lemon", label: "Tomato Butter Beans on Toast with Garlic and Lemon" },
    ],
    food_story: [
      { slug: "food-stories", label: "Food Stories" },
      { slug: "recipes", label: "Recipes" },
      { slug: "the-quiet-value-of-a-soup-pot-on-a-busy-weeknight", label: "The Quiet Value of a Soup Pot on a Busy Weeknight" },
      { slug: "creamy-mushroom-barley-soup-for-busy-evenings", label: "Creamy Mushroom Barley Soup for Busy Evenings" },
    ],
  };

  const seen = new Set();

  return [...(byType[job.content_type] || byType.recipe), ...shared].filter((item) => {
    const slug = String(item?.slug || "").trim();
    if (!slug || seen.has(slug)) {
      return false;
    }

    seen.add(slug);
    return true;
  });
}
