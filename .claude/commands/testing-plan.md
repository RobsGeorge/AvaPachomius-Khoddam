---
description: Audit a repository's real test coverage and generate a living, evidence-based automated testing plan (docs/testing-plan.md), optionally with a CI/pipeline companion doc.
argument-hint: "[output-path] [--pipelines] [--focus <module|area>] [--no-write]"
allowed-tools: Read, Grep, Glob, Write, Edit, Bash(git *), Bash(ls *), Bash(find *), Bash(wc *), Bash(cat *), Bash(rg *), Bash(composer *), Bash(npm *), Bash(pnpm *), Bash(yarn *), Bash(php *), Bash(pytest *), Bash(go *), Bash(bundle *), Bash(make *)
---

# Generate an automated testing plan for this repository

Arguments: `$ARGUMENTS`

You are producing a **living planning document** that an engineering team will work
from for months. It must describe *this* repository as it actually is — never a
generic testing-best-practices essay, and never an invented codebase.

---

## Non-negotiables

Read these before doing anything else. They are what separate a useful plan from
a plausible-sounding one.

1. **Evidence or silence.** Every file path, class name, service, route, config
   key, and test name you write down must have been seen in a tool result during
   *this* run. If you did not open or grep it, it does not go in the document.
2. **Counts are counted, never estimated.** "~35 test files", "60 services",
   "210 passed / 14 failed" — each of those is a number you produced with a
   command, and you say which command. If you could not run it, write
   "not measured" rather than a guess.
3. **Distinguish present from proposed.** Section 1 describes what exists today.
   Sections 4+ propose files that do *not* exist yet. Never blur the two: a
   proposed test file is written as a path to *create*, and the document says so.
4. **Do not write tests, do not modify source.** This command produces
   documentation only. The single exception is the output doc(s).
5. **Obey the repo's own rules.** If `CLAUDE.md`, `AGENTS.md`, `.cursorrules`,
   or `CONTRIBUTING.md` exist, they outrank your defaults — including any rule
   about phases, scope, or not building ahead. If the repo forbids work you were
   about to plan, say so in the doc and stop planning it.
6. **Behavior, not line coverage.** Never set a coverage-percentage target.
   Target flows, authorization boundaries, and invariants.
7. **Prefer extending the existing shape.** If the repo already has a base test
   case, factories, fixtures, helpers, or suite names, the plan builds on them
   rather than proposing a parallel universe.

---

## Step 0 — Parse arguments

- **Output path**: first positional argument, else `docs/testing-plan.md`.
  If `docs/` does not exist, use the repo's existing docs location
  (`doc/`, `documentation/`, `.github/`), else repo root.
- `--pipelines` → also produce a companion `testing-pipeline.md` next to the
  plan (see Step 10b). Only do this if CI config actually exists or you are
  proposing one.
- `--focus <area>` → still audit the whole repo, but weight the workstreams and
  phasing toward that area, and say in the doc that it was scoped that way.
- `--no-write` → do all the analysis, print the full document to the chat, and
  do not touch the filesystem.

If an existing testing plan is already at the output path, **read it first** and
*update* it: preserve its structure and any still-accurate content, refresh the
baseline, mark newly-closed gaps as done, and bump "Last updated". Do not
silently discard a human's prose.

---

## Step 1 — Stack discovery

Identify the language(s), framework, test runner, and CI before analyzing
anything. Check for manifests and runner configs in parallel:

| Ecosystem | Manifest | Runner config | Test dir |
|---|---|---|---|
| PHP / Laravel | `composer.json` | `phpunit.xml`, `pest.php` | `tests/` |
| JS / TS | `package.json` | `jest.config.*`, `vitest.config.*`, `playwright.config.*` | `test/`, `__tests__/`, `*.spec.*` |
| Python | `pyproject.toml`, `setup.cfg` | `pytest.ini`, `tox.ini` | `tests/` |
| Ruby | `Gemfile` | `.rspec`, `spec_helper.rb` | `spec/` |
| Go | `go.mod` | — | `*_test.go` |
| Java / Kotlin | `pom.xml`, `build.gradle` | surefire/failsafe config | `src/test/` |
| Rust | `Cargo.toml` | — | `tests/`, `#[cfg(test)]` |

Also capture, from real files:
- **The runner invocation** the repo actually uses (a `scripts`/`composer` entry,
  a Makefile target, or the raw command). If none exists, note that adding a
  stable entrypoint is itself a plan item.
- **Declared suites/projects/markers** and what each maps to.
- **Test environment**: DB driver under test, in-memory vs. real, transaction or
  refresh strategy, faked mail/queue/cache, seeded fixtures, env overrides and
  whether the runner *forces* them (a runner that inherits a developer's or
  production `.env` is a finding — call it out).
- **CI**: `.github/workflows/*`, `.gitlab-ci.yml`, `Jenkinsfile`, etc. What runs
  on PR, what gates deploy, what is advisory.
- Any build step CI depends on — and equally, the *absence* of one, if the repo
  says not to add it.

---

## Step 2 — Baseline: what is tested today

Enumerate the actual test files (`Glob`), then group them by the product area
they exercise — read enough of each to classify it correctly; filenames lie.

Record:
- Total test file count, and the counts per directory/suite.
- The **shared base class / fixture / helper layer**: what it seeds, what builder
  helpers it exposes, and which tests depend on it. This is load-bearing —
  future tests must not break it.
- **Fixture strategy**: factories, builders, hand-rolled helpers, fixture files.
  Note which models/entities have factories and which do not.
- **Authorization/permission model as tests see it**: how a test grants an actor
  a capability. Quote the real mechanism (config keys, seeders, roles, scopes).
- **Known-failing or skipped tests**, if you can determine them cheaply.

Optionally run the suite once if it is fast and safe (no network, no real DB, no
migrations against a live database). If you run it, report the real numbers and
the exact command. If running it is risky or slow, **do not run it** — say the
baseline is unmeasured and make measuring it plan item #1.

---

## Step 3 — Surface inventory: what *could* be tested

Build the denominator. Depending on stack, enumerate:

- **Routes/endpoints** (route files, controller annotations, router config) —
  the count and the modules they cluster into.
- **Controllers / handlers / resolvers**.
- **Service / domain / use-case classes** — the count, and which ones hold
  non-trivial logic (money, scoring, eligibility, scheduling, state machines,
  fan-out, permission resolution). These are the unit-test priorities.
- **Background jobs, scheduled tasks, queue consumers, webhooks, CLI commands**.
- **Outbound integrations** (mail, SMS, push, payment, third-party APIs) —
  each one needs a faked-transport assertion, never a live call.
- **Models/entities** and any global scopes or lifecycle hooks that silently
  change query results.
- **Migrations** that changed behavior recently (`git log` on the migration dir)
  — recent schema churn is where regressions hide.

---

## Step 4 — Gap analysis

Cross the Step 3 surface against the Step 2 baseline and produce two things:

1. **A coverage table** — area → existing test files (real paths).
2. **A zero-coverage list** — modules and services with *no* automated test at
   all, ordered by risk. Rank by: blast radius if broken × how often it changes
   (`git log --format=%ad --date=short -- <path> | head` gives you churn) ×
   whether it guards money, access, or data integrity.

Call out explicitly, if true:
- Auth/authorization paths with no negative tests (only happy paths).
- Destructive actions with no audit/logging assertion.
- Anything time-dependent with no clock control.
- Anything localized with tests asserting literal display strings.

---

## Step 5 — Turn the repo's rules into executable guards

Read `CLAUDE.md` / `AGENTS.md` / `CONTRIBUTING.md` / `.cursorrules` / ADRs. For
each **hard rule** that is mechanically checkable, propose a guard test that
fails CI when the rule is violated. This is the highest-leverage part of the
plan — write it as a table of `rule → guard test → how it detects a violation`.

Typical checkable shapes:
- A grep-based test that fails on a forbidden pattern (hardcoded role names,
  float arithmetic on money, raw queries bypassing a scope, a banned import).
- A structural test enumerating a category of things and asserting each satisfies
  an invariant (every tenant-scoped model uses the scoping trait; every new table
  has the required column; every API route is auth-guarded).
- A parity test across parallel resources (every `en` translation key exists in
  every other locale; every migration has a matching rollback).
- An escape-hatch audit: every call site of a documented bypass has a justifying
  comment adjacent to it.

Rules that are *not* mechanically checkable get asserted per-flow inside the
workstreams instead — say which workstream carries them.

---

## Step 6 — Conventions for new tests

Write the house style a contributor needs in order to add a test that fits.
Derive each convention from what the repo already does, not from your taste.
Cover at minimum:

- Which base class/fixture to extend, and when to extend the bare one instead.
- Test naming pattern (copy the dominant existing one).
- **The authorization matrix pattern**: for every protected route, assert
  (a) actor with the capability succeeds, (b) actor without it is denied with the
  right status, (c) an unauthenticated actor is rejected/redirected. Drive
  permissions through the real mechanism — never assert on role *names* if the
  repo forbids that.
- Localization: assert on translation keys, not rendered strings; at least one
  test per module that flips locale.
- Notifications/mail: fake the transport, assert both the persisted record and
  the send, and assert **absence** on no-op paths.
- Destructive paths: assert the audit/log record.
- Determinism: freeze the clock for anything date-sensitive; no network; no
  reliance on test ordering or leftover state.
- Fixtures: how to add a missing factory without breaking existing helpers.

---

## Step 7 — Workstreams

Decompose the work into **numbered workstreams (WS-1, WS-2, …), ordered by
risk**, not by codebase layout. For each workstream give:

- **Under test**: the real controllers/services/models it covers (real paths).
- **Test files to create**: proposed paths that follow the repo's existing
  layout convention.
- **Key assertions**: the specific behaviors — happy path, the authorization
  matrix, the primary validation failures, and the one nasty edge case that
  makes the workstream worth doing (timer expiry, idempotent re-submit,
  partial failure, boundary of a policy window).

Put areas with **active in-flight changes** first — code being edited right now
has the highest regression risk. Check `git status` and recent branches to find
them. Then zero-coverage high-risk modules. Then the service-layer unit tests.

If the repo's rules doc defines phases and forbids building ahead, add a
forward-looking workstream **marked as scaffold-only**, stating the trigger
condition that unblocks it and the one guard test worth committing now so the
intent is version-controlled.

---

## Step 8 — Execution, pipelines and the CI gate

Describe how the plan is actually run:

- Local invocation, including any interpreter/version pinning the repo requires.
- How to run one suite or one module in isolation.
- **Which suites gate a PR and which are advisory.** Slow suites (load, E2E,
  browser) stay out of the PR gate.
- The gap between what CI runs today and what this plan needs it to run —
  as a concrete diff to the CI config, not a wish.
- If suites should be split into named, independently-runnable pipelines,
  propose the split as a table: pipeline → path → what it covers.
- If there are pre-existing failures outside the gate, list them with their
  cause and whether they are stale-schema, assertion drift, or real bugs — so
  they can graduate into the gate later instead of being ignored forever.

---

## Step 9 — Phasing and definition of done

- **Phasing**: 3–5 phases, each a coherent shippable chunk, ordered so the
  highest-regression-risk surface is netted first. Every phase names its
  workstreams. Gate any phase whose prerequisite hasn't landed.
- **Definition of done, per test-writing PR**: a short checklist a reviewer can
  actually apply — new tests green, conventions from Step 6 honored, guards not
  weakened, shared helpers intact, no network, no wall-clock dependence.

---

## Step 10 — Write the document

Write to the resolved output path using this skeleton. Drop any section that
genuinely does not apply to this repo rather than padding it.

```markdown
# Automated Test Suite Plan — <Project Name>

**Status:** Living document. **Owner:** <team>. **Last updated:** <YYYY-MM-DD>.

<One paragraph: what this plan covers, what is explicitly out of scope
(manual QA/UAT, E2E, perf), and any repo rule that constrains it.>

---

## 1. Current state (baseline)
<Runner, suites, DB-under-test, env, shared base case, fixtures, permission model.>
### 1.1 What is already covered
<Table: area → real test file paths.>
### 1.2 Coverage gaps
<Zero-coverage modules and services, risk-ordered.>

## 2. Goals & non-goals

## 3. Conventions for new tests

## 4. Workstreams
### WS-1 — <highest risk>
…

## 5. Cross-cutting invariant tests (CI guards)
<Table: rule → guard test.>

## 6. Execution & CI

## 7. Phasing

## 8. Definition of done (per test-writing PR)
```

**Voice:** terse, declarative, second-person imperative for instructions. Tables
for anything enumerable. Bold the load-bearing constraint in a paragraph. No
hedging, no "consider possibly", no motivational filler about why testing matters.

### 10b — `--pipelines` companion doc

Only with the flag. Produce `testing-pipeline.md` beside the plan, cross-linked
from it, covering: the named pipelines and what each contains; the exact CI job
layout (which job blocks deploy, which is advisory); how the runner is forced
onto a safe environment so it can never touch a real database; any in-app or
dashboard surface that runs/reports the suites; and a handoff list of
pre-existing failures with causes.

---

## Final report to the user

After writing, report in chat — do not re-paste the document:

1. Output path(s) written.
2. The three numbers that matter: test files found, untested high-risk modules,
   proposed workstreams.
3. The single highest-risk gap you found, in one sentence.
4. Anything you could **not** determine and why (suite not run, CI not readable,
   ambiguous module boundaries) — so the gaps in the plan are visible rather
   than papered over.

Do not commit or push unless the user asks.
