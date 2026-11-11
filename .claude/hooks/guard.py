#!/usr/bin/env python3
"""Claude Code PreToolUse guard for outward-facing commands.

Runs before every Bash call (see .claude/settings.json and AGENTS.md section 3,
Guardrails). It reads the hook's JSON on stdin and answers with a permission
decision:

- deny (never run by Claude Code; the maintainer runs these himself): SVN
  writes to a repository and force pushes.
- ask (Claude Code stops and the maintainer approves at the prompt): anything
  that reaches main or develop directly, tags, releases, GitHub settings,
  deploys, other machines, the guardrails themselves or the pre-push gate.
- no decision: everything else, which then goes through the permission rules.

Design: fail closed. A command that uses an outward-capable program must be
understood completely and match a known-safe shape (for example
`git push -u origin fix/x`), or the guard asks. Anything it cannot parse,
resolve or classify asks. The hook entry in settings.json turns a crash or a
missing interpreter into a block (exit 2), and an overall deadline turns slow
lookups into an ask.

Standard library only; tested by .claude/hooks/test_guard.py.
"""

from __future__ import annotations

import json
import os
import re
import shlex
import subprocess
import sys
import time

PROTECTED_BRANCHES = {"main", "develop"}
RELEASE_BRANCH = "main"
INTEGRATION_BRANCH = "develop"
DEADLINE_SECONDS = 20.0
SCRIPT_SCAN_LIMIT = 256 * 1024

_started = time.monotonic()

# Command-like patterns, for code strings, heredocs and scripts.
OUTWARD_RE = re.compile(
    r"\bsvn\s+(commit|ci|import|cp|copy|mv|move|rm|delete|mkdir|propedit|propset)\b|\bsvnmucc\b|"
    r"\bgit\b[^\n;|&]*\b(push|tag|update-ref|svn|config)\b|"
    r"\bgh\s+(release|api|pr\s+merge|repo|secret|workflow|issue\s+(create|comment))\b|"
    r"\bdeploy\b|\bssh\s|\bscp\s|\bsftp\s|\brsync\s|\bwp\s+@|--ssh=|"
    r"\b(system|exec|execSync|spawn|spawnSync|popen|shell_exec|passthru|check_call|check_output|run)\s*\(",
    re.I,
)
OUTWARD_CMD_RE = re.compile(  # without the generic function-call alternative
    r"\bsvn\s+(commit|ci|import)\b|\bsvnmucc\b|\bgit\b[^\n;|&]*\b(push|tag|update-ref|svn)\b|"
    r"\bgh\s+(release|api|pr\s+merge)\b|\bdeploy\b|\bssh\s|\bscp\s|\brsync\s",
    re.I,
)
SHELLS = {"sh", "bash", "zsh", "dash", "ksh", "fish"}
INTERPRETERS = {"python", "python3", "node", "php", "perl", "ruby", "osascript", "deno", "bun"}
CODE_FLAGS = {"-c", "-e", "-E", "-p", "-r", "--eval", "--print"}
REDIRECT_OPS = {">", ">>", "<", ">|", "&>", "&>>", ">&", "<&", "<>", "<<", "<<-"}
SEPARATORS = {"&&", "||", ";", "|", "|&", "&", ";;", "(", ")", "{", "}", "!"}
SHELL_KEYWORDS = {"do", "then", "else", "elif", "if", "while", "until"}
WRAPPERS = {  # wrapper programs and the options of theirs that take a value
    "timeout": {"-s", "--signal", "-k", "--kill-after"},
    "nice": {"-n", "--adjustment"},
    "nohup": set(),
    "stdbuf": {"-i", "-o", "-e"},
    "command": set(),
    "builtin": set(),
    "noglob": set(),
    "exec": {"-a"},
    "time": set(),
    "caffeinate": {"-t", "-w"},
    "env": {"-u", "--unset", "-C", "--chdir"},
    "sudo": {"-u", "-g", "-C", "-D", "-h", "-p", "-U", "-T", "-r", "-t"},
    "watch": {"-n", "--interval"},
    "ionice": {"-c", "-n", "-p"},
    "flock": {"-w", "-E"},
    "setsid": set(),
}
GIT_KNOWN = {
    "add", "am", "apply", "archive", "bisect", "blame", "branch", "bundle", "cat-file", "check-ignore",
    "check-ref-format", "checkout", "cherry", "cherry-pick", "clean", "clone", "commit", "config",
    "count-objects", "describe", "diff", "difftool", "fetch", "for-each-ref", "format-patch", "fsck", "gc",
    "grep", "hash-object", "help", "init", "log", "ls-files", "ls-remote", "ls-tree", "maintenance", "merge",
    "merge-base", "mv", "name-rev", "notes", "pull", "push", "range-diff", "rebase", "reflog", "remote",
    "reset", "restore", "rev-list", "rev-parse", "revert", "rm", "shortlog", "show", "show-branch",
    "show-ref", "sparse-checkout", "stash", "status", "submodule", "svn", "switch", "symbolic-ref", "tag",
    "update-index", "update-ref", "var", "verify-commit", "version", "whatchanged", "worktree",
}
GIT_CHANGES_CURRENT_BRANCH = {"merge", "rebase", "reset", "commit", "cherry-pick", "am", "revert", "pull"}
GIT_RISKY_GLOBALS = {"-c", "--config-env", "--git-dir", "--work-tree", "--namespace", "--exec-path", "--super-prefix"}
SVN_SAFE = {"status", "st", "stat", "diff", "di", "update", "up", "info", "log", "ls", "list", "cat",
            "blame", "praise", "annotate", "ann", "add", "revert", "cleanup", "resolve", "resolved",
            "proplist", "pl", "propget", "pg", "checkout", "co", "export", "help", "changelist", "cl",
            "patch", "switch", "sw", "upgrade", "mergeinfo"}
SVN_LOCAL_IF_NO_URL = {"copy", "cp", "move", "mv", "rename", "ren", "delete", "del", "remove", "rm",
                       "mkdir", "propset", "ps", "propdel", "pd", "propedit", "pe", "lock", "unlock"}
SVN_VALUE_OPTIONS = {"--username", "--password", "--config-dir", "--config-option", "-m", "--message",
                     "-F", "--file", "-r", "--revision", "--depth", "--encoding", "--with-revprop",
                     "--editor-cmd", "--cl", "--changelist", "-c", "--change", "-l", "--limit",
                     "--set-depth", "--accept"}
SVN_URL_RE = re.compile(r"^(\^/|file://|https?://|svn(\+[a-z]+)?://)", re.I)
BRANCH_NAME_RE = re.compile(r"^[A-Za-z0-9._/-]+$")
VERSION_LIKE_RE = re.compile(r"^v?\d+(\.\d+)+([-+][0-9A-Za-z.-]+)?$")
ASSIGNMENT_RE = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*=")
GUARD_PATH_RE = re.compile(r"\.claude/(settings[^/\s'\"]*\.json|hooks(/|\b))")
READ_ONLY_PROGRAMS = {"cat", "less", "more", "head", "tail", "grep", "egrep", "rg", "wc", "diff", "ls",
                      "stat", "file", "jq", "bat", "sha256sum", "shasum", "md5sum", "realpath", "dirname",
                      "basename", "test", "[", "echo", "printf"}


class Decision(Exception):
    def __init__(self, kind: str, reason: str) -> None:
        super().__init__(reason)
        self.kind = kind
        self.reason = reason


def deny(reason: str) -> None:
    raise Decision("deny", reason)


def ask(reason: str) -> None:
    raise Decision("ask", reason)


def check_deadline() -> None:
    if time.monotonic() - _started > DEADLINE_SECONDS:
        ask("The guard ran out of time checking this command.")


def run(cmd: list[str], cwd: str, timeout: float = 8.0) -> subprocess.CompletedProcess | None:
    check_deadline()
    remaining = DEADLINE_SECONDS - (time.monotonic() - _started)
    try:
        return subprocess.run(cmd, capture_output=True, text=True,
                              cwd=cwd if os.path.isdir(cwd) else None,
                              timeout=max(1.0, min(timeout, remaining)), check=False)
    except (OSError, subprocess.SubprocessError):
        return None


# --- normalising and splitting ------------------------------------------------------

HEREDOC_RE = re.compile(r"(?<!<)<<(-?)[ \t]*(['\"]?)([A-Za-z_][A-Za-z0-9_]*)\2")


def strip_heredocs(text: str) -> str:
    """Remove heredoc bodies (data); ask when a heredoc feeds commands to a program."""
    lines = text.split("\n")
    out: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        markers = list(HEREDOC_RE.finditer(line))
        out.append(HEREDOC_RE.sub(" ", line))
        i += 1
        if not markers:
            continue
        words = re.findall(r"[A-Za-z0-9_.+/-]+", line[: markers[0].start()])
        feeds_code = any(os.path.basename(w) in SHELLS | INTERPRETERS | {"ssh", "eval", "xargs", "source"} for w in words)
        for m in markers:
            strip_tabs, word = m.group(1) == "-", m.group(3)
            body: list[str] = []
            terminated = False
            while i < len(lines):
                candidate = lines[i].lstrip("\t") if strip_tabs else lines[i]
                i += 1
                if candidate == word:
                    terminated = True
                    break
                body.append(candidate)
            if not terminated:
                ask("A heredoc is not terminated; the guard can't tell where the command ends.")
            if feeds_code and OUTWARD_RE.search("\n".join(body)):
                ask("A heredoc feeds commands to a program, and they include an outward-facing action.")
    return "\n".join(out)


def normalise(text: str) -> str:
    """Join line continuations, drop comments, turn unquoted newlines into `;`."""
    text = strip_heredocs(text)
    out: list[str] = []
    state = None  # None, "'" or '"'
    prev = " "
    i = 0
    while i < len(text):
        c = text[i]
        if state == "'":
            out.append(c)
            if c == "'":
                state = None
        elif c == "\\" and i + 1 < len(text):
            if text[i + 1] == "\n":
                i += 2
                continue
            out.append(text[i : i + 2])
            prev = text[i + 1]
            i += 2
            continue
        elif state == '"':
            out.append(c)
            if c == '"':
                state = None
        elif c in "'\"":
            state = c
            out.append(c)
        elif c == "#" and (prev.isspace() or prev in ";&|()"):
            while i < len(text) and text[i] != "\n":
                i += 1
            continue
        elif c == "\n":
            out.append(" ; ")
        else:
            out.append(c)
        prev = c
        i += 1
    if state is not None:
        raise ValueError("unterminated quote")
    return "".join(out)


def substitutions(text: str) -> list[str]:
    """Bodies of $(...), <(...), >(...) and backtick substitutions."""
    found: list[str] = []
    i = 0
    in_single = False
    while i < len(text):
        c = text[i]
        if c == "'" and not in_single:
            j = text.find("'", i + 1)
            if j == -1:
                raise ValueError("unterminated quote")
            i = j + 1
            continue
        if text.startswith(("$(", "<(", ">("), i):
            depth, j = 1, i + 2
            while j < len(text) and depth:
                if text[j] == "(":
                    depth += 1
                elif text[j] == ")":
                    depth -= 1
                j += 1
            if depth:
                raise ValueError("unterminated substitution")
            found.append(text[i + 2 : j - 1])
            i = j
            continue
        if c == "`":
            j = text.find("`", i + 1)
            if j == -1:
                raise ValueError("unterminated backtick")
            found.append(text[i + 1 : j])
            i = j + 1
            continue
        i += 1
    return found


def split_commands(text: str) -> list[list[str]]:
    """Simple commands of a normalised shell string, nested substitutions included."""
    commands: list[list[str]] = []
    for inner in substitutions(text):
        commands.extend(split_commands(normalise(inner)))
    lexer = shlex.shlex(text, posix=True, punctuation_chars=";&|()<>")
    lexer.whitespace_split = True
    lexer.commenters = ""
    current: list[str] = []
    skip_target = False
    for tok in lexer:
        if skip_target:
            skip_target = False
            continue
        if tok in SEPARATORS or (tok and set(tok) <= set(";&|()") and tok not in {"<", ">"}):
            if current:
                commands.append(current)
            current = []
            continue
        if tok == "<<<":
            current.append("<<<")
            continue
        if tok in REDIRECT_OPS or (tok and set(tok) <= set("<>&")):
            skip_target = True
            continue
        current.append(tok)
    if current:
        commands.append(current)
    return commands


# --- unwrapping -------------------------------------------------------------------------

def unwrap(cmd: list[str], depth: int = 0) -> list[list[str]]:
    """Strip assignments, keywords and wrappers; descend into shells, eval and env -S."""
    if depth > 8:
        ask("The command nests too deeply for the guard to follow.")
    args = list(cmd)
    while args:
        word = args[0]
        if ASSIGNMENT_RE.match(word):
            name, value = word.split("=", 1)
            if name.startswith("GIT_") or OUTWARD_RE.search(value) or OUTWARD_CMD_RE.search(value):
                ask(f"`{name}` holds or changes an outward-facing command.")
            args = args[1:]
            continue
        if "$" in word or "`" in word:
            ask("The program to run comes from a variable or substitution the guard can't resolve.")
        head = os.path.basename(word)
        if head in SHELL_KEYWORDS:
            args = args[1:]
            continue
        if head in {"for", "select", "case", "in", "done", "fi", "esac", "function"}:
            return []
        if head in WRAPPERS:
            value_opts = WRAPPERS[head]
            args = args[1:]
            if head == "timeout" and args and re.match(r"^[\d.]+[smhd]?$", args[0]):
                args = args[1:]
            while args and args[0].startswith("-"):
                opt = args.pop(0)
                if head == "env" and (opt in {"-S", "--split-string"} or opt.startswith("-S")):
                    payload = opt[2:] if len(opt) > 2 else (args.pop(0) if args else "")
                    return unwrap_all(split_commands(normalise(payload + " " + " ".join(shlex.quote(a) for a in args))), depth + 1)
                if opt in value_opts and args:
                    args.pop(0)
                elif head == "sudo" and opt in {"-s", "-i"}:
                    ask("sudo starts a shell the guard can't see.")
            if head == "timeout" and args and re.match(r"^[\d.]+[smhd]?$", args[0]):
                args = args[1:]
            while args and ASSIGNMENT_RE.match(args[0]):
                name, value = args[0].split("=", 1)
                if name.startswith("GIT_") or OUTWARD_RE.search(value):
                    ask(f"`{name}` holds or changes an outward-facing command.")
                args = args[1:]
            continue
        if head == "xargs":
            if any(os.path.basename(a) in {"git", "gh", "svn", "svnmucc", "ssh", "scp", "rsync", "wp",
                                            "bash", "sh", "zsh", "eval", "env"} for a in args[1:]):
                ask("xargs builds an outward-capable command from input the guard can't see.")
            return [args]
        if head == "eval":
            code = " ".join(args[1:])
            if "$" in code or "`" in code:
                ask("eval runs a command built from variables the guard can't resolve.")
            return unwrap_all(split_commands(normalise(code)), depth + 1)
        if head in SHELLS:
            rest = args[1:]
            opts: list[str] = []
            while rest and (rest[0].startswith("-") or rest[0].startswith("+")):
                opt = rest.pop(0)
                opts.append(opt)
                if opt in {"-o", "+o", "--rcfile", "--init-file"} and rest:
                    rest.pop(0)
            if any(o == "-c" or (o.startswith("-") and not o.startswith("--") and "c" in o[1:]) for o in opts):
                return unwrap_all(split_commands(normalise(rest[0])), depth + 1) if rest else []
            if not rest or rest[0] == "<<<" or any(o == "-s" or (o.startswith("-") and not o.startswith("--") and "s" in o[1:]) for o in opts):
                ask(f"`{head}` reads its commands from input the guard can't see.")
            return [[as_path(rest[0])] + rest[1:]]  # `bash script.sh args`: the script is the program
        if head in {"source", "."}:
            return [[as_path(args[1])] + args[2:]] if len(args) > 1 else []
        break
    return [args] if args else []


def as_path(word: str) -> str:
    """A script name run by a shell, written so check_program scans it."""
    return word if word.startswith(("/", "./", "../", "~")) else "./" + word


def unwrap_all(commands: list[list[str]], depth: int = 0) -> list[list[str]]:
    out: list[list[str]] = []
    for cmd in commands:
        out.extend(unwrap(cmd, depth))
    return out


# --- shared state across one Bash call ---------------------------------------------------

class Context:
    def __init__(self, cwd: str) -> None:
        self.cwd = cwd
        self.branch_unknown = False  # a switch/checkout/pushd/unresolvable cd ran earlier
        self.pr_changed = False      # a PR was edited earlier in the same call


def short_flags(opt: str) -> set[str]:
    return set(opt[1:]) if re.match(r"^-[A-Za-z]+$", opt) else set()


# --- git ------------------------------------------------------------------------------

def git_parts(args: list[str]) -> tuple[list[str], str | None, list[str]]:
    rest = args[1:]
    globals_: list[str] = []
    while rest and rest[0].startswith("-"):
        opt = rest.pop(0)
        globals_.append(opt)
        if opt in GIT_RISKY_GLOBALS | {"-C"} and rest:
            globals_.append(rest.pop(0))
    if not rest:
        return globals_, None, []
    return globals_, rest[0], rest[1:]


def git_path(globals_: list[str], ctx: Context) -> str:
    path = ctx.cwd
    for i, opt in enumerate(globals_):
        if opt == "-C" and i + 1 < len(globals_):
            path = os.path.normpath(os.path.join(path, os.path.expanduser(globals_[i + 1])))
    return path


def git_out(path: str, *args: str) -> str | None:
    res = run(["git", "-C", path, *args], path, timeout=5)
    if res is None or res.returncode != 0:
        return None
    return res.stdout.strip() or None


def current_branch(path: str) -> str | None:
    return git_out(path, "rev-parse", "--abbrev-ref", "HEAD")


def push_destination(path: str) -> str | None:
    """remote/branch a bare `git push` sends the current branch to, if git can say."""
    return git_out(path, "rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{push}")


def is_tag(path: str, name: str) -> bool:
    if VERSION_LIKE_RE.match(name):
        return True
    res = run(["git", "-C", path, "show-ref", "--verify", "--quiet", f"refs/tags/{name}"], path, timeout=5)
    return res is not None and res.returncode == 0


def check_git(args: list[str], ctx: Context) -> None:
    globals_, sub, rest = git_parts(args)
    if sub is None:
        return
    if any(g.split("=", 1)[0] in GIT_RISKY_GLOBALS for g in globals_) and sub not in {"status", "log", "diff", "show", "rev-parse", "ls-files", "branch", "fetch"}:
        ask("git runs with configuration or repository overrides the guard can't follow.")
    path = git_path(globals_, ctx)
    if sub not in GIT_KNOWN:
        ask(f"`git {sub}` is an alias, extension or unknown command; the guard can't see what it runs.")
    if any(GUARD_PATH_RE.search(a) for a in rest) and sub in {"checkout", "restore", "rm", "mv", "apply", "stash", "reset", "switch"}:
        ask("This git command could change the guardrails; it needs the maintainer's yes.")
    if sub == "push":
        check_git_push(rest, path, ctx)
    elif sub == "tag":
        listing = {"-l", "--list", "-v", "--verify", "--contains", "--no-contains", "--merged",
                   "--no-merged", "--points-at", "--column", "--no-column", "-i", "--ignore-case"}
        is_listing = not rest or any(a in listing or a.startswith(("--sort", "--format", "-n", "--contains=",
                                                                     "--points-at=", "--merged="))
                                     for a in rest)
        creating = any(a in {"-a", "-s", "-d", "-f", "--delete", "--force", "-m", "-F", "-u", "--annotate", "--sign"} for a in rest)
        if creating or not is_listing:
            ask("Creating or deleting a git tag needs the maintainer's yes.")
    elif sub == "svn":
        if rest and rest[0] in {"dcommit", "set-tree", "tag", "branch", "commit-diff"}:
            deny("git svn writes to the SVN repository; the maintainer runs that, never Claude Code.")
    elif sub in GIT_CHANGES_CURRENT_BRANCH:
        if ctx.branch_unknown:
            ask(f"`git {sub}` runs after a branch switch in the same command; the guard can't confirm which branch it changes.")
        branch = current_branch(path)
        if branch is None or branch in PROTECTED_BRANCHES:
            ask(f"`git {sub}` would change {branch or 'a branch the guard cannot identify'}; protected branches change only through PRs.")
    elif sub in {"switch", "checkout"}:
        ctx.branch_unknown = True
        flags: set[str] = set()
        for a in rest:
            flags |= short_flags(a)
        if (flags & {"B", "C"} or any(a in {"--force-create"} for a in rest)) and any(a in PROTECTED_BRANCHES for a in rest):
            ask("Resetting a protected branch needs the maintainer's yes.")
    elif sub == "branch":
        flags = set()
        for a in rest:
            flags |= short_flags(a)
        names = [a for a in rest if not a.startswith("-")]
        touches = any(n in PROTECTED_BRANCHES or n.rsplit("/", 1)[-1] in PROTECTED_BRANCHES for n in names)
        changing = bool(flags & {"D", "d", "m", "M", "f", "c", "C", "u"}) or any(
            a in {"--delete", "--move", "--force", "--copy", "--set-upstream-to"} or a.startswith("--set-upstream-to=") for a in rest)
        if touches and changing:
            ask("Changing a protected branch, or pointing a branch at one, needs the maintainer's yes.")
    elif sub == "fetch":
        for a in rest:
            if ":" in a and a.split(":", 1)[1].lstrip("+").removeprefix("refs/heads/") in PROTECTED_BRANCHES:
                ask("Fetching straight into a protected branch needs the maintainer's yes.")
    elif sub == "update-ref":
        ask("`git update-ref` changes refs directly; it needs the maintainer's yes.")
    elif sub == "config":
        reading = {"--get", "--get-all", "--get-regexp", "-l", "--list", "--show-origin", "--show-scope", "get", "list", "--name-only"}
        if not any(a in reading for a in rest):
            keys = [a for a in rest if not a.startswith("-")]
            if keys:
                key = keys[1] if keys[0] in {"set", "unset", "edit", "rename-section", "remove-section"} and len(keys) > 1 else keys[0]
                if keys[0] in {"edit", "rename-section", "remove-section"} or re.match(
                        r"^(alias\.|remote\.|push\.|url\.|branch\.|core\.(hookspath|sshcommand|fsmonitor)|include|credential)", key, re.I):
                    ask("Changing git settings for aliases, remotes, pushes, hooks or credentials needs the maintainer's yes.")
            elif any(a in {"--edit", "-e"} for a in rest):
                ask("Editing git settings needs the maintainer's yes.")
    elif sub == "remote":
        if rest and rest[0] in {"add", "set-url", "rename", "remove", "rm", "set-branches", "set-head"}:
            ask("Changing git remotes needs the maintainer's yes.")


PUSH_SAFE_SHORT = {"u", "q", "v", "n"}
PUSH_SAFE_LONG = {"--set-upstream", "--quiet", "--verbose", "--dry-run", "--porcelain", "--progress",
                  "--no-progress", "--atomic", "--no-atomic"}


def check_git_push(rest: list[str], path: str, ctx: Context) -> None:
    positional: list[str] = []
    skip = False
    for a in rest:
        if skip:
            skip = False
            continue
        if a in {"-o", "--push-option"}:
            skip = True
            continue
        if a.startswith("--"):
            name = a.split("=", 1)[0]
            if name in {"--force", "--force-with-lease", "--force-if-includes", "--mirror"}:
                deny("Force and mirror pushes are never run by Claude Code (AGENTS.md section 3, Guardrails).")
            if name == "--no-verify":
                ask("`--no-verify` skips the pre-push quality gate; it needs the maintainer's yes.")
            if name in {"--tags", "--follow-tags", "--all", "--branches", "--prune", "--delete"}:
                ask(f"`git push {name}` reaches beyond one branch; it needs the maintainer's yes.")
            if name not in PUSH_SAFE_LONG and not name.startswith("--push-option"):
                ask(f"`git push {name}` is not a known-safe option.")
            continue
        if a.startswith("-"):
            flags = short_flags(a)
            if "f" in flags:
                deny("Force pushes are never run by Claude Code (AGENTS.md section 3, Guardrails).")
            if "d" in flags:
                ask("Deleting a remote branch or tag needs the maintainer's yes.")
            if not flags or not flags <= PUSH_SAFE_SHORT:
                ask(f"`git push {a}` is not a known-safe option.")
            continue
        positional.append(a)
    if positional:
        remote = positional[0]
        if not BRANCH_NAME_RE.match(remote) or "/" in remote or remote.startswith("."):
            ask("Pushing to a URL or path instead of a named remote needs the maintainer's yes.")
    refspecs = positional[1:]
    if not refspecs:
        if ctx.branch_unknown:
            ask("A push without a branch name after a branch switch: the guard can't confirm what it pushes.")
        destination = push_destination(path)
        if destination is None:
            ask("A push without a branch name whose destination the guard can't confirm needs the maintainer's yes.")
        check_destination(destination.split("/", 1)[1] if "/" in destination else destination, path)
        return
    if "tag" in refspecs:
        ask("Pushing a tag needs the maintainer's yes (release step).")
    for ref in refspecs:
        if ref.startswith("+"):
            deny("Force pushes (+refspec) are never run by Claude Code.")
        if "*" in ref or ref.startswith(":") or "$" in ref or "`" in ref:
            ask("A wildcard, deleting or variable refspec needs the maintainer's yes.")
        src, colon, dst = ref.partition(":")
        if src.startswith("refs/tags/") or dst.startswith("refs/tags/"):
            ask("Pushing a tag needs the maintainer's yes (release step).")
        if not colon:
            dst = src
        if src in {"HEAD", "@"}:
            if ctx.branch_unknown:
                ask("`git push HEAD` after a branch switch: the guard can't tell which branch it pushes.")
            branch = current_branch(path)
            if not branch or branch == "HEAD":
                ask("`git push HEAD` from a detached or unknown branch needs the maintainer's yes.")
            if not colon:
                dst = branch
        elif is_tag(path, src.removeprefix("refs/heads/")):
            ask("Pushing a tag needs the maintainer's yes (release step).")
        check_destination(dst.removeprefix("refs/heads/"), path)


def check_destination(branch: str, path: str) -> None:
    if not branch or not BRANCH_NAME_RE.match(branch) or branch.startswith("refs/"):
        ask("The push destination is not a plain branch name.")
    if branch == RELEASE_BRANCH:
        ask("Pushing to main needs the maintainer's yes (release step).")
    if branch in PROTECTED_BRANCHES:
        ask(f"Pushing straight to {branch} needs the maintainer's yes; it changes only through PRs.")
    if is_tag(path, branch):
        ask("Pushing a tag needs the maintainer's yes (release step).")


# --- gh -------------------------------------------------------------------------------

def gh_split(args: list[str]) -> tuple[list[str], list[str]]:
    repo: list[str] = []
    rest: list[str] = []
    i = 1
    while i < len(args):
        a = args[i]
        if a in {"-R", "--repo"}:
            repo = ["-R", args[i + 1] if i + 1 < len(args) else ""]
            i += 2
            continue
        if a.startswith("--repo="):
            repo = ["-R", a.split("=", 1)[1]]
        elif a.startswith("-R") and len(a) > 2:
            repo = ["-R", a[2:]]
        else:
            rest.append(a)
        i += 1
    return repo, rest


def options(rest: list[str], value_opts: set[str]) -> tuple[list[str], dict[str, list[str]]]:
    """Positional words and option values; handles `--x=y`, `-xy` and `-x y`."""
    positional: list[str] = []
    values: dict[str, list[str]] = {}
    i = 0
    while i < len(rest):
        a = rest[i]
        if a.startswith("--") and "=" in a:
            name, val = a.split("=", 1)
            values.setdefault(name, []).append(val)
        elif a in value_opts:
            values.setdefault(a, []).append(rest[i + 1] if i + 1 < len(rest) else "")
            i += 1
        elif re.match(r"^-[A-Za-z].+", a) and a[:2] in value_opts:
            values.setdefault(a[:2], []).append(a[2:])
        elif a.startswith("-") and len(a) > 1:
            values.setdefault(a, []).append("")
        else:
            positional.append(a)
        i += 1
    return positional, values


def pr_base(target: str | None, repo: list[str], cwd: str) -> str | None:
    cmd = ["gh", "pr", "view"] + ([target] if target else []) + repo + ["--json", "baseRefName", "-q", ".baseRefName"]
    res = run(cmd, cwd, timeout=12)
    if res is None or res.returncode != 0:
        return None
    return res.stdout.strip() or None


GH_SAFE = {
    "pr": {"create", "view", "list", "diff", "checks", "comment", "status", "checkout", "ready", "review", "merge", "edit"},
    "issue": {"view", "list", "status"},
    "release": {"list", "view", "download"},
    "repo": {"view"},
    "run": {"list", "view", "watch", "download"},
    "search": None,
    "browse": None,
    "status": None,
    "label": {"list"},
    "auth": {"status"},
}
PR_VALUE_OPTS = {"-B", "--base", "-t", "--title", "-b", "--body", "-F", "--body-file", "-H", "--head",
                 "-a", "--assignee", "-l", "--label", "-r", "--reviewer", "-m", "--milestone", "-p",
                 "--project", "--subject", "-A", "--author-email", "--match-head-commit", "--add-label",
                 "--remove-label", "--add-reviewer", "--remove-reviewer", "--add-assignee",
                 "--remove-assignee", "-T", "--template", "--json", "-q", "--jq"}


def check_gh(args: list[str], ctx: Context) -> None:
    repo, rest = gh_split(args)
    if not rest:
        return
    group = rest[0]
    sub = rest[1] if len(rest) > 1 else ""
    if group == "api":
        check_gh_api(rest[1:], repo, ctx)
        return
    allowed = GH_SAFE.get(group, "unknown")
    if allowed == "unknown" or (allowed is not None and sub not in allowed):
        ask(f"`gh {group} {sub}` is not a known-safe GitHub command; it needs the maintainer's yes.")
    if group != "pr":
        return
    positional, values = options(rest[2:], PR_VALUE_OPTS)
    bases = values.get("-B", []) + values.get("--base", [])
    if any(b in PROTECTED_BRANCHES - {INTEGRATION_BRANCH} or "$" in b or "`" in b for b in bases):
        ask("A PR into main is the release PR; it needs the maintainer's yes.")
    if sub == "edit" and bases:
        ctx.pr_changed = True
    if sub == "merge":
        if ctx.pr_changed:
            ask("A PR is changed and merged in the same command; the guard can't confirm its base.")
        if any(k in values for k in {"--admin", "--auto"}):
            ask("Merging with --admin or --auto needs the maintainer's yes.")
        target = positional[0] if positional else None
        if target and ("$" in target or "`" in target):
            ask("The PR to merge comes from a variable the guard can't resolve.")
        base = pr_base(target, repo, ctx.cwd)
        if base != INTEGRATION_BRANCH:
            ask(f"Merging a PR into {base or 'a base the guard could not confirm'} needs the maintainer's yes (only develop is pre-approved).")


API_VALUE_OPTS = {"-X", "--method", "-f", "-F", "--field", "--raw-field", "--input", "-H", "--header",
                  "-q", "--jq", "-t", "--template", "--hostname", "--cache", "-p", "--preview"}


def check_gh_api(rest: list[str], repo: list[str], ctx: Context) -> None:
    positional, values = options(rest, API_VALUE_OPTS)
    method = (values.get("-X") or values.get("--method") or [""])[-1].upper()
    fields = values.get("-f", []) + values.get("-F", []) + values.get("--field", []) + values.get("--raw-field", [])
    writes = bool(fields) or "--input" in values or method in {"POST", "PUT", "PATCH", "DELETE"}
    path = positional[0] if positional else ""
    if "$" in path or "`" in path or "$" in method:
        ask("The API path or method comes from a variable the guard can't resolve.")
    if path.strip("/") == "graphql":
        if "--input" in values or any(re.search(r"\bmutation\b", f, re.I) for f in fields):
            ask("A GraphQL mutation changes GitHub; it needs the maintainer's yes.")
        return
    if method == "GET" or not writes:
        return
    merge = re.search(r"pulls/(\d+)/merge/?$", path)
    if merge and method in {"PUT", ""} and not ctx.pr_changed and "--input" not in values:
        if pr_base(merge.group(1), repo, ctx.cwd) == INTEGRATION_BRANCH:
            return
    if re.search(r"pulls(/\d+)?/?$", path):
        ctx.pr_changed = True
    if re.search(r"(issues|pulls)/\d+/comments/?$", path) and method in {"POST", ""}:
        return
    ask("Writing to GitHub through the API (other than merging a PR into develop or commenting on one) needs the maintainer's yes.")


# --- svn, wp, deploys, remote shells, scripts ---------------------------------------------

def check_svn(args: list[str]) -> None:
    positional: list[str] = []
    skip = False
    revprop = False
    for a in args[1:]:
        if skip:
            skip = False
            continue
        if a == "--revprop":
            revprop = True
            continue
        if a in SVN_VALUE_OPTIONS:
            skip = True
            continue
        if a.startswith("-"):
            continue
        positional.append(a)
    if not positional:
        return
    sub, targets = positional[0], positional[1:]
    if sub in {"commit", "ci", "import"}:
        deny("SVN commits to WordPress.org are run by the maintainer, never by Claude Code.")
    if revprop:
        deny("Changing SVN revision properties is run by the maintainer, never by Claude Code.")
    if sub in SVN_LOCAL_IF_NO_URL:
        if any(SVN_URL_RE.match(t) or "$" in t for t in targets):
            deny("SVN writes to the repository are run by the maintainer, never by Claude Code.")
        return
    if sub in SVN_SAFE:
        return
    ask(f"`svn {sub}` is not a known-safe SVN command; it needs the maintainer's yes.")


def wp_remote(args: list[str]) -> bool:
    return any(a.startswith(("@", "--ssh", "--http")) for a in args)


def committed_unchanged(full: str, cwd: str) -> bool:
    directory = os.path.dirname(full) or cwd
    tracked = run(["git", "-C", directory, "ls-files", "--error-unmatch", "--", full], directory, timeout=5)
    if tracked is None or tracked.returncode != 0:
        return False
    status = run(["git", "-C", directory, "status", "--porcelain", "--", full], directory, timeout=5)
    return status is not None and status.returncode == 0 and status.stdout.strip() == ""


def scan_script(path: str, ctx: Context) -> None:
    """Ask when a new or changed script contains an outward-facing command."""
    if "$" in path or "`" in path:
        ask("The script to run comes from a variable the guard can't resolve.")
    full = os.path.normpath(os.path.join(ctx.cwd, os.path.expanduser(path)))
    if not os.path.isfile(full):
        return
    if committed_unchanged(full, ctx.cwd):
        return  # committed scripts were reviewed in a PR
    try:
        with open(full, "r", encoding="utf-8", errors="replace") as fh:
            body = fh.read(SCRIPT_SCAN_LIMIT + 1)
    except OSError:
        ask("The guard can't read the script this command runs.")
    if len(body) > SCRIPT_SCAN_LIMIT or OUTWARD_RE.search(body):
        ask("This command runs a new or changed script that contains an outward-facing command.")


def check_program(args: list[str], ctx: Context) -> None:
    word = args[0]
    head = os.path.basename(word)
    base = re.sub(r"[\d.]+$", "", head)
    if head == "cd":
        target = args[1] if len(args) > 1 else "~"
        if "$" in target or "`" in target or target == "-":
            ctx.branch_unknown = True
        else:
            ctx.cwd = os.path.normpath(os.path.join(ctx.cwd, os.path.expanduser(target)))
        return
    if head in {"pushd", "popd"}:
        ctx.branch_unknown = True
        return
    if head in {"svnmucc", "svnrdump", "svnadmin", "svnsync"}:
        deny("SVN repository writes are run by the maintainer, never by Claude Code.")
    if head in {"ssh", "scp", "sftp", "mosh", "telnet"}:
        ask(f"`{head}` reaches another machine; it needs the maintainer's yes.")
    if head == "rsync" and any(not a.startswith("-") and re.match(r"^([^/\s:]+@)?[^/\s:]+:", a) for a in args[1:]):
        ask("rsync to another machine needs the maintainer's yes.")
    if "deploy" in head.lower():
        ask("Deploy scripts change production; they need the maintainer's yes.")
    if head in {"wp", "wp-cli.phar"} and wp_remote(args[1:]):
        ask("WP-CLI against a remote site needs the maintainer's yes.")
    if base in INTERPRETERS:
        first_positional = None
        i = 1
        while i < len(args):
            a = args[i]
            if a in CODE_FLAGS:
                if i + 1 < len(args) and OUTWARD_RE.search(args[i + 1]):
                    ask(f"Inline {head} code runs an outward-facing command; it needs the maintainer's yes.")
                i += 2
                continue
            if a in {"-d", "-m", "-W", "-X", "-I", "--require", "-r"} and base != "php" or (base == "php" and a in {"-d", "-c", "-n", "-f"}):
                if base == "php" and a == "-f" and i + 1 < len(args):
                    first_positional = args[i + 1]
                    break
                i += 2
                continue
            if a.startswith("-"):
                i += 1
                continue
            first_positional = a
            break
        if first_positional is not None:
            rest = args[i + 1:]
            if os.path.basename(first_positional) in {"wp", "wp-cli.phar"}:
                if wp_remote(rest):
                    ask("WP-CLI against a remote site needs the maintainer's yes.")
            elif first_positional != "-":
                scan_script(first_positional, ctx)
            if first_positional == "-":
                ask(f"`{head}` reads its code from input the guard can't see.")
    if head in {"npm", "pnpm", "yarn", "bun", "composer", "make", "npx"}:
        targets = [a for a in args[1:] if not a.startswith("-")]
        if any(re.search(r"deploy|release|publish", t, re.I) for t in targets):
            ask(f"`{head}` runs a deploy, release or publish task; it needs the maintainer's yes.")
    if word.startswith(("./", "../", "/", "~")) and head not in READ_ONLY_PROGRAMS:
        scan_script(word, ctx)
    if head not in READ_ONLY_PROGRAMS | {"git", "python3", "python"} and any(GUARD_PATH_RE.search(a) for a in args[1:]):
        ask("This command could change the guardrails (.claude/settings or .claude/hooks); it needs the maintainer's yes.")
    if base in {"python", "python3"} and any(GUARD_PATH_RE.search(a) for a in args[1:]) and not (
            "-m" in args and "unittest" in args):
        ask("This command could change the guardrails (.claude/settings or .claude/hooks); it needs the maintainer's yes.")


# --- entry point --------------------------------------------------------------------

def classify(command: str, cwd: str) -> None:
    ctx = Context(cwd)
    text = normalise(command)
    if re.search(r"(>|>>|>\|)\s*['\"]?[^\s;&|]*" + GUARD_PATH_RE.pattern, text):
        ask("This command writes to the guardrails (.claude/settings or .claude/hooks); it needs the maintainer's yes.")
    for cmd in unwrap_all(split_commands(text)):
        check_deadline()
        if not cmd:
            continue
        if "<<<" in cmd:
            idx = cmd.index("<<<")
            if os.path.basename(cmd[0]) in SHELLS | INTERPRETERS:
                ask("A here-string feeds commands to a shell or interpreter.")
            cmd = cmd[:idx] + cmd[idx + 2:]
            if not cmd:
                continue
        head = os.path.basename(cmd[0])
        if head == "git":
            check_git(cmd, ctx)
        elif head == "gh":
            check_gh(cmd, ctx)
        elif head == "svn":
            check_svn(cmd)
        check_program(cmd, ctx)


FALLBACK_DENY_RE = re.compile(
    r"\bsvn\b[^\n]*\b(commit|ci|import)\b|\bsvnmucc\b|\bgit\b[^\n]*\bsvn\s+dcommit\b|"
    r"\bsvn\s+(cp|copy|mv|move|rm|delete|mkdir)\b[^\n]*(\^/|://)|"
    r"\bgit\b[^\n]*\bpush\b[^\n]*(--force|--mirror|\s-[a-zA-Z]*f[a-zA-Z]*\b|\s\+\S)",
    re.I,
)
FALLBACK_ASK_RE = re.compile(
    r"\b(push|tag|svn|svnmucc|release|deploy|ssh|scp|sftp|rsync|graphql|update-ref|merge|eval|xargs)\b|"
    r"\bgh\s+api\b|\bwp\s+@|--ssh|\.claude/(settings|hooks)",
    re.I,
)


def evaluate(command: str, cwd: str) -> tuple[str | None, str]:
    global _started
    _started = time.monotonic()
    try:
        classify(command, cwd)
    except Decision as d:
        return d.kind, d.reason
    except (ValueError, IndexError, RecursionError):
        if FALLBACK_DENY_RE.search(command):
            return "deny", "The guard could not parse this command; it contains an SVN write or a force push."
        if FALLBACK_ASK_RE.search(command):
            return "ask", "The guard could not parse this command and it mentions an outward-facing action."
    return None, ""


def main() -> int:
    try:
        payload = json.load(sys.stdin)
        if not isinstance(payload, dict):
            raise TypeError("hook input is not an object")
        if payload.get("tool_name") != "Bash":
            return 0
        command = (payload.get("tool_input") or {}).get("command")
        if not isinstance(command, str):
            raise TypeError("command is not a string")
        cwd = payload.get("cwd") or os.getcwd()
        kind, reason = evaluate(command, cwd)
    except Exception as exc:  # noqa: BLE001 - a broken guard must never wave commands through.
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
