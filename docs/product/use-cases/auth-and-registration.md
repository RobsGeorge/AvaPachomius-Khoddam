# Use cases — Auth & Registration

Personas: **Guest**, **Applicant**. Entry routes in `routes/web.php` (login/register/otp/password),
controllers `Auth\LoginController`, `Auth\RegisterController`, `OTPController`,
`Auth\ForgotPasswordController`, `Auth\NewPasswordController`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-AUTH-01 | Guest | Log in with email + password → redirected to post-login route (course/service context or dashboard) | Wrong password → back with `credentials_mismatch`; unverified/incomplete → logged out with `account_not_verified` | Public |
| UC-AUTH-02 | Guest | Register: submit Arabic names + national_id(14) + mobile(10) + email + DOB → user created (unverified) → OTP emailed → redirected to OTP verify | Non-Arabic name / bad national_id → validation errors; duplicate email/mobile/national_id → field error | Public |
| UC-AUTH-03 | Guest | Verify OTP: submit 6-digit code + user_id → OTP consumed → redirected to set-password | Wrong/expired code → back with `otp` error, code preserved; already completed → redirect to login | Public; code bound to user_id |
| UC-AUTH-04 | Guest | Resend OTP → new code emailed | Rate-limited resend | Public |
| UC-AUTH-05 | Guest | Set password after OTP → registration completed | Missing OTP session → redirect | Session-bound to verified user |
| UC-AUTH-06 | Guest | Request password reset (`password.email`) → `ResetPasswordMail` sent if email exists | Unknown email → no mail, generic status; throttled | Public |
| UC-AUTH-07 | Guest | Reset password with valid token (`password.update`) → password changed | Invalid/expired token → error | Token-bound |
| UC-AUTH-08 | Applicant | View application status (`application.status`) | — | `auth`; own application |
| UC-AUTH-09 | Applicant | Edit/resubmit application after corrections requested | Submit while `approved` → no-op/redirect | `auth`; own application |
| UC-AUTH-10 | Applicant | Blocked from portal content until approved → redirected to status | — | `RequireApprovedApplication` |
| UC-AUTH-11 | Any auth user | Log out (`logout`, GET or POST) → session invalidated → redirect | — | `auth` |
| UC-AUTH-12 | Any user | Switch locale (`locale.switch`) ar⇄en; all strings localized, RTL/LTR flips | — | Public |

**Coverage:** `tests/Feature/Auth/AuthenticationTest`, `OtpVerificationTest`, `PasswordResetTest`,
`RegistrationTest`; applicant gating in `tests/Feature/UseCases/Journeys/PersonaLandingAccessTest`
and `RegistrationApplicationReviewTest`.
