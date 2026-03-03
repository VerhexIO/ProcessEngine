<?php
/**
 * ProcessEngine - Notification API
 *
 * Email notification templates and sending functions for SLA events.
 * Uses MantisBT's built-in email infrastructure.
 */

/**
 * Send SLA warning notification to the assigned user of a bug.
 *
 * @param int $p_bug_id Bug ID
 * @param string $p_step_name Step name
 * @param int $p_sla_hours Total SLA hours
 * @param int $p_elapsed_seconds Elapsed seconds
 */
function notification_send_sla_warning( $p_bug_id, $p_step_name, $p_sla_hours, $p_elapsed_seconds ) {
    $t_handler_id = bug_get_field( $p_bug_id, 'handler_id' );
    if( $t_handler_id == 0 ) {
        return; // No one assigned
    }

    $t_remaining_hours = round( ( $p_sla_hours * 3600 - $p_elapsed_seconds ) / 3600, 1 );
    $t_subject = sprintf( plugin_lang_get( 'escalation_warning_subject' ), $p_bug_id );
    $t_body = sprintf( plugin_lang_get( 'escalation_warning_body' ), $p_bug_id, $p_step_name, $t_remaining_hours );
    $t_body .= "\n\n" . notification_get_bug_url( $p_bug_id );

    notification_send_to_users( array( $t_handler_id ), $t_subject, $t_body );
}

/**
 * Send SLA exceeded notification to the assigned user and department managers.
 *
 * @param int $p_bug_id Bug ID
 * @param string $p_step_name Step name
 * @param int $p_sla_hours Total SLA hours
 * @param int $p_elapsed_seconds Elapsed seconds
 */
function notification_send_sla_exceeded( $p_bug_id, $p_step_name, $p_sla_hours, $p_elapsed_seconds ) {
    $t_handler_id = bug_get_field( $p_bug_id, 'handler_id' );
    $t_overdue_hours = round( ( $p_elapsed_seconds - $p_sla_hours * 3600 ) / 3600, 1 );
    $t_subject = sprintf( plugin_lang_get( 'escalation_exceeded_subject' ), $p_bug_id );
    $t_body = sprintf( plugin_lang_get( 'escalation_exceeded_body' ), $p_bug_id, $p_step_name, $t_overdue_hours );
    $t_body .= "\n\n" . notification_get_bug_url( $p_bug_id );

    $t_users = array();
    if( $t_handler_id > 0 ) {
        $t_users[] = $t_handler_id;
    }

    // Also notify project managers
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_managers = project_get_all_user_rows( $t_project_id, MANAGER );
    foreach( $t_managers as $t_mgr ) {
        $t_mgr_id = (int) $t_mgr['id'];
        if( !in_array( $t_mgr_id, $t_users ) ) {
            $t_users[] = $t_mgr_id;
        }
    }

    notification_send_to_users( $t_users, $t_subject, $t_body );
}

/**
 * Send email to a list of users.
 *
 * @param array $p_user_ids Array of user IDs
 * @param string $p_subject Email subject
 * @param string $p_body Email body
 */
function notification_send_to_users( $p_user_ids, $p_subject, $p_body ) {
    foreach( $p_user_ids as $t_user_id ) {
        if( $t_user_id <= 0 ) {
            continue;
        }
        if( !user_exists( $t_user_id ) ) {
            continue;
        }
        $t_email = user_get_email( $t_user_id );
        if( is_blank( $t_email ) ) {
            continue;
        }
        $t_header = 'X-MantisBT-ProcessEngine: SLA-Notification';
        email_store( $t_email, $p_subject, $p_body, $t_header );
    }

    // Trigger email send if not using cronjob
    if( config_get( 'email_send_using_cronjob' ) == OFF ) {
        email_send_all();
    }
}

/**
 * Get the URL for viewing a bug.
 *
 * @param int $p_bug_id Bug ID
 * @return string URL
 */
function notification_get_bug_url( $p_bug_id ) {
    return config_get( 'path' ) . 'view.php?id=' . (int) $p_bug_id;
}
