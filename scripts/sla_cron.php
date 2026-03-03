<?php
/**
 * ProcessEngine - SLA Cron Script
 *
 * External cron script that bootstraps MantisBT and runs SLA checks.
 *
 * Usage:
 *   php /var/www/html/scripts/sla_cron.php
 *
 * Docker:
 *   docker exec mantisbt php /var/www/html/scripts/sla_cron.php
 *
 * Schedule: Every 5 minutes via Task Scheduler or crontab
 */

// Bootstrap MantisBT
$g_bypass_headers = true;
$t_mantis_dir = dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR;

require_once( $t_mantis_dir . 'core.php' );

// Ensure plugin is installed and active
$t_plugin = plugin_get( 'ProcessEngine' );
if( $t_plugin === null ) {
    echo "ProcessEngine plugin is not installed or active.\n";
    exit( 1 );
}

// Load SLA API
require_once( $t_mantis_dir . 'plugins/ProcessEngine/core/sla_api.php' );

echo '[' . date( 'Y-m-d H:i:s' ) . '] Running SLA check...' . "\n";

try {
    sla_run_check();
    echo '[' . date( 'Y-m-d H:i:s' ) . '] SLA check completed successfully.' . "\n";
} catch( Exception $e ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] SLA check error: ' . $e->getMessage() . "\n";
    exit( 1 );
}

exit( 0 );
