#!/usr/bin/env python3
"""Safe one-click fast-forward updater for the local Zayan Hardware checkout.

Preserves the one approved XAMPP-only .htaccess RewriteBase override.
Untracked local files are left untouched; incoming path collisions fail safely.
Never resets, cleans, stashes, switches branches, runs migrations, or edits app data.
"""
from __future__ import annotations

import argparse
import datetime as dt
import os
import subprocess
import sys
import tempfile
from pathlib import Path

EXPECTED_REPO = "abdelhamidzayan/ZayanHardwareWebsite"
REMOTE_BRANCH = "main"
LOCAL_REWRITE_BASE = b"RewriteBase /zayan_hardware/"
HTACCESS = ".htaccess"


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
        raise UpdateError("تعذر تحديد مجلد مشروع Zayan Hardware.")
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


def local_htaccess_override(repo: Path) -> bytes | None:
    """Return the exact local file when its only delta is our XAMPP RewriteBase."""
    status = git(repo, "status", "--porcelain=v1", "--untracked-files=no").stdout.splitlines()
    changed = [line[3:] for line in status]
    if not changed:
        return None
    if changed != [HTACCESS]:
        raise UpdateError(
            "توجد تعديلات محلية متتبعة خارج إعداد RewriteBase المسموح: "
            + ", ".join(changed)
        )

    staged = git(repo, "diff", "--cached", "--name-only").stdout.strip()
    if staged:
        raise UpdateError("يوجد تعديل staged محلي؛ أوقف التحديث لحمايته.")

    local_path = repo / HTACCESS
    head_content = git(repo, "show", f"HEAD:{HTACCESS}").stdout.encode("utf-8")
    local_content = local_path.read_bytes()
    marker = LOCAL_REWRITE_BASE + b"\n"
    if local_content.count(marker) != 1:
        raise UpdateError("تعديل .htaccess لا يطابق RewriteBase المحلي المتوقع؛ لم ألمس الملف.")

    normalized = local_content.replace(marker, b"", 1)
    if normalized != head_content:
        raise UpdateError("يحتوي .htaccess على تعديلات أخرى بجانب RewriteBase؛ أوقف التحديث لحمايتها.")
    return local_content


def compose_local_htaccess(remote_content: bytes, saved_local: bytes | None) -> bytes | None:
    if saved_local is None:
        return None
    marker = LOCAL_REWRITE_BASE + b"\n"
    if remote_content.count(marker) > 1:
        raise UpdateError("نسخة GitHub من .htaccess تحتوي RewriteBase مكررًا.")
    if marker in remote_content:
        return remote_content
    if b"RewriteBase " in remote_content:
        raise UpdateError("نسخة GitHub غيّرت RewriteBase؛ يلزم فحص التعارض قبل التحديث.")
    anchor = b"RewriteEngine On\n"
    if remote_content.count(anchor) != 1:
        raise UpdateError("تعذر تحديد مكان آمن لإضافة RewriteBase إلى .htaccess الجديد.")
    return remote_content.replace(anchor, anchor + marker, 1)


def backup_git_refs(repo: Path, branch: str) -> Path:
    stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S")
    backup_dir = Path.home() / ".local/share/zayan-hardware-update/backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    safe_branch = branch.replace("/", "-").replace("\\", "-")
    bundle = backup_dir / f"before-update-{safe_branch}-{stamp}.bundle"
    git(repo, "bundle", "create", str(bundle), "--all")
    git(repo, "bundle", "verify", str(bundle))
    return bundle


def atomic_write(path: Path, content: bytes) -> None:
    mode = path.stat().st_mode & 0o777
    fd, temp_name = tempfile.mkstemp(prefix=".htaccess-update-", dir=str(path.parent))
    try:
        with os.fdopen(fd, "wb") as handle:
            handle.write(content)
            handle.flush()
            os.fsync(handle.fileno())
        os.chmod(temp_name, mode)
        os.replace(temp_name, path)
    except Exception:
        try:
            os.unlink(temp_name)
        except FileNotFoundError:
            pass
        raise


def main() -> int:
    parser = argparse.ArgumentParser(description="تحديث آمن بنقرة واحدة من GitHub main.")
    parser.add_argument("--dry-run", action="store_true",
                        help="يعرض التحديث المتاح دون تغيير ملفات المشروع.")
    args = parser.parse_args()

    try:
        repo = locate_repo()
        verify_remote(repo)
        branch = git(repo, "branch", "--show-current").stdout.strip()
        if not branch:
            raise UpdateError("المستودع في وضع detached HEAD؛ لم يتم التحديث.")

        saved_htaccess = local_htaccess_override(repo)
        git(repo, "fetch", "origin", REMOTE_BRANCH)
        target_ref = f"origin/{REMOTE_BRANCH}"

        ancestor = git(repo, "merge-base", "--is-ancestor", "HEAD", target_ref, check=False)
        if ancestor.returncode:
            raise UpdateError(
                f"الفرع المحلي {branch} ليس سلفًا لـ {target_ref}. "
                "لن أدمج أو أستبدل أي commits محلية."
            )

        old = git(repo, "rev-parse", "HEAD").stdout.strip()
        new = git(repo, "rev-parse", target_ref).stdout.strip()
        if old == new:
            print("نسختك محدثة بالفعل.")
            return 0

        remote_htaccess = git(repo, "show", f"{target_ref}:{HTACCESS}").stdout.encode("utf-8")
        merged_htaccess = compose_local_htaccess(remote_htaccess, saved_htaccess)

        count = git(repo, "rev-list", "--count", f"HEAD..{target_ref}").stdout.strip()
        print(f"التحديثات المتاحة: {count} commit(s) | {old[:12]} -> {new[:12]}")
        if args.dry_run:
            print("معاينة فقط؛ لم يتم تعديل ملفات المشروع.")
            return 0

        bundle = backup_git_refs(repo, branch)
        print(f"نسخة Git الاحتياطية: {bundle}")
        if saved_htaccess is not None:
            backup_dir = bundle.parent
            backup_file = backup_dir / f"{bundle.stem}-htaccess"
            backup_file.write_bytes(saved_htaccess)
            print(f"نسخة إعداد XAMPP الاحتياطية: {backup_file}")

            # Temporarily restore the exact tracked version so a future upstream
            # .htaccess edit cannot make Git refuse the fast-forward.
            tracked_htaccess = git(repo, "show", f"HEAD:{HTACCESS}").stdout.encode("utf-8")
            atomic_write(repo / HTACCESS, tracked_htaccess)

        try:
            git(repo, "merge", "--ff-only", target_ref)
        except Exception:
            if saved_htaccess is not None:
                atomic_write(repo / HTACCESS, saved_htaccess)
            raise

        if merged_htaccess is not None:
            try:
                atomic_write(repo / HTACCESS, merged_htaccess)
                print("تم الحفاظ على RewriteBase /zayan_hardware/ المحلي.")
            except OSError:
                # Keep the user's working configuration recoverable even if
                # the final atomic write fails after the code fast-forward.
                if saved_htaccess is not None:
                    atomic_write(repo / HTACCESS, saved_htaccess)
                raise

        print("\nتم تحديث ملفات المشروع بنجاح.")
        print("لم يتم تشغيل migrations أو تعديل قاعدة البيانات أو المنشورات أو uploads.")
        print(git(repo, "status", "--short", "--branch").stdout.rstrip())
        return 0

    except UpdateError as exc:
        print(f"لم يتم التحديث: {exc}", file=sys.stderr)
        return 1
    except (OSError, subprocess.SubprocessError) as exc:
        print(f"تعذر إكمال التحديث بأمان: {exc}", file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        print("تم الإيقاف. لم ينفذ السكريبت reset أو clean.", file=sys.stderr)
        return 130


if __name__ == "__main__":
    raise SystemExit(main())
