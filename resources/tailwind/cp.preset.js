const defaultTheme = require("tailwindcss/defaultTheme");

/**
 * centralize Tailwind "design tokens" here so CP + future themes share it.
 */
module.exports = {
  theme: {
    extend: {
      fontFamily: {
        public: ["Public Sans", ...defaultTheme.fontFamily.sans],
        remix: ["remixicon"],
      },

      /**
       * define semantic tokens using CSS variables.
       */
      colors: {
        // Primary ("custom-500" in templates)
        custom: {
          50: "rgb(var(--sc-custom-50) / <alpha-value>)",
          100: "rgb(var(--sc-custom-100) / <alpha-value>)",
          200: "rgb(var(--sc-custom-200) / <alpha-value>)",
          500: "rgb(var(--sc-custom-500) / <alpha-value>)",
          600: "rgb(var(--sc-custom-600) / <alpha-value>)",
        },

        // use "zink" naming in templates (not Tailwind's default "zinc").
        zink: {
          50: "rgb(var(--sc-zink-50) / <alpha-value>)",
          100: "rgb(var(--sc-zink-100) / <alpha-value>)",
          200: "rgb(var(--sc-zink-200) / <alpha-value>)",
          300: "rgb(var(--sc-zink-300) / <alpha-value>)",
          400: "rgb(var(--sc-zink-400) / <alpha-value>)",
          500: "rgb(var(--sc-zink-500) / <alpha-value>)",
          600: "rgb(var(--sc-zink-600) / <alpha-value>)",
          700: "rgb(var(--sc-zink-700) / <alpha-value>)",
          800: "rgb(var(--sc-zink-800) / <alpha-value>)",
        },

        body: {
          bg: "rgb(var(--sc-body-bg) / <alpha-value>)",
          bordered: "rgb(var(--sc-body-bordered) / <alpha-value>)",
        },

        topbar: {
          DEFAULT: "rgb(var(--sc-topbar-bg) / <alpha-value>)",
          border: "rgb(var(--sc-topbar-border) / <alpha-value>)",
          item: "rgb(var(--sc-topbar-item) / <alpha-value>)",
          "item-dark": "rgb(var(--sc-topbar-item-dark) / <alpha-value>)",
          "item-hover": "rgb(var(--sc-topbar-item-hover) / <alpha-value>)",
          "item-bg-hover": "rgb(var(--sc-topbar-item-bg-hover) / <alpha-value>)",
          dark: "rgb(var(--sc-topbar-bg-dark) / <alpha-value>)",
          "border-dark": "rgb(var(--sc-topbar-border-dark) / <alpha-value>)",
          brand: "rgb(var(--sc-topbar-bg-brand) / <alpha-value>)",
          "border-brand": "rgb(var(--sc-topbar-border-brand) / <alpha-value>)",
        },

        "vertical-menu": {
          DEFAULT: "rgb(var(--sc-vertical-menu-bg) / <alpha-value>)",
          border: "rgb(var(--sc-vertical-menu-border) / <alpha-value>)",
          dark: "rgb(var(--sc-vertical-menu-bg-dark) / <alpha-value>)",
          "border-dark": "rgb(var(--sc-vertical-menu-border-dark) / <alpha-value>)",
          brand: "rgb(var(--sc-vertical-menu-bg-brand) / <alpha-value>)",
          "border-brand": "rgb(var(--sc-vertical-menu-border-brand) / <alpha-value>)",
          /* define item-level tokens used by menu links. */
          item: "rgb(var(--sc-vertical-menu-item) / <alpha-value>)",
          "item-hover": "rgb(var(--sc-vertical-menu-item-hover) / <alpha-value>)",
          "item-bg-hover": "rgb(var(--sc-vertical-menu-item-bg-hover) / <alpha-value>)",
          "item-active": "rgb(var(--sc-vertical-menu-item-active) / <alpha-value>)",
          "item-bg-active": "rgb(var(--sc-vertical-menu-item-bg-active) / <alpha-value>)",
        },
      },

      zIndex: {
        1000: "1000",
        1002: "1002",
        1003: "1003",
        1049: "1049",
      },

      fontSize: {
        // redefine Tailwind's `text-sm` globally.
        sm: [".8125rem"],

        // redefine Tailwind's `text-base` globally.
        base: [".875rem"],

        11: ["11px", "1rem"],
        15: ["15px", "1.25rem"],
        16: ["16px", "1.25rem"],
      },

      /**
       * layout tokens used by classnames like h-header / w-vertical-menu / max-w-boxed.
       * keep values adjustable here so you can match pixel-perfect later.
       */
      height: {
        header: "70px",
      },
      width: {
        "vertical-menu": "260px",
        "vertical-menu-md": "84px",
        "vertical-menu-sm": "4.375rem",
      },
      maxWidth: {
        boxed: "1320px",
      },

      spacing: {
        header: "70px",
        "vertical-menu-sm": "4.375rem",
      },
    },
  },
};
