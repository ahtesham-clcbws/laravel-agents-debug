export default {
  title: "Laravel Agent-Debugger",
  description: "A Zero-JS, High-Fidelity Server-Side Diagnostics & Profiling Suite — v3.1.0 with 13 interactive tabs, 29 diagnostic profilers, real-time SSE streaming, and an interactive SPA dashboard.",
  base: "/laravel-agents-debug/",
  themeConfig: {
    nav: [
      { text: "Home", link: "/" },
      { 
        text: "Documentation", 
        items: [
          { text: "The Vision", link: "/vision" },
          { text: "All Features (13+29)", link: "/features" },
          { text: "Config & Deployment", link: "/configuration" },
          { text: "Artisan CLI Guide", link: "/artisan" },
          { text: "Log Schema", link: "/schema" }
        ]
      },
      { 
        text: "Project History", 
        items: [
          { text: "Changelog v3.1.0", link: "/changelogs/v3.1.0" },
          { text: "Changelog v2.9.0", link: "/changelogs/v2.9.0" },
          { text: "Changelog v2.8.0", link: "/changelogs/v2.8.0" },
          { text: "Changelog v2.7.0", link: "/changelogs/v2.7.0" }
        ]
      }
    ],
    sidebar: [
      {
        text: "Introduction",
        items: [
          { text: "Home & Intro", link: "/" },
          { text: "The Vision", link: "/vision" }
        ]
      },
      {
        text: "Diagnostic Features",
        items: [
          { text: "All 13+29 Features", link: "/features" },
          { text: "Log Output Schema", link: "/schema" }
        ]
      },
      {
        text: "Developer Guide",
        items: [
          { text: "Configuration Schema", link: "/configuration" },
          { text: "Artisan CLI Panel", link: "/artisan" }
        ]
      },
      {
        text: "Versions & Legal",
        items: [
          { text: "Changelog v3.1.0 ✨", link: "/changelogs/v3.1.0" },
          { text: "Changelog v2.9.0", link: "/changelogs/v2.9.0" },
          { text: "Changelog v2.8.0", link: "/changelogs/v2.8.0" },
          { text: "Changelog v2.7.0", link: "/changelogs/v2.7.0" },
          { text: "Acknowledgements", link: "/acknowledgments" }
        ]
      }
    ],
    socialLinks: [
      { icon: "github", link: "https://github.com/ahtesham-clcbws/laravel-agents-debug" },
      {
        icon: {
          svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.5L2.25 6.75v10.5L12 22.5l9.75-5.25V6.75L12 1.5zm0 2.1l7.5 4.05v8.7L12 20.4l-7.5-4.05v-8.7L12 3.6zM12 7l-4.5 2.4V14l4.5 2.4 4.5-2.4V9.4L12 7zm0 2.1l2.4 1.3v2.6L12 14.3l-2.4-1.3V10.4L12 9.1z"/></svg>'
        },
        link: "https://packagist.org/packages/clcbws/laravel-agents-debug"
      }
    ]
  }
}
