<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Ability: Execute a WordPress ability by name.
 *
 * This meta-ability lets MCP clients run any publicly exposed ability.
 * Mirrors the standalone MCP Adapter plugin's execute-ability ability.
 */

if (!defined('ABSPATH')) {
    exit();
}

if (wp_get_ability('mcp-adapter/execute-ability')) {
    return;
}

wp_register_ability('mcp-adapter/execute-ability', [
    'label' => 'Execute Ability',
    'description' => 'Execute a WordPress ability with the provided parameters. This is the primary execution layer that can run any registered ability.',
    'category' => 'mcp-adapter',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'ability_name' => [
                'type' => 'string',
                'description' => 'The full name of the ability to execute',
            ],
            'parameters' => [
                'type' => 'object',
                'description' => 'Parameters to pass to the ability',
            ],
        ],
        'required' => ['ability_name', 'parameters'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'data' => ['description' => 'The result data from the ability execution'],
            'error' => ['type' => 'string', 'description' => 'Error message if execution failed'],
        ],
        'required' => ['success'],
    ],
    'permission_callback' => static function ($input) {
        if (!current_user_can('read')) {
            return false;
        }
        /** @var string $name */
        $name = $input['ability_name'] ?? '';
        if ($name === '') {
            return new WP_Error('missing_ability_name', 'Ability name is required');
        }
        $ability = wp_get_ability($name);
        if (!$ability) {
            return new WP_Error('ability_not_found', "Ability '{$name}' not found");
        }
        $meta = $ability->get_meta();
        if (!($meta['mcp']['public'] ?? false)) {
            return new WP_Error('ability_not_public', "Ability '{$name}' is not exposed via MCP");
        }
        /** @var array<string, mixed>|null $params */
        $params = $input['parameters'] ?? null;
        $result = $ability->check_permissions($params !== null && $params !== [] ? $params : null);
        if (is_wp_error($result)) {
            return $result;
        }
        return $result;
    },
    'execute_callback' => static function ($input) {
        /** @var string $name */
        $name = $input['ability_name'] ?? '';
        /** @var array<string, mixed>|null $params */
        $params = $input['parameters'] ?? null;

        $ability = wp_get_ability($name);
        if (!$ability) {
            return ['success' => false, 'error' => "Ability '{$name}' not found"];
        }

        try {
            /** @var mixed $result */
            $result = $ability->execute($params !== null && $params !== [] ? $params : null);
            if (is_wp_error($result)) {
                return ['success' => false, 'error' => $result->get_error_message()];
            }
            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    },
    'meta' => [
        'mcp' => ['public' => false],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => false],
    ],
]);
