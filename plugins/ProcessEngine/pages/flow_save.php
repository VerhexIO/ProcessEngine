<?php
/**
 * ProcessEngine - Flow Save (AJAX endpoint)
 *
 * Accepts JSON POST with flow data and saves to database.
 */

// AJAX endpoint: auth_ensure ile kontrol (auth_reauthenticate HTML form döndürür)
auth_ensure_user_authenticated();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

header( 'Content-Type: application/json; charset=utf-8' );

$t_input = json_decode( file_get_contents( 'php://input' ), true );

// CSRF token doğrulama (JSON body'den $_POST'a simüle et)
if( isset( $t_input['_csrf_token'] ) ) {
    $_POST['ProcessEngine_flow_editor_token'] = $t_input['_csrf_token'];
}
form_security_validate( 'ProcessEngine_flow_editor' );
form_security_purge( 'ProcessEngine_flow_editor' );

if( !$t_input || !isset( $t_input['flow_id'] ) ) {
    echo json_encode( array( 'success' => false, 'error' => 'Invalid input' ) );
    exit;
}

$t_flow_id = (int) $t_input['flow_id'];
$t_flow = flow_get( $t_flow_id );

if( $t_flow === null ) {
    echo json_encode( array( 'success' => false, 'error' => 'Flow not found' ) );
    exit;
}

// Update flow metadata
if( isset( $t_input['name'] ) ) {
    $t_project_id = isset( $t_input['project_id'] ) ? (int) $t_input['project_id'] : 0;
    flow_update( $t_flow_id, $t_input['name'], isset( $t_input['description'] ) ? $t_input['description'] : '', $t_project_id );
}

// Aktif akış koruması
if( (int) $t_flow['status'] === 2 ) {
    echo json_encode( array( 'success' => false, 'error' => plugin_lang_get( 'flow_edit_blocked' ) ) );
    exit;
}

// Save steps and transitions
$t_steps = isset( $t_input['steps'] ) ? $t_input['steps'] : array();
$t_transitions = isset( $t_input['transitions'] ) ? $t_input['transitions'] : array();

$t_id_map = flow_save_complete( $t_flow_id, $t_steps, $t_transitions );

if( $t_id_map === false ) {
    echo json_encode( array( 'success' => false, 'error' => plugin_lang_get( 'flow_edit_blocked' ) ) );
    exit;
}

// Return updated data
$t_new_steps = flow_get_steps( $t_flow_id );
$t_new_transitions = flow_get_transitions( $t_flow_id );

echo json_encode( array(
    'success'      => true,
    'id_map'       => $t_id_map,
    'steps'        => $t_new_steps,
    'transitions'  => $t_new_transitions,
    '_csrf_token'  => form_security_token( 'ProcessEngine_flow_editor' ),
) );
