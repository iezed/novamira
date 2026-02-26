# WordPress MCP Adapter

> Source: [github.com/WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter)
> Part of: AI Building Blocks for WordPress initiative
> License: GPL-2.0

## Overview

The MCP Adapter is WordPress's official package for Model Context Protocol integration. It transforms WordPress abilities into MCP tools, resources, and prompts, enabling AI agents to discover and invoke WordPress functionality programmatically.

## Core Features

- **Ability-to-MCP Conversion:** Automatically transforms WordPress abilities into MCP tools, resources, and prompts
- **Multi-Server Management:** Create and manage multiple MCP servers with unique configurations
- **Transport Layer Support:**
  - HTTP Transport (MCP 2025-06-18 compliant)
  - STDIO Transport for CLI and local development
  - Custom transport implementation support
- **Error Handling & Observability:** Built-in error logging, custom error handlers, zero-overhead metrics
- **Validation:** Built-in validation for tools, resources, and prompts
- **Permission Control:** Granular permission checking with transport-specific configurations
- **Jetpack Autoloader Support:** Prevents version conflicts across multiple plugins

## Architecture

```
./includes/
├── Core/
│   ├── McpAdapter.php          (Main registry - singleton)
│   ├── McpServer.php           (Server configuration)
│   ├── McpComponentRegistry.php (Component management)
│   └── McpTransportFactory.php (Transport factory)
├── Abilities/
│   ├── DiscoverAbilitiesAbility.php
│   ├── ExecuteAbilityAbility.php
│   └── GetAbilityInfoAbility.php
├── Cli/
│   ├── McpCommand.php
│   └── StdioServerBridge.php
├── Domain/
│   ├── Tools/
│   ├── Resources/
│   └── Prompts/
├── Handlers/
│   ├── Initialize/
│   ├── Tools/
│   ├── Resources/
│   ├── Prompts/
│   └── System/
├── Infrastructure/
│   ├── ErrorHandling/
│   ├── Observability/
│   └── Transport/
└── Servers/
    └── DefaultServerFactory.php
```

## Dependencies

### Required

- **PHP:** >= 7.4
- **WordPress Abilities API:** For ability registration and management

### Recommended

- **Jetpack Autoloader:** Resolves version conflicts when multiple plugins use MCP Adapter

## Installation

### Primary Method: Composer

```bash
composer require wordpress/abilities-api wordpress/mcp-adapter
```

### With Jetpack Autoloader (Highly Recommended)

```bash
composer require automattic/jetpack-autoloader
```

Load in main plugin file:
```php
<?php
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
```

### With WP-Env

Add to `.wp-env.json`:
```json
{
  "plugins": [
    "WordPress/abilities-api",
    "WordPress/mcp-adapter"
  ]
}
```

### Development Installation

```bash
git clone https://github.com/WordPress/mcp-adapter.git wp-content/plugins/mcp-adapter
cd wp-content/plugins/mcp-adapter
composer install
```

## Basic Usage

### Initialize in Your Plugin

```php
use WP\MCP\Core\McpAdapter;

if ( ! class_exists( McpAdapter::class ) ) {
    return; // Handle missing dependency
}

McpAdapter::instance();
// That's it! A default server is automatically created.
```

### Automatic Default Server

The adapter automatically creates a default server exposing all registered WordPress abilities:

- **HTTP Access:** `/wp-json/mcp/mcp-adapter-default-server`
- **STDIO Access:** `wp mcp-adapter serve --server=mcp-adapter-default-server`

### Register a WordPress Ability

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/get-posts', [
        'label'       => 'Get Posts',
        'description' => 'Retrieve WordPress posts with optional filtering',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'numberposts' => [
                    'type'        => 'integer',
                    'description' => 'Number of posts to retrieve',
                    'default'     => 5,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ],
                'post_status' => [
                    'type'    => 'string',
                    'enum'    => ['publish', 'draft', 'private'],
                    'default' => 'publish',
                ],
            ],
        ],
        'output_schema' => [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'ID'           => ['type' => 'integer'],
                    'post_title'   => ['type' => 'string'],
                    'post_content' => ['type' => 'string'],
                    'post_date'    => ['type' => 'string'],
                    'post_author'  => ['type' => 'string'],
                ],
            ],
        ],
        'execute_callback' => function( $input ) {
            return get_posts([
                'numberposts' => $input['numberposts'] ?? 5,
                'post_status' => $input['post_status'] ?? 'publish',
            ]);
        },
        'permission_callback' => function() {
            return current_user_can( 'read' );
        },
    ]);
});
// Automatically available via default MCP server!
```

## Connecting AI Clients

### STDIO Transport (Local Development / Testing)

#### WP-CLI Commands

```bash
# List all MCP servers
wp mcp-adapter list

# Discover available abilities
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"mcp-adapter-discover-abilities","arguments":{}}}' \
  | wp mcp-adapter serve --user=admin --server=mcp-adapter-default-server

# List available tools
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  | wp mcp-adapter serve --user=admin --server=mcp-adapter-default-server
```

#### Client Configuration (Claude Desktop, Claude Code, VS Code, Cursor)

```json
{
  "mcpServers": {
    "wordpress-default": {
      "command": "wp",
      "args": [
        "--path=/path/to/wordpress/site",
        "mcp-adapter",
        "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

Custom server:
```json
{
  "mcpServers": {
    "wordpress-custom": {
      "command": "wp",
      "args": [
        "--path=/path/to/wordpress/site",
        "mcp-adapter",
        "serve",
        "--server=your-custom-server-id",
        "--user=admin"
      ]
    }
  }
}
```

### HTTP Transport via Remote Proxy

```json
{
  "mcpServers": {
    "wordpress-http-default": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "http://your-site.test/wp-json/mcp/mcp-adapter-default-server",
        "LOG_FILE": "/path/to/logs/mcp-adapter.log",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

## Default Abilities (WordPress 6.9 Core)

Three core abilities ship with WordPress 6.9:

1. **`core/get-site-info`** — Returns configured site information with optional filtering
2. **`core/get-user-info`** — Provides basic profile details for authenticated users
3. **`core/get-environment-info`** — Supplies runtime context (PHP version, database info, WordPress version)

## Advanced Configuration

### Custom Transports

Implement `McpTransportInterface` to create specialized communication protocols beyond HTTP and STDIO.

### Custom Error Handlers

Implement `McpErrorHandlerInterface` for integration with custom logging, monitoring, or notification systems.

### Custom Observability

Implement `McpObservabilityHandlerInterface` for integration with monitoring and analytics systems.

### Server-Specific Configuration

Configure different error handling, observability, and transport strategies per MCP server.

## Resources

- [WordPress Abilities API](https://github.com/WordPress/abilities-api)
- [MCP Specification 2025-06-18](https://modelcontextprotocol.io/specification/2025-06-18/)
- [AI Building Blocks](https://make.wordpress.org/ai/2025/07/17/ai-building-blocks)
