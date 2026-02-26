<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Ability: Discover available WordPress abilities.
 *
 * This meta-ability lets MCP clients list all publicly exposed abilities.
 * Mirrors the standalone MCP Adapter plugin's discover-abilities ability.
 */

if (!defined('ABSPATH')) {
    exit();
}

// Skip if already registered (e.g. by the standalone MCP Adapter plugin).
if (wp_get_ability('mcp-adapter/discover-abilities')) {
    return;
}

wp_register_ability('mcp-adapter/discover-abilities', [
    'label' => 'Discover Abilities',
    'description' => 'Discover all available WordPress abilities in the system. Returns a list of all registered abilities with their basic information.',
    'category' => 'mcp-adapter',
    'input_schema' => [
        'type' => 'object',
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'abilities' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'label', 'description'],
                ],
            ],
        ],
        'required' => ['abilities'],
    ],
    'permission_callback' => static fn() => current_user_can('read'),
    'execute_callback' => static function () {
        $abilities = wp_get_abilities();
        $list = [];

        foreach ($abilities as $ability) {
            $meta = $ability->get_meta();
            if (!($meta['mcp']['public'] ?? false)) {
                continue;
            }
            if (($meta['mcp']['type'] ?? 'tool') !== 'tool') {
                continue;
            }

            $list[] = [
                'name' => $ability->get_name(),
                'label' => $ability->get_label(),
                'description' => $ability->get_description(),
            ];
        }

        return ['abilities' => $list];
    },
    'meta' => [
        'mcp' => ['public' => false],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);
