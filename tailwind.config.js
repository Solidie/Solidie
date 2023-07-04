const { createThemes } = require('tw-colors');
const { theme } = require('./theme');

/** @type {import('tailwindcss').Config} */
module.exports = {
  mode: 'jit',
  content: ["./components/**/**/**/*.{php,js,jsx}"],
  theme: {
    extend: {},
  },
  plugins: [
    createThemes(theme),
    // Initialize with default values (see options below)
    require("tailwindcss-radix")(),
    require("daisyui"),
    require('tailwind-scrollbar'),
    require("@tailwindcss/forms")
  ],
  daisyui: {
    theme: [
      {
        light: {
          primary: "#E5ECF2",
          secondary: "#091E42",
          accent: "#EFF1FC",
          neutral: "#F6F7FD"
        }
      }
    ]
  }
}

