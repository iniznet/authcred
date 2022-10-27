/** @type {import('tailwindcss').Config} */
module.exports = {
  important: true,
  experimental: {
    optimizeUniversalDefaults: true
  },
  darkMode: "class",
  content: [
    "./resources/**/*.js",
    "./templates/**/*.php",
    "./src/**/*.php",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
