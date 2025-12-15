# gmcli

Gmail command-line interface. Self-contained binary, no PHP required.

## Setup

### Personal Use

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create project → Enable Gmail API
3. Credentials → OAuth 2.0 → Desktop app
4. Download JSON file

```bash
gmcli accounts credentials ~/Downloads/client_secret.json
gmcli accounts add you@gmail.com
```

### Team Distribution

Admin creates shared credentials once:

```bash
# Copy .env.example to .env (next to gmcli binary)
cp .env.example .env
# Fill in GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET
```

Team members only need to:

```bash
gmcli accounts add their@company.com
```

Credentials load from `.env` next to binary; tokens save to `~/.gmcli/.env`.

## Usage

```bash
gmcli search "in:inbox is:unread"
gmcli thread <id>
gmcli thread <id> --download
gmcli labels list
gmcli labels <id> --add STARRED --remove UNREAD
gmcli send --to "to@example.com" --subject "Hi" --body "Hello"
```

Email is optional once configured. Use `gmcli <email> <cmd>` to override.

## Data

| Path | Purpose |
|------|---------|
| `.env` (next to binary) | Shared OAuth credentials (optional) |
| `~/.gmcli/.env` | Personal tokens and email |
| `~/.gmcli/attachments/` | Downloaded attachments |

## Development

See [src/README.md](src/README.md) for building from source.
