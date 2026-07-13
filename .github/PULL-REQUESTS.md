# Creating pull requests

The fastest way to open a PR is the GitHub CLI (`gh`). It is a one-time install; after
that, opening a PR is a single command from the branch.

## 1. Install `gh` (one time)

**Windows** (this project's dev box) — pick whichever package manager you have:

```powershell
winget install --id GitHub.cli        # winget (built into Windows 11)
# or
scoop install gh                      # scoop
# or
choco install gh                      # chocolatey
```

**macOS:** `brew install gh`
**Linux (Debian/Ubuntu, e.g. the VPS):** `sudo apt update && sudo apt install gh`

Full instructions: https://github.com/cli/cli#installation

## 2. Authenticate (one time)

```bash
gh auth login
```

Choose **GitHub.com** → **HTTPS** → **Login with a web browser**, and paste the one-time
code. This must be done in an interactive terminal (it cannot run in CI or an automated
agent session).

## 3. Open a PR from your branch

```bash
# From the feature branch, targeting main:
gh pr create --base main --head "$(git branch --show-current)" \
  --title "Short title" --body "What & why"

# Or let gh prompt you interactively:
gh pr create --web        # opens the PR form in your browser, pre-filled
```

Useful follow-ups:

```bash
gh pr view --web          # open the current branch's PR in the browser
gh pr checks              # watch CI status for the PR
gh pr merge --squash      # merge when green (this repo auto-deploys main to production)
```

## Fallback (no `gh`)

Push the branch, then open the compare page — it pre-fills the diff and PR form:

```
https://github.com/RobsGeorge/AvaPachomius-Khoddam/compare/main...<your-branch>?expand=1
```

## Notes

- Pushing a branch does **not** run CI. CI (`.github/workflows/ci.yml`) runs on
  **pull request**, so open the PR to trigger the gated pipelines.
- Merging to `main` triggers the production deploy (`.github/workflows/deploy.yml`).
  Only merge once the CI gate — including the MySQL migration check — is green.
- Automated agents (e.g. Claude Code running non-interactively) cannot run the `gh`
  OAuth flow, so they will hand you the compare link instead of creating the PR.
