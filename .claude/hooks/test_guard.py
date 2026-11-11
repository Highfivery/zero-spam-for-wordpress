"""Tests for the Claude Code guard hook (.claude/hooks/guard.py).

Run: python3 -m unittest discover -s .claude/hooks -p 'test_*.py'
Standard library only; no network. `git` is stubbed so branch checks are
deterministic, and `gh pr view` is stubbed per test.
"""

import json
import os
import subprocess
import sys
import unittest
from unittest import mock

sys.path.insert(0, os.path.dirname(__file__))
import guard  # noqa: E402


def decide(command, branch="feature/x", pr_base="develop"):
    with mock.patch.object(guard, "current_branch", return_value=branch), mock.patch.object(guard, "pr_base", return_value=pr_base):
        return guard.evaluate(command, "/tmp")[0]


class Blocked(unittest.TestCase):
    def test_svn_commit_forms(self):
        for cmd in [
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
            "svnmucc -m x rm https://plugins.svn.wordpress.org/zero-spam/tags/1.0",
        ]:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "deny")

    def test_force_pushes(self):
        for cmd in [
            "git push --force origin feature/x",
            "git push -f",
            "git push --force-with-lease origin feature/x",
            "git push --force-with-lease=feature/x origin feature/x",
            "git push origin +feature/x",
            "git -C . push --force origin develop",
            "git push --mirror",
        ]:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "deny")


class Asks(unittest.TestCase):
    def test_main_and_tags(self):
        for cmd in [
            "git push origin main",
            "git push origin HEAD:main",
            "git push origin feature/x:refs/heads/main",
            "git -C /repo push origin main",
            "git -c push.default=current push origin main",
            "git 'push' origin main",
            "git push --tags",
            "git push --follow-tags origin develop",
            "git push origin v6.0.1",
            "git push origin refs/tags/v6.0.1",
            "git push origin :feature/old",
            "git push --delete origin v6.0.0",
            "git tag v6.0.1",
            "git tag -a v6.0.1 -m 'Release'",
            "git tag -d v6.0.1",
            "git -c alias.p=push p origin main",
        ]:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "ask")

    def test_disguised_forms(self):
        for cmd in [
            "git push origin develop && git push origin main",
            "(git push origin main)",
            "git status\ngit push origin main",
            "GIT_DIR=.git git push origin main",
            "git push origin develop main",
            "echo x | xargs git push origin main",
            "printf 'git push origin main' | sh",
            "python3 -c \"import os; os.system('git push origin main')\"",
            "node -e \"require('child_process').execSync('git push origin main')\"",
            "php -r \"system('git tag v1');\"",
            "eval 'git push origin main'",
            "nohup git push origin main &",
            "for b in main; do git push origin $b; done",
            "if true; then git push origin main; fi",
            "git push origin ${BRANCH}",
        ]:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "ask")
        for cmd in [
            "cd trunk; svn commit -m x",
            "node -e \"require('child_process').execSync('svn commit -m x')\"",
        ]:
            with self.subTest(cmd=cmd):
                self.assertIn(decide(cmd), {"ask", "deny"})

    def test_push_from_main_without_refspec(self):
        self.assertEqual(decide("git push", branch="main"), "ask")
        self.assertEqual(decide("git push origin HEAD", branch="main"), "ask")
        self.assertEqual(decide("git push", branch=None), "ask")

    def test_local_main_changes(self):
        self.assertEqual(decide("git merge develop", branch="main"), "ask")
        self.assertEqual(decide("git commit -m x", branch="main"), "ask")
        self.assertEqual(decide("git branch -D main"), "ask")

    def test_github(self):
        self.assertEqual(decide("gh pr merge 490 --merge", pr_base="main"), "ask")
        self.assertEqual(decide("gh pr merge 490 --merge", pr_base=None), "ask")
        self.assertEqual(decide("gh pr create --base main --title Release"), "ask")
        self.assertEqual(decide("gh pr create --base=main"), "ask")
        self.assertEqual(decide("gh release create v6.0.1"), "ask")
        self.assertEqual(decide("gh api repos/o/r/pulls/490/merge --method PUT -f merge_method=merge", pr_base="main"), "ask")
        self.assertEqual(decide("gh api repos/o/r/git/refs -f ref=refs/tags/v6.0.1 -f sha=abc"), "ask")
        self.assertEqual(decide("gh api repos/o/r/releases -X POST -f tag_name=v6.0.1"), "ask")
        self.assertEqual(decide("gh api -X PUT repos/o/r/branches/main/protection"), "ask")

    def test_deploys_and_remote(self):
        for cmd in [
            "bin/deploy.sh",
            "./bin/deploy.sh --site",
            "bash bin/deploy.sh",
            "ssh user@host 'wp cache flush'",
            "scp file user@host:/tmp/",
            "rsync -a build/ user@host:/var/www/",
            "wp @production plugin list",
        ]:
            with self.subTest(cmd=cmd):
                self.assertEqual(decide(cmd), "ask")


class Allowed(unittest.TestCase):
    def test_routine_work_gets_no_decision(self):
        for cmd in [
            "git push -u origin fix/ipstack-https",
            "git push origin develop:develop",
            "git push origin chore/agent-guardrails",
            "git tag -l",
            "git tag --list 'v6*'",
            "git tag --contains HEAD",
            "git commit -m 'fix(ipstack): use https'",
            "git merge origin/develop",
            "git fetch origin main",
            "git checkout main",
            "git log main..develop",
            "git diff origin/main",
            "svn status",
            "svn diff",
            "svn up",
            "svn cp trunk tags/6.0.1",
            "gh pr create --base develop --title x",
            "gh pr view 483",
            "gh release list",
            "gh api repos/o/r/pulls/483",
            "composer check",
            "npm run test:e2e:wp-env",
            "rsync -a --exclude-from=.distignore ./ /tmp/trunk/",
            "grep -rn deploy README.md",
            "echo 'git push origin main'",
            "cat bin/deploy.sh",
            "python3 -c 'print(1)'",
            "bash -lc 'composer check'",
            "while read l; do echo $l; done < f",
        ]:
            with self.subTest(cmd=cmd):
                self.assertIsNone(decide(cmd))

    def test_pr_merge_into_develop(self):
        self.assertIsNone(decide("gh pr merge 490 --merge", pr_base="develop"))
        self.assertIsNone(decide("gh api repos/o/r/pulls/490/merge --method PUT -f merge_method=merge", pr_base="develop"))


class Hook(unittest.TestCase):
    def run_hook(self, payload):
        out = subprocess.run(
            [sys.executable, os.path.join(os.path.dirname(__file__), "guard.py")],
            input=payload, capture_output=True, text=True, timeout=30, check=False,
        )
        return out

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
        out = self.run_hook("not json")
        self.assertEqual(json.loads(out.stdout)["hookSpecificOutput"]["permissionDecision"], "ask")

    def test_unparseable_outward_command_asks(self):
        out = self.run_hook(json.dumps({"tool_name": "Bash", "tool_input": {"command": "svn commit -m 'unterminated"}, "cwd": "/tmp"}))
        self.assertIn(json.loads(out.stdout)["hookSpecificOutput"]["permissionDecision"], {"ask", "deny"})


if __name__ == "__main__":
    unittest.main()
