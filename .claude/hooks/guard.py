#!/usr/bin/env python3
"""Claude Code PreToolUse guard: pause for the maintainer before outward steps.

Runs before every Bash call (see .claude/settings.json and AGENTS.md section 3,
Guardrails). It reads the hook's JSON on stdin and answers with a permission
decision:

- deny (never): force or mirror pushes and `git push --no-verify`.
- ask (Claude Code stops; the maintainer approves at the prompt): pushing or
  merging to main or develop directly, tags, GitHub releases and other GitHub
  writes, SVN writes to WordPress.org (svn commit and friends), deploys and
  other machines, the quality-gate escape hatch, and changes to the
  guardrails themselves.
- no decision: everything else, which then goes through the permission rules.

Design: fail closed. A command that uses an outward-capable program must be
understood completely and match a known-safe shape (for example
`git push -u origin fix/x`), or the guard asks. Anything it cannot parse,
resolve or classify asks. The hook entry in settings.json turns a crash or a
missing interpreter into a block (exit 2), and an overall deadline turns slow
lookups into an ask. This is a speed bump that makes Claude Code stop and ask;
it is not a security boundary (AGENTS.md section 3, Guardrails, says what is).

Standard library only; tested by .claude/hooks/test_guard.py.
"""

from __future__ import annotations

import json
import os
import re
import shlex
import signal
import subprocess
import sys
import time

PROTECTED_BRANCHES = {"main", "develop"}
INTEGRATION_BRANCH = "develop"
DEADLINE_SECONDS = 20.0
SCRIPT_SCAN_LIMIT = 256 * 1024
LINE_SCAN_LIMIT = 4000

_started = time.monotonic()

OUTWARD_RE = re.compile(
    r"\bsvn\s+(commit|ci|import|cp|copy|mv|move|rm|delete|mkdir|propedit|propset)\b|\bsvnmucc\b|"
    r"\bgit\b[^\n;|&]{0,200}\b(push|tag|update-ref|svn|config|remote)\b|"
    r"\bgh\s+(release|api|pr\s+(merge|review)|repo|secret|workflow|issue)\b|"
    r"\bdeploy\b|\bssh\s|\bscp\s|\bsftp\s|\brsync\s|\bwp\s+@|--ssh=|api\.github\.com|"
    r"\.claude/(hooks|settings)|\.githooks|"
    r"\b(system|exec|execSync|execFile|execFileSync|spawn|spawnSync|popen|Popen|shell_exec|passthru|"
    r"check_call|check_output|getoutput|run)\s*\(",
    re.I,
)
SHELLS = {"sh", "bash", "zsh", "dash", "ksh", "fish"}
INTERPRETERS = {"python": "c", "python3": "c", "node": "ep", "nodejs": "ep", "php": "rB",
                "perl": "eE", "ruby": "e", "osascript": "e", "deno": "", "bun": "e", "tclsh": "",
                "lua": "e", "pwsh": "c", "powershell": "c"}
LONG_CODE_FLAGS = {"--eval", "--print", "--command"}
INFO_FLAGS = {"--version", "-V", "-v", "--help", "-h"}
SEPARATORS = {"&&", "||", ";", "|", "|&", "&", ";;", "(", ")", "{", "}", "!"}
REDIRECT_OPS = {">", ">>", "<", ">|", "&>", "&>>", ">&", "<&", "<>"}
SHELL_KEYWORDS = {"do", "then", "else", "elif", "if", "while", "until"}
EXECUTORS = {"script", "parallel", "doas", "nsenter", "chroot", "su", "runuser", "unshare", "ssh-agent",
             "screen", "tmux", "at", "batch", "crontab", "launchctl", "osascript", "open"}
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
    "setsid": set(),
}
SAFE_ENV = {"GIT_EDITOR": {"true", ":"}, "GIT_SEQUENCE_EDITOR": {"true", ":"}, "GIT_PAGER": {"cat", ""},
            "GIT_TERMINAL_PROMPT": {"0"}}
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
GIT_READ_ONLY = {"status", "log", "diff", "show", "rev-parse", "ls-files", "ls-tree", "ls-remote", "blame",
                 "describe", "shortlog", "show-ref", "for-each-ref", "rev-list", "merge-base", "cat-file",
                 "grep", "reflog", "range-diff", "name-rev", "count-objects", "var", "version", "help",
                 "check-ignore", "verify-commit", "show-branch", "whatchanged", "cherry"}
GIT_CHANGES_CURRENT_BRANCH = {"merge", "rebase", "reset", "commit", "cherry-pick", "am", "revert", "pull"}
GIT_RISKY_GLOBALS = {"-c", "--config-env", "--git-dir", "--work-tree", "--namespace", "--exec-path", "--super-prefix"}
GIT_CONFIG_SAFE_KEYS = re.compile(r"^(user\.(name|email)|color\..+|core\.autocrlf|pull\.(rebase|ff)|"
                                  r"init\.defaultbranch|advice\..+|rerere\.enabled|merge\.conflictstyle|"
                                  r"diff\.(algorithm|renames)|fetch\.prune|rebase\.autostash)$", re.I)
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
GUARD_TEXT_RE = re.compile(r"\.claude/+(settings[^/\s'\"]*\.json|hooks\b)|\.githooks\b|\.git/+(config|hooks)\b")
READ_ONLY_PROGRAMS = {"cat", "less", "more", "head", "tail", "grep", "egrep", "fgrep", "rg", "wc", "diff",
                      "ls", "stat", "file", "jq", "bat", "sha256sum", "shasum", "md5sum", "realpath",
                      "dirname", "basename", "test", "[", "echo", "printf", "true", "false", ":", "cmp",
                      "find", "tree", "du", "pwd", "which", "type", "sort", "uniq", "cut", "tr", "column"}
NETWORK_CLIENTS = {"curl", "wget", "http", "https", "xh", "httpie"}


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
    """Run a lookup in its own process group, killed whole on timeout."""
    check_deadline()
    remaining = DEADLINE_SECONDS - (time.monotonic() - _started)
    limit = max(0.5, min(timeout, remaining))
    try:
        proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
                                cwd=cwd if os.path.isdir(cwd) else None, start_new_session=True)
    except OSError:
        return None
    try:
        out, err = proc.communicate(timeout=limit)
    except subprocess.TimeoutExpired:
        try:
            os.killpg(proc.pid, signal.SIGKILL)
        except OSError:
            pass
        proc.communicate()
        ask("A lookup the guard needed took too long.")
    return subprocess.CompletedProcess(cmd, proc.returncode, out, err)


def matches_outward(text: str) -> bool:
    for line in text.splitlines() or [text]:
        if OUTWARD_RE.search(line[:LINE_SCAN_LIMIT]):
            return True
    return False


# --- normalising: heredocs, continuations, comments, fd numbers ----------------------

HEREDOC_START_RE = re.compile(r"<<(-?)[ \t]*(['\"]?)([A-Za-z_][A-Za-z0-9_]*)\2")


def normalise(text: str) -> str:
    """One pass, quote-aware: joins continuations, drops comments and file-descriptor
    numbers before redirects, turns unquoted newlines into `;`, and removes heredoc
    bodies (asking when one feeds commands to a program)."""
    out: list[str] = []
    state = None  # None, "'" or '"'
    prev = " "
    pending: list[tuple[str, bool, bool]] = []
    segment_start = 0
    i = 0
    n = len(text)
    while i < n:
        c = text[i]
        if state == "'":
            out.append(c)
            if c == "'":
                state = None
            prev = c
            i += 1
            continue
        if c == "\\" and i + 1 < n:
            if text[i + 1] == "\n":
                i += 2
                continue
            out.append(text[i : i + 2])
            prev = text[i + 1]
            i += 2
            continue
        if state == '"':
            out.append(c)
            if c == '"':
                state = None
            prev = c
            i += 1
            continue
        if c in "'\"":
            state = c
            out.append(c)
        elif c == "#" and (prev.isspace() or prev in ";&|()"):
            while i < n and text[i] != "\n":
                i += 1
            continue
        elif c.isdigit() and (prev.isspace() or prev in ";&|(") and i + 1 < n and text[i + 1] in "<>":
            pass  # file-descriptor number before a redirect (2>&1, 2>/dev/null)
        elif c == "<" and text.startswith("<<", i) and not text.startswith("<<<", i):
            m = HEREDOC_START_RE.match(text, i)
            if not m:
                raise ValueError("unreadable heredoc")
            words = re.findall(r"[A-Za-z0-9_.+/-]+", "".join(out[segment_start:]))
            feeds = any(os.path.basename(w) in set(SHELLS) | set(INTERPRETERS) | EXECUTORS |
                        {"ssh", "eval", "xargs", "source", "sudo", "env", "gh", "git", "svn", "wp", "patch"}
                        for w in words)
            pending.append((m.group(3), m.group(1) == "-", feeds))
            out.append(" ")
            i = m.end()
            prev = " "
            continue
        elif c == "\n":
            out.append(" ; ")
            segment_start = len(out)
            i += 1
            for word, strip_tabs, feeds in pending:
                body: list[str] = []
                while True:
                    if i >= n:
                        raise ValueError("unterminated heredoc")
                    end = text.find("\n", i)
                    line = text[i:] if end == -1 else text[i:end]
                    i = n if end == -1 else end + 1
                    if (line.lstrip("\t") if strip_tabs else line) == word:
                        break
                    body.append(line)
                if feeds and matches_outward("\n".join(body)):
                    ask("A heredoc feeds commands or data to a program, and it mentions an outward-facing action.")
            pending = []
            prev = " "
            continue
        elif c in ";&|":
            out.append(c)
            segment_start = len(out)
        else:
            out.append(c)
        prev = c
        i += 1
    if state is not None:
        raise ValueError("unterminated quote")
    if pending:
        raise ValueError("unterminated heredoc")
    return "".join(out)


def substitutions(text: str) -> list[str]:
    """Bodies of $(...), <(...), >(...) and backticks, honouring single and double quotes."""
    found: list[str] = []
    i = 0
    in_double = False
    while i < len(text):
        c = text[i]
        if c == "\\" and i + 1 < len(text):
            i += 2
            continue
        if c == "'" and not in_double:
            j = text.find("'", i + 1)
            if j == -1:
                raise ValueError("unterminated quote")
            i = j + 1
            continue
        if c == '"':
            in_double = not in_double
            i += 1
            continue
        if text.startswith(("$(", "<(", ">("), i) and not (in_double and text[i] != "$"):
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


REDIRECT_MARK = "\x00redirect:"


def split_commands(text: str) -> list[list[str]]:
    """Simple commands of a normalised string; redirect targets kept as marked words."""
    commands: list[list[str]] = []
    for inner in substitutions(text):
        commands.extend(split_commands(normalise(inner)))
    lexer = shlex.shlex(text, posix=True, punctuation_chars=";&|()<>")
    lexer.whitespace_split = True
    lexer.commenters = ""
    current: list[str] = []
    take_target = False
    for tok in lexer:
        if take_target:
            take_target = False
            current.append(REDIRECT_MARK + tok)
            continue
        if tok in SEPARATORS or (tok and set(tok) <= set(";&|()")):
            if current:
                commands.append(current)
            current = []
            continue
        if tok == "<<<":
            current.append("<<<")
            continue
        if tok in REDIRECT_OPS or (tok and set(tok) <= set("<>&")):
            take_target = True
            continue
        current.append(tok)
    if current:
        commands.append(current)
    return commands


# --- unwrapping ----------------------------------------------------------------------

def check_assignment(word: str) -> None:
    name, value = word.split("=", 1)
    if name in SAFE_ENV and value in SAFE_ENV[name]:
        return
    if name.startswith(("GIT_", "GH_", "GITHUB_", "SVN_")) or name in {"SKIP_QUALITY_GATE", "SSH_AUTH_SOCK",
                                                                      "SSH_ASKPASS", "PATH", "BASH_ENV", "ENV",
                                                                      "PROMPT_COMMAND", "LD_PRELOAD",
                                                                      "DYLD_INSERT_LIBRARIES", "PYTHONSTARTUP",
                                                                      "NODE_OPTIONS", "CLAUDE_PROJECT_DIR"}:
        ask(f"`{name}` changes how git, gh, svn, the quality gate or the shell behave; it needs the maintainer's yes.")
    if matches_outward(value):
        ask(f"`{name}` holds what looks like an outward-facing command.")


def as_path(word: str) -> str:
    """A script run by a shell or interpreter, written so it is scanned as a path."""
    return word if "/" in word or word.startswith("~") else "./" + word


def unwrap(cmd: list[str], depth: int = 0) -> list[list[str]]:
    """Strip assignments, keywords and wrappers; descend into shells, eval, env -S, find -exec."""
    if depth > 8:
        ask("The command nests too deeply for the guard to follow.")
    args = list(cmd)
    while args:
        word = args[0]
        if word.startswith(REDIRECT_MARK):
            args = args[1:] + [word]
            if all(a.startswith(REDIRECT_MARK) for a in args):
                return [args]
            continue
        if ASSIGNMENT_RE.match(word):
            check_assignment(word)
            args = args[1:]
            continue
        if "$" in word or "`" in word:
            ask("The program to run comes from a variable or substitution the guard can't resolve.")
        if any(ch in word for ch in "*?[]{}"):
            ask("The program name is a pattern; the guard can't tell which program runs.")
        head = os.path.basename(word)
        if head in SHELL_KEYWORDS:
            args = args[1:]
            continue
        if head in {"for", "select", "case", "in", "done", "fi", "esac", "function"}:
            return []
        if head in {"export", "declare", "typeset", "local", "readonly"}:
            for a in args[1:]:
                if ASSIGNMENT_RE.match(a):
                    check_assignment(a)
            return []
        if head == "alias":
            if any(matches_outward(a) for a in args[1:]):
                ask("An alias is defined for an outward-facing command.")
            return []
        if head in WRAPPERS:
            value_opts = WRAPPERS[head]
            args = args[1:]
            if head == "timeout" and args and re.match(r"^[\d.]+[smhd]?$", args[0]):
                args = args[1:]
            while args and args[0].startswith("-"):
                opt = args.pop(0)
                if head == "env" and (opt.startswith("-S") or opt.startswith("--split-string")):
                    if opt in {"-S", "--split-string"}:
                        payload = args.pop(0) if args else ""
                    elif opt.startswith("--split-string="):
                        payload = opt.split("=", 1)[1]
                    else:
                        payload = opt[2:]
                    rest = " ".join(shlex.quote(a) for a in args)
                    return unwrap_all(split_commands(normalise(payload + " " + rest)), depth + 1)
                if head == "sudo" and (opt in {"-s", "-i", "--shell", "--login"} or (re.match(r"^-[A-Za-z]+$", opt) and set(opt[1:]) & {"s", "i"})):
                    ask("sudo starts a shell the guard can't see.")
                if opt in value_opts and args:
                    args.pop(0)
            if head == "timeout" and args and re.match(r"^[\d.]+[smhd]?$", args[0]):
                args = args[1:]
            while args and ASSIGNMENT_RE.match(args[0]):
                check_assignment(args[0])
                args = args[1:]
            continue
        if head == "flock":
            rest = args[1:]
            while rest and rest[0].startswith("-"):
                opt = rest.pop(0)
                if opt in {"-c", "--command"}:
                    return unwrap_all(split_commands(normalise(rest[0] if rest else "")), depth + 1)
                if opt in {"-w", "--wait", "--timeout", "-E", "--conflict-exit-code"} and rest:
                    rest.pop(0)
            if rest:
                rest = rest[1:]  # the lock file
            if rest and rest[0] in {"-c", "--command"}:
                return unwrap_all(split_commands(normalise(rest[1] if len(rest) > 1 else "")), depth + 1)
            args = rest
            continue
        if head == "xargs":
            if any(os.path.basename(a) not in READ_ONLY_PROGRAMS for a in args[1:] if not a.startswith("-") and "/" not in a and not a.startswith(REDIRECT_MARK)):
                inner = [a for a in args[1:] if not a.startswith("-")]
                if inner and os.path.basename(inner[0]) not in READ_ONLY_PROGRAMS:
                    ask("xargs builds a command from input the guard can't see.")
            return [args]
        if head in {"find", "fd"}:
            for flag in ("-exec", "-execdir", "-ok", "-okdir", "-x", "--exec", "-X", "--exec-batch"):
                if flag in args:
                    inner = args[args.index(flag) + 1 :]
                    inner = [a for a in inner if a not in {";", "+", "{}", "\\;"}]
                    if inner:
                        return unwrap_all([inner], depth + 1) + [[args[0]] + args[1 : args.index(flag)]]
            return [args]
        if head == "eval":
            code = " ".join(args[1:])
            if "$" in code or "`" in code:
                ask("eval runs a command built from variables the guard can't resolve.")
            return unwrap_all(split_commands(normalise(code)), depth + 1)
        if head in SHELLS:
            rest = args[1:]
            opts: list[str] = []
            while rest and (rest[0].startswith("-") or rest[0].startswith("+")) and not rest[0].startswith(REDIRECT_MARK):
                opt = rest.pop(0)
                opts.append(opt)
                if opt in {"-o", "+o", "--rcfile", "--init-file"} and rest:
                    rest.pop(0)
            if any(o == "-c" or (o.startswith("-") and not o.startswith("--") and "c" in o[1:]) for o in opts):
                return unwrap_all(split_commands(normalise(rest[0])), depth + 1) if rest else []
            rest = [a for a in rest if not a.startswith(REDIRECT_MARK)]
            if not rest or rest[0] == "<<<" or any(o == "-s" or (o.startswith("-") and not o.startswith("--") and "s" in o[1:]) for o in opts):
                ask(f"`{head}` reads its commands from input the guard can't see.")
            return [[as_path(rest[0])] + rest[1:]]
        if head in {"source", "."}:
            return [[as_path(args[1])] + args[2:]] if len(args) > 1 else []
        if head in EXECUTORS:
            ask(f"`{head}` runs other commands the guard can't follow.")
        break
    return [args] if args else []


def unwrap_all(commands: list[list[str]], depth: int = 0) -> list[list[str]]:
    out: list[list[str]] = []
    for cmd in commands:
        out.extend(unwrap(cmd, depth))
    return out


# --- shared state ------------------------------------------------------------------------

class Context:
    def __init__(self, cwd: str, text: str) -> None:
        self.cwd = cwd
        self.branch_unknown = False
        self.pr_changed = False
        self.subshells = "(" in text
        self._repo: str | None | bool = False
        self._root: str | None | bool = False

    def project_root(self) -> str | None:
        if self._root is False:
            env_root = os.environ.get("CLAUDE_PROJECT_DIR")
            top = git_out(self.cwd, "rev-parse", "--show-toplevel")
            self._root = top or env_root
        return self._root  # type: ignore[return-value]

    def this_repo(self) -> str | None:
        """owner/repo of this checkout's origin, from its URL."""
        if self._repo is False:
            url = git_out(self.cwd, "config", "--get", "remote.origin.url") or ""
            m = re.search(r"github\.com[:/]+([^/\s]+/[^/\s]+?)(\.git)?/?$", url)
            self._repo = m.group(1).lower() if m else None
        return self._repo  # type: ignore[return-value]


def short_flags(opt: str) -> set[str]:
    return set(opt[1:]) if re.match(r"^-[A-Za-z]+$", opt) else set()


def resolve(path: str, ctx: Context) -> str:
    return os.path.normpath(os.path.join(ctx.cwd, os.path.expanduser(path)))


def is_guard_path(path: str, ctx: Context) -> bool:
    full = resolve(path, ctx)
    return bool(GUARD_TEXT_RE.search(full.replace(os.sep, "/") + ("/" if os.path.isdir(full) else "")))


# --- git -------------------------------------------------------------------------------

def git_parts(args: list[str]) -> tuple[list[str], str | None, list[str]]:
    rest = [a for a in args[1:] if not a.startswith(REDIRECT_MARK)]
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
    return git_out(path, "rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{push}")


def push_config_risky(path: str, remote: str | None) -> bool:
    """True when git config would push more than the current branch."""
    default = (git_out(path, "config", "--get", "push.default") or "simple").lower()
    if default not in {"simple", "current", "upstream", "tracking"}:
        return True
    pattern = r"^remote\..*\.(push|mirror)$" if remote is None else rf"^remote\.{re.escape(remote)}\.(push|mirror)$"
    return git_out(path, "config", "--get-regexp", pattern) is not None


def is_tag(path: str, name: str) -> bool:
    if VERSION_LIKE_RE.match(name):
        return True
    res = run(["git", "-C", path, "show-ref", "--verify", "--quiet", f"refs/tags/{name}"], path, timeout=5)
    return res is not None and res.returncode == 0


def check_git(args: list[str], ctx: Context) -> None:
    globals_, sub, rest = git_parts(args)
    if sub is None:
        return
    if any(g.split("=", 1)[0] in GIT_RISKY_GLOBALS for g in globals_) and sub not in GIT_READ_ONLY:
        ask("git runs with configuration or repository overrides the guard can't follow.")
    path = git_path(globals_, ctx)
    if sub not in GIT_KNOWN:
        ask(f"`git {sub}` is an alias, extension or unknown command; the guard can't see what it runs.")
    if sub not in GIT_READ_ONLY and any(is_guard_path(a, ctx) for a in rest if not a.startswith("-") and sub not in {"commit", "tag", "push", "fetch", "pull", "merge", "add", "notes"}):
        ask("This git command could change the guardrails or the quality gate; it needs the maintainer's yes.")
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
        if not rest or any(a in {"dcommit", "set-tree", "tag", "branch", "commit-diff", "init", "clone",
                                 "create-ignore", "propset", "mkdirs"} for a in rest) or \
                [a for a in rest if not a.startswith("-")][:1] not in (["fetch"], ["info"], ["log"], ["find-rev"], ["show-ignore"], ["rebase"]):
            ask("git svn can write to the SVN repository; it needs the maintainer's yes.")
    elif sub in GIT_CHANGES_CURRENT_BRANCH:
        if ctx.branch_unknown:
            ask(f"`git {sub}` runs after a branch switch or cd the guard can't follow in the same command.")
        if sub == "rebase" and any(a in {"-i", "--interactive"} or a.startswith(("--exec", "-x")) or
                                   (re.match(r"^-[A-Za-z]+$", a) and set(a[1:]) & {"x", "i"}) for a in rest):
            ask("An exec or interactive rebase runs commands the guard can't see.")
        positional = [a for a in rest if not a.startswith("-")]
        if sub == "rebase" and any(p in PROTECTED_BRANCHES for p in positional[1:]):
            ask("Rebasing a protected branch needs the maintainer's yes.")
        if sub == "pull" and any(":" in p and p.split(":", 1)[1].removeprefix("refs/heads/") in PROTECTED_BRANCHES for p in positional):
            ask("Pulling into a protected branch needs the maintainer's yes.")
        branch = current_branch(path)
        if branch is None or branch in PROTECTED_BRANCHES:
            ask(f"`git {sub}` would change {branch or 'a branch the guard cannot identify'}; protected branches change only through PRs.")
    elif sub in {"switch", "checkout"}:
        ctx.branch_unknown = True
        flags: set[str] = set()
        for a in rest:
            flags |= short_flags(a)
        if (flags & {"B", "C"} or "--force-create" in rest) and any(a in PROTECTED_BRANCHES for a in rest):
            ask("Resetting a protected branch needs the maintainer's yes.")
    elif sub == "worktree":
        if any(a in {"-B"} for a in rest) and any(a in PROTECTED_BRANCHES for a in rest):
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
        reading = {"--get", "--get-all", "--get-regexp", "-l", "--list", "--show-origin", "--show-scope",
                   "get", "list", "--name-only", "--get-urlmatch", "--get-color", "--get-colorbool"}
        if not any(a in reading for a in rest):
            keys: list[str] = []
            skip = False
            for a in rest:
                if skip:
                    skip = False
                    continue
                if a in {"-f", "--file", "--blob", "--type", "--default", "--comment"}:
                    skip = True
                    continue
                if a.startswith("-"):
                    continue
                keys.append(a)
            if keys and keys[0] in {"set", "unset", "edit", "rename-section", "remove-section"}:
                keys = keys[1:] if keys[0] in {"set", "unset"} else ["*"]
            if not keys or not GIT_CONFIG_SAFE_KEYS.match(keys[0]):
                ask("Changing git settings (other than name, email and display options) needs the maintainer's yes.")
    elif sub == "remote":
        if rest and rest[0] in {"add", "set-url", "rename", "remove", "rm", "set-branches", "set-head"}:
            ask("Changing git remotes needs the maintainer's yes.")
    elif sub in {"submodule", "bisect"}:
        if rest and rest[0] in {"foreach", "run"}:
            ask(f"`git {sub} {rest[0]}` runs commands the guard can't see.")
    elif sub == "apply":
        for a in rest:
            if not a.startswith("-") and os.path.isfile(resolve(a, ctx)):
                with open(resolve(a, ctx), encoding="utf-8", errors="replace") as fh:
                    if GUARD_TEXT_RE.search(fh.read(SCRIPT_SCAN_LIMIT)):
                        ask("This patch changes the guardrails or the quality gate; it needs the maintainer's yes.")
        if not [a for a in rest if not a.startswith("-")]:
            ask("`git apply` reads a patch from input the guard can't see.")


PUSH_SAFE_SHORT = {"u", "q", "v", "n"}
PUSH_SAFE_LONG = {"--set-upstream", "--quiet", "--verbose", "--dry-run", "--porcelain", "--progress",
                  "--no-progress", "--atomic", "--no-atomic"}


def check_git_push(rest: list[str], path: str, ctx: Context) -> None:
    positional: list[str] = []
    skip = False
    after_dashes = False
    for a in rest:
        if skip:
            skip = False
            continue
        if after_dashes:
            positional.append(a)
            continue
        if a == "--":
            after_dashes = True
            continue
        if a in {"-o", "--push-option"}:
            skip = True
            continue
        if a.startswith("--"):
            name = a.split("=", 1)[0]
            if name.startswith(("--forc", "--mi")) or name in {"--force-with-lease", "--force-if-includes"}:
                deny("Force and mirror pushes are never run by Claude Code (AGENTS.md section 3, Guardrails).")
            if name.startswith("--no-v"):
                deny("`--no-verify` skips the pre-push quality gate; Claude Code never uses it.")
            if name.startswith(("--tag", "--fol", "--al", "--br", "--pru", "--del")):
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
    remote = positional[0] if positional else None
    if remote is not None and (not BRANCH_NAME_RE.match(remote) or "/" in remote or remote.startswith(".")):
        ask("Pushing to a URL or path instead of a named remote needs the maintainer's yes.")
    refspecs = positional[1:]
    if not refspecs:
        if ctx.branch_unknown:
            ask("A push without a branch name after a branch switch or cd: the guard can't confirm what it pushes.")
        if push_config_risky(path, remote):
            ask("git is set to push more than the current branch (push.default or remote push/mirror settings).")
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
                ask("`git push HEAD` after a branch switch or cd: the guard can't tell which branch it pushes.")
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
    if branch in PROTECTED_BRANCHES:
        ask(f"Pushing straight to {branch} needs the maintainer's yes (release step, or it changes only through PRs).")
    if is_tag(path, branch):
        ask("Pushing a tag needs the maintainer's yes (release step).")


# --- gh ----------------------------------------------------------------------------------

def gh_split(args: list[str]) -> tuple[str | None, list[str]]:
    repo: str | None = None
    rest: list[str] = []
    i = 1
    while i < len(args):
        a = args[i]
        if a.startswith(REDIRECT_MARK):
            i += 1
            continue
        if a in {"-R", "--repo"}:
            repo = args[i + 1] if i + 1 < len(args) else ""
            i += 2
            continue
        if a.startswith("--repo="):
            repo = a.split("=", 1)[1]
        elif a.startswith("-R") and len(a) > 2:
            repo = a[2:].lstrip("=")
        else:
            rest.append(a)
        i += 1
    return repo, rest


def options(rest: list[str], value_opts: set[str]) -> tuple[list[str], dict[str, list[str]]]:
    """Positional words and option values; handles `--x=y`, `-xy`, `-x=y` and `-x y`."""
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
            values.setdefault(a[:2], []).append(a[2:].removeprefix("="))
        elif a.startswith("-") and len(a) > 1:
            values.setdefault(a, []).append("")
        else:
            positional.append(a)
        i += 1
    return positional, values


def pinned_repo(given: str | None, ctx: Context) -> str:
    """The repository a gh command acts on; asks unless it is this checkout's."""
    here = ctx.this_repo()
    if here is None:
        ask("The guard can't tell which GitHub repository this checkout belongs to.")
    if given is not None and given.lower().removeprefix("https://github.com/").strip("/") != here:
        ask(f"This acts on another repository ({given}); it needs the maintainer's yes.")
    return here  # type: ignore[return-value]


def pr_base(target: str | None, repo: str, cwd: str) -> str | None:
    cmd = ["gh", "pr", "view"] + ([target] if target else []) + ["-R", repo, "--json", "baseRefName", "-q", ".baseRefName"]
    res = run(cmd, cwd, timeout=12)
    if res is None or res.returncode != 0:
        return None
    return res.stdout.strip() or None


GH_SAFE = {
    "pr": {"create", "view", "list", "diff", "checks", "comment", "status", "checkout", "ready", "merge", "edit"},
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
    given, rest = gh_split(args)
    if not rest:
        return
    group = rest[0]
    sub = rest[1] if len(rest) > 1 else ""
    if group == "api":
        check_gh_api(rest[1:], given, ctx)
        return
    allowed = GH_SAFE.get(group, "unknown")
    if allowed == "unknown" or (allowed is not None and sub not in allowed):
        ask(f"`gh {group} {sub}` is not a known-safe GitHub command; it needs the maintainer's yes.")
    if group != "pr" or sub in {"view", "list", "diff", "checks", "status"}:
        return
    repo = pinned_repo(given, ctx)
    positional, values = options(rest[2:], PR_VALUE_OPTS)
    bases = values.get("-B", []) + values.get("--base", [])
    if any(b not in {INTEGRATION_BRANCH} for b in bases):
        ask("A PR into a branch other than develop (for example the release PR into main) needs the maintainer's yes.")
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


def check_gh_api(rest: list[str], given: str | None, ctx: Context) -> None:
    positional, values = options(rest, API_VALUE_OPTS)
    method = (values.get("-X") or values.get("--method") or [""])[-1].lstrip("=").upper()
    fields = values.get("-f", []) + values.get("-F", []) + values.get("--field", []) + values.get("--raw-field", [])
    writes = bool(fields) or "--input" in values or method not in {"", "GET", "HEAD"}
    path = positional[0] if positional else ""
    if "$" in path or "`" in path or "$" in method or "--hostname" in values:
        ask("The API path, method or host comes from something the guard can't resolve.")
    if path.strip("/") == "graphql":
        queries = [f for f in fields if f.startswith("query=")]
        literal_reads = all(("@" not in f and "$" not in f and "`" not in f and
                             re.match(r"^query=\s*(\{|query\b)", f) and "mutation" not in f.lower()) for f in queries)
        if "--input" in values or not queries or not literal_reads:
            ask("A GraphQL request that isn't a plain literal query needs the maintainer's yes.")
        return
    if not writes:
        return
    m = re.match(r"^/?repos/([^/]+/[^/]+)/(.*)$", path)
    if not m:
        ask("Writing to GitHub through the API needs the maintainer's yes.")
    repo = pinned_repo(m.group(1), ctx)
    tail = m.group(2).rstrip("/")
    merge = re.fullmatch(r"pulls/(\d+)/merge", tail)
    if merge and method in {"PUT", ""} and not ctx.pr_changed and "--input" not in values:
        if pr_base(merge.group(1), repo, ctx.cwd) == INTEGRATION_BRANCH:
            return
    if re.fullmatch(r"pulls(/\d+)?", tail):
        ctx.pr_changed = True
    if re.fullmatch(r"(issues|pulls)/\d+/comments", tail) and method in {"POST", ""} and "--input" not in values:
        return
    ask("Writing to GitHub through the API (other than merging a PR into develop or commenting on one) needs the maintainer's yes.")


# --- svn, wp, deploys, remote shells, scripts ---------------------------------------------

def check_svn(args: list[str]) -> None:
    positional: list[str] = []
    skip = False
    revprop = False
    for a in args[1:]:
        if a.startswith(REDIRECT_MARK):
            continue
        if skip:
            skip = False
            continue
        if a == "--revprop":
            revprop = True
            continue
        if a in SVN_VALUE_OPTIONS:
            skip = True
            continue
        if a in {"--targets"}:
            ask("svn reads its targets from a file the guard can't follow.")
        if a.startswith("-"):
            continue
        positional.append(a)
    if not positional:
        return
    sub, targets = positional[0], positional[1:]
    if sub in {"commit", "ci", "import"}:
        ask("SVN commits publish to WordPress.org; they need the maintainer's yes (and his Keychain password).")
    if revprop:
        ask("Changing SVN revision properties needs the maintainer's yes.")
    if sub in SVN_LOCAL_IF_NO_URL:
        if any(SVN_URL_RE.match(t) or "$" in t for t in targets):
            ask("SVN writes to the repository publish to WordPress.org; they need the maintainer's yes.")
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


def scan_file(full: str, why: str) -> None:
    try:
        with open(full, "r", encoding="utf-8", errors="replace") as fh:
            body = fh.read(SCRIPT_SCAN_LIMIT + 1)
    except OSError:
        ask("The guard can't read the file this command runs.")
    if len(body) > SCRIPT_SCAN_LIMIT or matches_outward(body) or GUARD_TEXT_RE.search(body):
        ask(why)


def scan_script(path: str, ctx: Context) -> None:
    """Ask when a new or changed script contains an outward-facing command."""
    if "$" in path or "`" in path:
        ask("The script to run comes from a variable the guard can't resolve.")
    full = resolve(path, ctx)
    if not os.path.isfile(full):
        return
    if committed_unchanged(full, ctx.cwd):
        return  # committed scripts were reviewed in a PR
    scan_file(full, "This command runs a new or changed script that contains an outward-facing command.")


def check_task_runner(head: str, ctx: Context) -> None:
    """npm/composer/make run scripts from files an edit could change: scan changed ones."""
    root = ctx.project_root() or ctx.cwd
    res = run(["git", "-C", root, "status", "--porcelain", "--", "package.json", "composer.json", "Makefile",
               "bin", ".githooks", "tests/e2e/bin"], root, timeout=6)
    if res is None or res.returncode != 0:
        if os.path.isdir(os.path.join(root, ".git")) or os.path.isfile(os.path.join(root, ".git")):
            ask(f"The guard can't check whether `{head}`'s scripts changed.")
        return
    for line in res.stdout.splitlines():
        rel = line[3:].strip().strip('"')
        full = os.path.join(root, rel)
        if os.path.isfile(full):
            scan_file(full, f"`{head}` would run a new or changed script that contains an outward-facing command.")
        elif os.path.isdir(full):
            ask(f"`{head}` would run scripts from a new folder the guard hasn't checked.")


def interpreter_code(base: str, args: list[str]) -> tuple[list[str], str | None, int]:
    """Inline code strings, the script (first positional) and its index."""
    letters = INTERPRETERS.get(base, "")
    codes: list[str] = []
    i = 1
    while i < len(args):
        a = args[i]
        if a.startswith(REDIRECT_MARK):
            i += 1
            continue
        if a.startswith("--"):
            name, eq, val = a.partition("=")
            if name in LONG_CODE_FLAGS:
                if eq:
                    codes.append(val)
                elif i + 1 < len(args):
                    codes.append(args[i + 1])
                    i += 1
                return codes, None, i
            i += 1
            continue
        if a.startswith("-") and len(a) > 1:
            bundle = a[1:]
            for j, ch in enumerate(bundle):
                if ch in letters:
                    rest = bundle[j + 1:]
                    if rest:
                        codes.append(rest)
                    elif i + 1 < len(args):
                        codes.append(args[i + 1])
                        i += 1
                    return codes, None, i
            if base in {"python", "python3"} and bundle in {"m"} and i + 1 < len(args):
                return codes, "-m " + args[i + 1], i + 1
            if a in {"-d", "-W", "-X", "-I", "-r", "--require", "-c", "-n", "-f"} and base in {"php", "node", "python", "python3"} and i + 1 < len(args) and not (a == "-f" and base == "php"):
                i += 2
                continue
            if a == "-f" and base == "php" and i + 1 < len(args):
                return codes, args[i + 1], i + 1
            i += 1
            continue
        return codes, a, i
    return codes, None, i


def check_network_client(head: str, args: list[str]) -> None:
    joined = " ".join(args[1:])
    if re.search(r"(api\.)?github\.com|wordpress\.org|zerospam\.org|svn\.", joined, re.I):
        writing = re.search(r"(^|\s)(-X|--request)\s*=?\s*(POST|PUT|PATCH|DELETE)|(^|\s)(-d|--data\S*|-F|--form\S*|-T|--upload-file|--json|--method)(\s|=|$)|--post-(data|file)", joined, re.I)
        if writing:
            ask(f"`{head}` sends data to GitHub, WordPress.org or zerospam.org; it needs the maintainer's yes.")


def check_program(args: list[str], ctx: Context) -> None:
    targets = [a[len(REDIRECT_MARK):] for a in args if a.startswith(REDIRECT_MARK)]
    args = [a for a in args if not a.startswith(REDIRECT_MARK)]
    for t in targets:
        if "$" in t or "`" in t or is_guard_path(t, ctx):
            ask("This command writes to the guardrails, the quality gate or git's settings; it needs the maintainer's yes.")
    if not args:
        return
    word = args[0]
    head = os.path.basename(word)
    base = re.sub(r"[\d.]+$", "", head)
    if head == "cd":
        operands = [a for a in args[1:] if not a.startswith("-")]
        target = operands[0] if operands else "~"
        if ctx.subshells or "$" in target or "`" in target or target == "-":
            ctx.branch_unknown = True
        else:
            ctx.cwd = resolve(target, ctx)
        return
    if head in {"pushd", "popd"}:
        ctx.branch_unknown = True
        return
    if head in {"svnmucc", "svnrdump", "svnadmin", "svnsync"}:
        ask("SVN repository writes publish to WordPress.org; they need the maintainer's yes.")
    if head in {"ssh", "scp", "sftp", "mosh", "telnet"}:
        ask(f"`{head}` reaches another machine; it needs the maintainer's yes.")
    if head == "rsync" and any(not a.startswith("-") and re.match(r"^([^/\s:]+@)?[^/\s:]+:", a) for a in args[1:]):
        ask("rsync to another machine needs the maintainer's yes.")
    if head in NETWORK_CLIENTS:
        check_network_client(head, args)
    if "deploy" in head.lower():
        ask("Deploys change production; they need the maintainer's yes (and his SSH key).")
    if head in {"wp", "wp-cli.phar"} and wp_remote(args[1:]):
        ask("WP-CLI against a remote site needs the maintainer's yes.")
    if head == "patch":
        files = [a for a in args[1:] if not a.startswith("-")]
        if any(a in {"-i", "--input"} or a.startswith("--input=") for a in args[1:]):
            files = [args[args.index(a) + 1] for a in args[1:] if a in {"-i", "--input"} and args.index(a) + 1 < len(args)]
        if not targets and not files:
            ask("`patch` reads a patch from input the guard can't see.")
    if base in INTERPRETERS:
        codes, script, idx = interpreter_code(base, args)
        for code in codes:
            if matches_outward(code) or GUARD_TEXT_RE.search(code):
                ask(f"Inline {head} code runs an outward-facing command or touches the guardrails; it needs the maintainer's yes.")
        if script is None and not codes and not (set(args[1:]) & INFO_FLAGS):
            ask(f"`{head}` reads its code from input the guard can't see.")
        if script is not None and not script.startswith("-m "):
            rest = args[idx + 1:]
            if os.path.basename(script) in {"wp", "wp-cli.phar"}:
                if wp_remote(rest):
                    ask("WP-CLI against a remote site needs the maintainer's yes.")
            elif script == "-":
                ask(f"`{head}` reads its code from input the guard can't see.")
            else:
                scan_script(script, ctx)
    if head in {"npm", "pnpm", "yarn", "bun", "composer", "make", "npx"}:
        words = [a for a in args[1:] if not a.startswith("-")]
        if any(re.search(r"deploy|release|publish", t, re.I) for t in words):
            ask(f"`{head}` runs a deploy, release or publish task; it needs the maintainer's yes.")
        if head != "npx":
            check_task_runner(head, ctx)
    if "/" in word and head not in READ_ONLY_PROGRAMS and not word.startswith(REDIRECT_MARK):
        scan_script(word, ctx)
    mutating = head not in READ_ONLY_PROGRAMS | {"git", "gh", "svn"} and base not in {"python", "python3"}
    if mutating:
        if any(is_guard_path(a, ctx) for a in args[1:] if not a.startswith("-") or "/" in a):
            ask("This command could change the guardrails or the quality gate (.claude/settings, .claude/hooks, .githooks, .git/config); it needs the maintainer's yes.")
        if GUARD_TEXT_RE.search(resolve(".", ctx).replace(os.sep, "/") + "/"):
            ask("This command runs inside the guardrails folder; it needs the maintainer's yes.")


# --- entry point ----------------------------------------------------------------------

def classify(command: str, cwd: str) -> None:
    text = normalise(command)
    ctx = Context(cwd, text)
    for cmd in unwrap_all(split_commands(text)):
        check_deadline()
        if not cmd:
            continue
        if "<<<" in cmd:
            idx = cmd.index("<<<")
            head = os.path.basename(cmd[0])
            if head in SHELLS or head in INTERPRETERS or head in {"eval", "xargs", "patch"}:
                ask("A here-string feeds commands to a shell or interpreter.")
            cmd = cmd[:idx] + cmd[idx + 2:]
            if not cmd:
                continue
        plain = [a for a in cmd if not a.startswith(REDIRECT_MARK)]
        head = os.path.basename(plain[0]) if plain else ""
        if head == "git":
            check_git(plain, ctx)
        elif head == "gh":
            check_gh(plain, ctx)
        elif head == "svn":
            check_svn(plain)
        check_program(cmd, ctx)


FALLBACK_DENY_RE = re.compile(r"\bgit\b[^\n]*\bpush\b[^\n]*(--forc|--mi|--no-v|\s-[a-zA-Z]*f[a-zA-Z]*\b|\s\+\S)", re.I)
FALLBACK_ASK_RE = re.compile(
    r"\b(push|tag|svn|svnmucc|release|deploy|ssh|scp|sftp|rsync|graphql|update-ref|merge|eval|xargs|curl|wget)\b|"
    r"\bgh\b|\bwp\s+@|--ssh|\.claude|\.githooks|\.git/",
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
            return "deny", "The guard could not parse this command; it contains a force push or skips the quality gate."
        if FALLBACK_ASK_RE.search(command) or matches_outward(command):
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
