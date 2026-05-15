<?php
/**
 * ProcessEngine — Seed Loader (script çalıştırma için)
 *
 *   docker exec mantisbt php /var/www/html/scripts/run_seed.php
 */

$g_bypass_headers = true;
chdir( dirname( __FILE__ ) . '/..' );
require_once( 'core.php' );

plugin_push_current( 'ProcessEngine' );

require_once( 'plugins/ProcessEngine/db/seed_data.php' );

$t_ok = process_seed_load();

plugin_pop_current();

echo $t_ok ? "SEED LOADED\n" : "ALREADY SEEDED OR NOTHING DONE\n";
