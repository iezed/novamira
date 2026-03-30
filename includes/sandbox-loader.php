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

/**
 * Shutdown handler that creates a .crashed marker only when a fatal error originated from a sandbox file.
 */
function novamira_sandbox_crash_handler(string $crashed_file, string $sandbox_dir): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    // Only react to fatal error types that kill execution.
    if (!($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        return;
    }

    // Only flag as crash if the error originated from a sandbox file.
    if (strncmp($error['file'], $sandbox_dir, strlen($sandbox_dir)) !== 0) {
        return;
    }

    file_put_contents($crashed_file, (string) wp_json_encode($error), LOCK_EX);
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

    // Clean up legacy .loading marker if present.
    if (file_exists($loading_file)) {
        unlink($loading_file);
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

    // Normal load with shutdown-based crash detection.
    $files = glob($sandbox_dir . '*.php');
    if (!$files) {
        return;
    }

    // The shutdown function detects fatal errors during sandbox loading.
    // If loading completes, the constant is defined and the handler becomes a no-op.
    register_shutdown_function(static function () use ($crashed_file, $sandbox_dir) {
        if (defined('NOVAMIRA_SANDBOX_LOADED')) {
            return;
        }

        novamira_sandbox_crash_handler($crashed_file, $sandbox_dir);
    });

    foreach ($files as $file) {
        require_once $file;
    }

    define('NOVAMIRA_SANDBOX_LOADED', value: true);
})();
