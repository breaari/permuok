/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx,ts,tsx}"],
  theme: {
    extend: {
      // colors: {
      //   primary: "#0056b3",
      //   secondary: "#76bc21",
      //   "background-light": "#f8fafc",
      //   "background-dark": "#0f172a",
      //   "brand-dark": "#0a192f",
      // },
      fontFamily: {
        display: ["Inter", "sans-serif"],
      },
      borderRadius: {
        DEFAULT: "0.5rem",
      },
    },
  },
  plugins: [require("@tailwindcss/forms"), require("@tailwindcss/typography")],
};