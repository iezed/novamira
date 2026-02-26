## What This Is

Novamira gives AI agents **unrestricted control over a WordPress installation** through the WordPress Abilities API + MCP Adapter. The core idea: with arbitrary PHP execution and full filesystem access, an agent can do *anything* WordPress can do — install plugins, modify themes, query the database, call external APIs, create custom functionality on the fly. The abilities are intentionally unconstrained building blocks; the plugin's value is that it turns a WordPress site into a fully programmable environment for AI.

Requires WordPress 6.9+ and the MCP Adapter plugin.

## Code Quality

All code changes must pass these before committing:

```sh
mago format    # auto-format (print-width 120)
mago lint      # lint checks
mago analyze   # static analysis (PHP 8.0, includes WP stubs)
```

Mago config is in `mago.toml`. Source paths: `includes/` and `novamira.php`. WordPress stubs are in `vendor/php-stubs/wordpress-stubs` and `stubs/`.


