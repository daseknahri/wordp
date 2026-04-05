(function ($) {
  const emptyPortraitText = "<p>No portrait selected.</p>";
  const autoRefreshStorageKey = "kuchniaTwistAutoRefreshPaused";

  const parseTarget = (button) => {
    try {
      return JSON.parse(button.attr("data-target") || "{}");
    } catch {
      return {};
    }
  };

  const shouldIgnoreRowClick = (target) =>
    $(target).closest("a, button, input, select, textarea, label").length > 0;

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
    window.setTimeout(() => {
      button.text(original);
    }, 1400);
  });

  $(document).on("change", ".kt-jobs-toolbar select", function () {
    $(this).closest("form").trigger("submit");
  });

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
