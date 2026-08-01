# Task T02 — Notizen (dev-typescript)

## Ausgeführte Schritte

1. Installiert via `npm install --save-dev eslint typescript-eslint eslint-plugin-vue`,
   danach zusätzlich `@vue/eslint-config-typescript` und `globals` (für Browser-/Node-Globals
   in der Flat-Config), sowie `jiti` (explizit, s. u. "Ungeplante Zusatz-Abhängigkeit") und
   `@eslint/js` (für `eslint:recommended` als Basis-Preset, s. Decision 5 in `design.md`).
2. Neue Datei `frontend/eslint.config.ts` (Flat Config) angelegt.
3. `frontend/package.json` bekommt `"lint": "eslint ."`.
4. Verbindlicher Prüfschritt (Vorher/Nachher, siehe unten) durchgeführt.
5. `npm run build` und `npx vitest run` erneut ausgeführt, um sicherzustellen, dass die
   ESLint-Einführung den bestehenden Build/Test-Lauf nicht beeinflusst.

## Real installierte Versionen (Nachweis: `frontend/package.json`, `frontend/package-lock.json`)

- `eslint`: `^10.8.0` (real installiert: `10.8.0`)
- `typescript-eslint`: `^8.65.0` (real installiert: `8.65.0`)
- `eslint-plugin-vue`: `^10.10.0` (real installiert: `10.10.0`)
- `@vue/eslint-config-typescript`: `^14.9.0` (real installiert: `14.9.0`)
- `globals`: `^17.8.0` (real installiert: `17.8.0`)
- `jiti`: `^2.7.0` (real installiert: `2.7.0`)
- `@eslint/js`: `^10.0.1` (real installiert: `10.0.1`)

### Ungeplante Zusatz-Abhängigkeit: `jiti`

`design.md` Decision 5 sah nur `eslint`, `typescript-eslint`, `eslint-plugin-vue` (plus optional
`@vue/eslint-config-typescript`) vor. Bei der Implementierung stellte sich heraus, dass ESLint
10 `.ts`-Config-Dateien (`eslint.config.ts`) nur über die **optionale** Peer-Dependency `jiti`
lädt (`node_modules/eslint/lib/config/config-loader.js:142-169`). Ohne `jiti` schlägt jeder aus
diesem Projekt heraus gestartete `eslint`-Aufruf sofort mit einem Ladefehler fehl, bevor
überhaupt eine Regel geprüft wird. Vorhandene, transitiv installierte `jiti`-Version war
`1.21.7` (u. a. über `tailwindcss`) — ESLint verlangt aber `>= 2.2.0`
(`node_modules/eslint/lib/config/config-loader.js:154`, Fehlermeldung "outdated version of the
'jiti' library"). Daher `jiti@^2.2` explizit als `devDependency` ergänzt (aufgelöst: `2.7.0`).
Alternative wäre eine `.mjs`/`.js`-Config ohne TypeScript gewesen — verworfen, weil `design.md`
`eslint.config.ts` explizit als bevorzugte Variante nennt ("da das der aktuelle Standard für
diese Toolchain-Alter ist") und die reale ESLint-10-Auflösung dies mit `jiti` unterstützt.

### Grund für `@vue/eslint-config-typescript` statt reiner manueller Komposition

`design.md` Decision 5 stellt die Wahl frei ("Entscheidung beim `dev-typescript`-Task"). Das
offizielle Preset stellt den Helper `withVueTs(...)` bereit, der die TypeScript-Parser-Auflösung
für `.vue`-Dateien korrekt mit `eslint-plugin-vue` verzahnt (inkl. `<script setup lang="ts">`,
siehe `node_modules/@vue/eslint-config-typescript/README.md`). Manuelle Komposition hätte
denselben Parser-Verzahnungs-Code dupliziert — Wahl reduziert Konfigurationsaufwand und
Fehleranfälligkeit (DRY), exakt wie in `design.md` als Kriterium genannt.

## Aufbau von `frontend/eslint.config.ts`

- Basis: `js.configs.recommended` (`eslint:recommended`) + `pluginVue.configs['flat/recommended']`
  (Vue-3-"recommended"-Preset, in `eslint-plugin-vue` v10 der Nachfolger von
  `vue/vue3-recommended`) + `vueTsConfigs.recommended` (`@typescript-eslint/recommended`,
  über `@vue/eslint-config-typescript` an `.vue`-Dateien angepasst) — **nicht type-checked**
  (kein `recommendedTypeChecked`), wie in `design.md` Decision 5 vorgesehen, um die
  Performance-/Komplexitätskosten von type-aware Linting im Erstlauf zu vermeiden.
- `scriptLangs: ['ts']` (Projekt-Option von `withVueTs`): erzwingt, dass alle `<script>`-Blöcke
  in `.vue`-Dateien TypeScript sind — passend zu `CLAUDE.md` Vue-Konventionen
  (`<script setup lang="ts">`).
- Globale `ignores`: Build-Output (`dist/**`, `dist-ssr/**`), Test-/Report-Artefakte
  (`coverage/**`, `playwright-report/**`, `test-results/**`), `public/**`, `.vite/**`, sowie
  `manual-test.cjs` (Begründung siehe Abschnitt "Baseline-Mechanik" unten).
- Deckt `frontend/src/**/*.vue` und `frontend/src/**/*.ts` ab (Pflicht-Scope laut `tasks.md`),
  zusätzlich `frontend/e2e/**/*.ts` (Playwright-Specs) und die Root-Konfigurationsdateien
  (`vite.config.ts`, `vitest.config.ts`, `playwright.config.ts`, `postcss.config.js`,
  `tailwind.config.js`) — `eslint .` linted den kompletten Verzeichnisbaum, daher wurden diese
  bewusst mit einbezogen statt implizit unkonfiguriert (fehlende Node-Globals) zu bleiben.
- `languageOptions.globals`: `globals.browser` + `globals.es2021` global (Frontend-App läuft im
  Browser), `globals.node` zusätzlich für Test-Dateien (`*.test.ts`, `*.spec.ts`, `e2e/**/*.ts`)
  und die genannten Node-Konfigurationsdateien.

## Verbindlicher Prüfschritt — Vorher/Nachher (design.md, "Verbindlicher Prüfschritt vor Task-Abschluss")

### Vorher (minimale empfohlene Konfiguration, ohne Baseline-Herabstufung/Ignores für Bestandsdateien)

`npm run lint` (Exit-Code 1):

```
✖ 3035 problems (152 errors, 2883 warnings)
  0 errors and 2139 warnings potentially fixable with the `--fix` option.
```

100 gelintete Dateien (`frontend/src/**` [88 Dateien], `frontend/e2e/**` [5 Dateien],
Root-Configs [`vite.config.ts`, `vitest.config.ts`, `playwright.config.ts`,
`postcss.config.js`, `tailwind.config.js`, `eslint.config.ts`] sowie `manual-test.cjs`),
62 Dateien mit mindestens einem Finding, 39 Dateien mit mindestens einem Error.

Aufschlüsselung der 152 Errors nach Regel (via `eslint . --format json`, ausgezählt):

| Regel | Errors | Betroffene Dateien |
|---|---|---|
| `@typescript-eslint/no-explicit-any` | 136 | 34 |
| `@typescript-eslint/no-unused-vars` | 7 | 6 |
| `no-case-declarations` | 4 | 1 (`src/views/trainers/TrainersView.vue`) |
| `@typescript-eslint/no-require-imports` | 2 | 1 (`manual-test.cjs`) |
| `no-undef` | 2 | 1 (`manual-test.cjs`) |
| `no-useless-escape` | 1 | 1 (`e2e/installation-wizard.spec.ts`) |
| **Summe** | **152** | |

Die 2883 Warnings verteilen sich zum Erstlauf bereits fast ausschließlich auf
Vue-Stilregeln, die `pluginVue.configs['flat/recommended']` selbst schon als `warn`
einstuft (`vue/max-attributes-per-line`: 1526, `vue/singleline-html-element-content-newline`:
852, `vue/attributes-order`: 242, `vue/html-self-closing`: 214, u. a.) — diese blockieren
`npm run lint` bereits im Erstlauf nicht und wurden nicht angefasst.

### Baseline-Mechanik (Regel-Herabstufung + gezielte Einzelfall-Ignores)

Gemäß `design.md` Decision 3c gewählte Kombination:

**a) Regel-Herabstufung auf `warn`** (betrifft alle vier verbleibenden Error-Regeln aus der
realen Bestandscode-Basis, siehe `eslint.config.ts` letzter Config-Block):
- `@typescript-eslint/no-explicit-any` (136 Errors, 34 Dateien — dominant, 89 % aller Errors)
- `@typescript-eslint/no-unused-vars` (7 Errors, 6 Dateien)
- `no-case-declarations` (4 Errors, 1 Datei)
- `no-useless-escape` (1 Error, 1 Datei — `e2e/installation-wizard.spec.ts`)

Begründung: Alle vier Regeln sind Typsicherheits-/Stilregeln ohne akute Laufzeit-Bugrelevanz;
eine Herabstufung ist die vom `design.md` explizit vorgesehene Standardstrategie. Neue
Verstöße bleiben über die Warnung im Lint-Report sichtbar (Requirement "npm run lint erkennt
neu eingeführte Verstöße" in `specs/qa-tooling/spec.md` ist über die Warnungs-Sichtbarkeit
weiterhin erfüllt, auch wenn diese vier Regeln keinen Exit-Code-Fehler mehr auslösen — echte
Syntax-/Korrektheitsfehler, z. B. nicht auflösbare Importe, bleiben unverändert `error`).

**b) Gezielte Datei-`ignores`** (nur ein Einzelfall, siehe `eslint.config.ts` `ignores`-Block):
- `manual-test.cjs` — ein freistehendes, am Repo-Root liegendes CommonJS-Skript für manuelle
  API-Tests (`require()`-basiert, kein ES-Modul). Es ist **kein** Teil von
  `frontend/src/**/*.vue`/`*.ts` (dem in `tasks.md`/`specs/qa-tooling/spec.md` verbindlich
  geforderten Lint-Scope) und kein Vue-/TypeScript-Code. Statt die Regeln
  `@typescript-eslint/no-require-imports`/`no-undef` projektweit herabzustufen (was echte
  TypeScript-Dateien beträfe, in denen `require()` tatsächlich ein Fehler wäre), wird nur diese
  eine Datei ausgeschlossen — die präzisere, vom `design.md` empfohlene Variante
  ("möglichst gezielte Regel-Herabstufungen ... kein pauschales Abschalten ganzer
  Regel-Kategorien ohne Begründung").

### Nachher

`npm run lint` (Exit-Code 0):

```
✖ 3031 problems (0 errors, 3031 warnings)
  0 errors and 2139 warnings potentially fixable with the `--fix` option.
```

Rechnerische Kontrolle: 3035 Probleme vorher − 4 (durch `ignores` für `manual-test.cjs`
vollständig entfernte Errors: `no-require-imports` × 2, `no-undef` × 2) = 3031 Probleme nachher,
alle als `warning` (0 Errors). 99 gelintete Dateien (100 − 1 durch `manual-test.cjs`-Ignore).

## Akzeptanzkriterien-Abgleich

- `npm run lint` existiert (`frontend/package.json`) und ist ausführbar — erfüllt.
- ESLint-Dependencies real installiert — erfüllt, siehe Versionstabelle oben,
  Nachweis in `frontend/package-lock.json`.
- `frontend/eslint.config.ts` existiert, deckt `.vue`/`.ts` unter `frontend/src/` ab —
  erfüllt.
- `npm run lint` läuft mit Exit-Code 0 — erfüllt (verifiziert lokal, `echo $?` → `0`).
- Vorher-/Nachher-Zahlen dokumentiert — erfüllt, siehe oben.
- Kein Bestandscode unter `frontend/src/` inhaltlich verändert — erfüllt: einzige
  Nicht-Konfigurationsänderung ist die neue Datei `frontend/eslint.config.ts` selbst; keine
  Datei unter `frontend/src/` wurde angefasst (`git status` zeigt ausschließlich
  `frontend/package.json`, `frontend/package-lock.json` als geändert sowie
  `frontend/eslint.config.ts` als neu).
- `npm run build` läuft weiterhin fehlerfrei — erfüllt, erneut lokal ausgeführt
  (`vue-tsc -b && vite build`, Exit-Code 0, keine neuen Fehler/Warnings durch die
  ESLint-Einführung; ESLint ist nicht Teil der Build-Kette).

## Zusätzlich verifiziert (nicht explizit gefordert, aber Regressionsschutz)

- `npx vitest run`: 18 Testdateien, 194 Tests, alle grün — durch die neue `eslint.config.ts`
  und die `package.json`-Änderung keine Regression an der bestehenden Test-Suite.

## Nicht getan (bewusst, Scope-Grenze laut `design.md`/`proposal.md`)

- Kein Beheben der 3031 dokumentierten Bestandsverstöße (Stilregeln, `no-explicit-any`,
  `no-unused-vars` etc.) im Anwendungscode — explizites Non-Goal dieses Changes.
- `--max-warnings` wurde bewusst **nicht** gesetzt, da das `npm run lint` sofort wieder auf
  Exit-Code ≠ 0 kippen würde (3031 bestehende Warnings) — außerhalb des Scopes dieses Tasks,
  könnte Gegenstand eines Folge-Changes sein, sobald die Baseline gezielt abgebaut wird
  (siehe `design.md`, "Gemeinsamer Grundsatz aller drei Baselines").
