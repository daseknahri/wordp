import { createFacebookPostingExecutor } from "../channels/facebook/posting-executor.mjs";
import { createUnsupportedPostingExecutor } from "./unsupported-posting-executor.mjs";
import { createWordPressPostingExecutor } from "./wordpress-posting-executor.mjs";

export function createPostingExecutors(deps) {
  const wordPressPostingExecutor = createWordPressPostingExecutor(deps);
  const facebookPostingExecutor = createFacebookPostingExecutor(deps);
  const facebookGroupsDraftExecutor = createUnsupportedPostingExecutor({
    executorKey: "facebook_groups_draft",
    label: "Facebook Groups draft delivery",
  });
  const pinterestDraftExecutor = createUnsupportedPostingExecutor({
    executorKey: "pinterest_draft",
    label: "Pinterest draft delivery",
  });

  return {
    wordpress_publish: wordPressPostingExecutor,
    publish_blog: wordPressPostingExecutor,
    facebook_distribution: facebookPostingExecutor,
    facebook: facebookPostingExecutor,
    facebook_groups_draft: facebookGroupsDraftExecutor,
    pinterest_draft: pinterestDraftExecutor,
  };
}
