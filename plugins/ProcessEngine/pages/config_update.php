<?php
/**
 * ProcessEngine - Config Update Handler
 *
 * Processes config form submission and seed data loading.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'ProcessEngine_config_update' );

$t_action = gpc_get_string( 'action', '' );

if( $t_action === 'seed' ) {
    // Load seed data
    require_once( dirname( __DIR__ ) . '/db/seed_data.php' );
    $t_loaded = process_seed_load();

    form_security_purge( 'ProcessEngine_config_update' );

    if( $t_loaded ) {
        $t_redirect_url = plugin_page( 'config_page', true ) . '&seed=ok';
    } else {
        $t_redirect_url = plugin_page( 'config_page', true ) . '&seed=exists';
    }
    print_header_redirect( $t_redirect_url );
}

if( $t_action === 'migrate' ) {
    // Mevcut log verilerinden process_instance kayıtları oluştur
    require_once( dirname( __DIR__ ) . '/core/subprocess_api.php' );
    $t_count = subprocess_migrate_existing_data();

    form_security_purge( 'ProcessEngine_config_update' );

    if( $t_count > 0 ) {
        $t_redirect_url = plugin_page( 'config_page', true ) . '&migrate=ok&count=' . $t_count;
    } else {
        $t_redirect_url = plugin_page( 'config_page', true ) . '&migrate=none';
    }
    print_header_redirect( $t_redirect_url );
}

// Normal config update
$t_manage_threshold          = gpc_get_int( 'manage_threshold', MANAGER );
$t_view_threshold            = gpc_get_int( 'view_threshold', REPORTER );
$t_action_threshold          = gpc_get_int( 'action_threshold', DEVELOPER );
$t_sla_warning_percent       = gpc_get_int( 'sla_warning_percent', 80 );
$t_business_hours_start      = gpc_get_string( 'business_hours_start', '09:00' );
$t_business_hours_end        = gpc_get_string( 'business_hours_end', '18:00' );
$t_working_days              = gpc_get_string( 'working_days', '1,2,3,4,5' );
$t_departments               = gpc_get_string( 'departments', '' );
$t_allow_automatic_processes = gpc_get_int( 'allow_automatic_processes', 0 );

// Validate working days format
$t_working_days = preg_replace( '/[^0-9,]/', '', $t_working_days );

// Clean departments: trim whitespace, remove empty entries
$t_dept_arr = array_map( 'trim', explode( ',', $t_departments ) );
$t_dept_arr = array_filter( $t_dept_arr, function( $v ) { return $v !== ''; } );
$t_departments = implode( ', ', $t_dept_arr );

// Validate business hours (HH:MM format)
if( !preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $t_business_hours_start ) ) {
    $t_business_hours_start = '09:00';
}
if( !preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $t_business_hours_end ) ) {
    $t_business_hours_end = '18:00';
}
// Bitiş başlangıçtan büyük olmalı
if( $t_business_hours_end <= $t_business_hours_start ) {
    $t_business_hours_end = '18:00';
}

// Validate SLA warning percent
if( $t_sla_warning_percent < 50 || $t_sla_warning_percent > 99 ) {
    $t_sla_warning_percent = 80;
}

plugin_config_set( 'manage_threshold',          $t_manage_threshold );
plugin_config_set( 'view_threshold',            $t_view_threshold );
plugin_config_set( 'action_threshold',          $t_action_threshold );
plugin_config_set( 'sla_warning_percent',       $t_sla_warning_percent );
plugin_config_set( 'business_hours_start',      $t_business_hours_start );
plugin_config_set( 'business_hours_end',        $t_business_hours_end );
plugin_config_set( 'working_days',              $t_working_days );
plugin_config_set( 'departments',               $t_departments );
plugin_config_set( 'allow_automatic_processes', $t_allow_automatic_processes );

form_security_purge( 'ProcessEngine_config_update' );
print_header_redirect( plugin_page( 'config_page', true ) );
