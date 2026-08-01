import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import { withVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import globals from 'globals'

// Flat Config für ESLint >= 9, passend zu Vue 3 + `<script setup lang="ts">` + Vite 7.
// Basis: eslint:recommended + eslint-plugin-vue (Vue 3 "recommended") +
// typescript-eslint "recommended" (nicht type-checked, siehe design.md
// Decision 5 — vermeidet die Komplexität/Performance-Kosten von
// type-aware Linting für den Erstlauf gegen den kompletten Bestandscode).
export default withVueTs(
  {
    // .vue-Dateien in diesem Projekt sind ausschließlich <script setup lang="ts">
    // bzw. <script lang="ts">, siehe CLAUDE.md Abschnitt 2/6.
    scriptLangs: ['ts'],
    rootDir: import.meta.dirname,
  },
  {
    // Globaler Scope: nur der eigentliche Frontend-Quellcode und die
    // Projekt-Konfigurationsdateien. Build-Output, Reports und generierte
    // Artefakte werden nie gelintet.
    ignores: [
      'dist/**',
      'dist-ssr/**',
      'coverage/**',
      'playwright-report/**',
      'test-results/**',
      'public/**',
      '.vite/**',
      // Legacy, freistehendes CommonJS-Skript für manuelle API-Tests
      // (kein Teil von frontend/src/, kein TypeScript/Vue-Code, siehe
      // task-T02.notes.md — gezielte Einzelfall-Ausnahme statt
      // Regel-Herabstufung für die restliche Codebasis).
      'manual-test.cjs',
    ],
  },
  js.configs.recommended,
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.recommended,
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.es2021,
      },
    },
  },
  {
    files: ['**/*.test.ts', '**/*.spec.ts', 'e2e/**/*.ts'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  {
    files: ['vite.config.ts', 'vitest.config.ts', 'playwright.config.ts', 'postcss.config.js', 'tailwind.config.js'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  {
    // Baseline-Herabstufung für Bestandsverstöße (siehe task-T02.notes.md,
    // design.md Decision 3c): Diese Regeln erzeugten beim Erstlauf gegen
    // den kompletten Bestandscode zu viele Alt-Verstöße, um sie in diesem
    // reinen Tooling-Change zu beheben (Non-Goal laut design.md). `warn`
    // blockiert `npm run lint` nicht (Exit-Code 0), solange kein
    // `--max-warnings` gesetzt ist. Neuer Code, der diese Regeln
    // verletzt, erzeugt weiterhin sichtbare Warnungen im Lint-Report.
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': 'warn',
      'no-case-declarations': 'warn',
      'no-useless-escape': 'warn',
    },
  },
)
