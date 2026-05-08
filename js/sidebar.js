document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");

  // Sidebar not present on this page — skip initialization.
  if (!sidebar) {
    return;
  }

  const collapseBtn = document.getElementById("collapseBtn");
  const mainWrapper = document.getElementById("mainWrapper");
  const navItems = document.querySelectorAll(".nav-item");
  const pageTitle = document.getElementById("pageTitle");
  const pageSubtitle = document.getElementById("pageSubtitle");
  const mobileToggle = document.getElementById("mobileToggle");
  const overlay = document.getElementById("overlay");

  function isMobile() {
    return window.innerWidth <= 768;
  }

  // Collapse toggle (desktop)
  if (collapseBtn) {
    collapseBtn.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      if (mainWrapper) mainWrapper.classList.toggle("expanded");
    });
  }

  // Mobile menu toggle
  if (mobileToggle) {
    mobileToggle.addEventListener("click", function () {
      sidebar.classList.add("open-mobile");
      if (overlay) overlay.classList.add("active");
      document.body.style.overflow = "hidden";
    });
  }

  // Close mobile menu on overlay click
  if (overlay) {
    overlay.addEventListener("click", closeMobile);
  }

  function closeMobile() {
    sidebar.classList.remove("open-mobile");
    if (overlay) overlay.classList.remove("active");
    document.body.style.overflow = "";
  }

  // Nav item click handling
  navItems.forEach(function (item) {
    item.addEventListener("click", function (e) {
      // Only prevent default if href is "#" (placeholder links)
      const href = item.getAttribute("href");
      if (href === "#" || !href) {
        e.preventDefault();
      }

      // Update active state visually
      navItems.forEach(function (nav) {
        nav.classList.remove("active");
      });
      item.classList.add("active");

      // Update page title/subtitle if elements exist
      if (pageTitle && pageSubtitle) {
        var pageName = item.getAttribute("data-page");
        var displayName = pageName
          .replace(/-/g, " ")
          .replace(/\b\w/g, function (c) {
            return c.toUpperCase();
          });
        pageTitle.textContent = displayName;
        pageSubtitle.textContent = "Welcome to " + displayName;
      }

      // Close mobile menu after selection
      if (isMobile()) {
        setTimeout(closeMobile, 200);
      }
    });
  });

  // Close on Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && sidebar.classList.contains("open-mobile")) {
      closeMobile();
    }
  });

  // Handle window resize
  window.addEventListener("resize", function () {
    if (!isMobile() && sidebar.classList.contains("open-mobile")) {
      closeMobile();
    }
  });
});