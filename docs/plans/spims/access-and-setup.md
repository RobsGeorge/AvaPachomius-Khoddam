# Access required to execute this plan in spims-edu (and a mobile repo)

What blocks a Cursor agent from working directly in the SPIMS repositories, and the smallest set of
actions that unblocks it.

## 1. Why the push failed

The agent authenticates with a GitHub **App installation token** (`ghs_…`), not a user token — a
call to `/user` returns `Resource not accessible by integration`. That installation currently has
access to exactly one repository:

```
$ gh api /installation/repositories -q '.total_count'
1
$ gh api /installation/repositories -q '.repositories[].full_name'
RobsGeorge/AvaPachomius-Khoddam
```

Hence:

```
remote: Permission to RobsGeorge/spims-edu.git denied to cursor[bot].
fatal: unable to access 'https://github.com/RobsGeorge/spims-edu.git/': 403
```

Reading spims-edu works only because it is public. Nothing about the token is broken; the repository
simply is not in the installation's scope.

## 2. What is needed — two independent things

Granting repository access and having an environment to boot are separate. Both are required.

### 2.1 Repository access (blocks pushing)

`RobsGeorge` is a personal account, so this is the personal installation page:

**<https://github.com/settings/installations>** → **Cursor** → *Repository access* →
add `RobsGeorge/spims-edu` → **Save**.

Keep "Only select repositories" and add the one repo; there is no need to grant all-repository
access.

> Installation tokens are minted per agent run. Granting access while an agent is running does not
> widen that agent's existing token — start a fresh agent afterwards.

### 2.2 An environment bound to spims-edu (blocks running an agent there)

A cloud agent boots from an environment tied to specific repositories. This run's environment lists
only `github.com/RobsGeorge/AvaPachomius-Khoddam`, so even with the token widened there is nowhere
for a spims-edu agent to start. Two options:

| Option | When to prefer it |
|---|---|
| Commit `.cursor/environment.json` into spims-edu | **Recommended.** Versioned with the code, follows branches and PRs, and spims-edu already has a defined CI to mirror |
| Create a dashboard environment for spims-edu | Useful if setup needs secrets or an interactive snapshot |

If one agent should ever touch backend and mobile together — for example keeping the API contract
and the client in sync — use `repositoryDependencies` in `environment.json` to bring the second
repository into the token's scope, rather than creating two disconnected environments.

## 3. What the spims-edu environment needs

Taken from what actually worked when the full suite was run on this machine, plus the extension list
in `.github/workflows/ci.yml`, which is the authoritative source.

**Toolchain:** PHP **8.2** and Composer, with extensions
`mbstring, xml, ctype, curl, json, bcmath, gd, sqlite3, pdo_sqlite, pgsql, pdo_pgsql`.

**Install step** — this exact sequence produced a green suite here:

```bash
composer install --no-interaction --prefer-dist --no-progress
cp -n .env.example .env
php artisan key:generate --force
```

**PostgreSQL is optional.** `phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`,
so the whole suite runs with no database server. Postgres 16 is only needed to reproduce CI's second
job (`migrate:fresh --seed` against PostgreSQL). If included, start it in `start`, never `install` —
a daemon launched during install does not survive into a later boot.

**No npm step.** spims-edu has no frontend build (`CLAUDE.md` rule 8).

Rather than hand-writing a Dockerfile blind, the practical path once access is granted is to start an
agent on spims-edu and run the environment setup flow there; it can validate a build instead of
guessing. The facts above are what that flow needs as input.

## 4. What is *not* needed

Worth stating, because it removes work:

- **No secrets.** The full suite passes with none. Mailers degrade to log when `MAIL_MAILER` is
  `log`/`array`, the Gemini client no-ops without `GOOGLE_API_KEY`, and the payment gateways have a
  mock path. The two fixes already delivered were verified with an empty secret store.
- **No production or staging access.** Nothing in the plan deploys. Deployment stays with the
  existing GitHub Actions workflows on `main` and `staging`.
- **No database credentials.** In-memory SQLite, as above.

## 5. The mobile repository does not exist yet

No SPIMS mobile repository is visible: `spims-mobile`, `Spims-Mobile`, and `spims-edu-mobile` all
return 404, as does `AvaPachomius-Khoddam-Mobile` — the sibling repo named in this project's
[`docs/mobile/mvp.md`](../../mobile/mvp.md).

**Caveat:** the installation can see only one repository, so a *private* mobile repo would return 404
in exactly the same way. This cannot distinguish "does not exist" from "exists but not granted." If
one already exists, adding it in step 2.1 makes it visible.

Assuming it does not exist, three things are needed before an agent is useful there:

1. **Create the repository** and grant the Cursor app access to it.
2. **Decide the stack.** The documented precedent is a single **Expo / React Native** codebase
   targeting iOS and Android. Nothing in spims-edu commits to this yet.
3. **Do not start it before the API exists.** The client should follow phase **S1** (API foundation)
   and at least **S6 Wave A**. Building a client against
   [`mobile-api-spec.md`](mobile-api-spec.md) before any endpoint is real means writing to a contract
   that has not been tested against a server, and every mismatch surfaces as rework in two
   repositories at once.

## 6. Recommended order

1. Grant access to `RobsGeorge/spims-edu` (step 2.1). **This is the only thing blocking progress
   today.**
2. Apply the two verified fixes in [`patches/`](patches/) — they are ready, tested, and independent
   of everything else.
3. Stand up the spims-edu environment (step 2.2) and start an agent there.
4. Execute **S0** (resource-scoped authorization) before any API work.
5. Create the mobile repository once S1 and S6 Wave A are real.

Steps 1 and 2 need nothing from the plan and can happen immediately. Everything from step 3 onward
needs an agent that can actually run inside spims-edu.
