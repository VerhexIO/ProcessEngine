<?php
/**
 * ProcessEngine - Dashboard Action Handler (AJAX)
 *
 * Handles advance_step and refresh_sla actions from the dashboard.
 * Returns JSON responses.
 */

auth_ensure_user_authenticated();
access_ensure_global_level( plugin_config_get( 'action_threshold' ) );

form_security_validate( 'ProcessEngine_dashboard_action' );

require_once( dirname( __DIR__ ) . '/core/process_api.php' );
require_once( dirname( __DIR__ ) . '/core/subprocess_api.php' );
require_once( dirname( __DIR__ ) . '/core/sla_api.php' );

$t_action = gpc_get_string( 'action', '' );
$t_bug_id = gpc_get_int( 'bug_id', 0 );

$t_response = array( 'success' => false, 'message' => '' );

if( $t_bug_id <= 0 || !bug_exists( $t_bug_id ) ) {
    $t_response['message'] = plugin_lang_get( 'no_data' );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo json_encode( $t_response );
    exit;
}

// Bug bazlı yetkilendirme kontrolü
if( !access_has_bug_level( plugin_config_get( 'action_threshold' ), $t_bug_id ) ) {
    $t_response['message'] = plugin_lang_get( 'no_data' );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo json_encode( $t_response );
    exit;
}

switch( $t_action ) {
    case 'advance_step':
        $t_response = pe_action_advance_step( $t_bug_id );
        break;

    case 'refresh_sla':
        $t_response = pe_action_refresh_sla( $t_bug_id );
        break;

    case 'create_subprocess':
        $t_response = pe_action_create_subprocess( $t_bug_id );
        break;

    case 'link_manual_child':
        $t_response = pe_action_link_manual_child( $t_bug_id );
        break;

    default:
        $t_response['message'] = plugin_lang_get( 'action_invalid' );
        break;
}

form_security_purge( 'ProcessEngine_dashboard_action' );

header( 'Content-Type: application/json; charset=utf-8' );
echo json_encode( $t_response );
exit;

/**
 * Advance a bug to its next process step.
 *
 * @param int $p_bug_id Bug ID
 * @return array Response array
 */
function pe_action_advance_step( $p_bug_id ) {
    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance === null ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'no_data' ) );
    }

    $t_flow_id = (int) $t_instance['flow_id'];
    $t_current_step_id = (int) $t_instance['current_step_id'];
    $t_instance_id = (int) $t_instance['id'];

    // Mevcut adımın bilgilerini oku (not zorunluluğu ve çıkış koşulları için)
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_cur_step_result = db_query( "SELECT * FROM $t_step_table WHERE id = " . db_param(), array( $t_current_step_id ) );
    $t_current_step = db_fetch_array( $t_cur_step_result );

    // Kullanıcı notu
    $t_advance_note = gpc_get_string( 'note', '' );

    // Not zorunluluğu kontrolü
    if( $t_current_step !== false
        && isset( $t_current_step['note_required'] )
        && (int) $t_current_step['note_required'] === 1
        && trim( $t_advance_note ) === ''
    ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'note_required_error' ) );
    }

    // Çıkış koşulu kontrolü
    if( $t_current_step !== false ) {
        $t_exit_check = process_check_step_exit_conditions( $p_bug_id, $t_current_step );
        if( !$t_exit_check['can_advance'] ) {
            return array( 'success' => false, 'message' => $t_exit_check['reason'] );
        }
    }

    // Not boşsa varsayılan mesaj
    if( trim( $t_advance_note ) === '' ) {
        $t_advance_note = plugin_lang_get( 'action_advance_success' );
    }

    // Geçerli geçişleri bul
    $t_valid_transitions = process_get_valid_transitions( $t_flow_id, $t_current_step_id, $p_bug_id );
    if( empty( $t_valid_transitions ) ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'action_advance_no_transition' ) );
    }

    // İlk geçerli geçişi seç
    $t_transition = $t_valid_transitions[0];
    $t_next_step_id = (int) $t_transition['to_step_id'];

    // Sonraki adımın bilgilerini oku
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_step_query = "SELECT * FROM $t_step_table WHERE id = " . db_param();
    $t_step_result = db_query( $t_step_query, array( $t_next_step_id ) );
    $t_next_step = db_fetch_array( $t_step_result );

    if( $t_next_step === false ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'action_advance_no_transition' ) );
    }

    $t_new_mantis_status = (int) $t_next_step['mantis_status'];
    $t_old_mantis_status = (int) bug_get_field( $p_bug_id, 'status' );

    // 1. Bug durumunu güncelle (doğrudan DB — hook tetiklememek için)
    $t_bug_table = db_get_table( 'bug' );
    db_param_push();
    db_query(
        "UPDATE $t_bug_table SET status = " . db_param() . ", last_updated = " . db_param()
        . " WHERE id = " . db_param(),
        array( $t_new_mantis_status, time(), $p_bug_id )
    );

    // MantisBT history kaydı ekle
    history_log_event_direct( $p_bug_id, 'status', $t_old_mantis_status, $t_new_mantis_status );

    // 2. Instance current_step_id güncelle
    subprocess_update_current_step( $t_instance_id, $t_next_step_id );

    // 3. SLA tamamla + yeni SLA başlat
    sla_complete_tracking( $p_bug_id );
    if( (int) $t_next_step['sla_hours'] > 0 ) {
        sla_start_tracking( $p_bug_id, $t_next_step_id, $t_flow_id, (int) $t_next_step['sla_hours'] );
    }

    // 4. Process log kaydı yaz
    $t_transition_label = isset( $t_transition['label'] ) ? $t_transition['label'] : '';
    $t_log_table = plugin_table( 'log' );
    db_param_push();
    db_query(
        "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ")",
        array(
            $p_bug_id,
            $t_flow_id,
            $t_next_step_id,
            $t_old_mantis_status,
            $t_new_mantis_status,
            auth_get_current_user_id(),
            $t_advance_note,
            time(),
            'step_advanced',
            $t_transition_label,
        )
    );

    // 5. Faz 11: Sonraki adım subprocess ise otomatik çocuk oluşturma YAPILMAZ.
    // Kullanıcı manuel olarak "Şimdi Aç" butonuyla oluşturacak.

    // 6. Bitiş adımı kontrolü
    subprocess_check_and_complete( $p_bug_id, $t_flow_id, $t_next_step_id );

    // 7. Otomatik sorumlu atama
    if( isset( $t_next_step['handler_id'] ) && (int) $t_next_step['handler_id'] > 0
        && user_exists( (int) $t_next_step['handler_id'] )
    ) {
        db_param_push();
        db_query(
            "UPDATE $t_bug_table SET handler_id = " . db_param() . " WHERE id = " . db_param(),
            array( (int) $t_next_step['handler_id'], $p_bug_id )
        );
    }

    return array( 'success' => true, 'message' => plugin_lang_get( 'action_advance_success' ) );
}

/**
 * Create a subprocess child issue manually.
 *
 * @param int $p_bug_id Parent bug ID
 * @return array Response array
 */
function pe_action_create_subprocess( $p_bug_id ) {
    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance === null ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'no_data' ) );
    }

    $t_current_step_id = (int) $t_instance['current_step_id'];
    $t_instance_id = (int) $t_instance['id'];

    // Mevcut adımın subprocess olup olmadığını kontrol et
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_step_result = db_query( "SELECT * FROM $t_step_table WHERE id = " . db_param(), array( $t_current_step_id ) );
    $t_step = db_fetch_array( $t_step_result );

    if( $t_step === false || !isset( $t_step['step_type'] ) || $t_step['step_type'] !== 'subprocess' ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'manual_link_not_subprocess' ) );
    }

    // Zaten çocuk var mı kontrol et
    $t_children = subprocess_get_children( $t_instance_id, $t_current_step_id );
    if( !empty( $t_children ) ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'subprocess_already_exists' ) );
    }

    $t_child_flow_id = isset( $t_step['child_flow_id'] ) ? (int) $t_step['child_flow_id'] : 0;
    if( $t_child_flow_id <= 0 ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'subprocess_invalid_flow' ) );
    }

    $t_child_project_id = isset( $t_step['child_project_id'] ) ? (int) $t_step['child_project_id'] : 0;
    if( $t_child_project_id <= 0 ) {
        $t_child_project_id = bug_get_field( $p_bug_id, 'project_id' );
    }

    $t_child_bug_id = subprocess_create_child_issue(
        $p_bug_id,
        $t_child_flow_id,
        $t_child_project_id,
        $t_instance_id,
        $t_current_step_id
    );

    if( $t_child_bug_id === null ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'no_data' ) );
    }

    // Süreç logu ekle
    $t_log_table = plugin_table( 'log' );
    db_param_push();
    db_query(
        "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ")",
        array(
            $p_bug_id,
            (int) $t_instance['flow_id'],
            $t_current_step_id,
            (int) bug_get_field( $p_bug_id, 'status' ),
            (int) bug_get_field( $p_bug_id, 'status' ),
            auth_get_current_user_id(),
            sprintf( plugin_lang_get( 'subprocess_created_note' ), $t_child_bug_id ),
            time(),
            'subprocess_created',
            '',
        )
    );

    return array(
        'success'      => true,
        'message'      => plugin_lang_get( 'subprocess_created_success' ),
        'child_bug_id' => $t_child_bug_id,
        'child_url'    => string_get_bug_view_url( $t_child_bug_id ),
    );
}

/**
 * Link a manually existing issue as subprocess child.
 *
 * @param int $p_bug_id Parent bug ID
 * @return array Response array
 */
function pe_action_link_manual_child( $p_bug_id ) {
    $t_child_bug_id = gpc_get_int( 'child_bug_id', 0 );
    if( $t_child_bug_id <= 0 || !bug_exists( $t_child_bug_id ) ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'no_data' ) );
    }

    $t_linked = subprocess_link_manual_child( $t_child_bug_id, $p_bug_id );
    if( !$t_linked ) {
        return array( 'success' => false, 'message' => plugin_lang_get( 'manual_link_not_subprocess' ) );
    }

    // Süreç logu ekle
    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance !== null ) {
        $t_log_table = plugin_table( 'log' );
        db_param_push();
        db_query(
            "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
            . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
            . db_param() . ", " . db_param() . ")",
            array(
                $p_bug_id,
                (int) $t_instance['flow_id'],
                (int) $t_instance['current_step_id'],
                (int) bug_get_field( $p_bug_id, 'status' ),
                (int) bug_get_field( $p_bug_id, 'status' ),
                auth_get_current_user_id(),
                sprintf( plugin_lang_get( 'subprocess_linked_note' ), $t_child_bug_id ),
                time(),
                'subprocess_created',
                '',
            )
        );
    }

    return array( 'success' => true, 'message' => plugin_lang_get( 'manual_link_success' ) );
}

/**
 * Refresh SLA status for a bug.
 *
 * @param int $p_bug_id Bug ID
 * @return array Response array
 */
function pe_action_refresh_sla( $p_bug_id ) {
    // Mevcut aktif SLA'yı kontrol et ve güncelle
    $t_sla_table = plugin_table( 'sla_tracking' );
    db_param_push();
    $t_query = "SELECT * FROM $t_sla_table
        WHERE bug_id = " . db_param() . "
        AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );

    if( $t_row === false ) {
        return array( 'success' => true, 'message' => plugin_lang_get( 'action_sla_refreshed' ) );
    }

    // SLA durumunu yeniden hesapla
    $t_now = time();
    $t_deadline = (int) $t_row['deadline_at'];
    $t_sla_hours = (int) $t_row['sla_hours'];
    $t_started = (int) $t_row['started_at'];
    $t_warning_pct = (int) plugin_config_get( 'sla_warning_percent' );

    $t_total_sec = $t_deadline - $t_started;
    $t_elapsed_sec = $t_now - $t_started;
    $t_elapsed_pct = ( $t_total_sec > 0 ) ? ( $t_elapsed_sec / $t_total_sec * 100 ) : 100;

    $t_new_status = 'NORMAL';
    if( $t_now >= $t_deadline ) {
        $t_new_status = 'EXCEEDED';
    } else if( $t_elapsed_pct >= $t_warning_pct ) {
        $t_new_status = 'WARNING';
    }

    if( $t_new_status !== $t_row['sla_status'] ) {
        sla_update_field( (int) $t_row['id'], 'sla_status', $t_new_status );
    }

    return array( 'success' => true, 'message' => plugin_lang_get( 'action_sla_refreshed' ) );
}
