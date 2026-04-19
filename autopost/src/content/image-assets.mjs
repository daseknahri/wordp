import { buildPlatformRestPath, resolvePlatformPolicy } from "../runtime/platform-policy.mjs";

export function createImageAssetHelpers(deps) {
  const {
    buildImagePrompt,
    ensureOpenAiConfigured,
    generateImageBase64,
    log,
    normalizeSlug,
    resolveCanonicalContentPackage,
    wpRequest,
  } = deps;

  async function generateAndUploadImage(jobId, settings, generated, options) {
    const platformPolicy = resolvePlatformPolicy(settings);
    const contentPackage = resolveCanonicalContentPackage(generated);
    const base64Data = await generateImageBase64(
      settings,
      buildImagePrompt(generated, {
        variantHint: options.variantHint,
        platform: options.slot,
        settings,
      }),
      options.size,
    );

    return wpRequest(buildPlatformRestPath(platformPolicy, "media", { id: jobId }), {
      method: "POST",
      secretHeaderName: platformPolicy.auth.secretHeader,
      body: {
        slot: options.slot,
        filename: options.filename,
        title: `${contentPackage.title} ${options.slot === "blog" ? "Hero" : "Facebook"} Image`,
        alt: contentPackage.image_alt || contentPackage.title,
        base64_data: base64Data,
      },
    });
  }

  async function ensureJobImages(job, settings, generated, current) {
    let featuredImage = current.featuredImage;
    let facebookImage = current.facebookImage;
    const manualOnly = (settings.imageGenerationMode || "manual_only") === "manual_only";
    const uploadedFirst = !manualOnly;
    const contentPackage = resolveCanonicalContentPackage(generated, job);
    const assetSlug = normalizeSlug(contentPackage.slug || contentPackage.title || job.topic);

    if (manualOnly && (!featuredImage?.id || !facebookImage?.id)) {
      throw new Error("Manual-only image handling requires both a real uploaded blog image and a real uploaded Facebook image.");
    }

    if (uploadedFirst && (!featuredImage?.id || !facebookImage?.id)) {
      ensureOpenAiConfigured(settings);
    }

    if (!featuredImage?.id && uploadedFirst) {
      log(`generating blog hero image for job #${job.id}`);
      featuredImage = await generateAndUploadImage(job.id, settings, generated, {
        slot: "blog",
        filename: `${assetSlug}-blog.png`,
        size: "1536x1024",
        variantHint: "Landscape hero image for the blog article header.",
      });
    }

    if (!facebookImage?.id && uploadedFirst) {
      log(`generating Facebook image for job #${job.id}`);
      facebookImage = await generateAndUploadImage(job.id, settings, generated, {
        slot: "facebook",
        filename: `${assetSlug}-facebook.png`,
        size: "1024x1024",
        variantHint: "Square social image for a Facebook Page post.",
      });
    }

    generated = {
      ...generated,
      assets: {
        featured_image_id: featuredImage?.id || 0,
        facebook_image_id: facebookImage?.id || featuredImage?.id || 0,
      },
    };

    return { featuredImage, facebookImage: facebookImage || featuredImage, generated };
  }

  return {
    ensureJobImages,
    generateAndUploadImage,
  };
}
