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
| `gmcli accounts:credentials <file.json>` | Set OAuth credentials |
| `gmcli accounts:list` | List configured account |
| `gmcli accounts:add <email>` | Add Gmail account via OAuth |
| `gmcli accounts:remove <email>` | Remove account |
| `gmcli gmail:search "<query>"` | Search threads |
| `gmcli gmail:thread <id>` | View thread messages |
| `gmcli gmail:labels:list` | List all labels |
| `gmcli gmail:labels:modify <ids...> --add/--remove` | Modify thread labels |
| `gmcli gmail:drafts:list` | List drafts |
| `gmcli gmail:drafts:create --to --subject --body` | Create draft |
| `gmcli gmail:send --to --subject --body` | Send email |
| `gmcli gmail:url <ids...>` | Generate Gmail web URLs |

Account is optional when configured. Use `-a <email>` to override.

## Setup

Personal use:
```bash
gmcli accounts:credentials ~/path/to/client_secret.json
gmcli accounts:add you@gmail.com
```

Team use (credentials in `.env` next to binary):
```bash
gmcli accounts:add you@gmail.com
```

## Usage Examples

```bash
# Search unread emails
gmcli gmail:search "in:inbox is:unread"

# View thread with attachments
gmcli gmail:thread 19aea1f2f3532db5 --download

# Send email
gmcli gmail:send --to "recipient@example.com" \
    --subject "Hello" --body "Message body"

# Reply to thread
gmcli gmail:send --to "recipient@example.com" \
    --subject "Re: Hello" --body "Reply text" \
    --reply-to 19aea1f2f3532db5

# Label operations
gmcli gmail:labels:modify abc123 --remove UNREAD
gmcli gmail:labels:modify abc123 --add TRASH --remove INBOX
```

## JSON Output

Use `--json` for structured output:

```bash
# Text output (default)
gmcli gmail:search "is:unread"

# JSON output
gmcli gmail:search "is:unread" --json
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
