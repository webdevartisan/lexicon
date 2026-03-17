const cpPreset = require("./resources/tailwind/cp.preset");

/** @type {import('tailwindcss').Config} */
module.exports = {
  presets: [cpPreset],

  // Match dark mode using data-mode on the <html> element, toggled by the control panel.
  darkMode: ["selector", '[data-mode="dark"]'],

  content: [
    "./views/**/*.lex.php",
    "./views/**/*.php",
    "./public/cp-assets/js/**/*.js",
  ],

  safelist: [
    "hidden",
    "block",
    "open",
    "show",
    "active",
    "is-sticky",
    "overflow-hidden",
    "fill-topbar-item",
    "fill-topbar-item-dark",
    "group-data-[topbar=dark]:fill-topbar-item-dark",
  ],
};
