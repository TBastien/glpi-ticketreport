<?php

/**
 * ---------------------------------------------------------------------
 * Ticket Report — "Send report by email" button in the ticket form.
 * ---------------------------------------------------------------------
 * Sends an HTML and plain-text email report through GLPI's mailer:
 * description, followups and tasks (optionally the solution), merged
 * chronologically, excluding private items. Sections are configurable.
 *
 * @copyright 2025 SOFIA.nc
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

define('PLUGIN_TICKETREPORT_VERSION', '1.0.3');
define('PLUGIN_TICKETREPORT_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_TICKETREPORT_MAX_GLPI_VERSION', '12.9.99');

// Per-user send rate limit. Defense in depth: the report endpoint is also gated
// on the central (technician) interface plus the ticket update right, so this
// only bounds a technician account; it is not the primary access control.
define('PLUGIN_TICKETREPORT_RATE_LIMIT_MAX', 20);      // max sends per window
define('PLUGIN_TICKETREPORT_RATE_LIMIT_WINDOW', 3600); // window length, seconds

/**
 * Plugin initialization: hook declaration.
 */
function plugin_init_ticketreport()
{
    global $PLUGIN_HOOKS;

    // Button injected at the top of a ticket form.
    $PLUGIN_HOOKS['pre_show_item']['ticketreport'] = 'plugin_ticketreport_pre_show_item';

    // Configuration page (Setup > Plugins > Ticket Report > Configure).
    $PLUGIN_HOOKS['config_page']['ticketreport'] = 'front/config.form.php';
}

/**
 * Version and metadata shown in Configuration > Plugins.
 */
function plugin_version_ticketreport()
{
    return [
        'name'         => 'Ticket Report',
        'version'      => PLUGIN_TICKETREPORT_VERSION,
        'author'       => 'SOFIA.nc',
        'license'      => 'GPL-2.0-or-later',
        'homepage'     => 'https://github.com/TBastien/glpi-ticketreport',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TICKETREPORT_MIN_GLPI_VERSION,
                'max' => PLUGIN_TICKETREPORT_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Configuration check.
 */
function plugin_ticketreport_check_config($verbose = false)
{
    return true;
}
