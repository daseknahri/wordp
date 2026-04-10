(function ($) {
  const emptyPortraitText = "<p>No portrait selected.</p>";
  const autoRefreshStorageKey = "kuchniaTwistAutoRefreshPaused";
  const announce = (message) => {
    const region = $("[data-copy-live-region]");
    if (!region.length || !message) {
      return;
    }

    region.text("");
    window.setTimeout(() => {
      region.text(message);
    }, 10);
  };

  const parseTarget = (button) => {
    try {
      return JSON.parse(button.attr("data-target") || "{}");
    } catch {
      return {};
    }
  };

  const shouldIgnoreRowClick = (target) =>
    $(target).closest("a, button, input, select, textarea, label").length > 0;

  const syncFacebookPageRows = () => {
    $("[data-facebook-page-list]").find("[data-facebook-page-row]").each(function (index) {
      $(this)
        .find("[data-facebook-page-field]")
        .each(function () {
          const field = $(this).attr("data-facebook-page-field");
          if (!field) {
            return;
          }

          $(this).attr("name", `facebook_pages[${index}][${field}]`);
        });
    });
  };

  const updateFacebookSelectionState = () => {
    $(".kt-checkbox-card").each(function () {
      const card = $(this);
      const checkboxes = card.find("[data-facebook-page-checkbox]");
      const checked = checkboxes.filter(":checked").length;
      const submit = card.closest("form").find("[data-facebook-submit]");
      const counter = card.find("[data-facebook-selection-count]");

      if (counter.length) {
        counter.text(`${checked} page${checked === 1 ? "" : "s"} selected`);
      }

      if (submit.length) {
        submit.prop("disabled", checked === 0);
        submit.attr("aria-disabled", checked === 0 ? "true" : "false");
      }
    });
  };

  const updateComposerTypeState = () => {
    const select = $("[data-content-type-select]");
    if (!select.length) {
      return;
    }

    const selectedType = String(select.val() || "recipe");
    const topicLabel = $("[data-topic-label]");
    const topicInput = $("[data-topic-input]");
    const topicHelp = $("[data-topic-help]");
    const titleOverride = $("[data-title-override-input]");

    if (topicLabel.length) {
      topicLabel.text(topicLabel.attr(`data-label-${selectedType}`) || topicLabel.attr("data-label-recipe") || "");
    }

    if (topicInput.length) {
      topicInput.attr("placeholder", topicInput.attr(`data-placeholder-${selectedType}`) || topicInput.attr("data-placeholder-recipe") || "");
    }

    if (topicHelp.length) {
      topicHelp.text(topicHelp.attr(`data-help-${selectedType}`) || topicHelp.attr("data-help-recipe") || "");
    }

    if (titleOverride.length) {
      titleOverride.attr(
        "placeholder",
        titleOverride.attr(`data-placeholder-${selectedType}`) || titleOverride.attr("data-placeholder-recipe") || "",
      );
    }
  };

  $(document).on("click", ".kt-media-select", function (event) {
    event.preventDefault();

    const button = $(this);
    const target = parseTarget(button);
    const input = $(target.input);
    const preview = button.closest(".kt-media-field").find(target.preview || ".kt-media-preview");

    if (!input.length || !preview.length || typeof wp === "undefined" || !wp.media) {
      return;
    }

    const frame = wp.media({
      title: "Choose image",
      button: { text: "Use this image" },
      library: { type: "image" },
      multiple: false,
    });

    frame.on("select", () => {
      const attachment = frame.state().get("selection").first()?.toJSON();
      if (!attachment) {
        return;
      }

      input.val(attachment.id);
      preview.html(`<img src="${attachment.sizes?.thumbnail?.url || attachment.url}" alt="">`);
    });

    frame.open();
  });

  $(document).on("click", ".kt-media-clear", function (event) {
    event.preventDefault();

    const field = $(this).closest(".kt-media-field");
    field.find('input[type="hidden"]').val("0");
    field.find(".kt-media-preview").html(emptyPortraitText);
  });

  $(document).on("click", ".kt-job-row[data-href]", function (event) {
    if (shouldIgnoreRowClick(event.target)) {
      return;
    }

    const href = $(this).attr("data-href");
    if (href) {
      window.location.href = href;
    }
  });

  $(document).on("keydown", ".kt-job-row[data-href]", function (event) {
    if (event.key !== "Enter" && event.key !== " ") {
      return;
    }

    event.preventDefault();
    const href = $(this).attr("data-href");
    if (href) {
      window.location.href = href;
    }
  });

  $(document).on("click", ".kt-copy-button[data-copy-target]", async function (event) {
    event.preventDefault();

    const button = $(this);
    const target = $(button.attr("data-copy-target") || "");
    const value = target.length ? (target.val() || target.text() || "").trim() : "";

    if (!value) {
      return;
    }

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else if (target.length && target.is("textarea, input")) {
        target.trigger("focus").trigger("select");
        document.execCommand("copy");
      }
    } catch {
      return;
    }

    const original = button.text();
    button.text("Copied");
    announce("Copied to clipboard");
    window.setTimeout(() => {
      button.text(original);
    }, 1400);
  });

  $(document).on("change", ".kt-jobs-toolbar select", function () {
    $(this).closest("form").trigger("submit");
  });

  $(document).on("click", "[data-add-facebook-page]", function (event) {
    event.preventDefault();

    const library = $(this).closest("[data-facebook-pages]");
    const list = library.find("[data-facebook-page-list]");
    const template = document.getElementById("kt-facebook-page-template");

    if (!list.length || !template) {
      return;
    }

    const fragment = template.content.cloneNode(true);
    list[0].appendChild(fragment);
    syncFacebookPageRows();
  });

  $(document).on("click", "[data-remove-facebook-page]", function (event) {
    event.preventDefault();
    $(this).closest("[data-facebook-page-row]").remove();
    syncFacebookPageRows();
  });

  $(document).on("click", "[data-facebook-page-select-all]", function (event) {
    event.preventDefault();
    $(this)
      .closest(".kt-checkbox-card")
      .find("[data-facebook-page-checkbox]")
      .prop("checked", true);
    updateFacebookSelectionState();
  });

  $(document).on("click", "[data-facebook-page-clear]", function (event) {
    event.preventDefault();
    $(this)
      .closest(".kt-checkbox-card")
      .find("[data-facebook-page-checkbox]")
      .prop("checked", false);
    updateFacebookSelectionState();
  });

  $(document).on("change", "[data-facebook-page-checkbox]", function () {
    updateFacebookSelectionState();
  });

  $(document).on("change", "[data-content-type-select]", function () {
    updateComposerTypeState();
  });

  syncFacebookPageRows();
  updateFacebookSelectionState();
  updateComposerTypeState();

  const adminRoot = $(".kt-admin[data-auto-refresh-seconds]");
  const toggle = $(".kt-auto-refresh-toggle[data-seconds]");
  const label = $("[data-auto-refresh-label]");

  if (adminRoot.length && toggle.length) {
    const refreshSeconds = Number(adminRoot.attr("data-auto-refresh-seconds") || toggle.attr("data-seconds") || 0);
    let paused = window.sessionStorage?.getItem(autoRefreshStorageKey) === "1";
    let remaining = refreshSeconds;

    const isInteractiveFocus = () => {
      const active = document.activeElement;
      return Boolean(active && /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(active.tagName));
    };

    const renderRefreshState = () => {
      toggle.text(paused ? "Resume Auto Refresh" : "Pause Auto Refresh");
      toggle.attr("aria-pressed", paused ? "true" : "false");

      if (!label.length) {
        return;
      }

      if (paused) {
        label.text("Auto refresh paused");
        return;
      }

      label.text(`Refreshing in ${remaining}s while jobs are active`);
    };

    toggle.on("click", function (event) {
      event.preventDefault();
      paused = !paused;
      remaining = refreshSeconds;

      if (window.sessionStorage) {
        window.sessionStorage.setItem(autoRefreshStorageKey, paused ? "1" : "0");
      }

      renderRefreshState();
      announce(paused ? "Auto refresh paused" : "Auto refresh resumed");
    });

    renderRefreshState();

    if (refreshSeconds > 0) {
      window.setInterval(() => {
        if (paused) {
          return;
        }

        if (document.hidden) {
          remaining = refreshSeconds;
          renderRefreshState();
          return;
        }

        if (isInteractiveFocus()) {
          remaining = Math.min(refreshSeconds, Math.max(5, remaining));
          renderRefreshState();
          return;
        }

        remaining -= 1;

        if (remaining <= 0) {
          window.location.reload();
          return;
        }

        renderRefreshState();
      }, 1000);
    }
  }
})(jQuery);
