import js from "@eslint/js";
import globals from "globals";

export default [
  { ignores: ["node_modules/**", "vendor/**", "wordpress/**", "playwright-report/**"] },
  js.configs.recommended,
  {
    files: ["**/*.js", "**/*.mjs"],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
  },
];
