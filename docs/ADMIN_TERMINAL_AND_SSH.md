# Admin diagnostic console and privileged SSH operations

The administrator page at `/admin/terminal` is a diagnostic batch interface, not a general-purpose OS shell. It accepts at most 8 independent allowlisted commands, 300 characters per line, 1,800 characters total, and 10 batches per administrator per minute. Each line is dispatched internally; no input is passed to `sh -c`, `bash -c`, or another shell. Existing CSRF, active-admin revalidation, audit, path restrictions, secret redaction, and read-only database diagnostics remain.

The web console does not support shell composition, arbitrary PHP, file writes/deletes, package installation, `sudo`, or `su`. Never paste credentials into it. Tailscale reduces network reachability but does not make an unrestricted web-to-root endpoint safe.

For privileged work, connect directly from a trusted tailnet device using SSH. The SSH client TTY presents the sudo password prompt; that password is not sent to the website, HTTP, PHP session, or audit log.

```bash
ssh <linux-user>@<tailscale-device-name-or-ip>
sudo -v
sudo <specific-approved-command>
```

Use a dedicated non-root Linux account, keep SSH root login disabled, restrict SSH using Tailscale ACLs/grants and the host firewall, keep host-key verification enabled, and grant only the minimum sudo commands. Do not use blanket `NOPASSWD: ALL`.

This repository change does not configure SSH, Tailscale ACLs, firewall, Linux accounts, or sudoers. Host configuration must be reviewed on the actual Kali/XAMPP machine. Preserve the owner's uncommitted local files; do not pull/reset/clean or run the updater until safely reconciled.
