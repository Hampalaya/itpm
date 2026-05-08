document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");

  if (!sidebar) return;

  const collapseBtn = document.getElementById("collapseBtn");
  const mainWrapper = document.getElementById("mainWrapper");
  const navItems = document.querySelectorAll(".nav-item");
  const pageTitle = document.getElementById("pageTitle");
  const pageSubtitle = document.getElementById("pageSubtitle");
  const mobileToggle = document.getElementById("mobileToggle");
  const overlay = document.getElementById("overlay");

  // ── Persist collapsed state across pages ──────────────────────────
  const STORAGE_KEY = "sidebar_collapsed";

  function setCollapsed(collapsed) {
    if (collapsed) {
      sidebar.classList.add("collapsed");
      if (mainWrapper) mainWrapper.classList.add("expanded");
      localStorage.setItem(STORAGE_KEY, "1");
    } else {
      sidebar.classList.remove("collapsed");
      if (mainWrapper) mainWrapper.classList.remove("expanded");
      localStorage.removeItem(STORAGE_KEY);
    }
  }

  // Restore on load
  if (localStorage.getItem(STORAGE_KEY) === "1") {
    setCollapsed(true);
  }

  // Collapse button → collapses and stays collapsed
  if (collapseBtn) {
    collapseBtn.addEventListener("click", function () {
      setCollapsed(true); // always collapse; expand is via expandBtn only
    });
  }

  // ── Expand button (shown only when collapsed) ─────────────────────
  // Inject the expand button into the sidebar header
  const expandBtn = document.createElement("button");
  expandBtn.className = "expand-btn";
  expandBtn.id = "expandBtn";
  expandBtn.setAttribute("aria-label", "Expand sidebar");
  expandBtn.innerHTML = `<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>`;

  const sidebarHeader = sidebar.querySelector(".sidebar-header");
  if (sidebarHeader) sidebarHeader.appendChild(expandBtn);

  expandBtn.addEventListener("click", function () {
    setCollapsed(false);
  });

  // ── Mobile ────────────────────────────────────────────────────────
  function isMobile() {
    return window.innerWidth <= 768;
  }

  if (mobileToggle) {
    mobileToggle.addEventListener("click", function () {
      sidebar.classList.add("open-mobile");
      if (overlay) overlay.classList.add("active");
      document.body.style.overflow = "hidden";
    });
  }

  if (overlay) overlay.addEventListener("click", closeMobile);

  function closeMobile() {
    sidebar.classList.remove("open-mobile");
    if (overlay) overlay.classList.remove("active");
    document.body.style.overflow = "";
  }

  // ── Nav items ─────────────────────────────────────────────────────
  navItems.forEach(function (item) {
    item.addEventListener("click", function (e) {
      const href = item.getAttribute("href");
      if (href === "#" || !href) e.preventDefault();

      navItems.forEach(function (nav) { nav.classList.remove("active"); });
      item.classList.add("active");

      if (pageTitle && pageSubtitle) {
        var pageName = item.getAttribute("data-page");
        var displayName = pageName
          .replace(/-/g, " ")
          .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        pageTitle.textContent = displayName;
        pageSubtitle.textContent = "Welcome to " + displayName;
      }

      if (isMobile()) setTimeout(closeMobile, 200);
    });
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && sidebar.classList.contains("open-mobile")) closeMobile();
  });

  window.addEventListener("resize", function () {
    if (!isMobile() && sidebar.classList.contains("open-mobile")) closeMobile();
  });
});