# Accessibility audit (WCAG 2.1 AA)

Scope: the server-rendered Blade UI (Bootstrap 5, no SPA build step), RTL-first Arabic + English,
light/dark themes, SweetAlert dialogs, page-entrance animations. Automated server-checkable invariants
are guarded by `tests/Feature/UseCases/Accessibility/RenderedPageA11yTest`; everything else here is a
**manual finding with a recommended fix**, ranked by severity.

## Baseline (already good)
- `layouts/app.blade.php` sets `<html lang dir data-bs-theme>`, `<meta viewport>`, a real `<title>`,
  and a `<main class="app-main">` landmark. Locale toggle flips ar/en + RTL/LTR. Images carry `alt`
  (verified across all pages). Bootstrap gives many components baseline semantics.

## Findings

| ID | Severity | WCAG | Finding | Affected personas | Recommended fix |
|---|---|---|---|---|---|
| A11Y-01 | High | 2.4.1 Bypass Blocks | **No skip-to-content link.** Keyboard/SR users must tab through the whole nav on every page. | keyboard, SR | Add a visually-hidden-until-focus `<a href="#app-main">` as the first focusable element; give `<main id="app-main">`. |
| A11Y-02 | High | 2.3.3 / 2.2.2 | **`prefers-reduced-motion` not honored.** `animate-in` page-entrance animations run for everyone. | vestibular, motion-sensitive | Wrap animations in `@media (prefers-reduced-motion: no-preference)`; disable transforms otherwise. |
| A11Y-03 | High | 4.1.3 Status Messages | **Dynamic content isn't announced.** Notification toasts, live-quiz timers/state, SweetAlert results aren't in ARIA live regions. | SR | Add `aria-live="polite"` (or `assertive` for errors) regions; ensure SweetAlert dialogs get focus and are labelled. |
| A11Y-04 | High | 1.3.1 / 3.3.2 Labels | **Form-label rigor unverified.** Multi-step course/service application forms and admin builders may rely on placeholders. | SR, all | Ensure every control has a `<label for>` or `aria-label`; add an error summary that receives focus on submit. Consider a server-side label-coverage test (see note). |
| A11Y-05 | Medium | 1.4.3 Contrast | **Theme color contrast not verified.** Portal theme colors (light/dark) and badges may fall below 4.5:1. | low-vision | Audit palette pairs with a contrast checker; adjust tokens; verify in both themes. |
| A11Y-06 | Medium | 2.4.6 Headings | **Heading structure inconsistent.** Not all pages have a single `<h1>` / logical order. | SR | Ensure one `<h1>` per page; nest headings without skipping levels. |
| A11Y-07 | Medium | 2.1.1 / 2.4.7 | **Focus visibility & keyboard traps.** Custom controls, dropdowns, modals, QR/check-in flows need verified focus order and visible focus. | keyboard | Audit tab order; ensure `:focus-visible` styles; trap focus in modals and restore on close. |
| A11Y-08 | Medium | 1.3.1 Tables | **Data tables** (rosters, gradebooks, attendance) need `<th scope>`, captions, and responsive semantics when they collapse to cards. | SR, mobile | Add scoped headers + `<caption>`; ensure the card fallback keeps label/value pairs. |
| A11Y-09 | Medium | 1.4.10 Reflow / 2.5.5 | **Mobile: dense tables & tap targets.** Data-dense tables and small controls on small viewports. | mobile | Card layouts for tables; ≥44px tap targets; test at 320px width. |
| A11Y-10 | Low | 3.1.2 Lang of Parts | **Mixed ar/en runs** may not mark inline language changes. | SR (Arabic) | Add `lang`/`dir` on inline foreign-language spans where content mixes scripts. |
| A11Y-11 | Low | 1.1.1 | **Icon-only buttons** (Bootstrap Icons `<i>`) may lack accessible names. | SR | Add `aria-label` / visually-hidden text to icon-only actions. |
| A11Y-12 | Low | 2.2.1 Timing | **Timed exams / live quiz** offer no time-extension accommodation. | disabilities | Provide an accommodation (extra time) hook; warn before timeout with the option to extend. |

## Suggested remediation order
1. **A11Y-01, A11Y-02, A11Y-03** — low-effort, high-impact, global (skip-link, reduced-motion, live regions).
2. **A11Y-04, A11Y-07** — forms & keyboard operability (the interactive core: applications, exams, check-in).
3. **A11Y-05, A11Y-06, A11Y-08** — contrast, headings, tables.
4. **A11Y-09..12** — mobile, inline lang, icon labels, timing accommodations.

## Note — extending automated a11y coverage
`RenderedPageA11yTest` already checks lang/dir/title and image alt server-side. Two more are
cheaply automatable and worth adding once the underlying gaps are fixed:
- **Form-label coverage** (A11Y-04): parse each page's form controls and assert an associated
  label/`aria-label` — add after forms are remediated so it lands green.
- **Skip-link presence** (A11Y-01): assert the first focusable element targets `#app-main` — add
  once the skip-link exists.
Full WCAG conformance (contrast, keyboard, SR) needs a browser tool (axe-core via Playwright/Dusk)
and manual testing with NVDA/VoiceOver — tracked as manual TC-A11Y-03..05.
