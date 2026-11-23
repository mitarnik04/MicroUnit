# Product

<!-- impeccable:product-schema 1 -->

The source of truth for what MicroUnit is, who it is for and how it talks about itself. Every text
(docs, README, CHANGELOG, posts, brand assets, the video) and every design follows this file.

## Platform

web

## Users

PHP developers who pick their own tools. Two groups matter most:

- **Developers open to new, modern tools.** They like trying something built for how PHP is written
  today and are drawn by the new parts: the Test Explorer, configuration in plain PHP, coverage
  without extensions, support for AI coding agents.
- **Developers who want fast tests.** They re-run tests all day, alone or through an AI agent, and
  feel every second of runner overhead.

A third group follows from both: teams on PHPUnit who decide MicroUnit suits a project. For them the
job is an easy move, not a rewrite.

## Product Purpose

MicroUnit is a PHP testing framework with zero dependencies, written entirely in plain PHP. It offers
everything people expect from a testing framework (assertions, mocks, data sets, fixtures, snapshots,
coverage, parallel runs, reports, framework integrations) plus features that are new to PHP testing,
and it runs fast. Success means developers who choose it enjoy writing and reading their tests and
never wait for them.

## Positioning

**A new way to test PHP. Built for today.**

MicroUnit is a modern option for the projects that want what it offers. It does not compete with
PHPUnit or ask anyone to replace it. PHPUnit, built and maintained for two decades by Sebastian
Bergmann, is respected and an inspiration. MicroUnit gives its users PHPUnit's capabilities so that
adoption is easy wherever MicroUnit's own strengths matter:

- **Zero dependencies, nothing but PHP.** Not one Composer package, not even for development;
  coverage without Xdebug or PCOV; parallel runs without extra packages.
- **Fast.** Low startup and per-test overhead, measured on the real test suites of six popular
  projects (`benchmarks/`).
- **New features.** The browser Test Explorer, configuration in plain PHP with autocompletion, the
  integrity check against weakened tests, agent-friendly output.
- **Readable tests.** A test is a closure with a name that says what it checks; PHPUnit argument
  order (expected, actual).
- **Works without setup, configurable in detail.** No config file needed to start; one
  `microunit.config.php` holds many options for an exact setup.

Agent support is a real feature (compact JSON output, the integrity check, an installable skill) but
not the headline. "Modern" hints at it; the docs show it.

## Operating Context

- Installed with `composer require --dev microunit/microunit`, run with `vendor/bin/microunit`.
- Used from the terminal, in CI (GitHub Actions, GitLab and others), with AI coding agents, and in
  the opt-in browser Test Explorer.
- Evaluated through the README, the docs site (`docs/`, GitHub Pages), the benchmarks, the social
  image and the 1.0 product video.
- Built by one maintainer, with AI writing code, tests and docs. The maintainer designs the product
  and reviews the work; CI checks every change. `docs/quality-with-ai.md` describes the process.

## Capabilities and Constraints

- PHP 8.5 or newer; tested on 8.5 and 8.6. No PHP 8.6-only features until a future major version.
- Zero dependencies is a ground rule: no Composer packages, not even `require-dev`. Tools like
  PHP-CS-Fixer and PHPStan run as PHARs.
- Configuration lives in PHP (`MicroUnitConfigBuilder`); CLI flags only override a single run.
- `microunit migrate` translates PHPUnit suites and leaves the originals untouched.

## Brand Commitments

**Name:** MicroUnit in text, `microunit` as the wordmark and package name.

**Brand line:** *Minimal. Modern. Mighty.* (banners, video opener, README footer).

**Slogan:** *A new way to test PHP. Built for today.* On the social image the first sentence is
white and "Built for today." is highlighted in cyan.

**Descriptor:** "The dependency-free testing framework for PHP." (banners and video opener) stays as
written.

**Brand colours** (authority: `resources/brand.css`, copied to `docs/assets/css/brand.css`):

| Token | Hex | Use |
| --- | --- | --- |
| `--ultra` | `#0A17A8` | Primary brand blue: the mark's block, brand panels, social image background |
| `--navy` | `#0A1D86` | Wordmark on light backgrounds |
| `--cyan` | `#3FC6F3` | The mark's unit square, highlights, accents, links in dark mode |
| `--cobalt` | `#114EA9` | Links and secondary accents on light backgrounds |
| `--deep` | `#050B3A` | Darkest brand background (dark banner, video) |
| `--run` | `#2440F0` | Active and running states |

Status colours, light / dark: pass `#1A8F4E` / `#3DCB7B`, reject `#D3343C` / `#FF6A6F`, busy
`#C77F00` / `#FFBE3D`, skip `#7A6A1E` / `#D6C15A`.

Neutrals, light / dark: background `#F4F6F9` / `#0A0D16`, surface `#FFFFFF` / `#121417`, ink
`#10131A` / `#E8ECF4`, secondary ink `#4A5361` / `#A9B2C3`, hairline `#D3D9E1` / `#262D3D`.

Every surface supports a light and a dark theme. The Test Explorer is dark by default, and dark is
the theme to show first in screenshots; light is shown as the alternative.

**Voice.** The maintainer speaking to peers: first person where it fits, plain words, short
sentences, specific facts, a little personality. The rules for every text:

- **Current state only.** Describe MicroUnit as it is. Old wording is deleted, never referenced: no
  "previously", no "we no longer", no notes about what changed.
- **Explain decisions by their benefits.** Fold worries into that explanation and show why they
  don't hold, with proof. No objection headings, no "what we don't do" sections.
- **Checkable facts, no hyperbole.** No "the new standard", "the best", "the fastest". Claim only
  what the repository backs, and leave out what it doesn't.
- **Compare with PHPUnit only where it is measured:** the benchmarks page and benchmark scenes, stated
  plainly ("faster than PHPUnit on the same tests"), never belittling. No "switch everything" push:
  migrating is framed as making the move easy for people who choose MicroUnit.
- **Easy to understand for newcomers**, so they catch the vital parts at first read.
- **Being open about AI is a strength.** AI is used to write the code and the docs; say so plainly,
  without saying how much, and point to the guardrails.
- **No em dashes, no decorative emoji.**

## Evidence on Hand

- Benchmarks: `benchmarks/` (harness, pinned corpus, published results under `results/<date>/`),
  charts in `assets/benchmarks/` and `docs/assets/img/benchmarks/`.
- Test Explorer screenshots: `assets/brand/explorer-dark.png`, `assets/brand/explorer.png`.
- Brand assets: `assets/brand/` (mark, lockups, banners, social image, release images, the 1.0 video
  and their HTML sources in `assets/brand/src/`).
- The quality process: `.github/workflows/ci.yml`, `bin/canary.php`, `bin/lint.php`,
  `docs/quality-with-ai.md`.
- Not on hand, never to be invented: testimonials, user or download numbers, company users,
  sponsors (none yet), press coverage, a release date.

## Product Principles

1. **Easy to write, easy to scan.** A test reads top to bottom like a description of behaviour;
   every public API is judged by that first.
2. **Nothing but PHP.** Zero dependencies and no required extensions; the code you install is all
   there is.
3. **Everything expected, plus what is new.** Cover the standard features fully so adoption is easy,
   and add what PHP testing has not had before.
4. **Fast where it counts.** Startup and per-test overhead matter, because tests run all day.
5. **Trust through proof.** Every claim links to something people can check.

## Accessibility & Inclusion

Light and dark themes everywhere; motion respects `prefers-reduced-motion`; text is written to be
easy to follow for newcomers and non-native English readers.
