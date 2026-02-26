# WordPress Abilities API

> Source: [github.com/WordPress/abilities-api](https://github.com/WordPress/abilities-api)
> Status: Moving into WordPress Core (6.9). Repository archived Feb 5, 2026.
> License: GPL-2.0

## Overview

The Abilities API provides a standardized way for WordPress core, plugins, and themes to describe what they can do ("abilities") in a machine-readable, human-friendly form. It handles discovery, permissioning, and execution metadata — actual business logic stays inside the registering component.

### Design Principles

1. **Discoverability** — All abilities can be listed, queried, and inspected
2. **Interoperability** — Uniform schema enables unrelated components to compose workflows
3. **Security-first** — Explicit permissions control who/what may invoke abilities
4. **Gradual adoption** — Deploys as Composer package before potential core integration

## Installation

### As a Plugin (WP-CLI)

```bash
wp plugin install https://github.com/WordPress/abilities-api/releases/latest/download/abilities-api.zip
```

### With WP-Env

```json
{
  "$schema": "https://schemas.wp.org/trunk/wp-env.json",
  "plugins": [
    "WordPress/abilities-api"
  ]
}
```

### As a Plugin Dependency

```php
<?php
/*
 * Plugin Name: My Plugin
 * Requires Plugins: abilities-api
 */
```

### As a Composer Dependency

```bash
composer require wordpress/abilities-api
```

### Checking Availability

```php
if ( ! class_exists( 'WP_Ability' ) ) {
    add_action( 'admin_notices', static function() {
        wp_admin_notice(
            esc_html__( 'This plugin requires the Abilities API.', 'my-plugin' ),
            'error'
        );
    } );
    return;
}

// Check specific version
if ( ! defined( 'WP_ABILITIES_API_VERSION' ) ||
     version_compare( WP_ABILITIES_API_VERSION, '0.1.0', '<' ) ) {
    // handle version mismatch
}
```

---

## PHP API Reference

### Category Management

#### Register a Category

```php
wp_register_ability_category( string $slug, array $args ): ?WP_Ability_Category
```

**Hook:** Must be called on `wp_abilities_api_categories_init`

**Parameters:**
- `$slug` (string): Unique identifier — lowercase alphanumerics and hyphens only
- `$args` (array):
  - `label` (string, **required**): Human-readable category name
  - `description` (string, **required**): Category purpose description
  - `meta` (array, optional): Additional metadata

**Returns:** `WP_Ability_Category` on success, `null` on failure

#### Unregister a Category

```php
wp_unregister_ability_category( string $slug ): ?WP_Ability_Category
```

#### Get Categories

```php
wp_get_ability_category( string $slug ): ?WP_Ability_Category
wp_get_ability_categories(): array  // keyed by slug
```

---

### Ability Registration

#### Register an Ability

```php
wp_register_ability( string $name, array $args ): ?WP_Ability
```

**Hook:** Must be called on `wp_abilities_api_init`

**Name Format:** `namespace/ability-name` (lowercase letters, numbers, hyphens, one forward slash)

**Required `$args`:**

| Key | Type | Description |
|-----|------|-------------|
| `label` | string | Human-readable ability name |
| `description` | string | Detailed explanation (crucial for AI understanding) |
| `category` | string | Slug of a registered category |
| `output_schema` | array | JSON Schema defining return value format |
| `execute_callback` | callable | PHP function that performs the work |
| `permission_callback` | callable | Returns `bool` or `WP_Error` for authorization |

**Optional `$args`:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `input_schema` | array | — | JSON Schema for input parameters |
| `meta` | array | — | Additional metadata (see below) |
| `ability_class` | string | — | Custom class extending `WP_Ability` |

**Meta Options:**

```php
'meta' => [
    'annotations' => [
        'instructions'  => '',    // Custom usage guidance string
        'readonly'      => false, // Data-read-only operation
        'destructive'   => true,  // May perform deletions
        'idempotent'    => false, // Repeated calls safe
    ],
    'show_in_rest' => false, // Expose via REST API
]
```

**Callback Signatures:**

```php
// execute_callback
function( $input = null ): mixed|WP_Error

// permission_callback
function( $input = null ): bool|WP_Error
```

#### Check / Get / List Abilities

```php
wp_ability_exists( string $name ): bool
wp_get_ability( string $name ): ?WP_Ability
wp_get_abilities(): array
```

#### Execute an Ability

```php
$ability = wp_get_ability( 'my-plugin/get-site-title' );
$result  = $ability->execute( $input );

if ( is_wp_error( $result ) ) {
    $error_message = $result->get_error_message();
}
```

#### Check Permissions

```php
$allowed = $ability->check_permissions( $input );
```

#### Inspect Properties

```php
$ability->name
$ability->label
$ability->description
$ability->category
$ability->input_schema
$ability->output_schema
```

---

## REST API Reference

**Namespace:** `/wp-abilities/v1`

All endpoints require an authenticated user. Abilities must have `show_in_rest => true` to be exposed.

### Authentication

- Cookie authentication (same-origin)
- Application passwords (recommended for external access)

```bash
curl -u 'USERNAME:APPLICATION_PASSWORD' \
  https://example.com/wp-json/wp-abilities/v1/abilities
```

### Endpoints

#### List Abilities

```
GET /wp-abilities/v1/abilities
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Current page |
| `per_page` | integer | 50 (max 100) | Items per page |
| `category` | string | — | Filter by category slug |

#### List Categories

```
GET /wp-abilities/v1/categories
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Current page |
| `per_page` | integer | 50 (max 100) | Items per page |

#### Get a Category

```
GET /wp-abilities/v1/categories/{slug}
```

#### Get an Ability

```
GET /wp-abilities/v1/{namespace}/{ability}
```

Example response:
```json
{
  "name": "my-plugin/get-site-info",
  "label": "Get Site Information",
  "description": "Retrieves basic information about the WordPress site.",
  "category": "site-information",
  "output_schema": {
    "type": "object",
    "properties": {
      "name": { "type": "string" },
      "url": { "type": "string", "format": "uri" }
    }
  },
  "meta": {
    "annotations": {
      "instructions": "",
      "readonly": true,
      "destructive": false,
      "idempotent": false
    }
  }
}
```

#### Execute an Ability

```
GET|POST|DELETE /wp-abilities/v1/{namespace}/{ability}/run
```

HTTP method depends on ability annotations:
- Read-only → `GET`
- Regular with input → `POST`
- Destructive → `DELETE`

**GET (no input):**
```bash
curl https://example.com/wp-json/wp-abilities/v1/my-plugin/get-site-info/run
```

**GET (with input, URL-encoded):**
```bash
curl "https://example.com/wp-json/wp-abilities/v1/my-plugin/get-user-info/run?input=%7B%22user_id%22%3A1%7D"
```

**POST:**
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"input":{"option_name":"blogname","option_value":"New Site Name"}}' \
  https://example.com/wp-json/wp-abilities/v1/my-plugin/update-option/run
```

**DELETE:**
```bash
curl -X DELETE \
  "https://example.com/wp-json/wp-abilities/v1/my-plugin/delete-post/run?input=%7B%22post_id%22%3A123%7D"
```

### Error Codes

| Code | Description |
|------|-------------|
| `ability_missing_input_schema` | Input required but not provided |
| `ability_invalid_input` | Input fails validation |
| `ability_invalid_permissions` | User lacks execution permission |
| `ability_invalid_output` | Output fails validation |
| `ability_invalid_execute_callback` | Callback not callable |
| `rest_ability_not_found` | Ability not registered |
| `rest_ability_category_not_found` | Category not registered |
| `rest_ability_invalid_method` | HTTP method not allowed |
| `rest_ability_cannot_execute` | Insufficient permissions |

---

## Hooks Reference

### Actions

#### `wp_abilities_api_categories_init`

Fires when the category registry initializes.

```php
do_action( 'wp_abilities_api_categories_init', $registry );
```

```php
add_action( 'wp_abilities_api_categories_init', function( $registry ) {
    wp_register_ability_category( 'ecommerce', [
        'label'       => __( 'E-commerce', 'my-plugin' ),
        'description' => __( 'E-commerce related abilities.', 'my-plugin' ),
    ]);
});
```

#### `wp_abilities_api_init`

Fires when the abilities registry initializes. Register abilities here.

```php
do_action( 'wp_abilities_api_init', $registry );
```

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/ability', [
        'label'               => __( 'Title', 'my-plugin' ),
        'description'         => __( 'Description.', 'my-plugin' ),
        'category'            => 'analytics',
        'input_schema'        => [ 'type' => 'object', 'properties' => [], 'additionalProperties' => false ],
        'output_schema'       => [ 'type' => 'string', 'description' => 'Result.' ],
        'execute_callback'    => 'my_plugin_callback',
        'permission_callback' => '__return_true',
        'meta'                => [ 'show_in_rest' => true ],
    ]);
});
```

#### `wp_before_execute_ability`

Fires immediately before ability execution (after permission checks pass).

```php
do_action( 'wp_before_execute_ability', $ability_name, $input );
```

```php
add_action( 'wp_before_execute_ability', function( string $name, $input ) {
    error_log( "Executing ability: $name" );
    if ( $input !== null ) {
        error_log( 'Input: ' . wp_json_encode( $input ) );
    }
}, 10, 2 );
```

#### `wp_after_execute_ability`

Fires immediately after successful ability execution (after output validation).

```php
do_action( 'wp_after_execute_ability', $ability_name, $input, $result );
```

```php
add_action( 'wp_after_execute_ability', function( string $name, $input, $result ) {
    error_log( "Completed: $name → " . wp_json_encode( $result ) );
}, 10, 3 );
```

### Filters

#### `wp_register_ability_args`

Modify ability arguments before validation.

```php
$args = apply_filters( 'wp_register_ability_args', array $args, string $ability_name );
```

```php
add_filter( 'wp_register_ability_args', function( array $args, string $name ): array {
    if ( 'my-namespace/my-ability' === $name ) {
        $args['permission_callback'] = fn() => current_user_can( 'my_custom_cap' );
    }
    return $args;
}, 10, 2 );
```

#### `wp_register_ability_category_args`

Modify category arguments before validation.

```php
$args = apply_filters( 'wp_register_ability_category_args', array $args, string $slug );
```

---

## Complete Examples

### Simple Data Retrieval (No Input)

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/get-site-info', [
        'label'       => __( 'Get Site Information', 'my-plugin' ),
        'description' => __( 'Retrieves site name, description, URL', 'my-plugin' ),
        'category'    => 'data-retrieval',
        'output_schema' => [
            'type'       => 'object',
            'properties' => [
                'name'        => [ 'type' => 'string' ],
                'description' => [ 'type' => 'string' ],
                'url'         => [ 'type' => 'string', 'format' => 'uri' ],
            ],
        ],
        'execute_callback'    => fn() => [
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url'         => home_url(),
        ],
        'permission_callback' => '__return_true',
        'meta' => [
            'annotations' => [ 'readonly' => true, 'destructive' => false ],
        ],
    ]);
});
```

### With Input Parameters

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/update-option', [
        'label'       => __( 'Update WordPress Option', 'my-plugin' ),
        'description' => __( 'Updates option values; requires manage_options', 'my-plugin' ),
        'category'    => 'data-modification',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'option_name'  => [ 'type' => 'string', 'minLength' => 1 ],
                'option_value' => [ 'description' => 'New option value' ],
            ],
            'required'             => [ 'option_name', 'option_value' ],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type'       => 'object',
            'properties' => [
                'success'        => [ 'type' => 'boolean' ],
                'previous_value' => [ 'description' => 'Previous value' ],
            ],
        ],
        'execute_callback' => fn( $input ) => [
            'success'        => update_option( $input['option_name'], $input['option_value'] ),
            'previous_value' => get_option( $input['option_name'] ),
        ],
        'permission_callback' => fn() => current_user_can( 'manage_options' ),
        'meta' => [
            'annotations' => [ 'destructive' => false, 'idempotent' => true ],
        ],
    ]);
});
```

### With Plugin Dependencies (WooCommerce)

```php
add_action( 'wp_abilities_api_init', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    wp_register_ability( 'my-plugin/get-woo-stats', [
        'label'       => __( 'Get WooCommerce Statistics', 'my-plugin' ),
        'description' => __( 'Retrieves store statistics; requires WooCommerce', 'my-plugin' ),
        'category'    => 'ecommerce',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'period' => [
                    'type'    => 'string',
                    'enum'    => [ 'today', 'week', 'month', 'year' ],
                    'default' => 'month',
                ],
            ],
        ],
        'output_schema' => [
            'type'       => 'object',
            'properties' => [
                'total_orders'  => [ 'type' => 'integer' ],
                'total_revenue' => [ 'type' => 'number' ],
            ],
        ],
        'execute_callback'    => fn( $input ) => [ 'total_orders' => 42, 'total_revenue' => 1250.50 ],
        'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
        'meta'                => [ 'requires_plugin' => 'woocommerce' ],
    ]);
});
```

### Full Basic Usage Pattern

```php
// 1. Define callback
function my_plugin_get_site_title( array $input = [] ): string {
    return get_bloginfo( 'name' );
}

// 2. Register on init hook
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-plugin/get-site-title', [
        'label'       => __( 'Get Site Title', 'my-plugin' ),
        'description' => __( 'Retrieves the title of the current WordPress site.', 'my-plugin' ),
        'category'    => 'site-info',
        'input_schema' => [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type'        => 'string',
            'description' => 'The site title.',
        ],
        'execute_callback'    => 'my_plugin_get_site_title',
        'permission_callback' => '__return_true',
        'meta'                => [ 'show_in_rest' => true ],
    ]);
});

// 3. Execute programmatically
add_action( 'admin_init', function() {
    $ability = wp_get_ability( 'my-plugin/get-site-title' );
    if ( ! $ability ) {
        return;
    }

    $result = $ability->execute();
    if ( is_wp_error( $result ) ) {
        error_log( 'Error: ' . $result->get_error_message() );
        return;
    }

    echo 'Site Title: ' . esc_html( $result );
});
```
