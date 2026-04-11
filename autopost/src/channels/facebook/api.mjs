export function createFacebookApiHelpers(deps) {
  const {
    formPostJson,
    buildFacebookPostUrl,
  } = deps;

  async function publishFacebookPost(settings, page, options) {
    const endpoint = options.imageUrl
      ? `https://graph.facebook.com/${settings.facebookGraphVersion}/${page.page_id}/photos`
      : `https://graph.facebook.com/${settings.facebookGraphVersion}/${page.page_id}/feed`;

    const params = new URLSearchParams();
    params.set("access_token", page.access_token);
    params.set("published", "true");

    if (options.imageUrl) {
      params.set("url", options.imageUrl);
      params.set("caption", options.message);
    } else {
      params.set("message", options.message);
    }

    const payload = await formPostJson(endpoint, params);
    const postId = String(payload.post_id || payload.id || "");

    if (!postId) {
      throw new Error("Facebook did not return a post identifier.");
    }

    return {
      postId,
      url: buildFacebookPostUrl(postId),
    };
  }

  async function publishFacebookComment(settings, page, postId, message) {
    const params = new URLSearchParams();
    params.set("access_token", page.access_token);
    params.set("message", message);

    const payload = await formPostJson(
      `https://graph.facebook.com/${settings.facebookGraphVersion}/${postId}/comments`,
      params,
    );

    if (!payload?.id) {
      throw new Error("Facebook did not return a comment identifier.");
    }

    return String(payload.id);
  }

  return {
    publishFacebookComment,
    publishFacebookPost,
  };
}
