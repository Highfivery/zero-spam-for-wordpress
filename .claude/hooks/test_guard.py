"""Tests for the Claude Code guard hook (.claude/hooks/guard.py).

Run: python3 -m unittest discover -s .claude/hooks -p 'test_*.py'
Standard library only; no network. Every lookup the guard makes (current
branch, push destination, tags, aliases, PR bases, script status) is stubbed,
so results don't depend on the machine.
"""

import json
import os
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

sys.path.insert(0, os.path.dirname(__file__))
import guard  # noqa: E402


def decide(command, branch="feature/x", destination="origin/feature/x", pr_base="develop",
           tags=(), aliases=None, committed=True, cwd="/tmp"):
    aliases = aliases or {}

    def fake_git_out(path, *args):
        if args[:2] == ("config", "--get") and args[2].startswith("alias."):
            return aliases.get(args[2][6:])
        return None

    with mock.patch.object(guard, "current_branch", return_value=branch), \
            mock.patch.object(guard, "push_destination", return_value=destination), \
            mock.patch.object(guard, "pr_base", return_value=pr_base), \
            mock.patch.object(guard, "is_tag", side_effect=lambda p, n: bool(guard.VERSION_LIKE_RE.match(n)) or n in tags), \
            mock.patch.object(guard, "git_out", side_effect=fake_git_out), \
            mock.patch.object(guard, "committed_unchanged", return_value=committed):
        return guard.evaluate(command, cwd)[0]


class Denied(unittest.TestCase):
    CASES = [
        "svn commit -m 'Release 6.0.1'",
        "svn ci -m x",
        "cd trunk && svn commit",
        "svn --username bm commit -m x",
        "/usr/bin/svn commit -m x",
        "bash -c 'svn commit -m x'",
        "sh -lc \"cd /tmp && svn ci\"",
        "env LANG=C svn commit -m x",
        "timeout 60 svn commit -m x",
        "echo $(svn commit -m x)",
        "svn import . https://plugins.svn.wordpress.org/zero-spam/trunk -m x",
        "svn cp https://plugins.svn.wordpress.org/zero-spam/trunk https://plugins.svn.wordpress.org/zero-spam/tags/6.0.1 -m x",
        "svn cp ^/trunk ^/tags/6.0.1 -m 'Tag 6.0.1'",
        "svn mv ^/a ^/b -m x",
        "svn propedit svn:ignore https://plugins.svn.wordpress.org/zero-spam/trunk -m x",
        "svn propset --revprop -r 5 svn:log x",
        "svnmucc -m x rm https://plugins.svn.wordpress.org/zero-spam/tags/1.0",
        "git svn dcommit",
        "git svn tag 6.0.1",
        "git push --force origin feature/x",
        "git push -f",
        "git push -uf origin fix/x",
        "git push -fu origin fix/x",
        "git push --force-with-lease origin feature/x",
        "git push --force-with-lease=feature/x origin feature/x",
        "git push origin +feature/x",
        "git -C . push --force origin feature/x",
        "git push --mirror",
        "# release step\nsvn commit -m x",
        "svn \\\n  commit -m x",
    ]

    def test_denied(self):
        for cmd in self.CASES:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "deny")


class Asked(unittest.TestCase):
    CASES = [
        # main, develop and tags
        "git push origin main",
        "git push origin HEAD:main",
        "git push origin feature/x:refs/heads/main",
        "git -C /repo push origin main",
        "git -c push.default=current push origin main",
        "git 'push' origin main",
        "git push origin develop",
        "git push origin develop:develop",
        "git push --tags",
        "git push --follow-tags origin feature/x",
        "git push --branches origin",
        "git push --all origin",
        "git push origin v6.0.1",
        "git push origin 6.0.1",
        "git push origin tag 6.0.1",
        "git push origin refs/tags/v6.0.1",
        "git push origin 'refs/heads/*:refs/heads/*'",
        "git push origin :feature/old",
        "git push --delete origin v6.0.0",
        "git push -d origin feature/old",
        "git push --no-verify origin feature/x",
        "git push https://github.com/x/y feature/x",
        "git tag v6.0.1",
        "git tag -a v6.0.1 -m 'Release'",
        "git tag -d v6.0.1",
        "git update-ref refs/heads/main HEAD",
        "git fetch origin develop:main",
        "git checkout -B main origin/develop",
        "git switch -C main",
        "git branch -D main",
        "git branch -u origin/main",
        "git config alias.p 'push --force'",
        "git config remote.origin.mirror true",
        "git config core.hooksPath /dev/null",
        "git remote set-url origin https://example.com/x.git",
        # comments, continuations and other forms the first version missed
        "# release step\ngit push origin main",
        "git status  # check\ngit tag v6.0.1",
        "open https://github.com/x#c && git push origin main",
        "git push origin \\\n  HEAD:main",
        "gh api repos/o/r/merges \\\n  -f base=main -f head=develop",
        "git -c remote.origin.mirror=true push origin",
        "git -c push.default=matching push origin",
        "git -c alias.p=push p origin main",
        "git origin-main",
        # branch context
        "git switch main && git merge develop",
        "git switch main && git pull origin develop",
        "GIT_DIR=../other/.git git push",
        "git --git-dir=../other/.git push",
        # wrappers, variables and substitutions
        "git push origin develop && git push origin main",
        "(git push origin main)",
        "git status\ngit push origin main",
        "GIT_DIR=.git git push origin main",
        "git push origin feature/x main",
        "echo x | xargs git push origin main",
        "echo main | xargs -I % git push origin %",
        "printf 'git push origin main' | sh",
        "bash <<<'git push origin main'",
        "python3 -c \"import os; os.system('git push origin main')\"",
        "node -e \"require('child_process').execSync('git push origin main')\"",
        "php -r \"system('git tag v1');\"",
        "eval 'git push origin main'",
        "CMD='git push origin main'; eval \"$CMD\"",
        "bash -c \"$CMD\"",
        "G=git; $G push origin main",
        "\"$(echo git)\" push origin main",
        "nohup git push origin main &",
        "for b in main; do git push origin $b; done",
        "if true; then git push origin main; fi",
        "git push origin ${BRANCH}",
        "caffeinate -i git push origin main",
        "env -i git push origin main",
        "env -C /repo git push origin main",
        "env -S 'git push origin main'",
        "cat <(git push origin main)",
        ">log git push origin main",
        "sudo -s",
        # GitHub
        "gh release create v6.0.1",
        "gh release delete v6.0.0 --yes",
        "gh pr create --base main --title Release",
        "gh pr create --base=main",
        "gh pr create -Bmain",
        "gh pr edit 45 --base main",
        "gh api repos/o/r/git/refs -f ref=refs/tags/v6.0.1 -f sha=abc",
        "gh api repos/o/r/releases -X POST -f tag_name=v6.0.1",
        "gh api -X PUT repos/o/r/branches/main/protection",
        "gh api repos/o/r/merges --field=base=main --field=head=develop",
        "gh api repos/o/r/merges -fbase=main -fhead=develop",
        "gh api graphql -f query='mutation{mergePullRequest(input:{pullRequestId:\"x\"}){clientMutationId}}'",
        "gh api -X PUT repos/o/r/contents/readme.txt -f branch=main -f message=x -f content=eA==",
        "gh api -X POST repos/o/r/branches/main/rename -f new_name=x",
        "gh api -X PATCH repos/o/r -f default_branch=x",
        "gh api -X POST repos/o/r/pulls -f base=main -f head=develop",
        "gh api -X PATCH repos/o/r/pulls/45 --input body.json && gh pr merge 45",
        "gh issue create --title x",
        "gh repo edit --default-branch main",
        "gh secret set TOKEN",
        "gh workflow run x",
        # deploys, other machines, remote WP-CLI
        "bin/deploy.sh",
        "./bin/deploy.sh --site",
        "bash bin/deploy.sh",
        "ssh user@host 'wp cache flush'",
        "scp file user@host:/tmp/",
        "rsync -a build/ user@host:/var/www/",
        "wp @production plugin list",
        "php -d mysqli.default_socket=/tmp/s /opt/homebrew/bin/wp --ssh=user@host plugin list",
        "wp --http=https://example.com plugin list",
        "npm run deploy",
        "make deploy",
        "composer release",
        "npm publish",
        # the guardrails themselves
        "sed -i 's/ask/allow/' .claude/settings.json",
        "echo '{}' > .claude/settings.local.json",
        "cp /tmp/guard.py .claude/hooks/guard.py",
        "rm .claude/hooks/guard.py",
        "git checkout HEAD~1 -- .claude/hooks/guard.py",
        "python3 -c \"open('.claude/settings.json','w').write('{}')\"",
    ]

    def test_asked(self):
        for cmd in self.CASES:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "ask")

    def test_on_protected_branches(self):
        self.assertEqual(decide("git push", branch="main", destination="origin/main"), "ask")
        self.assertEqual(decide("git push origin HEAD", branch="main"), "ask")
        self.assertEqual(decide("git push", destination=None), "ask")
        self.assertEqual(decide("git push", destination="origin/main"), "ask")
        self.assertEqual(decide("git merge develop", branch="main"), "ask")
        self.assertEqual(decide("git commit -m x", branch="develop"), "ask")
        self.assertEqual(decide("git pull", branch="main"), "ask")
        self.assertEqual(decide("git reset --hard origin/main", branch=None), "ask")

    def test_github_merges_outside_develop(self):
        self.assertEqual(decide("gh pr merge 490 --merge", pr_base="main"), "ask")
        self.assertEqual(decide("gh pr merge 490 --merge", pr_base=None), "ask")
        self.assertEqual(decide("gh pr merge 490 --admin", pr_base="develop"), "ask")
        self.assertEqual(decide("gh api repos/o/r/pulls/490/merge --method PUT -f merge_method=merge", pr_base="main"), "ask")
        self.assertEqual(decide("gh api --method=PUT repos/o/r/pulls/45/merge", pr_base="main"), "ask")

    def test_tags_without_a_v(self):
        self.assertEqual(decide("git push origin release-6", tags=("release-6",)), "ask")

    def test_aliases(self):
        self.assertEqual(decide("git pom", aliases={"pom": "push origin main"}), "ask")

    def test_changed_scripts(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = os.path.join(tmp, "release.sh")
            with open(path, "w") as fh:
                fh.write("#!/bin/sh\ngit push origin main\n")
            self.assertEqual(decide("./release.sh", committed=False, cwd=tmp), "ask")
            self.assertEqual(decide("bash release.sh", committed=False, cwd=tmp), "ask")
            self.assertIsNone(decide("./release.sh", committed=True, cwd=tmp))


class Allowed(unittest.TestCase):
    CASES = [
        "git push -u origin fix/ipstack-https",
        "git push origin chore/agent-guardrails",
        "git push",
        "git push origin HEAD",
        "git push -u origin $(git branch --show-current) # asks: variable",
        "git tag",
        "git tag -l",
        "git tag --list 'v6*'",
        "git tag --contains HEAD",
        "git commit -m 'fix(ipstack): use https'",
        "git commit -m \"$(cat <<'EOF'\nfix(x): don't push\n\nCo-Authored-By: x\nEOF\n)\"",
        "git merge origin/develop",
        "git fetch origin main",
        "git fetch origin develop",
        "git checkout main",
        "git log main..develop",
        "git diff origin/main",
        "git status",
        "git worktree add .claude/worktrees/fix-x -b fix/x origin/develop",
        "cd .claude/worktrees/fix-x && git push -u origin fix/x",
        "git config --get alias.x",
        "git config user.name 'Claude'",
        "svn status",
        "svn diff",
        "svn up",
        "svn cp trunk tags/6.0.1",
        "svn add --force trunk/*",
        "gh pr create --base develop --title x",
        "gh pr view 483",
        "gh pr list",
        "gh pr merge 490 --merge",
        "gh pr comment 490 --body 'Done'",
        "gh release list",
        "gh api repos/o/r/pulls/483",
        "gh api repos/o/r/pulls/483/merge --method PUT -f merge_method=merge",
        "gh api repos/o/r/issues/483/comments -f body=x",
        "gh api graphql -f query='{ viewer { login } }'",
        "composer check",
        "timeout 600 composer check",
        "npm run test:e2e:wp-env",
        "npm ci",
        "rsync -a --exclude-from=.distignore ./ /tmp/trunk/",
        "grep -rn deploy README.md",
        "echo 'git push origin main'",
        "cat bin/deploy.sh",
        "cat .claude/settings.json",
        "python3 -m unittest discover -s .claude/hooks -p 'test_*.py'",
        "python3 -c 'print(1)'",
        "bash -lc 'composer check'",
        "while read l; do echo $l; done < f",
        "ssh-add -l",
        "bin/test.sh --mode=all",
        "bin/check-dist.sh",
        "wp plugin list --path=/tmp/wp",
    ]

    def test_allowed(self):
        for cmd in self.CASES:
            if cmd.endswith("# asks: variable"):
                continue
            with self.subTest(cmd=cmd):
                self.assertIsNone(decide(cmd))

    def test_variable_push_target_asks(self):
        self.assertEqual(decide("git push -u origin $(git branch --show-current)"), "ask")


class Hook(unittest.TestCase):
    def run_hook(self, payload):
        return subprocess.run(
            [sys.executable, os.path.join(os.path.dirname(__file__), "guard.py")],
            input=payload, capture_output=True, text=True, timeout=60, check=False,
        )

    def test_denial_is_reported_as_json(self):
        out = self.run_hook(json.dumps({"tool_name": "Bash", "tool_input": {"command": "svn commit -m x"}, "cwd": "/tmp"}))
        self.assertEqual(out.returncode, 0)
        body = json.loads(out.stdout)["hookSpecificOutput"]
        self.assertEqual(body["hookEventName"], "PreToolUse")
        self.assertEqual(body["permissionDecision"], "deny")
        self.assertIn("maintainer", body["permissionDecisionReason"])

    def test_silent_for_routine_and_other_tools(self):
        self.assertEqual(self.run_hook(json.dumps({"tool_name": "Bash", "tool_input": {"command": "composer check"}, "cwd": "/tmp"})).stdout, "")
        self.assertEqual(self.run_hook(json.dumps({"tool_name": "Read", "tool_input": {"file_path": "x"}})).stdout, "")

    def test_broken_input_asks(self):
        for payload in ["not json", "[]", json.dumps({"tool_name": "Bash", "tool_input": {"command": 5}})]:
            with self.subTest(payload=payload):
                out = self.run_hook(payload)
                self.assertEqual(json.loads(out.stdout)["hookSpecificOutput"]["permissionDecision"], "ask")

    def test_unparseable_keeps_deny(self):
        self.assertEqual(decide("svn commit -m 'unterminated"), "deny")
        self.assertEqual(decide("git push --force origin 'x"), "deny")
        self.assertEqual(decide("git tag v6 'x"), "ask")
        self.assertEqual(decide("gh api repos/o/r/merges -f 'x"), "ask")

    def test_deadline_asks(self):
        with mock.patch.object(guard, "DEADLINE_SECONDS", -1):
            self.assertEqual(guard.evaluate("git push", "/tmp")[0], "ask")


if __name__ == "__main__":
    unittest.main()
