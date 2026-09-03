# sCommerce documentation site

This branch contains the Docusaurus shell, not package code or release Markdown.
The canonical documentation lives in `docs/<locale>/` in each matching major Git
branch. dDocs reads that same tree from the installed Composer package.

## Architecture

- `docs.config.json`: the only supported-version and locale registry.
- `scripts/prepare.cjs`: links branch documentation into disposable `.generated/`
  content and localization views. There are no Markdown copies to maintain.
- One `@docusaurus/plugin-content-docs` instance per major branch, with a stable ID.
- Current documentation is served at `/sCommerce/`. Older lines are served at
  `/sCommerce/<major>.x/`. Other locales precede the version segment, for example
  `/sCommerce/uk/1.x/` when 1.x is no longer current.
- Docusaurus supplies Markdown rendering, localized navigation, theme switching,
  sidebar trees and table of contents. Small theme wrappers supply the branch
  selector and the reusable older-version banner.
- A persistent sidebar credit (fixed bottom bar on mobile) links to
  [Seiger IT](https://seigerit.com/). Article edit links and Previous/Next cards
  are intentionally omitted; canonical localized
  overview pages carry the same developer credit for dDocs readers.
- English is the default locale. Missing translated pages use Docusaurus's normal
  English fallback. `localeSources: {"ru": "uk"}` makes existing `/ru/` URLs
  serve the canonical Ukrainian tree, without changing package `docs/ru` files.
  The alias also shares Ukrainian site UI translations and HTML language; de, fr and pl
  are also enabled because the package already contains those locale trees.

`.generated/i18n/<locale>/docusaurus-plugin-content-docs-<id>/current` is the standard
Docusaurus translation input location, linked to the branch's locale directory.
The word `current` here is an internal translation slot, not a release snapshot or
public URL. Do not run `docusaurus docs:version`, create `versions.json`, or commit
`versioned_docs` / `versioned_sidebars` trees.

## Local development

Requires Node.js 22 or newer and npm. Node 16 is not supported.

For this repository's existing nested site worktree, from the package root:

```powershell
cd .worktrees/docs
npm ci
npm run docs:prepare -- 1.x=../..
npm test
npm run build
npm run serve -- --port 3018
```

Open `http://localhost:3018/sCommerce/` or `/sCommerce/uk/`. The override reads the
live package checkout, including uncommitted documentation edits. For live editing:

```sh
npm start -- --locale uk
```

The development server serves one locale at a time. Use a production build to test
locale switching. Re-run preparation after changing the registry or source paths.

In a standalone checkout of the `docs` branch, obtain only the required source:

```sh
git clone --filter=blob:none --sparse --branch 1.x https://github.com/Seiger/sCommerce.git .sources/1.x
git -C .sources/1.x sparse-checkout set --no-cone /docs/
npm ci
npm run docs:prepare
npm run build
```

Repeat the sparse checkout for each branch declared in the registry. Alternatively
pass one `version=checkout-path` override for each local package checkout. Paths
are resolved relative to this site worktree, not to the package root.

## Switching the current line

First create and populate the real `2.x` package branch, including its own `docs/`.
Then change only `lines` in `docs.config.json`:

```json
[
  {"version": "2.x", "branch": "2.x", "label": "2.x", "status": "current"},
  {"version": "1.x", "branch": "1.x", "label": "1.x", "status": "legacy"}
]
```

For 3.x, add a corresponding entry with `status: "current"`, change 2.x to
`"previous"`, and keep 1.x as `"legacy"`. Exactly one entry must be current. The
matrix, checkouts, plugin instances, paths, menu and banners follow automatically.
Branch names must equal their major compatibility line; `master` and release tags
are deliberately not implicit aliases.

The version selector preserves the document ID when it exists in the target line;
otherwise it opens that line's localized root. URL hashes are dropped because
translated/versioned headings are not guaranteed to be equivalent.

## GitHub Actions and first publication

The site workflow is both directly runnable and reusable:

1. Checkout the `docs` site branch, record its exact commit and read the registry.
2. Matrix jobs use `actions/checkout` with sparse checkout for only `docs/` on every
   configured branch. They upload short-lived documentation artifacts.
3. The build checks out the recorded site commit, downloads those artifacts,
   prepares linked views, runs tests and builds all configured locales and lines.
4. Official Pages actions upload and deploy the static output.

The small workflow on each package branch calls the reusable workflow at `@docs`
when `docs/**` changes. It contains no version list. Keep this caller when starting
future major branches. Site and package events share one deployment concurrency
group so separate branch builds do not overwrite each other concurrently.

Initial deployment order matters:

1. Review this `docs` branch and the canonical-document migration on `1.x`.
   Pushing `docs` starts its workflow automatically. Coordinate the first rollout
   (for example, with environment approval) so deployment waits until both sides
   are present; do not mistake a build of the old branch tree for the migration.
2. Publish the package workflow caller only after the reusable `docs` workflow
   exists remotely, otherwise GitHub cannot resolve the reusable workflow.
3. Ensure repository Pages uses **GitHub Actions**. If the `github-pages`
   environment limits allowed branches, allow `docs` and the supported package
   branches that call the workflow. Manual dispatch can run from default `1.x`.
4. Run the workflow and verify the public deployment. Local tests are not proof
   that repository/environment permissions or the actual Pages deployment work.

No publication settings, remote branches or deployments are changed by local
preparation. The nested `.worktrees/docs` checkout has separate Git state: review
the package migration and the site branch separately before committing either.

## URL migration

The existing `/sCommerce/` base URL, `getting-started`, `admin`, `attributes`,
`developers` and nested document IDs are preserved. The historical typo
`admin/producs` remains valid; `admin/products` is a generated redirect to it.
The renamed source filename `admin/products.md` does not change its existing ID.
Both no-trailing-slash requests and slash URLs resolve on GitHub Pages.

The former public `pages/index.md` content is merged into each localized
`README.md`, preserving its old `intro` ID and root slug. Existing dDocs entrypoint
filenames and `docs.json` remain intact. Public articles were migrated, not audited
against every PHP API; historical examples and capability claims still need a
separate editorial/code audit. Some older translated articles also contain English
sections. Neither translation completeness nor API accuracy is proved by a build.

## Validation

```sh
npm test
node scripts/check-markdown.cjs ../../docs
node tests/build-fixture.cjs
npm run docs:prepare -- 1.x=../..
npm run build
```

The fixture command creates synthetic, ignored 3.x/2.x/1.x content and builds both
English, Ukrainian and the Ukrainian `/ru/` alias into `build-fixture`. It checks current/previous/legacy
routes, warnings and relative document/image links. These are not real releases.
After it runs, prepare the real inputs again before the normal build or dev server.
Missing local Markdown links and broken generated links fail the production build.

The shared Markdown contract is ordinary Markdown with YAML frontmatter. Keep
assets alongside each locale's pages so both dDocs and Docusaurus can resolve them.
Site UI translations belong here in `i18n`; package Markdown translations do not.
