document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const searchPanel = document.querySelector("[data-search-panel]");
  const menuPanel = document.querySelector("[data-menu-panel]");
  const menuToggle = document.querySelector("[data-menu-toggle]");
  const menuClose = document.querySelector("[data-menu-close]");
  const searchToggles = Array.from(document.querySelectorAll("[data-search-toggle]"));
  const footerSections = Array.from(document.querySelectorAll(".site-footer__section"));

  if (header) {
    const closeSearch = () => {
      if (!searchPanel) {
        return;
      }

      searchPanel.hidden = true;
      header.classList.remove("is-search-open");
      searchToggles.forEach((button) => button.setAttribute("aria-expanded", "false"));
    };

    const openSearch = () => {
      if (!searchPanel) {
        return;
      }

      if (menuPanel && !menuPanel.hidden) {
        closeMenu();
      }

      searchPanel.hidden = false;
      header.classList.add("is-search-open");
      searchToggles.forEach((button) => button.setAttribute("aria-expanded", "true"));
      window.setTimeout(() => {
        searchPanel.querySelector("input, button")?.focus();
      }, 20);
    };

    const closeMenu = () => {
      if (!menuPanel) {
        return;
      }

      menuPanel.hidden = true;
      header.classList.remove("is-menu-open");
      document.body.classList.remove("has-menu-sheet");

      if (menuToggle) {
        menuToggle.setAttribute("aria-expanded", "false");
      }
    };

    const openMenu = () => {
      if (!menuPanel) {
        return;
      }

      closeSearch();
      menuPanel.hidden = false;
      header.classList.add("is-menu-open");
      document.body.classList.add("has-menu-sheet");

      if (menuToggle) {
        menuToggle.setAttribute("aria-expanded", "true");
      }

      window.setTimeout(() => {
        menuPanel.querySelector("[data-menu-close], a, button")?.focus();
      }, 20);
    };

    searchToggles.forEach((button) => {
      button.addEventListener("click", () => {
        if (!searchPanel) {
          return;
        }

        if (header.classList.contains("is-search-open")) {
          closeSearch();
          return;
        }

        openSearch();
      });
    });

    if (menuToggle) {
      menuToggle.addEventListener("click", () => {
        if (!menuPanel) {
          return;
        }

        if (header.classList.contains("is-menu-open")) {
          closeMenu();
          return;
        }

        openMenu();
      });
    }

    if (menuClose) {
      menuClose.addEventListener("click", closeMenu);
    }

    document.addEventListener("click", (event) => {
      if (header.contains(event.target)) {
        return;
      }

      closeSearch();
      closeMenu();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeSearch();
        closeMenu();
      }
    });

    if (menuPanel) {
      menuPanel.addEventListener("click", (event) => {
        if (event.target === menuPanel) {
          closeMenu();
          return;
        }

        const target = event.target.closest("a");
        if (target) {
          closeMenu();
        }
      });
    }

    const updateHeaderState = () => {
      header.classList.toggle("is-condensed", (window.scrollY || window.pageYOffset) > 24);
    };

    updateHeaderState();
    window.addEventListener("scroll", updateHeaderState, { passive: true });
  }

  if (footerSections.length) {
    const syncFooterSections = () => {
      if (window.innerWidth < 980) {
        return;
      }

      footerSections.forEach((section) => {
        section.open = true;
      });
    };

    syncFooterSections();
    window.addEventListener("resize", syncFooterSections);
  }

  const progressBar = document.querySelector(".site-progress__bar");
  const article = document.querySelector(".single-story");
  const tocLinks = Array.from(document.querySelectorAll(".story-toc__link"));

  if (progressBar && article) {
    const headings = tocLinks
      .map((link) => {
        const id = link.getAttribute("href")?.replace("#", "");
        if (!id) {
          return null;
        }

        const heading = document.getElementById(id);
        if (!heading) {
          return null;
        }

        return { link, heading };
      })
      .filter(Boolean);

    let ticking = false;

    const updateReadingState = () => {
      ticking = false;

      const articleTop = article.offsetTop;
      const articleHeight = article.offsetHeight;
      const viewportHeight = window.innerHeight;
      const scrollTop = window.scrollY || window.pageYOffset;
      const articleEnd = Math.max(articleTop, articleTop + articleHeight - viewportHeight);
      const rawProgress = (scrollTop - articleTop) / Math.max(1, articleEnd - articleTop);
      const progress = Math.max(0, Math.min(1, rawProgress));

      progressBar.style.transform = `scaleX(${progress})`;

      if (!headings.length) {
        return;
      }

      const offset = scrollTop + 180;
      let active = headings[0];

      headings.forEach((item) => {
        if (item.heading.offsetTop <= offset) {
          active = item;
        }
      });

      headings.forEach((item) => {
        const isActive = item === active;
        item.link.classList.toggle("is-active", isActive);
        if (isActive) {
          item.link.setAttribute("aria-current", "true");
        } else {
          item.link.removeAttribute("aria-current");
        }
      });
    };

    const requestUpdate = () => {
      if (ticking) {
        return;
      }

      ticking = true;
      window.requestAnimationFrame(updateReadingState);
    };

    window.addEventListener("scroll", requestUpdate, { passive: true });
    window.addEventListener("resize", requestUpdate);
    requestUpdate();
  }

  const copyButtons = Array.from(document.querySelectorAll("[data-copy-link]"));
  copyButtons.forEach((button) => {
    button.addEventListener("click", async () => {
      const url = button.getAttribute("data-copy-link");
      if (!url) {
        return;
      }

      try {
        await navigator.clipboard.writeText(url);
        button.classList.add("is-copied");
        const label = button.querySelector(".share-links__label");
        if (label) {
          label.textContent = "Copied";
        }
        window.setTimeout(() => {
          button.classList.remove("is-copied");
          if (label) {
            label.textContent = "Copy link";
          }
        }, 1800);
      } catch (error) {
        window.prompt("Copy this link", url);
      }
    });
  });

  const revealItems = Array.from(document.querySelectorAll("[data-reveal]"));
  const motionReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (!revealItems.length) {
    return;
  }

  if (motionReduced || !("IntersectionObserver" in window)) {
    revealItems.forEach((item) => item.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    {
      threshold: 0.14,
      rootMargin: "0px 0px -10% 0px",
    }
  );

  revealItems.forEach((item) => observer.observe(item));
});
