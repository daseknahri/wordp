document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const button = document.querySelector(".site-nav-toggle");
  const panel = document.querySelector(".site-header__panel");

  if (header && button && panel) {
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
