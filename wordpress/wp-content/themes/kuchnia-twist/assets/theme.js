document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const button = document.querySelector(".site-nav-toggle");
  const panel = document.querySelector(".site-header__panel");

  if (!header || !button || !panel) {
    return;
  }

  const closeMenu = () => {
    header.classList.remove("is-menu-open");
    button.setAttribute("aria-expanded", "false");
  };

  const openMenu = () => {
    header.classList.add("is-menu-open");
    button.setAttribute("aria-expanded", "true");
  };

  button.addEventListener("click", () => {
    if (header.classList.contains("is-menu-open")) {
      closeMenu();
      return;
    }

    openMenu();
  });

  document.addEventListener("click", (event) => {
    if (!header.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });
});
