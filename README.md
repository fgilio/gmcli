# gmcli

Gmail command-line interface matching [gmcli v0.1.0](https://github.com/badlogic/gmcli) syntax. Built with Laravel Zero, packaged as a self-contained macOS binary.

## Features

- OAuth 2.0 authentication (auto browser flow + manual paste fallback)
- Search threads using Gmail query syntax
- View threads with full message content
- Download attachments
- Label management (list, add, remove)
- Drafts CRUD (list, get, create, delete, send)
- Send emails with attachments and reply-to threading
- Generate Gmail web URLs

## Requirements

- macOS (binary built with static-php-cli micro.sfx)
- Google Cloud project with Gmail API enabled

## Setup

### 1. Create OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable the Gmail API
4. Go to **Credentials** → **Create Credentials** → **OAuth client ID**
5. Select **Desktop app** as application type
6. Download the JSON credentials file

### 2. Configure gmcli

```bash
gmcli accounts credentials ~/Downloads/client_secret.json
gmcli accounts add you@gmail.com
```

The `add` command opens your browser for OAuth consent. After authorization, tokens are stored securely.

## Usage

```bash
# Search unread emails
gmcli you@gmail.com search "in:inbox is:unread"

# View specific thread
gmcli you@gmail.com thread 19aea1f2f3532db5

# Download attachments
gmcli you@gmail.com thread 19aea1f2f3532db5 --download

# List labels
gmcli you@gmail.com labels list

# Mark as read
gmcli you@gmail.com labels abc123 --remove UNREAD

# Move to trash
gmcli you@gmail.com labels abc123 --add TRASH --remove INBOX

# List drafts
gmcli you@gmail.com drafts list

# Create draft
gmcli you@gmail.com drafts create --to "recipient@example.com" \
    --subject "Hello" --body "Message body"

# Send email
gmcli you@gmail.com send --to "recipient@example.com" \
    --subject "Hello" --body "Message body"

# Reply to thread
gmcli you@gmail.com send --to "recipient@example.com" \
    --subject "Re: Hello" --body "Reply text" \
    --reply-to 19aea1f2f3532db5

# Get Gmail web URLs
gmcli you@gmail.com url abc123 def456
```

## Data Storage

| Path | Purpose |
|------|---------|
| `~/.gmcli/.env` | OAuth credentials and tokens (0600 permissions) |
| `~/.gmcli/attachments/` | Downloaded attachments |

## Building

Requires the `php-cli` skill for static-php-cli tooling.

```bash
cd ~/.claude/skills/gmcli

# One-time setup: build PHP + micro.sfx
phpcli-spc-setup --doctor
phpcli-spc-build

# Build gmcli binary
phpcli-build

# Binary output: builds/gmcli
```

The build process:
1. Creates `builds/gmcli.phar` using Box
2. Combines with `micro.sfx` to produce standalone binary
3. Final binary is ~50MB, requires no external PHP installation

## OAuth Scope

Uses `https://www.googleapis.com/auth/gmail.modify`:
- Read, compose, send, and modify email
- Manage labels
- **Cannot** permanently delete messages (only trash)

## Testing

```bash
./vendor/bin/pest
```

77 tests, 148 assertions covering:
- OAuth code extraction and URL building
- MIME parsing and base64url encoding
- Label name resolution
- Message building (headers, attachments, threading)
- Secret redaction in error output

## License

MIT
