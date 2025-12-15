---
name: gmcli
description: >
  Gmail CLI tool for managing email from the command line.
  Trigger words: gmail, email, gmcli, mail.
---

# gmcli - Gmail CLI

Gmail command-line interface matching [gmcli v0.1.0](https://github.com/badlogic/gmcli) syntax.

## Quick Reference

| Command | Purpose |
|---------|---------|
| `gmcli accounts credentials <file.json>` | Set OAuth credentials |
| `gmcli accounts list` | List configured account |
| `gmcli accounts add <email>` | Add Gmail account via OAuth |
| `gmcli accounts remove <email>` | Remove account |
| `gmcli <email> search "<query>"` | Search threads |
| `gmcli <email> thread <id>` | View thread messages |
| `gmcli <email> labels list` | List all labels |
| `gmcli <email> labels <ids...> --add/--remove` | Modify thread labels |
| `gmcli <email> drafts list` | List drafts |
| `gmcli <email> drafts create --to --subject --body` | Create draft |
| `gmcli <email> send --to --subject --body` | Send email |
| `gmcli <email> url <ids...>` | Generate Gmail web URLs |

## Setup

### 1. Get OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create project and enable Gmail API
3. Create OAuth 2.0 credentials (Desktop app)
4. Download JSON credentials file

### 2. Configure gmcli

```bash
gmcli accounts credentials ~/Downloads/client_secret.json
gmcli accounts add you@gmail.com
```

## Usage Examples

```bash
# Search unread emails
gmcli you@gmail.com search "in:inbox is:unread"

# View specific thread
gmcli you@gmail.com thread 19aea1f2f3532db5

# Download attachments
gmcli you@gmail.com thread 19aea1f2f3532db5 --download

# Send email
gmcli you@gmail.com send --to "recipient@example.com" \
    --subject "Hello" --body "Message body"

# Reply to thread
gmcli you@gmail.com send --to "recipient@example.com" \
    --subject "Re: Hello" --body "Reply text" \
    --reply-to 19aea1f2f3532db5

# Mark as read
gmcli you@gmail.com labels abc123 --remove UNREAD

# Move to trash
gmcli you@gmail.com labels abc123 --add TRASH --remove INBOX
```

## Data Storage

| Path | Purpose |
|------|---------|
| `~/.gmcli/.env` | Credentials and tokens (0600 perms) |
| `~/.gmcli/attachments/` | Downloaded attachments |

## Building

```bash
cd ~/.claude/skills/gmcli

# One-time setup
phpcli-spc-setup --doctor
phpcli-spc-build

# Build binary
phpcli-build

# Binary at: builds/gmcli
```

## OAuth Scope

Uses `https://www.googleapis.com/auth/gmail.modify` scope:
- Read, compose, send, and modify email
- Manage labels
- NO permanent delete (only trash)
