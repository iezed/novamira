# WordPress MCP (Model Context Protocol) Overview

## What is MCP?

MCP (Model Context Protocol) is an open standard that standardizes how applications provide context to Large Language Models (LLMs). It allows AI agents to interact with WordPress sites programmatically.

## WordPress MCP Ecosystem

### Core Components

| Component | Description | Repository |
|-----------|-------------|------------|
| **Abilities API** | Standardized system for registering capabilities in WordPress (moving into Core in 6.9) | [WordPress/abilities-api](https://github.com/WordPress/abilities-api) |
| **MCP Adapter** | Bridges the Abilities API to MCP, enabling AI clients to discover and invoke WordPress abilities | [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) |
| **MCP WordPress Remote** | Node.js proxy for connecting AI clients to remote WordPress MCP servers | [Automattic/mcp-wordpress-remote](https://github.com/Automattic/mcp-wordpress-remote) |

### Architecture

```
AI Client (Claude, Cursor, VS Code)
    │
    ├── STDIO Transport (local dev)
    │       └── wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin
    │
    └── HTTP Transport (remote)
            └── npx @automattic/mcp-wordpress-remote
                    └── WordPress REST API → MCP Adapter → Abilities API
```

### Two-Way Integration

**As MCP Server:** WordPress makes registered Abilities available as MCP tools, resources, and prompts, enabling AI assistants to discover and execute actions like creating posts and managing media.

**As MCP Client:** WordPress can integrate with other MCP servers, leveraging external tools and services.

### Transport Methods

- **Streamable HTTP** via the existing REST API (MCP 2025-06-18 compliant)
- **STDIO** via WP-CLI for local development
- **Custom transports** via `McpTransportInterface`

### Security Model

- WordPress application passwords for HTTP authentication
- WordPress capabilities system for granular authorization
- OAuth 2.1 with PKCE support
- Permission callbacks on every ability
- Input/output validation through JSON Schema

### MCP Component Types

- **Tools**: Executable functions (abilities → MCP tools)
- **Resources**: Data sources AI models can access for context
- **Prompts**: Instructions guiding AI behavior

## Status & Versioning

- **Abilities API**: Moving into WordPress Core as of version 6.9 (archived repo, code merged)
- **MCP Adapter**: Stable, canonical plugin and Composer package
- **MCP Specification**: 2025-06-18 version

## Related Documentation Files

- [WordPress MCP Adapter](./wordpress-mcp-adapter.md) - Self-hosted MCP adapter (installation, config, usage)
- [WordPress Abilities API](./wordpress-abilities-api.md) - Abilities API reference (PHP API, REST API, hooks)
- [MCP WordPress Remote Proxy](./mcp-wordpress-remote.md) - Remote proxy documentation (auth, config)
- [WooCommerce MCP Integration](./woocommerce-mcp-integration.md) - WooCommerce-specific MCP docs
