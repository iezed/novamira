# MCP WordPress Remote Proxy

> Source: [github.com/Automattic/mcp-wordpress-remote](https://github.com/Automattic/mcp-wordpress-remote)
> License: MIT

## Overview

MCP WordPress Remote is a Node.js MCP server/proxy that enables AI assistants (Claude Desktop, Claude Code, Cursor, VS Code) to integrate with remote WordPress sites. It converts MCP protocol messages to HTTP requests and handles authentication.

## Key Features

- **OAuth 2.1 with PKCE** (RFC 7636) — Secure authorization code flow
- **Resource Indicators** (RFC 8707) — Token audience binding
- **Dynamic Client Registration** (RFC 7591) — Automatic registration
- **Protected Resource Metadata Discovery** (RFC 9728)
- **Multiple Authentication Methods** — OAuth 2.1, JWT tokens, WordPress application passwords
- **Persistent Token Storage** — Secure OAuth token management with validation
- **Multi-instance Coordination** — Lockfiles prevent authentication conflicts
- **Automatic Token Management** — Validation, refresh, and cleanup

## Installation

```bash
npm install @automattic/mcp-wordpress-remote
```

## Basic Configuration

Add to your MCP client config (e.g., `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://your-wordpress-site.com"
      }
    }
  }
}
```

## Authentication Methods

### 1. OAuth 2.1 (Recommended)

Most secure. One-time browser authorization, no password management, automatic expiration handling.

```json
{
  "env": {
    "WP_API_URL": "https://your-wordpress-site.com",
    "OAUTH_ENABLED": "true"
  }
}
```

### 2. JWT Token Authentication

For server-to-server scenarios:

```json
{
  "env": {
    "WP_API_URL": "https://your-wordpress-site.com",
    "JWT_TOKEN": "your-jwt-token-here"
  }
}
```

### 3. WordPress Application Passwords (Legacy)

Basic HTTP authentication:

```json
{
  "env": {
    "WP_API_URL": "https://your-wordpress-site.com",
    "WP_API_USERNAME": "your-username",
    "WP_API_PASSWORD": "your-application-password",
    "OAUTH_ENABLED": "false"
  }
}
```

Create an application password: WordPress admin → Users → Profile → Application Passwords.

## Custom Headers

JSON format:
```json
{
  "env": {
    "CUSTOM_HEADERS": "{\"X-MCP-API-Key\": \"your-key-value\"}"
  }
}
```

Comma-separated format:
```json
{
  "env": {
    "CUSTOM_HEADERS": "X-MCP-API-Key:value,X-Custom-Header:value"
  }
}
```

Headers apply to: WordPress API requests, OAuth discovery, token exchange, client registration.

## Claude Code Integration

```bash
claude mcp add wordpress_mcp \
  --env WP_API_URL=https://your-site.com/wp-json/mcp/mcp-adapter-default-server \
  --env WP_API_USERNAME=your-username \
  --env WP_API_PASSWORD=your-application-password \
  -- npx -y @automattic/mcp-wordpress-remote@latest
```

## WooCommerce Integration

```json
{
  "env": {
    "WOO_CUSTOMER_KEY": "ck_your-consumer-key",
    "WOO_CUSTOMER_SECRET": "cs_your-consumer-secret"
  }
}
```

## Environment Variables Reference

| Variable | Description | Default | Required |
|----------|-------------|---------|:--------:|
| `WP_API_URL` | WordPress site URL or MCP endpoint | — | **Yes** |
| `OAUTH_ENABLED` | Enable OAuth authentication | `true` | — |
| `OAUTH_CALLBACK_PORT` | OAuth callback port | `7665` | — |
| `OAUTH_HOST` | OAuth callback hostname | `127.0.0.1` | — |
| `WP_OAUTH_CLIENT_ID` | Custom OAuth client ID | — | — |
| `OAUTH_FLOW_TYPE` | OAuth flow type | `authorization_code` | — |
| `OAUTH_USE_PKCE` | Use PKCE (required for 2.1) | `true` | — |
| `JWT_TOKEN` | JWT token for authentication | — | — |
| `WP_API_USERNAME` | WordPress username (legacy) | — | — |
| `WP_API_PASSWORD` | WordPress app password (legacy) | — | — |
| `LOG_LEVEL` | Log level (0-3) | `2` | — |
| `LOG_FILE` | Log output file path | — | — |
| `CUSTOM_HEADERS` | Custom headers (JSON or CSV) | — | — |
| `WOO_CUSTOMER_KEY` | WooCommerce consumer key | — | — |
| `WOO_CUSTOMER_SECRET` | WooCommerce consumer secret | — | — |

## Token Management

### Storage Location

```
~/.mcp-auth/wordpress-remote-{version}/
```

### Manual Management

```bash
# View stored tokens
ls -la ~/.mcp-auth/wordpress-remote-*/

# Clear all tokens
rm -rf ~/.mcp-auth/wordpress-remote-*/

# Clear version-specific tokens
rm -rf ~/.mcp-auth/wordpress-remote-0.2.1/
```

### Security

- Secure file permissions (600) on token files
- Automatic token validation before requests
- Expired token cleanup during startup
- Version isolation for token storage

## Multi-Instance Support

The proxy automatically coordinates between multiple instances:

- Lockfiles prevent simultaneous OAuth flows
- Process coordination ensures single authentication at a time
- Graceful waiting when another instance authenticates
- Automatic cleanup of stale locks

"Waiting for other instance" messages are normal behavior.

## Development Setup

```bash
git clone https://github.com/Automattic/mcp-wordpress-remote.git
cd mcp-wordpress-remote
npm install
npm run build
```

- **Watch mode:** `npm run build:watch`
- **Testing:** `npm test`
- **Type checking:** `npm run check`

Local dev config:
```json
{
  "command": "node",
  "args": ["/path/to/mcp-wordpress-remote/dist/proxy.js"],
  "env": {
    "WP_API_URL": "https://your-wordpress-site.com"
  }
}
```

## Troubleshooting

### OAuth Browser Won't Open
- Verify port 7665 is available
- Try different port via `OAUTH_CALLBACK_PORT`
- Manually open URL from logs

### OAuth Authorization Fails
- Confirm WordPress has MCP plugin installed and enabled
- Check WordPress admin user permissions
- Clear tokens and re-authenticate: `rm -rf ~/.mcp-auth/wordpress-remote-*/`

### JWT Authentication Fails
- Verify JWT token validity and expiration
- Check token format and encoding
- Ensure WordPress supports JWT authentication

### Basic Auth Fails
- Verify username and application password accuracy
- Confirm application password is active
- Check user has sufficient permissions

### Connection Issues
- Check WordPress site accessibility
- Review logs (set `LOG_FILE` env var)
- Ensure MCP adapter plugin is active on WordPress site
