import { SOCIAL_ANGLE_LIBRARY } from "../../contracts/profiles.mjs";
import { createTextHelpers } from "../../runtime/text.mjs";

const { cleanText } = createTextHelpers();

export function angleDefinitionsForType(contentType) {
  return SOCIAL_ANGLE_LIBRARY[contentType] || SOCIAL_ANGLE_LIBRARY.recipe;
}

export function normalizeAngleKey(value, contentType = "") {
  const key = cleanText(value || "").replace(/\s+/g, "_").toLowerCase();
  const definitions = contentType ? angleDefinitionsForType(contentType) : Object.values(SOCIAL_ANGLE_LIBRARY).flat();
  return definitions.some((angle) => angle.key === key) ? key : "";
}

export function resolvePreferredAngle(job) {
  return normalizeAngleKey(job?.request_payload?.preferred_angle || "", job?.content_type || "recipe");
}

export function buildAngleSequence(count, contentType = "recipe", preferredAngle = "") {
  const normalizedPreferred = normalizeAngleKey(preferredAngle, contentType);
  const keys = angleDefinitionsForType(contentType).map((angle) => angle.key);
  const ordered = normalizedPreferred
    ? [normalizedPreferred, ...keys.filter((key) => key !== normalizedPreferred)]
    : [...keys];

  return Array.from({ length: Math.max(1, count) }, (_, index) => ordered[index % ordered.length]);
}

export function angleDefinition(angleKey, contentType = "") {
  const normalized = normalizeAngleKey(angleKey, contentType);
  const definitions = contentType ? angleDefinitionsForType(contentType) : Object.values(SOCIAL_ANGLE_LIBRARY).flat();
  return definitions.find((angle) => angle.key === normalized) || null;
}

export function buildPageAnglePlan(targets, contentType = "recipe", preferredAngle = "") {
  const pages = Array.isArray(targets)
    ? targets
    : (Array.isArray(targets?.pages) ? targets.pages : []);
  const labels = Array.isArray(targets?.labels)
    ? targets.labels
    : [];
  const count = Math.max(
    1,
    Array.isArray(pages) && pages.length
      ? pages.length
      : (Number(targets?.count || 0) || labels.length || 0),
  );
  const angles = buildAngleSequence(count, contentType, preferredAngle);
  const definitions = angleDefinitionsForType(contentType);

  return Array.from({ length: count }, (_, index) => {
    const angleKey = angles[index] || definitions[index % definitions.length].key;
    const angle = angleDefinition(angleKey, contentType);
    const page = Array.isArray(pages) ? pages[index] || null : null;
    const label = cleanText(labels[index] || page?.label || `Page ${index + 1}`);

    return {
      index,
      angle_key: angleKey,
      page_label: label,
      instruction: angle?.instruction || "",
    };
  });
}
