# Use cases — Live Quiz

Personas: **Student** (play), **Instructor** (host). Controllers: `LiveQuizController`,
`LiveQuizBuilderController`, `LiveQuizHostController`, `LiveQuizPlayController`; services
`LiveQuizSessionService`, `LiveJoinCodeService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-LQ-01 | Instructor | Build a live quiz (questions/options) | — | `live_quiz.manage` |
| UC-LQ-02 | Instructor | Host a session → join code generated → open questions | End session ends play for all | `live_quiz.host` |
| UC-LQ-03 | Student | Join via code → answer questions → live scoring | Invalid code → refused; late join handled | `live_quiz.play` |
| UC-LQ-04 | Instructor | Advance questions; end session → results | — | `live_quiz.host` |

**Coverage:** `LiveQuizHostEndTest`; join/play/scoring `🔲 planned`. Hosting gated in `AuthorizationMatrixTest`.
Accessibility note: timed live interaction needs SR announcements + `prefers-reduced-motion` (a11y audit).
