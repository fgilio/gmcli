---
name: gmcli
description: >
  Gmail CLI tool for managing email from the command line.
  Trigger words: gmail, email, gmcli, mail.
---

# gmcli - Gmail CLI

## Quick Reference

| Command | Purpose |
|---------|---------|
| `gmcli accounts credentials <file.json>` | Set OAuth credentials |
| `gmcli accounts list` | List configured account |
| `gmcli accounts add <email>` | Add Gmail account via OAuth |
| `gmcli accounts remove <email>` | Remove account |
| `gmcli search "<query>"` | Search threads |
| `gmcli thread <id>` | View thread messages |
| `gmcli labels list` | List all labels |
| `gmcli labels <ids...> --add/--remove` | Modify thread labels |
| `gmcli drafts list` | List drafts |
| `gmcli drafts create --to --subject --body` | Create draft |
| `gmcli send --to --subject --body` | Send email |
| `gmcli url <ids...>` | Generate Gmail web URLs |

Email is optional when an account is configured. Use `gmcli <email> <cmd>` to override.

## Setup

Personal use:
```bash
gmcli accounts credentials ~/path/to/client_secret.json
gmcli accounts add you@gmail.com
```

Team use (credentials in `.env` next to binary):
```bash
gmcli accounts add you@gmail.com
```

## Usage Examples

```bash
# Search unread emails
gmcli search "in:inbox is:unread"

# View thread with attachments
gmcli thread 19aea1f2f3532db5 --download

# Send email
gmcli send --to "recipient@example.com" \
    --subject "Hello" --body "Message body"

# Reply to thread
gmcli send --to "recipient@example.com" \
    --subject "Re: Hello" --body "Reply text" \
    --reply-to 19aea1f2f3532db5

# Label operations
gmcli labels abc123 --remove UNREAD
gmcli labels abc123 --add TRASH --remove INBOX
```

## JSON Output

Use `--json` for structured output:

```bash
# Text output (default)
gmcli search "is:unread"

# JSON output
gmcli search "is:unread" --json
```

JSON structure:
- Success: `{"data": [...]}`
- Error: `{"error": "message"}` (to stderr)

## Data Storage

| Path | Purpose |
|------|---------|
| `.env` (next to binary) | Shared OAuth credentials (optional) |
| `~/.gmcli/.env` | Personal tokens and email (0600 perms) |
| `~/.gmcli/attachments/` | Downloaded attachments |
