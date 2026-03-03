<?php
/**
 * ProcessEngine - Flow Unpublish (AJAX endpoint)
 *
 * Deactivates an ACTIVE flow back to DRAFT status.
 */

auth_ensure_user_authenticated();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

header( 'Content-Type: application/json; charset=utf-8' );

$t_input = json_decode( file_get_contents( 'php://input' ), true );

// CSRF token doğrulama
if( isset( $t_input['_csrf_token'] ) ) {
    $_POST['ProcessEngine_flow_editor_token'] = $t_input['_csrf_token'];
}
form_security_validate( 'ProcessEngine_flow_editor' );
form_security_purge( 'ProcessEngine_flow_editor' );

$t_flow_id = isset( $t_input['flow_id'] ) ? (int) $t_input['flow_id'] : 0;

if( $t_flow_id === 0 ) {
    echo json_encode( array( 'success' => false, 'error' => 'Invalid flow ID' ) );
    exit;
}

$t_result = flow_unpublish( $t_flow_id );

if( $t_result === true ) {
    echo json_encode( array(
        'success' => true,
        '_csrf_token' => form_security_token( 'ProcessEngine_flow_editor' ),
    ) );
} elseif( $t_result === 'has_instances' ) {
    echo json_encode( array(
        'success' => false,
        'error'   => plugin_lang_get( 'flow_unpublish_has_instances' ),
        '_csrf_token' => form_security_token( 'ProcessEngine_flow_editor' ),
    ) );
} else {
    echo json_encode( array(
        'success' => false,
        'error'   => plugin_lang_get( 'flow_unpublish_not_active' ),
        '_csrf_token' => form_security_token( 'ProcessEngine_flow_editor' ),
    ) );
}
