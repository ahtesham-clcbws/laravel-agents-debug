export default {
  title: "Laravel Agent-Debugger",
  description: "A Zero-JS, High-Fidelity Server-Side Diagnostics & Profiling Suite.",
  base: "/laravel-agents-debug/",
  themeConfig: {
    nav: [
      { text: "Home", link: "/" },
      { 
        text: "Documentation", 
        items: [
          { text: "The Vision", link: "/vision" },
          { text: "Core Features", link: "/features" },
          { text: "Config & Deployment", link: "/configuration" },
          { text: "Artisan CLI Guide", link: "/artisan" }
        ]
      },
      { text: "Log Schema", link: "/schema" },
      { 
        text: "Project History", 
        items: [
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
          { text: "Core Profilers", link: "/features" },
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
          { text: "Changelog v2.8.0", link: "/changelogs/v2.8.0" },
          { text: "Changelog v2.7.0", link: "/changelogs/v2.7.0" },
          { text: "Acknowledgements", link: "/acknowledgments" }
        ]
      }
    ],
    socialLinks: [
      { icon: "github", link: "https://github.com/ahtesham-clcbws/laravel-agents-debug" }
    ]
  }
}
