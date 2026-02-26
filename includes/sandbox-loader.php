<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Sandbox Loader
 * Loads AI-written PHP plugins from the sandbox directory. Includes automatic crash recovery in dev mode.
 */

if (!defined('ABSPATH')) {
    exit();
}

(static function () {
    $sandbox_dir = NOVAMIRA_SANDBOX_DIR;

    // Ensure sandbox directory exists.
    if (!is_dir($sandbox_dir)) {
        return;
    }

    $loading_file = $sandbox_dir . '.loading';
    $crashed_file = $sandbox_dir . '.crashed';
    $abilities_enabled = novamira_is_enabled();

    // When abilities are disabled, load sandbox files without crash-recovery overhead.
    if (!$abilities_enabled) {
        $files = glob($sandbox_dir . '*.php');
        if ($files) {
            foreach ($files as $file) {
                require_once $file;
            }
        }
        return;
    }

    // Crash detection: .loading persisting from previous request means a fatal occurred.
    if (file_exists($loading_file)) {
        rename($loading_file, $crashed_file);
    }

    // Crash recovery: .crashed exists → stay in safe mode.
    $is_safe_mode = file_exists($crashed_file);

    // Manual safe mode via URL parameter.
    if (!$is_safe_mode && ($_GET['novamira_safe_mode'] ?? null) === '1') {
        $is_safe_mode = true;
    }

    // Dashboard warnings.
    add_action('admin_notices', static function () use ($crashed_file) {
        if (file_exists($crashed_file)) {
            wp_admin_notice(
                sprintf(
                    '<strong>%s</strong> %s',
                    esc_html__('Novamira Sandbox: Safe mode is active.', domain: 'novamira'),
                    esc_html__(
                        'A sandbox plugin caused a fatal error. All sandbox plugins are disabled. Fix or delete the broken plugin, then delete wp-content/novamira-sandbox/.crashed to resume.',
                        domain: 'novamira',
                    ),
                ),
                [
                    'type' => 'error',
                    'dismissible' => false,
                ],
            );
        }
    });

    // Safe mode: skip loading all sandbox files.
    if ($is_safe_mode) {
        return;
    }

    // Normal load: create .loading marker, load files, remove marker.
    $files = glob($sandbox_dir . '*.php');
    if (!$files) {
        return;
    }

    file_put_contents($loading_file, gmdate('c'), LOCK_EX);

    foreach ($files as $file) {
        require_once $file;
    }

    unlink($loading_file);
})();
