<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Ability: Get detailed information about a specific ability.
 *
 * This meta-ability lets MCP clients inspect an ability's schema and metadata.
 * Mirrors the standalone MCP Adapter plugin's get-ability-info ability.
 */

if (!defined('ABSPATH')) {
    exit();
}

if (wp_get_ability('mcp-adapter/get-ability-info')) {
    return;
}

wp_register_ability('mcp-adapter/get-ability-info', [
    'label' => 'Get Ability Info',
    'description' => 'Get detailed information about a specific WordPress ability including its input/output schema, description, and usage examples.',
    'category' => 'mcp-adapter',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'ability_name' => [
                'type' => 'string',
                'description' => 'The full name of the ability to get information about',
            ],
        ],
        'required' => ['ability_name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'input_schema' => ['type' => 'object', 'description' => 'JSON Schema for the ability input parameters'],
            'output_schema' => ['type' => 'object', 'description' => 'JSON Schema for the ability output structure'],
            'meta' => ['type' => 'object', 'description' => 'Additional metadata about the ability'],
        ],
        'required' => ['name', 'label', 'description', 'input_schema'],
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
        return true;
    },
    'execute_callback' => static function ($input) {
        /** @var string $name */
        $name = $input['ability_name'] ?? '';
        $ability = wp_get_ability($name);
        if (!$ability) {
            return ['error' => "Ability '{$name}' not found"];
        }

        $info = [
            'name' => $ability->get_name(),
            'label' => $ability->get_label(),
            'description' => $ability->get_description(),
            'input_schema' => $ability->get_input_schema(),
        ];

        $output_schema = $ability->get_output_schema();
        if ($output_schema !== []) {
            $info['output_schema'] = $output_schema;
        }

        $meta = $ability->get_meta();
        if ($meta !== []) {
            $info['meta'] = $meta;
        }

        return $info;
    },
    'meta' => [
        'mcp' => ['public' => false],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);
