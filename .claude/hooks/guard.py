#!/usr/bin/env python3
"""Claude Code PreToolUse guard for outward-facing commands.

Runs before every Bash call (see .claude/settings.json and AGENTS.md section 3,
Guardrails). It reads the hook's JSON on stdin and answers with a permission
decision:

- deny (always blocked; the maintainer runs these himself): SVN writes to the
  repository (commit, import, and remote copy/mkdir/delete/move) and force
  pushes.
- ask (Claude Code stops and the maintainer approves at the prompt): pushing,
  merging or deleting main; creating, deleting or pushing tags; GitHub
  releases; deploy scripts and remote shells or copies (ssh, scp, rsync to a
  host).

Anything else gets no decision and goes through the normal permission rules.
It parses the command itself (separators, subshells, `bash -c`, wrappers,
environment assignments, `git -C` / `-c` and other global options), because
permission rules only match the literal command text. If the guard cannot
understand a command or fails, it asks instead of letting the call through.

Standard library only; tested by .claude/hooks/test_guard.py.
"""

from __future__ import annotations

import json
import os
import re
import shlex
import subprocess
import sys

PROTECTED_BRANCH = "main"

SEPARATORS = {"&&", "||", ";", "|", "|&", "&", "\n", "(", ")", "{", "}", "!"}
WRAPPERS = {"timeout", "time", "nice", "nohup", "stdbuf", "command", "builtin", "noglob", "exec", "xargs", "sudo", "env", "watch", "caffeinate"}
SHELLS = {"sh", "bash", "zsh", "dash", "ksh", "fish"}
GIT_GLOBAL_WITH_VALUE = {"-C", "-c", "--git-dir", "--work-tree", "--namespace", "--exec-path", "--config-env"}
SVN_REMOTE_WRITE = {"copy", "cp", "mkdir", "delete", "del", "remove", "rm", "move", "mv", "rename", "ren"}
SVN_ALWAYS_WRITE = {"commit", "ci", "import"}
REMOTE_SHELL = {"ssh", "scp", "sftp"}
URL_RE = re.compile(r"^(https?|svn|svn\+ssh)://", re.I)
SHELL_KEYWORDS = {"do", "then", "else", "elif", "if", "while", "until", "in"}
INTERPRETER_CODE_FLAGS = {"python": {"-c"}, "python3": {"-c"}, "node": {"-e", "-p", "--eval", "--print"}, "php": {"-r"}, "perl": {"-e", "-E"}, "ruby": {"-e"}, "osascript": {"-e"}}
OUTWARD_RE = re.compile(r"(svn\W+(commit|ci|import)|svnmucc|git\W.*push|\bgit\W+tag\b|gh\W+(release|pr\W+merge|api)|deploy|\bssh\b|\bscp\b|rsync)", re.I)


class Decision(Exception):
    def __init__(self, kind: str, reason: str) -> None:
        super().__init__(reason)
        self.kind = kind
        self.reason = reason


def deny(reason: str) -> None:
    raise Decision("deny", reason)


def ask(reason: str) -> None:
    raise Decision("ask", reason)


# --- splitting --------------------------------------------------------------

def substitutions(text: str) -> list[str]:
    """The bodies of $( ... ) and backtick substitutions, outermost first."""
    found = []
    i = 0
    while i < len(text):
        if text.startswith("$(", i):
            depth, j = 1, i + 2
            while j < len(text) and depth:
                if text.startswith("$(", j):
                    depth += 1
                    j += 2
                    continue
                if text[j] == ")":
                    depth -= 1
                j += 1
            found.append(text[i + 2 : j - 1])
            i = j
        elif text[i] == "`":
            j = text.find("`", i + 1)
            if j == -1:
                break
            found.append(text[i + 1 : j])
            i = j + 1
        else:
            i += 1
    return found


def tokenize(text: str) -> list[str]:
    lexer = shlex.shlex(text, posix=True, punctuation_chars=";&|()<>")
    lexer.whitespace_split = True
    lexer.commenters = "#"
    lexer.whitespace = " \t\r"
    tokens = []
    for tok in lexer:
        tokens.append(tok)
    # shlex keeps newlines inside whitespace-split tokens only when quoted; split
    # remaining bare newlines into separators.
    out = []
    for tok in tokens:
        if "\n" in tok and not tok.strip("\n"):
            out.append("\n")
        else:
            out.append(tok)
    return out


def simple_commands(text: str) -> list[list[str]]:
    """Every simple command in a shell string, including nested ones."""
    commands: list[list[str]] = []
    for inner in substitutions(text):
        commands.extend(simple_commands(inner))
    tokens = tokenize(text.replace("\n", " ; "))
    current: list[str] = []
    for tok in tokens:
        if tok in SEPARATORS or set(tok) <= set(";&|()"):
            if current:
                commands.append(current)
            current = []
        elif tok in {">", ">>", "<", "2>", ">&", "<<"}:
            current.append(tok)
        else:
            current.append(tok)
    if current:
        commands.append(current)
    expanded: list[list[str]] = []
    for cmd in commands:
        expanded.extend(unwrap(cmd))
    return expanded


def unwrap(cmd: list[str]) -> list[list[str]]:
    """Strip environment assignments and wrappers; descend into `sh -c`."""
    args = list(cmd)
    while args:
        head = os.path.basename(args[0])
        if re.match(r"^[A-Za-z_][A-Za-z0-9_]*=", args[0]):
            args = args[1:]
            continue
        if head in WRAPPERS:
            args = args[1:]
            # Drop the wrapper's own options and values (timeout 30, env -u X, nice -n 5).
            while args and (args[0].startswith("-") or re.match(r"^\d+[smhd]?$", args[0]) or re.match(r"^[A-Za-z_][A-Za-z0-9_]*=", args[0])):
                opt = args.pop(0)
                if opt in {"-u", "-n", "-s", "-k", "-i", "-o", "-e", "--signal", "--kill-after", "--unset"} and args:
                    args.pop(0)
            continue
        if head in SHELL_KEYWORDS:
            args = args[1:]
            continue
        if head in {"for", "select", "case"}:
            return []
        if head in SHELLS:
            for i, a in enumerate(args[1:], 1):
                if a == "-c" or (a.startswith("-") and not a.startswith("--") and "c" in a[1:]):
                    if i + 1 < len(args):
                        return simple_commands(args[i + 1])
            # `bash script.sh args`: the script is the program that runs.
            script = [a for a in args[1:] if not a.startswith("-")]
            if not script:
                ask(f"`{head}` reads its commands from input the guard can't see.")
            return [script]
        if head == "eval":
            return simple_commands(" ".join(args[1:]))
        break
    return [args] if args else []


# --- git -------------------------------------------------------------------

def git_parts(args: list[str]) -> tuple[list[str], str | None, list[str]]:
    """Global options, the subcommand and its arguments."""
    rest = args[1:]
    globals_: list[str] = []
    while rest and rest[0].startswith("-"):
        opt = rest.pop(0)
        globals_.append(opt)
        if opt in GIT_GLOBAL_WITH_VALUE and rest:
            globals_.append(rest.pop(0))
    if not rest:
        return globals_, None, []
    return globals_, rest[0], rest[1:]


def git_dir_option(globals_: list[str], cwd: str) -> str:
    path = cwd
    for i, opt in enumerate(globals_):
        if opt == "-C" and i + 1 < len(globals_):
            path = os.path.join(path, globals_[i + 1])
    return path


def current_branch(path: str) -> str | None:
    try:
        out = subprocess.run(
            ["git", "-C", path, "rev-parse", "--abbrev-ref", "HEAD"],
            capture_output=True, text=True, timeout=5, check=False,
        )
    except (OSError, subprocess.SubprocessError):
        return None
    if out.returncode != 0:
        return None
    return out.stdout.strip() or None


def is_protected_ref(ref: str) -> bool:
    ref = ref.lstrip("+")
    dst = ref.split(":", 1)[1] if ":" in ref else ref
    dst = dst.removeprefix("refs/heads/")
    return dst == PROTECTED_BRANCH


def check_git(args: list[str], cwd: str) -> None:
    globals_, sub, rest = git_parts(args)
    if sub is None:
        return
    for i, g in enumerate(globals_):
        if g == "-c" and i + 1 < len(globals_) and globals_[i + 1].lower().startswith("alias."):
            ask("A git alias is defined on the command line; the guard can't see what it runs.")
    if sub == "push":
        check_git_push(rest, git_dir_option(globals_, cwd))
    elif sub == "tag":
        listing = {"-l", "--list", "-v", "--verify", "--contains", "--no-contains", "--merged", "--no-merged", "--points-at", "-n"}
        if rest and not any(a in listing or a.startswith("-n") or a.startswith("--sort") or a.startswith("--format") for a in rest):
            ask("Creating or deleting a git tag needs the maintainer's yes.")
    elif sub in {"merge", "rebase", "cherry-pick", "reset", "commit", "am", "revert"}:
        branch = current_branch(git_dir_option(globals_, cwd))
        if branch == PROTECTED_BRANCH and sub in {"merge", "rebase", "reset", "commit", "cherry-pick", "am", "revert"}:
            ask(f"Changing the local {PROTECTED_BRANCH} branch needs the maintainer's yes.")
    elif sub in {"branch"}:
        if any(a in {"-D", "-d", "--delete", "-m", "-M", "--move", "-f", "--force"} for a in rest) and PROTECTED_BRANCH in rest:
            ask(f"Deleting, moving or forcing {PROTECTED_BRANCH} needs the maintainer's yes.")
    elif sub == "update-ref" and any(PROTECTED_BRANCH in a or a.startswith("refs/tags/") for a in rest):
        ask("Changing main or a tag directly needs the maintainer's yes.")


def check_git_push(rest: list[str], path: str) -> None:
    opts = [a for a in rest if a.startswith("-")]
    positional = []
    skip = False
    for a in rest:
        if skip:
            skip = False
            continue
        if a in {"-o", "--push-option", "--repo", "--receive-pack", "--exec"}:
            skip = True
            continue
        if not a.startswith("-"):
            positional.append(a)
    force = {"-f", "--force", "--force-with-lease", "--force-if-includes", "--mirror"}
    if any(o in force or o.startswith("--force-with-lease=") for o in opts) or any(p.startswith("+") for p in positional[1:]):
        deny("Force pushes are never run by Claude Code (AGENTS.md section 3, Guardrails).")
    if any(o in {"--tags", "--follow-tags", "--all", "--prune"} for o in opts):
        ask("Pushing tags, every branch or pruning remote branches needs the maintainer's yes.")
    refspecs = positional[1:]
    if any(o in {"-d", "--delete"} for o in opts) and refspecs:
        if any(is_protected_ref(r) for r in refspecs) or any(r.startswith("refs/tags/") or r.startswith("v") for r in refspecs):
            ask("Deleting main or a tag on the remote needs the maintainer's yes.")
    for r in refspecs:
        if "$" in r or "`" in r:
            ask("The push target is a variable the guard can't resolve; it needs the maintainer's yes.")
        if is_protected_ref(r):
            ask(f"Pushing to {PROTECTED_BRANCH} needs the maintainer's yes (release step).")
        dst = r.split(":", 1)[-1]
        if dst.startswith("refs/tags/") or re.match(r"^v\d", dst):
            ask("Pushing a tag needs the maintainer's yes (release step).")
        if r.startswith(":"):
            ask("Deleting a remote ref needs the maintainer's yes.")
        if r in {"HEAD"} and current_branch(path) == PROTECTED_BRANCH:
            ask(f"Pushing {PROTECTED_BRANCH} needs the maintainer's yes.")
    if not refspecs and current_branch(path) in {PROTECTED_BRANCH, None}:
        ask(f"A push without a branch name from {PROTECTED_BRANCH} (or an unknown branch) needs the maintainer's yes.")


# --- gh --------------------------------------------------------------------

def pr_base(number: str | None, cwd: str) -> str | None:
    cmd = ["gh", "pr", "view", "--json", "baseRefName", "-q", ".baseRefName"]
    if number:
        cmd.insert(3, number)
    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=15, cwd=cwd, check=False)
    except (OSError, subprocess.SubprocessError):
        return None
    if out.returncode != 0:
        return None
    return out.stdout.strip() or None


def check_gh(args: list[str], cwd: str) -> None:
    rest = args[1:]
    while rest and rest[0] in {"-R", "--repo"}:
        rest = rest[2:]
    if len(rest) < 1:
        return
    group, sub = rest[0], (rest[1] if len(rest) > 1 else "")
    if group == "release" and sub not in {"list", "view", "download", ""}:
        ask("Creating, editing or deleting a GitHub release needs the maintainer's yes.")
    if group == "pr" and sub == "merge":
        target = next((a for a in rest[2:] if not a.startswith("-")), None)
        base = pr_base(target, cwd)
        if base != "develop":
            ask(f"Merging a PR into {base or 'an unknown base'} needs the maintainer's yes (only develop is pre-approved).")
    if group == "pr" and sub in {"create", "edit"}:
        for i, a in enumerate(rest):
            if a in {"-B", "--base"} and i + 1 < len(rest) and rest[i + 1] == PROTECTED_BRANCH:
                ask(f"A PR into {PROTECTED_BRANCH} is the release PR; it needs the maintainer's yes.")
            if a.startswith("--base=") and a.split("=", 1)[1] == PROTECTED_BRANCH:
                ask(f"A PR into {PROTECTED_BRANCH} is the release PR; it needs the maintainer's yes.")
    if group == "api":
        joined = " ".join(rest[1:])
        method_write = re.search(r"(-X|--method)\s*(PUT|POST|PATCH|DELETE)", joined, re.I) or any(a in {"-f", "-F", "--field", "--raw-field", "--input"} for a in rest)
        if method_write:
            path = ""
            skip = False
            for a in rest[1:]:
                if skip:
                    skip = False
                    continue
                if a in {"-X", "--method", "-f", "-F", "--field", "--raw-field", "--input", "-H", "--header", "-q", "--jq", "-t", "--template", "--hostname"}:
                    skip = True
                    continue
                if not a.startswith("-"):
                    path = a
                    break
            if re.search(r"/pulls/(\d+)/merge", path):
                number = re.search(r"/pulls/(\d+)/merge", path).group(1)
                if pr_base(number, cwd) != "develop":
                    ask("Merging a PR outside develop through the API needs the maintainer's yes.")
            elif re.search(r"/(releases|git/refs|git/tags|merges)\b", path) or "tags" in path.split("/"):
                ask("Writing releases, refs, tags or merges through the API needs the maintainer's yes.")
            elif re.search(r"/(branches/[^/]+/protection|rulesets)\b", path):
                ask("Changing branch protection needs the maintainer's yes.")
            elif re.search(r"/(pulls/\d+)$", path) and re.search(r"base\W+main", joined):
                ask(f"Retargeting a PR to {PROTECTED_BRANCH} needs the maintainer's yes.")


# --- svn, deploys, remote shells --------------------------------------------

SVN_VALUE_OPTIONS = {"--username", "--password", "--password-from-stdin", "--config-dir", "--config-option", "-m", "--message", "-F", "--file", "-r", "--revision", "--depth", "--encoding", "--with-revprop", "--editor-cmd", "--cl", "--changelist", "-c", "--change", "-l", "--limit"}


def check_svn(args: list[str]) -> None:
    rest = []
    skip = False
    for a in args[1:]:
        if skip:
            skip = False
            continue
        if a in SVN_VALUE_OPTIONS:
            skip = True
            continue
        if a.startswith("-"):
            continue
        rest.append(a)
    if not rest:
        return
    sub = rest[0]
    if sub in SVN_ALWAYS_WRITE:
        deny("SVN commits to WordPress.org are run by the maintainer, never by Claude Code.")
    if sub in SVN_REMOTE_WRITE and any(URL_RE.match(a) for a in rest[1:]):
        deny("SVN writes to the repository are run by the maintainer, never by Claude Code.")
    if sub in {"propset", "ps", "propdel", "pd", "propedit", "pe"} and any(a == "--revprop" for a in args):
        deny("Changing SVN revision properties is run by the maintainer, never by Claude Code.")


def check_program(args: list[str]) -> None:
    head = os.path.basename(args[0])
    base = re.sub(r"[\d.]+$", "", head)
    flags = INTERPRETER_CODE_FLAGS.get(head) or INTERPRETER_CODE_FLAGS.get(base)
    if flags:
        for i, a in enumerate(args[1:], 1):
            if a in flags and i + 1 < len(args) and OUTWARD_RE.search(args[i + 1]):
                ask(f"Inline {head} code runs an outward-facing command; it needs the maintainer's yes.")
    if head in {"svnmucc", "svnrdump"}:
        deny("SVN writes to the repository are run by the maintainer, never by Claude Code.")
    if re.search(r"deploy", head, re.I) and (head.endswith(".sh") or "/" in args[0] or head.startswith("deploy")):
        ask("Deploy scripts change production; they need the maintainer's yes.")
    if head in REMOTE_SHELL:
        ask(f"`{head}` reaches another machine; it needs the maintainer's yes.")
    if head == "rsync" and any(re.match(r"^[^/\s]+@?[^/\s]*:", a) and not a.startswith("-") for a in args[1:]):
        ask("rsync to another machine needs the maintainer's yes.")
    if head in {"wp"} and any(a.startswith("@") or a.startswith("--ssh") for a in args[1:]):
        ask("WP-CLI against a remote site needs the maintainer's yes.")


# --- entry point -------------------------------------------------------------

def evaluate(command: str, cwd: str) -> tuple[str | None, str]:
    try:
        for cmd in simple_commands(command):
            if not cmd:
                continue
            head = os.path.basename(cmd[0])
            if head == "git":
                check_git(cmd, cwd)
            elif head == "gh":
                check_gh(cmd, cwd)
            elif head == "svn":
                check_svn(cmd)
            check_program(cmd)
    except Decision as d:
        return d.kind, d.reason
    except ValueError:
        # Unbalanced quotes and the like: look at the raw text instead of guessing.
        if re.search(r"\b(svn\s+(commit|ci|import)|svnmucc|git\b.*\bpush\b|gh\s+(release|pr\s+merge)|deploy|\bssh\b|\bscp\b)", command):
            return "ask", "The guard could not parse this command and it mentions an outward-facing action."
    return None, ""


def main() -> int:
    try:
        payload = json.load(sys.stdin)
        if payload.get("tool_name") != "Bash":
            return 0
        command = (payload.get("tool_input") or {}).get("command") or ""
        cwd = payload.get("cwd") or os.getcwd()
        kind, reason = evaluate(command, cwd)
    except Exception as exc:  # noqa: BLE001 - a broken guard must not wave commands through.
        kind, reason = "ask", f"The guard failed ({exc.__class__.__name__}); asking instead."
    if kind:
        print(json.dumps({
            "hookSpecificOutput": {
                "hookEventName": "PreToolUse",
                "permissionDecision": kind,
                "permissionDecisionReason": f"Zero Spam guardrail: {reason}",
            }
        }))
    return 0


if __name__ == "__main__":
    sys.exit(main())
