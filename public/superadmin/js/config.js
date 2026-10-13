(()=>{
    const e = document.documentElement;
    
    // Clear any previous theme configuration to prevent it from overriding our defaults
    sessionStorage.removeItem("__THEME_CONFIG__");
    localStorage.removeItem("__THEME_CONFIG__");

    // Standard Luxe constants
    const config = {
        skin: "luxe",
        theme: "light", // or change to 'dark' or 'system' as needed
        topbarColor: "dark",
        sidenavColor: "light",
        sidenavSize: "on-hover-active",
        sidenavUser: true,
        position: "fixed",
        width: "fluid",
        dir: "ltr"
    };

    function getTheme() {
        return config.theme === "system" ? (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light") : config.theme;
    }

    // Apply attributes immediately
    e.setAttribute("data-skin", config.skin);
    e.setAttribute("data-bs-theme", getTheme());
    e.setAttribute("data-menu-color", config.sidenavColor);
    e.setAttribute("data-topbar-color", config.topbarColor);
    e.setAttribute("data-layout-width", config.width);
    e.setAttribute("data-layout-position", config.position);
    e.setAttribute("dir", config.dir);

    if (config.sidenavSize) {
        let size = config.sidenavSize;
        if (window.innerWidth <= 767) {
            size = "offcanvas";
        } else if (window.innerWidth <= 1140 && size !== "offcanvas") {
            size = "condensed";
        }
        e.setAttribute("data-sidenav-size", size);
    }

    if (config.sidenavUser) {
        e.setAttribute("data-sidenav-user", "true");
    } else {
        e.removeAttribute("data-sidenav-user");
    }

    // Make it available to the template's app.js
    window.defaultConfig = config;
    window.config = config;

})();