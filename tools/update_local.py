#!/usr/bin/env python3
"""Safe fast-forward updater for a local Zayan Hardware Git checkout.

It never resets, cleans, stashes, switches branches, runs migrations, or edits
application data. Dirty or divergent checkouts are refused without modification.
"""
from __future__ import annotations

import argparse
import datetime as dt
import subprocess
import sys
from pathlib import Path

EXPECTED_REPO = "abdelhamidzayan/ZayanHardwareWebsite"
REMOTE_BRANCH = "main"


class UpdateError(Exception):
    pass


def git(repo: Path, *args: str, check: bool = True) -> subprocess.CompletedProcess:
    result = subprocess.run(
        ["git", *args], cwd=repo, text=True,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False,
    )
    if check and result.returncode:
        raise UpdateError(result.stderr.strip() or result.stdout.strip() or "Git command failed")
    return result


def locate_repo() -> Path:
    result = subprocess.run(
        ["git", "rev-parse", "--show-toplevel"], cwd=Path.cwd(),
        text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    if result.returncode:
        raise UpdateError("شغّل الملف من داخل مجلد مشروع Zayan Hardware.")
    return Path(result.stdout.strip()).resolve()


def verify_remote(repo: Path) -> None:
    url = git(repo, "remote", "get-url", "origin").stdout.strip()
    normalized = url.removesuffix(".git").rstrip("/")
    allowed = {
        f"https://github.com/{EXPECTED_REPO}",
        f"http://github.com/{EXPECTED_REPO}",
        f"git@github.com:{EXPECTED_REPO}",
        f"ssh://git@github.com/{EXPECTED_REPO}",
    }
    if normalized not in allowed:
        raise UpdateError("رابط origin لا يطابق مستودع Zayan Hardware؛ أوقفت العملية.")


def backup_git_refs(repo: Path, branch: str) -> Path:
    stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    backup_dir = Path.home() / ".local/share/zayan-hardware-update/backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    safe_branch = branch.replace("/", "-").replace("\\", "-")
    bundle = backup_dir / f"before-update-{safe_branch}-{stamp}.bundle"
    git(repo, "bundle", "create", str(bundle), "--all")
    git(repo, "bundle", "verify", str(bundle))
    return bundle


def main() -> int:
    parser = argparse.ArgumentParser(description="تحديث محلي آمن من GitHub main.")
    parser.add_argument("--dry-run", action="store_true",
                        help="يعرض التحديث المتاح دون تغيير ملفات المشروع.")
    args = parser.parse_args()

    try:
        repo = locate_repo()
        verify_remote(repo)

        branch = git(repo, "branch", "--show-current").stdout.strip()
        if not branch:
            raise UpdateError("المستودع في وضع detached HEAD؛ لم يتم التحديث.")

        status = git(repo, "status", "--porcelain=v1", "--untracked-files=all").stdout
        if status.strip():
            print("توقّف آمن: توجد تعديلات محلية أو ملفات غير متتبعة.")
            print("لم ألمس أي ملف. احفظ التغييرات وراجعها قبل التحديث.")
            print(status.rstrip())
            return 2

        git(repo, "fetch", "origin", REMOTE_BRANCH)
        target_ref = f"origin/{REMOTE_BRANCH}"
        ancestor = git(repo, "merge-base", "--is-ancestor", "HEAD", target_ref, check=False)
        if ancestor.returncode:
            raise UpdateError(
                f"الفرع {branch} متفرع أو يحتوي commits محلية غير موجودة في {target_ref}. "
                "لن أدمج أو أستبدل أي تغييرات."
            )

        old = git(repo, "rev-parse", "HEAD").stdout.strip()
        new = git(repo, "rev-parse", target_ref).stdout.strip()
        if old == new:
            print("نسختك محدثة بالفعل.")
            return 0

        count = git(repo, "rev-list", "--count", f"HEAD..{target_ref}").stdout.strip()
        print(f"الفرع: {branch} | التحديثات المتاحة: {count} commit(s)")
        print(f"من {old[:12]} إلى {new[:12]}")
        if args.dry_run:
            print("معاينة فقط؛ لم يتم تعديل ملفات المشروع.")
            return 0

        bundle = backup_git_refs(repo, branch)
        print(f"نسخة Git الاحتياطية: {bundle}")
        git(repo, "merge", "--ff-only", target_ref)

        print("\nتم تحديث ملفات المشروع بنجاح.")
        print("لم يتم تشغيل migrations أو تعديل قاعدة البيانات أو uploads.")
        print(git(repo, "status", "--short", "--branch").stdout.rstrip())
        print("\nاختبر الموقع محليًا قبل استخدامه.")
        return 0

    except UpdateError as exc:
        print(f"لم يتم التحديث: {exc}", file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        print("تم الإيقاف. لم ينفذ السكربت reset أو clean.", file=sys.stderr)
        return 130


if __name__ == "__main__":
    raise SystemExit(main())
