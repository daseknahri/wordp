(function ($) {
  const parseTarget = (button) => {
    try {
      return JSON.parse(button.attr("data-target") || "{}");
    } catch {
      return {};
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
    field.find(".kt-media-preview").html("<p>No portrait selected yet. The front end will fall back to the editor email avatar until one is added.</p>");
  });
})(jQuery);
