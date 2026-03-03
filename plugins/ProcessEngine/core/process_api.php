<?php
/**
 * ProcessEngine - Core Process API
 *
 * Provides functions for status change logging, process queries,
 * and flow-to-bug matching.
 */

/**
 * Log a status change for a bug in the process log.
 * Finds the active flow and matching step for the new status.
 *
 * @param int $p_bug_id   Bug ID
 * @param int $p_old_status Previous status
 * @param int $p_new_status New status
 * @param string $p_note  Optional note
 */
function process_log_status_change( $p_bug_id, $p_old_status, $p_new_status, $p_note = '', $p_event_type = 'status_change', $p_transition_label = '' ) {
    if( !bug_exists( $p_bug_id ) ) {
        return;
    }
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_flow = process_get_active_flow_for_project( $t_project_id );

    if( $t_flow === null ) {
        return;
    }

    $t_step = process_find_step_by_status( $t_flow['id'], $p_new_status );
    $t_step_id = ( $t_step !== null ) ? $t_step['id'] : 0;

    // Akış dışı geçişleri otomatik işaretle
    if( $p_note === plugin_lang_get( 'out_of_flow_transition' ) ) {
        $p_event_type = 'out_of_flow';
    }

    $t_log_table = plugin_table( 'log' );
    db_param_push();
    $t_query = "INSERT INTO $t_log_table
        ( bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label )
        VALUES
        ( " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . " )";

    db_query( $t_query, array(
        (int) $p_bug_id,
        (int) $t_flow['id'],
        (int) $t_step_id,
        (int) $p_old_status,
        (int) $p_new_status,
        (int) auth_get_current_user_id(),
        $p_note,
        time(),
        $p_event_type,
        $p_transition_label,
    ) );

    // Trigger custom event
    event_signal( 'EVENT_PROCESSENGINE_STATUS_CHANGED', array(
        'bug_id'      => $p_bug_id,
        'flow_id'     => $t_flow['id'],
        'step_id'     => $t_step_id,
        'from_status' => $p_old_status,
        'to_status'   => $p_new_status,
    ) );
}

/**
 * Get the active flow for a project.
 * Falls back to flows with project_id = 0 (global).
 *
 * @param int $p_project_id Project ID
 * @return array|null Flow row or null
 */
function process_get_active_flow_for_project( $p_project_id ) {
    $t_table = plugin_table( 'flow_definition' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE status = 2
        AND ( project_id = " . db_param() . " OR project_id = 0 )
        ORDER BY project_id DESC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_project_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Find the step in a flow that matches a given MantisBT status.
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_mantis_status MantisBT status value
 * @return array|null Step row or null
 */
function process_find_step_by_status( $p_flow_id, $p_mantis_status ) {
    $t_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE flow_id = " . db_param() . "
        AND mantis_status = " . db_param() . "
        ORDER BY step_order ASC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_mantis_status ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Get all process logs for a bug, with step name joined.
 *
 * @param int $p_bug_id Bug ID
 * @return array Array of log rows
 */
function process_get_logs_for_bug( $p_bug_id ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT l.*, COALESCE(s.name, '') AS step_name
        FROM $t_log_table l
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE l.bug_id = " . db_param() . "
        ORDER BY l.created_at ASC";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );

    $t_logs = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_logs[] = $t_row;
    }
    return $t_logs;
}

/**
 * Get the latest log entry for a bug (current step info).
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Latest log row or null
 */
function process_get_current_step_for_bug( $p_bug_id ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT l.*, COALESCE(s.name, '') AS step_name, COALESCE(s.department, '') AS department
        FROM $t_log_table l
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE l.bug_id = " . db_param() . "
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Find the start step of a flow (step with no incoming transitions).
 *
 * @param int $p_flow_id Flow ID
 * @return array|null Step row or null
 */
function process_find_start_step( $p_flow_id ) {
    $t_step_table = plugin_table( 'step' );
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT s.* FROM $t_step_table s
        WHERE s.flow_id = " . db_param() . "
        AND s.id NOT IN (SELECT to_step_id FROM $t_trans_table WHERE flow_id = " . db_param() . ")
        ORDER BY s.step_order ASC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_flow_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Log initial process entry when a bug is created.
 *
 * @param int $p_bug_id Bug ID
 * @param int $p_flow_id Flow ID
 * @param array $p_step Start step data
 */
function process_log_initial( $p_bug_id, $p_flow_id, $p_step, $p_event_type = 'process_started' ) {
    $t_log_table = plugin_table( 'log' );
    db_param_push();
    $t_query = "INSERT INTO $t_log_table
        ( bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label )
        VALUES ( " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . " )";
    db_query( $t_query, array(
        (int) $p_bug_id,
        (int) $p_flow_id,
        (int) $p_step['id'],
        0,
        (int) $p_step['mantis_status'],
        (int) auth_get_current_user_id(),
        plugin_lang_get( 'process_started' ),
        time(),
        $p_event_type,
        '',
    ) );
}

/**
 * Check if a transition exists between two steps (by MantisBT status values).
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_from_status MantisBT from status
 * @param int $p_to_status MantisBT to status
 * @return bool True if transition exists
 */
function process_transition_exists( $p_flow_id, $p_from_status, $p_to_status ) {
    $t_step_table = plugin_table( 'step' );
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT t.id FROM $t_trans_table t
        INNER JOIN $t_step_table sf ON t.from_step_id = sf.id AND sf.flow_id = " . db_param() . "
        INNER JOIN $t_step_table st ON t.to_step_id = st.id AND st.flow_id = " . db_param() . "
        WHERE t.flow_id = " . db_param() . "
        AND sf.mantis_status = " . db_param() . "
        AND st.mantis_status = " . db_param() . "
        LIMIT 1";
    $t_result = db_query( $t_query, array(
        (int) $p_flow_id, (int) $p_flow_id, (int) $p_flow_id,
        (int) $p_from_status, (int) $p_to_status
    ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false );
}

/**
 * Get flow progress information for a bug.
 * Returns all steps with their completion status.
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Progress data or null if no process
 */
function process_get_flow_progress( $p_bug_id ) {
    if( !bug_exists( $p_bug_id ) ) {
        return null;
    }
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    $t_flow = process_get_active_flow_for_project( $t_project_id );
    if( $t_flow === null ) {
        return null;
    }

    require_once( dirname( __FILE__ ) . '/flow_api.php' );
    $t_steps = flow_get_steps( (int) $t_flow['id'] );
    if( empty( $t_steps ) ) {
        return null;
    }

    // Sürecin geçtiği tüm durum loglarını al
    $t_logs = process_get_logs_for_bug( $p_bug_id );
    $t_visited_step_ids = array();
    foreach( $t_logs as $t_log ) {
        if( (int) $t_log['step_id'] > 0 ) {
            $t_visited_step_ids[(int) $t_log['step_id']] = true;
        }
    }

    // Mevcut durumu al
    $t_current = process_get_current_step_for_bug( $p_bug_id );
    $t_current_step_id = $t_current !== null ? (int) $t_current['step_id'] : 0;

    // SLA bilgisi al
    $t_sla_table = plugin_table( 'sla_tracking' );
    db_param_push();
    $t_sla_query = "SELECT * FROM $t_sla_table
        WHERE bug_id = " . db_param() . "
        AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1";
    $t_sla_result = db_query( $t_sla_query, array( (int) $p_bug_id ) );
    $t_sla_row = db_fetch_array( $t_sla_result );

    // Her adımın durumunu belirle
    $t_step_list = array();
    $t_current_index = -1;
    foreach( $t_steps as $i => $t_step ) {
        $t_step_id = (int) $t_step['id'];
        if( $t_step_id === $t_current_step_id ) {
            $t_status = 'current';
            $t_current_index = $i;
        } else if( isset( $t_visited_step_ids[$t_step_id] ) && $t_step_id !== $t_current_step_id ) {
            $t_status = 'completed';
        } else {
            $t_status = 'pending';
        }
        $t_step_list[] = array(
            'id'            => $t_step_id,
            'name'          => $t_step['name'],
            'department'    => $t_step['department'],
            'handler_id'    => isset( $t_step['handler_id'] ) ? (int) $t_step['handler_id'] : 0,
            'sla_hours'     => (int) $t_step['sla_hours'],
            'status'        => $t_status,
        );
    }

    $t_current_sla = null;
    if( $t_sla_row !== false ) {
        $t_now = time();
        $t_deadline = (int) $t_sla_row['deadline_at'];
        $t_remaining_sec = $t_deadline - $t_now;
        $t_current_sla = array(
            'sla_status'    => $t_sla_row['sla_status'],
            'deadline_at'   => $t_deadline,
            'remaining_sec' => $t_remaining_sec,
            'remaining_hrs' => round( $t_remaining_sec / 3600, 1 ),
        );
    }

    return array(
        'flow'               => $t_flow,
        'steps'              => $t_step_list,
        'current_step_index' => $t_current_index,
        'total_steps'        => count( $t_step_list ),
        'current_sla'        => $t_current_sla,
    );
}

/**
 * Get all unique bug IDs that have process log entries.
 *
 * @return array Array of bug IDs
 */
function process_get_tracked_bug_ids() {
    $t_log_table = plugin_table( 'log' );
    $t_query = "SELECT DISTINCT bug_id FROM $t_log_table ORDER BY bug_id DESC";
    $t_result = db_query( $t_query );

    $t_ids = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_ids[] = (int) $t_row['bug_id'];
    }
    return $t_ids;
}

/**
 * Get dashboard summary statistics.
 *
 * @return array Associative array with dashboard counts
 */
function process_get_dashboard_stats() {
    $t_log_table = plugin_table( 'log' );
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_bug_table = db_get_table( 'bug' );
    $t_today_start = mktime( 0, 0, 0 );

    // Proje bazlı erişim kontrolü
    $t_user_id = auth_get_current_user_id();
    $t_accessible_projects = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible_projects ) ) {
        return array(
            'total' => 0, 'active' => 0, 'sla_exceeded' => 0,
            'avg_time' => 0, 'today' => 0, 'pending' => 0, 'waiting_subprocesses' => 0,
        );
    }
    $t_project_ids = array_map( 'intval', $t_accessible_projects );
    $t_project_in = implode( ',', $t_project_ids );

    // Total unique bugs with process logs (proje filtreli)
    $t_result = db_query( "SELECT COUNT(DISTINCT l.bug_id) AS cnt FROM $t_log_table l
        INNER JOIN $t_bug_table b ON l.bug_id = b.id WHERE b.project_id IN ($t_project_in)" );
    $t_row = db_fetch_array( $t_result );
    $t_total = (int) $t_row['cnt'];

    // Active SLA trackings (proje filtreli)
    $t_result = db_query( "SELECT COUNT(DISTINCT st.bug_id) AS cnt FROM $t_sla_table st
        INNER JOIN $t_bug_table b ON st.bug_id = b.id WHERE st.completed_at IS NULL AND b.project_id IN ($t_project_in)" );
    $t_row = db_fetch_array( $t_result );
    $t_active = (int) $t_row['cnt'];

    // SLA exceeded (proje filtreli)
    $t_result = db_query( "SELECT COUNT(*) AS cnt FROM $t_sla_table st
        INNER JOIN $t_bug_table b ON st.bug_id = b.id WHERE st.sla_status = 'EXCEEDED' AND st.completed_at IS NULL AND b.project_id IN ($t_project_in)" );
    $t_row = db_fetch_array( $t_result );
    $t_sla_exceeded = (int) $t_row['cnt'];

    // Average resolution time (proje filtreli)
    $t_result = db_query( "SELECT AVG(st.completed_at - st.started_at) AS avg_time FROM $t_sla_table st
        INNER JOIN $t_bug_table b ON st.bug_id = b.id WHERE st.completed_at IS NOT NULL AND st.completed_at > 0 AND b.project_id IN ($t_project_in)" );
    $t_row = db_fetch_array( $t_result );
    $t_avg_time = $t_row['avg_time'] ? round( (float) $t_row['avg_time'] / 3600, 1 ) : 0;

    // Updated today (proje filtreli)
    db_param_push();
    $t_result = db_query( "SELECT COUNT(DISTINCT l.bug_id) AS cnt FROM $t_log_table l
        INNER JOIN $t_bug_table b ON l.bug_id = b.id WHERE l.created_at >= " . db_param() . " AND b.project_id IN ($t_project_in)", array( $t_today_start ) );
    $t_row = db_fetch_array( $t_result );
    $t_today = (int) $t_row['cnt'];

    // Pending (proje filtreli)
    $t_pending = 0;
    $t_bug_ids = process_get_tracked_bug_ids();
    foreach( $t_bug_ids as $t_bug_id ) {
        if( bug_exists( $t_bug_id ) ) {
            $t_project = bug_get_field( $t_bug_id, 'project_id' );
            if( !in_array( (int) $t_project, $t_project_ids ) ) {
                continue;
            }
            $t_status = bug_get_field( $t_bug_id, 'status' );
            if( $t_status < config_get( 'bug_resolved_status_threshold' ) ) {
                $t_pending++;
            }
        }
    }

    // Bekleyen alt süreçler (proje filtreli)
    $t_inst_table = plugin_table( 'process_instance' );
    $t_result = db_query( "SELECT COUNT(*) AS cnt FROM $t_inst_table pi
        INNER JOIN $t_bug_table b ON pi.bug_id = b.id WHERE pi.status = 'WAITING' AND b.project_id IN ($t_project_in)" );
    $t_row = db_fetch_array( $t_result );
    $t_waiting_subprocesses = (int) $t_row['cnt'];

    return array(
        'total'                 => $t_total,
        'active'                => $t_active,
        'sla_exceeded'          => $t_sla_exceeded,
        'avg_time'              => $t_avg_time,
        'today'                 => $t_today,
        'pending'               => $t_pending,
        'waiting_subprocesses'  => $t_waiting_subprocesses,
    );
}

/**
 * Get the list of departments from config + existing step_table entries.
 *
 * @return array Sorted array of department names
 */
function process_get_departments() {
    // 1. Yapılandırmadan tanımlı departmanları al
    $t_config = plugin_config_get( 'departments', '' );
    $t_depts = array();
    if( $t_config !== '' ) {
        $t_depts = array_map( 'trim', explode( ',', $t_config ) );
        $t_depts = array_filter( $t_depts, function( $v ) { return $v !== ''; } );
    }
    // 2. step_table'daki mevcut departmanları da ekle
    $t_step_table = plugin_table( 'step' );
    $t_result = db_query( "SELECT DISTINCT department FROM $t_step_table WHERE department != '' ORDER BY department" );
    while( $t_row = db_fetch_array( $t_result ) ) {
        if( !in_array( $t_row['department'], $t_depts ) ) {
            $t_depts[] = $t_row['department'];
        }
    }
    sort( $t_depts );
    return $t_depts;
}

/**
 * Get the process instance for a bug from the process_instance table.
 * Falls back to log-based lookup if instance table is empty.
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Instance data with flow/step info, or null
 */
function process_get_instance( $p_bug_id ) {
    $t_inst_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "SELECT * FROM $t_inst_table
        WHERE bug_id = " . db_param() . "
        AND status IN ('ACTIVE', 'WAITING')
        ORDER BY id DESC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false ) {
        return $t_row;
    }

    // Tamamlanmış olanları da kontrol et
    db_param_push();
    $t_query = "SELECT * FROM $t_inst_table
        WHERE bug_id = " . db_param() . "
        ORDER BY id DESC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

// ============================================================
// Faz 11: Birleşik Aktivite Zaman Çizelgesi
// ============================================================

/**
 * Get a unified timeline combining process logs and bugnotes for a bug.
 * Returns events sorted by creation time ascending.
 *
 * @param int $p_bug_id Bug ID
 * @return array Array of timeline events
 */
function process_get_unified_timeline( $p_bug_id ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    $t_bugnote_table = db_get_table( 'bugnote' );
    $t_bugnote_text_table = db_get_table( 'bugnote_text' );

    $t_events = array();

    // Kaynak 1: Süreç logları
    db_param_push();
    $t_query = "SELECT l.*, COALESCE(s.name, '') AS step_name
        FROM $t_log_table l
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE l.bug_id = " . db_param() . "
        ORDER BY l.created_at ASC";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_event_type = isset( $t_row['event_type'] ) ? $t_row['event_type'] : 'status_change';
        $t_events[] = array(
            'source'           => 'process_log',
            'event_type'       => $t_event_type,
            'created_at'       => (int) $t_row['created_at'],
            'user_id'          => (int) $t_row['user_id'],
            'step_name'        => $t_row['step_name'],
            'from_status'      => (int) $t_row['from_status'],
            'to_status'        => (int) $t_row['to_status'],
            'note'             => $t_row['note'],
            'transition_label' => isset( $t_row['transition_label'] ) ? $t_row['transition_label'] : '',
        );
    }

    // Kaynak 2: MantisBT bugnote'ları
    $t_manage_threshold = plugin_config_get( 'manage_threshold' );
    $t_current_user_id = auth_get_current_user_id();
    $t_user_access = access_get_global_level();

    db_param_push();
    $t_query = "SELECT bn.id, bn.reporter_id, bn.view_state, bn.date_submitted,
            bnt.note AS bugnote_text
        FROM $t_bugnote_table bn
        LEFT JOIN $t_bugnote_text_table bnt ON bn.bugnote_text_id = bnt.id
        WHERE bn.bug_id = " . db_param() . "
        ORDER BY bn.date_submitted ASC";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    while( $t_row = db_fetch_array( $t_result ) ) {
        // Private bugnote filtreleme
        if( (int) $t_row['view_state'] === VS_PRIVATE ) {
            if( $t_user_access < $t_manage_threshold && (int) $t_row['reporter_id'] !== $t_current_user_id ) {
                continue;
            }
        }
        $t_events[] = array(
            'source'           => 'bugnote',
            'event_type'       => 'note_added',
            'created_at'       => (int) $t_row['date_submitted'],
            'user_id'          => (int) $t_row['reporter_id'],
            'step_name'        => '',
            'from_status'      => 0,
            'to_status'        => 0,
            'note'             => $t_row['bugnote_text'],
            'transition_label' => '',
        );
    }

    // Zamana göre sırala
    usort( $t_events, function( $a, $b ) {
        return $a['created_at'] - $b['created_at'];
    });

    return $t_events;
}

/**
 * Check if step exit conditions are met before advancing.
 *
 * @param int $p_bug_id Bug ID
 * @param array $p_step Current step data
 * @return array ['can_advance' => bool, 'reason' => string]
 */
function process_check_step_exit_conditions( $p_bug_id, $p_step ) {
    $t_criteria = isset( $p_step['completion_criteria'] ) ? $p_step['completion_criteria'] : 'manual';

    switch( $t_criteria ) {
        case 'on_status':
            $t_target = isset( $p_step['completion_status'] ) ? (int) $p_step['completion_status'] : 0;
            if( $t_target > 0 ) {
                $t_current = (int) bug_get_field( $p_bug_id, 'status' );
                if( $t_current !== $t_target ) {
                    $t_target_label = get_enum_element( 'status', $t_target );
                    return array(
                        'can_advance' => false,
                        'reason'      => sprintf( plugin_lang_get( 'exit_condition_status_required' ), $t_target_label ),
                    );
                }
            }
            break;

        case 'on_resolve':
            $t_resolved = config_get( 'bug_resolved_status_threshold' );
            $t_current = (int) bug_get_field( $p_bug_id, 'status' );
            if( $t_current < $t_resolved ) {
                return array(
                    'can_advance' => false,
                    'reason'      => plugin_lang_get( 'exit_condition_resolve_required' ),
                );
            }
            break;

        case 'manual':
        default:
            break;
    }

    return array( 'can_advance' => true, 'reason' => '' );
}

// ============================================================
// Faz 5: Koşullu Geçiş Değerlendirme
// ============================================================

/**
 * Evaluate a transition condition against a bug.
 *
 * @param array $p_transition Transition row (with condition_type, condition_field, condition_value)
 * @param int   $p_bug_id    Bug ID
 * @return bool True if condition is met (or no condition)
 */
function process_evaluate_condition( $p_transition, $p_bug_id ) {
    $t_condition_type = isset( $p_transition['condition_type'] ) ? $p_transition['condition_type'] : '';

    // Koşulsuz geçiş
    if( $t_condition_type === '' || $t_condition_type === 'none' ) {
        return true;
    }

    $t_field = isset( $p_transition['condition_field'] ) ? $p_transition['condition_field'] : '';
    $t_value = isset( $p_transition['condition_value'] ) ? $p_transition['condition_value'] : '';

    if( !bug_exists( $p_bug_id ) ) {
        return false;
    }

    switch( $t_condition_type ) {
        case 'field_equals':
            if( $t_field === '' ) {
                return true;
            }
            // MantisBT bug alanı kontrolü
            $t_valid_fields = array( 'priority', 'severity', 'reproducibility', 'status',
                'resolution', 'projection', 'eta', 'handler_id', 'category_id' );
            if( in_array( $t_field, $t_valid_fields ) ) {
                $t_actual = bug_get_field( $p_bug_id, $t_field );
                return ( (string) $t_actual === (string) $t_value );
            }
            // Özel alan kontrolü (custom field)
            if( strpos( $t_field, 'custom_' ) === 0 ) {
                $t_cf_id = (int) str_replace( 'custom_', '', $t_field );
                if( $t_cf_id > 0 && custom_field_exists( $t_cf_id ) ) {
                    $t_cf_value = custom_field_get_value( $t_cf_id, $p_bug_id );
                    return ( (string) $t_cf_value === (string) $t_value );
                }
            }
            return false;

        case 'status_is':
            $t_actual_status = bug_get_field( $p_bug_id, 'status' );
            return ( (string) $t_actual_status === (string) $t_value );

        default:
            return true;
    }
}

/**
 * Get valid (condition-passing) transitions from a step for a bug.
 * Returns transitions ordered by priority (ID ascending).
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_step_id Current step ID
 * @param int $p_bug_id  Bug ID for condition evaluation
 * @return array Array of valid transition rows
 */
function process_get_valid_transitions( $p_flow_id, $p_step_id, $p_bug_id ) {
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT * FROM $t_trans_table
        WHERE flow_id = " . db_param() . "
        AND from_step_id = " . db_param() . "
        ORDER BY id ASC";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_step_id ) );

    $t_valid = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        if( process_evaluate_condition( $t_row, $p_bug_id ) ) {
            $t_valid[] = $t_row;
        }
    }
    return $t_valid;
}

// ============================================================
// Faz 12: Rapor API Fonksiyonları
// ============================================================

/**
 * Get report data with filtering and pagination.
 *
 * @param array $p_filters Associative array with filter keys
 * @return array ['rows' => array, 'total' => int, 'summary' => array]
 */
function process_get_report_data( $p_filters ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_flow_table = plugin_table( 'flow_definition' );
    $t_inst_table = plugin_table( 'process_instance' );
    $t_bug_table = db_get_table( 'bug' );

    $t_date_from   = isset( $p_filters['date_from'] ) ? (int) $p_filters['date_from'] : 0;
    $t_date_to     = isset( $p_filters['date_to'] ) ? (int) $p_filters['date_to'] : 0;
    $t_project_id  = isset( $p_filters['project_id'] ) ? (int) $p_filters['project_id'] : 0;
    $t_department  = isset( $p_filters['department'] ) ? trim( $p_filters['department'] ) : '';
    $t_flow_id     = isset( $p_filters['flow_id'] ) ? (int) $p_filters['flow_id'] : 0;
    $t_status_filter = isset( $p_filters['status'] ) ? trim( $p_filters['status'] ) : '';
    $t_page        = isset( $p_filters['page'] ) ? max( 1, (int) $p_filters['page'] ) : 1;
    $t_per_page    = isset( $p_filters['per_page'] ) ? max( 1, (int) $p_filters['per_page'] ) : 25;

    // Proje erişim kontrolü
    $t_user_id = auth_get_current_user_id();
    $t_accessible = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible ) ) {
        return array( 'rows' => array(), 'total' => 0, 'summary' => array() );
    }
    $t_project_ids = array_map( 'intval', $t_accessible );
    if( $t_project_id > 0 && in_array( $t_project_id, $t_project_ids ) ) {
        $t_project_ids = array( $t_project_id );
    }
    $t_project_in = implode( ',', $t_project_ids );

    // Instance bazlı sorgu — her instance bir satır
    $t_where = "WHERE b.project_id IN ($t_project_in)";
    $t_params = array();

    if( $t_date_from > 0 ) {
        $t_where .= " AND pi.created_at >= " . db_param();
        $t_params[] = $t_date_from;
    }
    if( $t_date_to > 0 ) {
        $t_where .= " AND pi.created_at <= " . db_param();
        $t_params[] = $t_date_to;
    }
    if( $t_flow_id > 0 ) {
        $t_where .= " AND pi.flow_id = " . db_param();
        $t_params[] = $t_flow_id;
    }

    // SLA filtresi için sub-query
    $t_sla_join = "LEFT JOIN (
        SELECT bug_id, sla_status, started_at, completed_at,
            (CASE WHEN completed_at IS NOT NULL THEN completed_at - started_at ELSE NULL END) AS duration_sec
        FROM $t_sla_table st1
        WHERE st1.id = (SELECT MAX(st2.id) FROM $t_sla_table st2 WHERE st2.bug_id = st1.bug_id)
    ) sla ON sla.bug_id = pi.bug_id";

    // Durum filtresi
    if( $t_status_filter === 'active' ) {
        $t_where .= " AND pi.status IN ('ACTIVE', 'WAITING')";
    } else if( $t_status_filter === 'completed' ) {
        $t_where .= " AND pi.status = 'COMPLETED'";
    } else if( $t_status_filter === 'sla_exceeded' ) {
        $t_where .= " AND sla.sla_status = 'EXCEEDED'";
    }

    // Toplam sayı
    db_param_push();
    $t_count_query = "SELECT COUNT(DISTINCT pi.id) AS cnt
        FROM $t_inst_table pi
        INNER JOIN $t_bug_table b ON pi.bug_id = b.id
        $t_sla_join
        LEFT JOIN $t_step_table s ON pi.current_step_id = s.id
        $t_where";
    if( $t_department !== '' ) {
        $t_count_query .= " AND s.department = " . db_param();
        $t_count_params = array_merge( $t_params, array( $t_department ) );
    } else {
        $t_count_params = $t_params;
    }
    $t_count_result = db_query( $t_count_query, $t_count_params );
    $t_count_row = db_fetch_array( $t_count_result );
    $t_total = (int) $t_count_row['cnt'];

    // Ana veri sorgusu
    $t_offset = ( $t_page - 1 ) * $t_per_page;
    db_param_push();
    $t_main_query = "SELECT pi.*, b.summary, b.handler_id, b.status AS bug_status,
            COALESCE(s.name, '') AS step_name, COALESCE(s.department, '') AS department,
            COALESCE(f.name, '') AS flow_name,
            sla.sla_status, sla.duration_sec
        FROM $t_inst_table pi
        INNER JOIN $t_bug_table b ON pi.bug_id = b.id
        $t_sla_join
        LEFT JOIN $t_step_table s ON pi.current_step_id = s.id
        LEFT JOIN $t_flow_table f ON pi.flow_id = f.id
        $t_where";
    if( $t_department !== '' ) {
        $t_main_query .= " AND s.department = " . db_param();
        $t_main_params = array_merge( $t_params, array( $t_department ) );
    } else {
        $t_main_params = $t_params;
    }
    $t_main_query .= " ORDER BY pi.created_at DESC LIMIT $t_per_page OFFSET $t_offset";
    $t_result = db_query( $t_main_query, $t_main_params );

    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_bug_id = (int) $t_row['bug_id'];
        $t_handler_name = '';
        if( (int) $t_row['handler_id'] > 0 && user_exists( (int) $t_row['handler_id'] ) ) {
            $t_handler_name = user_get_name( (int) $t_row['handler_id'] );
        }

        $t_duration_hrs = null;
        if( $t_row['duration_sec'] !== null ) {
            $t_duration_hrs = round( (float) $t_row['duration_sec'] / 3600, 1 );
        }

        // Bekleme nedeni
        $t_wait_reason = '';
        if( $t_row['status'] === 'WAITING' ) {
            db_param_push();
            $t_child_q = "SELECT ci.bug_id FROM $t_inst_table ci
                WHERE ci.parent_instance_id = " . db_param() . " AND ci.status IN ('ACTIVE', 'WAITING')";
            $t_child_r = db_query( $t_child_q, array( (int) $t_row['id'] ) );
            $t_waiting_for = array();
            while( $t_child_row = db_fetch_array( $t_child_r ) ) {
                $t_waiting_for[] = '#' . $t_child_row['bug_id'];
            }
            if( !empty( $t_waiting_for ) ) {
                $t_wait_reason = implode( ', ', $t_waiting_for );
            }
        }

        $t_rows[] = array(
            'bug_id'        => $t_bug_id,
            'summary'       => $t_row['summary'],
            'flow_name'     => $t_row['flow_name'],
            'step_name'     => $t_row['step_name'],
            'department'    => $t_row['department'],
            'handler_name'  => $t_handler_name,
            'instance_status' => $t_row['status'],
            'bug_status'    => (int) $t_row['bug_status'],
            'sla_status'    => $t_row['sla_status'] ? $t_row['sla_status'] : 'NORMAL',
            'duration_hrs'  => $t_duration_hrs,
            'wait_reason'   => $t_wait_reason,
            'created_at'    => (int) $t_row['created_at'],
        );
    }

    // Özet istatistikler
    db_param_push();
    $t_summary_query = "SELECT
            COUNT(DISTINCT pi.id) AS total_count,
            SUM(CASE WHEN pi.status IN ('ACTIVE','WAITING') THEN 1 ELSE 0 END) AS active_count,
            AVG(CASE WHEN sla.duration_sec IS NOT NULL AND sla.duration_sec > 0 THEN sla.duration_sec ELSE NULL END) AS avg_duration_sec
        FROM $t_inst_table pi
        INNER JOIN $t_bug_table b ON pi.bug_id = b.id
        $t_sla_join
        LEFT JOIN $t_step_table s ON pi.current_step_id = s.id
        $t_where";
    if( $t_department !== '' ) {
        $t_summary_query .= " AND s.department = " . db_param();
        $t_summary_params = array_merge( $t_params, array( $t_department ) );
    } else {
        $t_summary_params = $t_params;
    }
    $t_summary_result = db_query( $t_summary_query, $t_summary_params );
    $t_summary_row = db_fetch_array( $t_summary_result );

    // SLA uyum oranı
    db_param_push();
    $t_sla_comp_query = "SELECT
            COUNT(*) AS total_sla,
            SUM(CASE WHEN sla.sla_status != 'EXCEEDED' THEN 1 ELSE 0 END) AS compliant
        FROM $t_inst_table pi
        INNER JOIN $t_bug_table b ON pi.bug_id = b.id
        $t_sla_join
        LEFT JOIN $t_step_table s ON pi.current_step_id = s.id
        $t_where AND sla.sla_status IS NOT NULL";
    if( $t_department !== '' ) {
        $t_sla_comp_query .= " AND s.department = " . db_param();
        $t_sla_comp_params = array_merge( $t_params, array( $t_department ) );
    } else {
        $t_sla_comp_params = $t_params;
    }
    $t_sla_comp_result = db_query( $t_sla_comp_query, $t_sla_comp_params );
    $t_sla_comp_row = db_fetch_array( $t_sla_comp_result );
    $t_sla_compliance = 0;
    if( (int) $t_sla_comp_row['total_sla'] > 0 ) {
        $t_sla_compliance = round( (int) $t_sla_comp_row['compliant'] / (int) $t_sla_comp_row['total_sla'] * 100 );
    }

    $t_avg_hrs = 0;
    if( $t_summary_row['avg_duration_sec'] !== null ) {
        $t_avg_hrs = round( (float) $t_summary_row['avg_duration_sec'] / 3600, 1 );
    }

    $t_summary = array(
        'total'          => (int) $t_summary_row['total_count'],
        'active'         => (int) $t_summary_row['active_count'],
        'avg_duration'   => $t_avg_hrs,
        'sla_compliance' => $t_sla_compliance,
    );

    return array( 'rows' => $t_rows, 'total' => $t_total, 'summary' => $t_summary );
}

/**
 * Get department performance statistics for charts.
 *
 * @param array $p_filters Same filter array as process_get_report_data
 * @return array Array of department performance data
 */
function process_get_department_performance( $p_filters ) {
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_step_table = plugin_table( 'step' );
    $t_inst_table = plugin_table( 'process_instance' );
    $t_bug_table = db_get_table( 'bug' );

    $t_date_from  = isset( $p_filters['date_from'] ) ? (int) $p_filters['date_from'] : 0;
    $t_date_to    = isset( $p_filters['date_to'] ) ? (int) $p_filters['date_to'] : 0;
    $t_project_id = isset( $p_filters['project_id'] ) ? (int) $p_filters['project_id'] : 0;
    $t_flow_id    = isset( $p_filters['flow_id'] ) ? (int) $p_filters['flow_id'] : 0;

    $t_user_id = auth_get_current_user_id();
    $t_accessible = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible ) ) {
        return array();
    }
    $t_project_ids = array_map( 'intval', $t_accessible );
    if( $t_project_id > 0 && in_array( $t_project_id, $t_project_ids ) ) {
        $t_project_ids = array( $t_project_id );
    }
    $t_project_in = implode( ',', $t_project_ids );

    $t_where = "WHERE b.project_id IN ($t_project_in) AND s.department != ''";
    $t_params = array();

    if( $t_date_from > 0 ) {
        $t_where .= " AND st.started_at >= " . db_param();
        $t_params[] = $t_date_from;
    }
    if( $t_date_to > 0 ) {
        $t_where .= " AND st.started_at <= " . db_param();
        $t_params[] = $t_date_to;
    }
    if( $t_flow_id > 0 ) {
        $t_where .= " AND st.flow_id = " . db_param();
        $t_params[] = $t_flow_id;
    }

    db_param_push();
    $t_query = "SELECT s.department,
            COUNT(*) AS total_steps,
            SUM(CASE WHEN st.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_steps,
            AVG(CASE WHEN st.completed_at IS NOT NULL AND st.completed_at > st.started_at
                THEN st.completed_at - st.started_at ELSE NULL END) AS avg_duration_sec,
            SUM(CASE WHEN st.sla_status != 'EXCEEDED' AND st.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS compliant,
            SUM(CASE WHEN st.completed_at IS NULL THEN 1 ELSE 0 END) AS active_count
        FROM $t_sla_table st
        INNER JOIN $t_step_table s ON st.step_id = s.id
        INNER JOIN $t_bug_table b ON st.bug_id = b.id
        $t_where
        GROUP BY s.department
        ORDER BY s.department";

    $t_result = db_query( $t_query, $t_params );
    $t_departments = array();

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_total_completed = (int) $t_row['completed_steps'];
        $t_compliant = (int) $t_row['compliant'];
        $t_sla_pct = ( $t_total_completed > 0 ) ? round( $t_compliant / $t_total_completed * 100 ) : 0;
        $t_avg_hrs = ( $t_row['avg_duration_sec'] !== null ) ? round( (float) $t_row['avg_duration_sec'] / 3600, 1 ) : 0;

        $t_departments[] = array(
            'department'       => $t_row['department'],
            'total_steps'      => (int) $t_row['total_steps'],
            'completed_steps'  => $t_total_completed,
            'avg_duration_hrs' => $t_avg_hrs,
            'sla_compliance'   => $t_sla_pct,
            'active_count'     => (int) $t_row['active_count'],
        );
    }

    return $t_departments;
}

/**
 * Get step duration statistics for charts.
 *
 * @param array $p_filters Same filter array as process_get_report_data
 * @return array Array of step duration stats
 */
function process_get_step_duration_stats( $p_filters ) {
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_step_table = plugin_table( 'step' );
    $t_flow_table = plugin_table( 'flow_definition' );
    $t_bug_table = db_get_table( 'bug' );

    $t_date_from  = isset( $p_filters['date_from'] ) ? (int) $p_filters['date_from'] : 0;
    $t_date_to    = isset( $p_filters['date_to'] ) ? (int) $p_filters['date_to'] : 0;
    $t_project_id = isset( $p_filters['project_id'] ) ? (int) $p_filters['project_id'] : 0;
    $t_flow_id    = isset( $p_filters['flow_id'] ) ? (int) $p_filters['flow_id'] : 0;

    $t_user_id = auth_get_current_user_id();
    $t_accessible = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible ) ) {
        return array();
    }
    $t_project_ids = array_map( 'intval', $t_accessible );
    if( $t_project_id > 0 && in_array( $t_project_id, $t_project_ids ) ) {
        $t_project_ids = array( $t_project_id );
    }
    $t_project_in = implode( ',', $t_project_ids );

    $t_where = "WHERE b.project_id IN ($t_project_in) AND st.completed_at IS NOT NULL AND st.completed_at > st.started_at";
    $t_params = array();

    if( $t_date_from > 0 ) {
        $t_where .= " AND st.started_at >= " . db_param();
        $t_params[] = $t_date_from;
    }
    if( $t_date_to > 0 ) {
        $t_where .= " AND st.started_at <= " . db_param();
        $t_params[] = $t_date_to;
    }
    if( $t_flow_id > 0 ) {
        $t_where .= " AND st.flow_id = " . db_param();
        $t_params[] = $t_flow_id;
    }

    db_param_push();
    $t_query = "SELECT s.name AS step_name, COALESCE(f.name, '') AS flow_name,
            AVG(st.completed_at - st.started_at) AS avg_sec,
            MIN(st.completed_at - st.started_at) AS min_sec,
            MAX(st.completed_at - st.started_at) AS max_sec,
            COUNT(*) AS total_completed,
            SUM(CASE WHEN st.sla_status = 'EXCEEDED' THEN 1 ELSE 0 END) AS exceeded_count
        FROM $t_sla_table st
        INNER JOIN $t_step_table s ON st.step_id = s.id
        INNER JOIN $t_bug_table b ON st.bug_id = b.id
        LEFT JOIN $t_flow_table f ON st.flow_id = f.id
        $t_where
        GROUP BY s.id, s.name, f.name
        ORDER BY avg_sec DESC";

    $t_result = db_query( $t_query, $t_params );
    $t_stats = array();

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_total = (int) $t_row['total_completed'];
        $t_exceeded = (int) $t_row['exceeded_count'];
        $t_exceeded_pct = ( $t_total > 0 ) ? round( $t_exceeded / $t_total * 100 ) : 0;

        $t_stats[] = array(
            'step_name'        => $t_row['step_name'],
            'flow_name'        => $t_row['flow_name'],
            'avg_duration_hrs' => round( (float) $t_row['avg_sec'] / 3600, 1 ),
            'min_duration_hrs' => round( (float) $t_row['min_sec'] / 3600, 1 ),
            'max_duration_hrs' => round( (float) $t_row['max_sec'] / 3600, 1 ),
            'total_completed'  => $t_total,
            'sla_exceeded_pct' => $t_exceeded_pct,
        );
    }

    return $t_stats;
}

/**
 * Get monthly trend data for chart.
 *
 * @param array $p_filters Same filter array
 * @return array Monthly trend data
 */
function process_get_monthly_trend( $p_filters ) {
    $t_inst_table = plugin_table( 'process_instance' );
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_bug_table = db_get_table( 'bug' );

    $t_project_id = isset( $p_filters['project_id'] ) ? (int) $p_filters['project_id'] : 0;

    $t_user_id = auth_get_current_user_id();
    $t_accessible = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible ) ) {
        return array();
    }
    $t_project_ids = array_map( 'intval', $t_accessible );
    if( $t_project_id > 0 && in_array( $t_project_id, $t_project_ids ) ) {
        $t_project_ids = array( $t_project_id );
    }
    $t_project_in = implode( ',', $t_project_ids );

    // Son 12 ay — tek sorgu ile GROUP BY
    $t_twelve_months_ago = mktime( 0, 0, 0, date( 'n' ) - 11, 1, date( 'Y' ) );

    // Süreç sayıları — aylık grupla
    db_param_push();
    $t_q = "SELECT YEAR(FROM_UNIXTIME(pi.created_at)) AS yr,
                   MONTH(FROM_UNIXTIME(pi.created_at)) AS mo,
                   COUNT(*) AS cnt
            FROM $t_inst_table pi
            INNER JOIN $t_bug_table b ON pi.bug_id = b.id
            WHERE b.project_id IN ($t_project_in)
            AND pi.created_at >= " . db_param() . "
            GROUP BY yr, mo ORDER BY yr, mo";
    $t_r = db_query( $t_q, array( $t_twelve_months_ago ) );
    $t_count_map = array();
    while( $t_row = db_fetch_array( $t_r ) ) {
        $t_key = sprintf( '%04d-%02d', (int) $t_row['yr'], (int) $t_row['mo'] );
        $t_count_map[$t_key] = (int) $t_row['cnt'];
    }

    // SLA aşım sayıları — aylık grupla
    db_param_push();
    $t_sla_q = "SELECT YEAR(FROM_UNIXTIME(st.started_at)) AS yr,
                       MONTH(FROM_UNIXTIME(st.started_at)) AS mo,
                       COUNT(*) AS cnt
                FROM $t_sla_table st
                INNER JOIN $t_bug_table b ON st.bug_id = b.id
                WHERE b.project_id IN ($t_project_in)
                AND st.sla_status = 'EXCEEDED'
                AND st.started_at >= " . db_param() . "
                GROUP BY yr, mo ORDER BY yr, mo";
    $t_sla_r = db_query( $t_sla_q, array( $t_twelve_months_ago ) );
    $t_exceeded_map = array();
    while( $t_sla_row = db_fetch_array( $t_sla_r ) ) {
        $t_key = sprintf( '%04d-%02d', (int) $t_sla_row['yr'], (int) $t_sla_row['mo'] );
        $t_exceeded_map[$t_key] = (int) $t_sla_row['cnt'];
    }

    // 12 aylık sonuç dizisi oluştur
    $t_months = array();
    for( $i = 11; $i >= 0; $i-- ) {
        $t_month_start = mktime( 0, 0, 0, date( 'n' ) - $i, 1, date( 'Y' ) );
        $t_label = date( 'Y-m', $t_month_start );
        $t_months[] = array(
            'label'    => $t_label,
            'count'    => isset( $t_count_map[$t_label] ) ? $t_count_map[$t_label] : 0,
            'exceeded' => isset( $t_exceeded_map[$t_label] ) ? $t_exceeded_map[$t_label] : 0,
        );
    }

    return $t_months;
}

/**
 * Get all tracked bugs with their current step info for the dashboard table.
 *
 * @param string $p_filter Filter type: 'all', 'active', 'sla_exceeded', 'completed'
 * @return array Array of bug process data
 */
function process_get_dashboard_bugs( $p_filter = 'all', $p_department = '' ) {
    $t_log_table = plugin_table( 'log' );
    $t_step_table = plugin_table( 'step' );
    $t_sla_table = plugin_table( 'sla_tracking' );
    $t_bug_table = db_get_table( 'bug' );

    // Proje bazlı erişim kontrolü: kullanıcının erişebildiği projeler
    $t_user_id = auth_get_current_user_id();
    $t_accessible_projects = user_get_accessible_projects( $t_user_id );
    if( empty( $t_accessible_projects ) ) {
        return array();
    }

    // Erişilebilir proje ID'lerini SQL IN listesi olarak hazırla
    $t_project_ids = array_map( 'intval', $t_accessible_projects );
    $t_project_in = implode( ',', $t_project_ids );

    // Get latest log entry per bug — proje filtreli
    $t_query = "SELECT l.bug_id, l.flow_id, l.step_id, l.to_status, l.created_at,
            COALESCE(s.name, '') AS step_name,
            COALESCE(s.department, '') AS department
        FROM $t_log_table l
        INNER JOIN (
            SELECT bug_id, MAX(id) AS max_id FROM $t_log_table GROUP BY bug_id
        ) latest ON l.id = latest.max_id
        INNER JOIN $t_bug_table b ON l.bug_id = b.id
        LEFT JOIN $t_step_table s ON l.step_id = s.id
        WHERE b.project_id IN ($t_project_in)
        ORDER BY l.created_at DESC";

    $t_result = db_query( $t_query );
    $t_bugs = array();

    $t_view_threshold = plugin_config_get( 'view_threshold' );

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_bug_id = (int) $t_row['bug_id'];
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }

        // Bug seviyesinde ek erişim kontrolü
        if( !access_has_bug_level( $t_view_threshold, $t_bug_id ) ) {
            continue;
        }

        $t_bug = bug_get( $t_bug_id );
        $t_status = $t_bug->status;

        // Get SLA status for this bug
        db_param_push();
        $t_sla_query = "SELECT sla_status FROM $t_sla_table
            WHERE bug_id = " . db_param() . "
            AND completed_at IS NULL
            ORDER BY id DESC LIMIT 1";
        $t_sla_result = db_query( $t_sla_query, array( $t_bug_id ) );
        $t_sla_row = db_fetch_array( $t_sla_result );
        $t_sla_status = $t_sla_row ? $t_sla_row['sla_status'] : 'NORMAL';

        $t_resolved = config_get( 'bug_resolved_status_threshold' );
        $t_is_active = ( $t_status < $t_resolved );
        $t_is_completed = ( $t_status >= $t_resolved );
        $t_is_sla_exceeded = ( $t_sla_status === 'EXCEEDED' );

        // Apply filter
        if( $p_filter === 'active' && !$t_is_active ) continue;
        if( $p_filter === 'completed' && !$t_is_completed ) continue;
        if( $p_filter === 'sla_exceeded' && !$t_is_sla_exceeded ) continue;

        // Apply department filter
        if( $p_department !== '' && $t_row['department'] !== $p_department ) continue;

        // İlerleme bilgisi
        $t_progress_data = process_get_flow_progress( $t_bug_id );
        $t_progress_pct = 0;
        if( $t_progress_data !== null && $t_progress_data['total_steps'] > 0 ) {
            $t_completed_count = 0;
            foreach( $t_progress_data['steps'] as $t_ps ) {
                if( $t_ps['status'] === 'completed' ) $t_completed_count++;
            }
            if( $t_progress_data['current_step_index'] >= 0 ) {
                $t_progress_pct = round( ( $t_progress_data['current_step_index'] + 1 ) / $t_progress_data['total_steps'] * 100 );
            } else {
                $t_progress_pct = round( $t_completed_count / $t_progress_data['total_steps'] * 100 );
            }
        }

        // Sorumlu kişi
        $t_handler = (int) $t_bug->handler_id;
        $t_handler_name = ( $t_handler > 0 ) ? user_get_name( $t_handler ) : '-';

        // Alt süreç bilgisi
        $t_subprocess_info = null;
        $t_has_children = false;
        $t_is_child = false;
        $t_inst_table = plugin_table( 'process_instance' );
        db_param_push();
        $t_inst_q = "SELECT id, parent_instance_id FROM $t_inst_table WHERE bug_id = " . db_param() . " ORDER BY id DESC LIMIT 1";
        $t_inst_r = db_query( $t_inst_q, array( $t_bug_id ) );
        $t_inst_row = db_fetch_array( $t_inst_r );
        if( $t_inst_row !== false ) {
            $t_child_inst_id = (int) $t_inst_row['id'];
            $t_is_child = ( $t_inst_row['parent_instance_id'] !== null );
            db_param_push();
            $t_children_q = "SELECT status FROM $t_inst_table WHERE parent_instance_id = " . db_param();
            $t_children_r = db_query( $t_children_q, array( $t_child_inst_id ) );
            $t_sub_total = 0;
            $t_sub_completed = 0;
            $t_sub_active = 0;
            $t_sub_waiting = 0;
            while( $t_child_row = db_fetch_array( $t_children_r ) ) {
                $t_sub_total++;
                if( $t_child_row['status'] === 'COMPLETED' ) {
                    $t_sub_completed++;
                } else if( $t_child_row['status'] === 'WAITING' ) {
                    $t_sub_waiting++;
                } else if( $t_child_row['status'] === 'ACTIVE' ) {
                    $t_sub_active++;
                }
            }
            $t_has_children = ( $t_sub_total > 0 );
            if( $t_has_children ) {
                $t_subprocess_info = array(
                    'total'     => $t_sub_total,
                    'completed' => $t_sub_completed,
                    'active'    => $t_sub_active,
                    'waiting'   => $t_sub_waiting,
                );
            }
        }

        // Ebeveyn instance durumunu kontrol et
        $t_instance_status = '';
        if( $t_inst_row !== false ) {
            db_param_push();
            $t_inst_status_q = "SELECT status FROM $t_inst_table WHERE id = " . db_param();
            $t_inst_status_r = db_query( $t_inst_status_q, array( (int) $t_inst_row['id'] ) );
            $t_inst_status_row = db_fetch_array( $t_inst_status_r );
            if( $t_inst_status_row !== false ) {
                $t_instance_status = $t_inst_status_row['status'];
            }
        }

        $t_bugs[] = array(
            'bug_id'          => $t_bug_id,
            'summary'         => $t_bug->summary,
            'step_name'       => $t_row['step_name'],
            'department'      => $t_row['department'],
            'sla_status'      => $t_sla_status,
            'updated_at'      => $t_row['created_at'],
            'bug_status'      => $t_status,
            'progress_pct'    => $t_progress_pct,
            'handler_name'    => $t_handler_name,
            'subprocess_info' => $t_subprocess_info,
            'has_children'    => $t_has_children,
            'is_child'        => $t_is_child,
            'instance_status' => $t_instance_status,
        );
    }

    return $t_bugs;
}
