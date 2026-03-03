<?php
/**
 * ProcessEngine - Escalation API
 *
 * Handles escalation rules and triggers based on SLA violations.
 */

/**
 * Trigger escalation for a bug at a specific level.
 *
 * Level 1: Notify MANAGER role users (1.5x SLA)
 * Level 2: Notify ADMINISTRATOR role users (2x SLA)
 *
 * @param int $p_bug_id Bug ID
 * @param string $p_step_name Step name
 * @param int $p_level Escalation level (1 or 2)
 */
function escalation_trigger( $p_bug_id, $p_step_name, $p_level ) {
    require_once( __DIR__ . '/notification_api.php' );

    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );

    if( $p_level === 1 ) {
        // Notify managers
        $t_subject = sprintf( plugin_lang_get( 'escalation_level1_subject' ), $p_bug_id );
        $t_body = sprintf( plugin_lang_get( 'escalation_level1_body' ), $p_bug_id, $p_step_name );
        $t_users = escalation_get_users_by_access( $t_project_id, MANAGER );
        notification_send_to_users( $t_users, $t_subject, $t_body );
    } else if( $p_level === 2 ) {
        // Notify administrators
        $t_subject = sprintf( plugin_lang_get( 'escalation_level2_subject' ), $p_bug_id );
        $t_body = sprintf( plugin_lang_get( 'escalation_level2_body' ), $p_bug_id, $p_step_name );
        $t_users = escalation_get_users_by_access( $t_project_id, ADMINISTRATOR );
        notification_send_to_users( $t_users, $t_subject, $t_body );
    }

    // Signal custom event
    event_signal( 'EVENT_PROCESSENGINE_ESCALATION', array(
        'bug_id'    => $p_bug_id,
        'step_name' => $p_step_name,
        'level'     => $p_level,
    ) );
}

/**
 * Get users with a minimum access level for a project.
 *
 * @param int $p_project_id Project ID
 * @param int $p_access_level Minimum access level
 * @return array Array of user IDs
 */
function escalation_get_users_by_access( $p_project_id, $p_access_level ) {
    $t_users = project_get_all_user_rows( $p_project_id, $p_access_level );
    $t_ids = array();
    foreach( $t_users as $t_user ) {
        $t_ids[] = (int) $t_user['id'];
    }
    return $t_ids;
}
