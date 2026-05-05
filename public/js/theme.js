(function () {
    "use strict";

    const storageKey = "slams-theme";
    const root = document.documentElement;
    const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;

    function savedTheme() {
        let stored = null;
        try {
            stored = localStorage.getItem(storageKey);
        } catch (error) {
            stored = null;
        }
        if (stored === "light" || stored === "dark") {
            return stored;
        }
        return prefersDark ? "dark" : "light";
    }

    function applyTheme(theme) {
        root.setAttribute("data-theme", theme);
        if (document.body) {
            document.body.setAttribute("data-theme", theme);
        }
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            // Theme still applies for this page even when storage is unavailable.
        }
    }

    applyTheme(savedTheme());

    function bindThemeToggle() {
        const toggle = document.getElementById("themeToggle");
        if (!toggle) {
            return;
        }

        toggle.addEventListener("click", function () {
            const current = root.getAttribute("data-theme") || "light";
            applyTheme(current === "dark" ? "light" : "dark");
        });
    }

    function bindSidebar() {
        const body = document.body;
        const overlay = document.getElementById("sidebarOverlay");
        const toggle = document.getElementById("sidebarToggle");
        const wide = window.matchMedia("(min-width: 992px)");

        if (toggle) {
            toggle.addEventListener("click", function () {
                body.classList.toggle("sidebar-open");
            });
        }

        if (overlay) {
            overlay.addEventListener("click", function () {
                body.classList.remove("sidebar-open");
            });
        }

        function syncSidebar() {
            if (wide.matches) {
                body.classList.remove("sidebar-open");
            }
        }

        syncSidebar();
        if (wide.addEventListener) {
            wide.addEventListener("change", syncSidebar);
        }
    }

    function bindNavbar() {
        const navbars = document.querySelectorAll(".glass-navbar, .admin-glass-navbar");
        const syncScrolled = function () {
            navbars.forEach(function (navbar) {
                navbar.classList.toggle("scrolled", window.scrollY > 24);
            });
        };

        syncScrolled();
        window.addEventListener("scroll", syncScrolled, { passive: true });

        const current = window.location.pathname || "/";
        document.querySelectorAll(".glass-navbar .nav-link[href]").forEach(function (link) {
            const href = link.getAttribute("href");
            if (!href || href === "#") {
                return;
            }
            const active = (current === "/" && href === "/") || (href !== "/" && current.startsWith(href));
            link.classList.toggle("active", active);
        });
    }

    function initReveal() {
        const candidates = document.querySelectorAll([
            ".dashboard-header",
            ".page-header",
            ".section-header",
            ".home-hero-panel",
            ".home-section-header",
            ".home-stat",
            ".feature-card",
            ".home-flow-card",
            ".cta-section",
            ".kpi-glass-card",
            ".widget-card",
            ".stats-card",
            ".quick-stat",
            ".admin-dashboard > .glass-card",
            ".lab-hero",
            ".asset-hero",
            ".contact-header",
            ".lab-header-card",
            ".pic-card",
            ".equipment-card",
            ".booking-card",
            ".calendar-card",
            ".filter-bar",
            ".auth-card",
            ".login-card",
            ".map-section"
        ].join(","));

        candidates.forEach(function (node) {
            if (!node.closest(".modal") && !node.classList.contains("slams-reveal")) {
                node.classList.add("slams-reveal");
            }
        });

        const revealNodes = document.querySelectorAll(".slams-reveal");
        const staggerGroups = [
            ".home-stat-grid",
            ".home-feature-grid",
            ".home-flow-grid",
            ".dashboard-grid",
            ".row"
        ];

        revealNodes.forEach(function (node) {
            node.style.setProperty("--slams-reveal-delay", "0ms");
        });

        staggerGroups.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (group) {
                group.querySelectorAll(".slams-reveal").forEach(function (node, index) {
                    node.style.setProperty("--slams-reveal-delay", Math.min((index % 4) * 30, 90) + "ms");
                });
            });
        });

        if (!("IntersectionObserver" in window) || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            revealNodes.forEach(function (node) {
                node.classList.add("is-visible");
            });
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: "0px 0px 12% 0px", threshold: 0.04 });

        revealNodes.forEach(function (node) {
            observer.observe(node);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        applyTheme(root.getAttribute("data-theme") || savedTheme());
        bindThemeToggle();
        bindSidebar();
        bindNavbar();
        initReveal();
    });
})();
