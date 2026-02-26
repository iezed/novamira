=== Novamira ===
Contributors: dynamicooo
Tags: mcp, ai, php, filesystem, abilities
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

MCP server that gives AI agents full access to WordPress through PHP execution and filesystem operations. For development and staging environments only.

== Description ==

**This plugin is for development and staging environments only. Do not use on production sites. Always keep backups.**

Novamira registers eight abilities that allow AI agents (via MCP) to execute PHP code and perform filesystem operations on your WordPress server:

* **Execute PHP** — Run PHP code with full access to the WordPress environment (`$wpdb`, all functions, loaded plugins).
* **Read File** — Read file contents with offset/limit support. Binary files are returned as base64.
* **Write File** — Create or overwrite files. PHP files are restricted to the sandbox directory. Supports base64 encoding, append mode, and automatic directory creation.
* **Edit File** — Apply a targeted text replacement to an existing file. The old string must be unique unless replace_all is set.
* **Delete File** — Delete files or directories. Critical WordPress directories and the sandbox loader are protected. Supports recursive deletion.
* **Disable File** — Disable a sandbox file by renaming it with a `.disabled` suffix. Safer than deleting — the file is preserved on disk and can be re-enabled later.
* **Enable File** — Re-enable a previously disabled sandbox file by removing the `.disabled` suffix. Accepts either the original or disabled filename.
* **List Directory** — List directory contents with glob filtering, recursive traversal, and configurable depth.

= How It Works =

WordPress 6.9 includes the Abilities API in core. This plugin registers eight abilities using that API. The MCP Adapter (bundled with Novamira) discovers abilities that have `mcp.public = true` in their metadata and automatically exposes them as MCP tools. No custom MCP protocol work is needed — activate the plugin and the tools appear in any connected AI client's tool list.

= Who Is This For =

Developers working on dev/staging sites with backups who want to give AI agents full programmatic access to their WordPress installation.

= PHP Sandbox =

The **write-file** ability confines PHP files to a sandbox directory (`wp-content/novamira-sandbox/`). A loader included directly by the plugin handles loading them on every request.

**Important:** The sandbox is a convenience guardrail for the write-file ability, not a security boundary. Any PHP code — whether run via execute-php or loaded from the sandbox itself — can write files anywhere on disk. The sandbox prevents accidental misuse of the write-file tool; it does not restrict what PHP code can do once it executes.

* **Write-file restriction** — The write-file ability only allows PHP files inside the sandbox. Non-PHP files can go anywhere under ABSPATH.
* **Auto-install** — The plugin automatically creates the sandbox directory on activation.

= Crash Recovery =

When AI Abilities are enabled in the plugin settings (dev mode), the loader detects fatal errors caused by sandbox plugins and auto-recovers:

1. Before loading sandbox files, the loader creates a `.loading` marker.
2. After all files load successfully, the marker is removed.
3. If a file causes a fatal error, PHP dies and `.loading` persists.
4. On the next request, the loader sees `.loading`, renames it to `.crashed`, and enters safe mode — all sandbox files are skipped.
5. In safe mode, MCP still works. The AI agent can read, fix, or delete the broken file, then delete `.crashed` to resume normal loading.

A manual safe mode is also available via `?novamira_safe_mode=1` in the URL (dev mode only).

In production (AI Abilities disabled), the loader just does `glob` + `require_once` with zero extra file I/O.

= Security Model =

* All eight abilities require the `manage_options` capability (administrator only).
* Filesystem operations are restricted to ABSPATH by default. The base directory can be changed or removed via the `novamira_filesystem_base_dir` filter.
* The write-file ability only allows PHP files inside the sandbox directory (`wp-content/novamira-sandbox/`). This is a convenience guardrail, not a security boundary — PHP code running via execute-php or from the sandbox itself is unrestricted.
* Critical directories (ABSPATH root, `wp-admin`, `wp-includes`, `mu-plugins`) are protected from deletion.
* PHP execution has a 30-second time limit to prevent runaway code.

== Installation ==

1. Install and activate Novamira. The MCP Adapter is bundled automatically.
2. Connect your AI client using one of the methods below.

Requires WordPress 6.9+ and PHP 7.4+. [WP-CLI](https://wp-cli.org/) is only needed if using the STDIO transport.


== Connecting AI Clients ==

The MCP Adapter supports two transports: HTTP (remote) and STDIO (local).

= HTTP Transport (Remote) =

The MCP Adapter exposes an HTTP endpoint for remote access. The default endpoint is:

`
https://your-site.com/wp-json/mcp/mcp-adapter-default-server
`

If your site does not use pretty permalinks, use the `rest_route` parameter instead:

`
https://your-site.com/index.php?rest_route=/mcp/mcp-adapter-default-server
`

HTTP transport requires authentication via [WordPress application passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/). Application passwords require HTTPS unless `WP_ENVIRONMENT_TYPE` is set to `local`.

**Setting up authentication:**

1. In WP Admin, go to Users → Your Profile → Application Passwords.
2. Enter a name (e.g. "MCP Client") and click "Add New Application Password".
3. Copy the generated password. It will only be shown once.

To connect AI clients via HTTP, use the `@automattic/mcp-wordpress-remote` proxy:

`
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "admin",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
`

= STDIO Transport (Local) =

If WP-CLI is available on the same machine, your AI client can launch `wp mcp-adapter serve` as a subprocess. Authentication is handled by the `--user` flag — no application passwords needed. Add the following to your MCP client config:

`
{
  "mcpServers": {
    "wordpress": {
      "command": "wp",
      "args": [
        "--path=/path/to/wordpress",
        "mcp-adapter",
        "serve",
        "--user=admin"
      ]
    }
  }
}
`

Replace `--path` with your WordPress root and `admin` with your admin username.

== Frequently Asked Questions ==

= Is this safe for production? =

No. This plugin is not intended for production use. The execute-php ability uses `eval()` to run code, and the delete-file ability can remove files. The sandbox and crash recovery features help mitigate risks from AI-generated code, but unintended changes can occur. Use only on development or staging environments with regular backups.

= Why does the notice stay visible? =

By design. The persistent admin notice is a reminder that AI Abilities are active and agents can interact with your site.

= What happens if the Abilities API is not installed? =

The plugin shows an error notice and does nothing. It will not register any abilities.

= What happens if the MCP Adapter is not installed? =

Novamira bundles the MCP Adapter, so it is always available. If you also have the standalone MCP Adapter plugin installed, the Jetpack Autoloader ensures the newest version is loaded — no conflicts. You can safely deactivate the standalone plugin.

= Can I restrict filesystem access to a specific directory? =

Yes. Use the `novamira_filesystem_base_dir` filter:

`
add_filter( 'novamira_filesystem_base_dir', function() {
    return WP_CONTENT_DIR; // Only allow access within wp-content
} );
`

Return `false` to disable the restriction entirely.

= Does execute-php support long-running operations? =

There is a 30-second time limit enforced via `set_time_limit()`. This can be adjusted by defining `NOVAMIRA_MAX_EXECUTION_TIME` before the plugin loads, but this is not recommended.

= What happens if an AI-written PHP file crashes the site? =

In dev mode (AI Abilities enabled), the sandbox loader automatically detects the crash on the next request and enters safe mode — all sandbox plugins are skipped, but MCP remains functional. The AI agent can then fix or delete the broken file and delete `wp-content/novamira-sandbox/.crashed` to resume.

= Can I manually enter safe mode? =

Yes. Append `?novamira_safe_mode=1` to any URL on your site. This only works in dev mode (when AI Abilities are enabled).

= What is the sandbox directory? =

`wp-content/novamira-sandbox/`. PHP files written by AI agents are placed here. The plugin loader loads them on every request.
