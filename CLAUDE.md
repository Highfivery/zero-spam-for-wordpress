# Claude Code instructions

@AGENTS.md

Claude-specific notes:

- Work as the orchestrator when the task has independent parts: fan out to
  subagents or sessions, verify their results yourself, and report what was
  verified versus what was not.
- Before opening a PR, run `composer check` and paste its summary into the PR
  body (the pre-push hook enforces it on push as well).
- Memory for this project is kept outside the repo; everything a future
  session must know about the codebase belongs in `AGENTS.md` instead.
- Superpowers is enabled for this repository (`.claude/settings.json`) and
  the reviewer agents live in `.claude/agents/`. How they fit this
  project's rules is in `AGENTS.md` section 3, Agent workflow; where they
  differ, `AGENTS.md` wins.
