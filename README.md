# gmcli

Gmail command-line interface. Self-contained binary, no PHP required.

## Setup

### 1. Get OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create project → Enable Gmail API
3. Credentials → OAuth 2.0 → Desktop app
4. Download JSON file

### 2. Configure

```bash
gmcli accounts credentials ~/Downloads/client_secret.json
gmcli accounts add you@gmail.com
```

## Usage

```bash
gmcli you@gmail.com search "in:inbox is:unread"
gmcli you@gmail.com thread <id>
gmcli you@gmail.com thread <id> --download
gmcli you@gmail.com labels list
gmcli you@gmail.com labels <id> --add STARRED --remove UNREAD
gmcli you@gmail.com send --to "to@example.com" --subject "Hi" --body "Hello"
```

## Data

| Path | Purpose |
|------|---------|
| `~/.gmcli/.env` | Credentials and tokens |
| `~/.gmcli/attachments/` | Downloaded attachments |

## Development

See [src/README.md](src/README.md) for building from source.
