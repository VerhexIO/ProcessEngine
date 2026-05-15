<?php
/**
 * ProcessEngine - KPI API (Faz 14)
 *
 * Step-level duration tracking with business hours calculation.
 * Supports advance, rollback, and completion tracking.
 */

/**
 * Record step entry in KPI table.
 *
 * @param int $p_instance_id Process instance ID
 * @param int $p_bug_id Bug ID
 * @param int $p_step_id Step ID
 * @param int $p_flow_id Flow ID
 */
function kpi_on_step_enter( $p_instance_id, $p_bug_id, $p_step_id, $p_flow_id ) {
    $t_table = plugin_table( 'step_kpi' );
    $t_step_table = plugin_table( 'step' );

    // Adımın departman bilgisini al
    $t_department = '';
    db_param_push();
    $t_result = db_query(
        "SELECT department FROM $t_step_table WHERE id = " . db_param(),
        array( (int) $p_step_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false ) {
        $t_department = $t_row['department'];
    }

    // Bug'ın handler_id'sini al
    $t_handler_id = 0;
    if( bug_exists( $p_bug_id ) ) {
        $t_handler_id = (int) bug_get_field( $p_bug_id, 'handler_id' );
    }

    $t_now = time();
    db_param_push();
    db_query(
        "INSERT INTO $t_table (instance_id, bug_id, step_id, flow_id, department, handler_id, entered_at, exited_at, elapsed_minutes, business_minutes, is_rollback, exit_type)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", 0, 0, 0, 0, '')",
        array(
            (int) $p_instance_id,
            (int) $p_bug_id,
            (int) $p_step_id,
            (int) $p_flow_id,
            $t_department,
            $t_handler_id,
            $t_now,
        )
    );
}

/**
 * Record step exit in KPI table.
 * Closes the open KPI record for the given instance+step.
 *
 * @param int $p_instance_id Process instance ID
 * @param int $p_step_id Step ID
 * @param string $p_exit_type 'advance', 'rollback', 'completed'
 */
function kpi_on_step_exit( $p_instance_id, $p_step_id, $p_exit_type = 'advance' ) {
    $t_table = plugin_table( 'step_kpi' );

    // Açık KPI kaydını bul (exited_at = 0)
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE instance_id = " . db_param()
        . " AND step_id = " . db_param() . " AND exited_at = 0 ORDER BY id DESC LIMIT 1",
        array( (int) $p_instance_id, (int) $p_step_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row === false ) {
        return;
    }

    $t_now = time();
    $t_entered_at = (int) $t_row['entered_at'];
    $t_elapsed_minutes = max( 0, (int) round( ( $t_now - $t_entered_at ) / 60 ) );

    // İş saati hesabı
    require_once( dirname( __FILE__ ) . '/sla_api.php' );
    $t_business_minutes = sla_calculate_business_minutes( $t_entered_at, $t_now );

    // Handler bilgisini güncelle (son handler)
    $t_handler_id = (int) $t_row['handler_id'];
    $t_bug_id = (int) $t_row['bug_id'];
    if( bug_exists( $t_bug_id ) ) {
        $t_handler_id = (int) bug_get_field( $t_bug_id, 'handler_id' );
    }

    db_param_push();
    db_query(
        "UPDATE $t_table SET exited_at = " . db_param() . ", elapsed_minutes = " . db_param()
        . ", business_minutes = " . db_param() . ", exit_type = " . db_param()
        . ", handler_id = " . db_param() . " WHERE id = " . db_param(),
        array( $t_now, $t_elapsed_minutes, $t_business_minutes, $p_exit_type, $t_handler_id, (int) $t_row['id'] )
    );
}

/**
 * Handle rollback: close old step KPI, open new step KPI with rollback flag.
 *
 * @param int $p_instance_id Process instance ID
 * @param int $p_bug_id Bug ID
 * @param int $p_old_step_id Step being rolled back from
 * @param int $p_new_step_id Step being rolled back to
 * @param int $p_flow_id Flow ID
 */
function kpi_on_rollback( $p_instance_id, $p_bug_id, $p_old_step_id, $p_new_step_id, $p_flow_id ) {
    // Eski adımın KPI kaydını kapat
    kpi_on_step_exit( $p_instance_id, $p_old_step_id, 'rollback' );

    // Yeni adım için KPI aç (rollback flag ile)
    $t_table = plugin_table( 'step_kpi' );
    $t_step_table = plugin_table( 'step' );

    $t_department = '';
    db_param_push();
    $t_result = db_query(
        "SELECT department FROM $t_step_table WHERE id = " . db_param(),
        array( (int) $p_new_step_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false ) {
        $t_department = $t_row['department'];
    }

    $t_handler_id = 0;
    if( bug_exists( $p_bug_id ) ) {
        $t_handler_id = (int) bug_get_field( $p_bug_id, 'handler_id' );
    }

    $t_now = time();
    db_param_push();
    db_query(
        "INSERT INTO $t_table (instance_id, bug_id, step_id, flow_id, department, handler_id, entered_at, exited_at, elapsed_minutes, business_minutes, is_rollback, exit_type)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", 0, 0, 0, 1, '')",
        array(
            (int) $p_instance_id,
            (int) $p_bug_id,
            (int) $p_new_step_id,
            (int) $p_flow_id,
            $t_department,
            $t_handler_id,
            $t_now,
        )
    );
}

/**
 * Get step-level KPI statistics for a flow (or specific step).
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_step_id Optional step ID (0 = all steps)
 * @return array Array of step stats: step_id => [avg, min, max, count]
 */
function kpi_get_step_stats( $p_flow_id, $p_step_id = 0 ) {
    $t_table = plugin_table( 'step_kpi' );
    $t_step_table = plugin_table( 'step' );

    $t_where = "k.flow_id = " . db_param() . " AND k.exited_at > 0";
    $t_params = array( (int) $p_flow_id );

    if( $p_step_id > 0 ) {
        $t_where .= " AND k.step_id = " . db_param();
        $t_params[] = (int) $p_step_id;
    }

    db_param_push();
    $t_result = db_query(
        "SELECT k.step_id, s.name AS step_name, s.department,
                AVG(k.business_minutes) AS avg_bm,
                MIN(k.business_minutes) AS min_bm,
                MAX(k.business_minutes) AS max_bm,
                COUNT(*) AS cnt
         FROM $t_table k
         LEFT JOIN $t_step_table s ON k.step_id = s.id
         WHERE $t_where
         GROUP BY k.step_id
         ORDER BY s.step_order ASC",
        $t_params
    );

    $t_stats = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_stats[(int) $t_row['step_id']] = array(
            'step_id'   => (int) $t_row['step_id'],
            'step_name' => $t_row['step_name'],
            'department' => $t_row['department'],
            'avg_business_minutes' => round( (float) $t_row['avg_bm'], 1 ),
            'min_business_minutes' => (int) $t_row['min_bm'],
            'max_business_minutes' => (int) $t_row['max_bm'],
            'count' => (int) $t_row['cnt'],
        );
    }
    return $t_stats;
}

/**
 * Get flow-level KPI statistics.
 *
 * @param int $p_flow_id Flow ID
 * @return array Flow stats: avg_business_minutes, completed_count
 */
function kpi_get_flow_stats( $p_flow_id ) {
    $t_table = plugin_table( 'step_kpi' );

    // Tamamlanmış instance'ların toplam business_minutes
    db_param_push();
    $t_result = db_query(
        "SELECT instance_id, SUM(business_minutes) AS total_bm
         FROM $t_table
         WHERE flow_id = " . db_param() . " AND exited_at > 0
         GROUP BY instance_id",
        array( (int) $p_flow_id )
    );

    $t_totals = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_totals[] = (int) $t_row['total_bm'];
    }

    $t_avg = 0;
    if( !empty( $t_totals ) ) {
        $t_avg = round( array_sum( $t_totals ) / count( $t_totals ), 1 );
    }

    return array(
        'avg_business_minutes' => $t_avg,
        'completed_count' => count( $t_totals ),
    );
}

/**
 * Get department-level KPI statistics.
 *
 * @param string $p_department Department name
 * @param int $p_date_from Start timestamp (0 = no filter)
 * @param int $p_date_to End timestamp (0 = no filter)
 * @return array Department stats
 */
function kpi_get_department_stats( $p_department, $p_date_from = 0, $p_date_to = 0 ) {
    $t_table = plugin_table( 'step_kpi' );

    $t_where = "department = " . db_param() . " AND exited_at > 0";
    $t_params = array( $p_department );

    if( $p_date_from > 0 ) {
        $t_where .= " AND entered_at >= " . db_param();
        $t_params[] = (int) $p_date_from;
    }
    if( $p_date_to > 0 ) {
        $t_where .= " AND entered_at <= " . db_param();
        $t_params[] = (int) $p_date_to;
    }

    db_param_push();
    $t_result = db_query(
        "SELECT AVG(business_minutes) AS avg_bm, COUNT(*) AS cnt,
                SUM(CASE WHEN is_rollback = 1 THEN 1 ELSE 0 END) AS rollback_cnt
         FROM $t_table WHERE $t_where",
        $t_params
    );
    $t_row = db_fetch_array( $t_result );

    return array(
        'department' => $p_department,
        'avg_business_minutes' => $t_row ? round( (float) $t_row['avg_bm'], 1 ) : 0,
        'total_records' => $t_row ? (int) $t_row['cnt'] : 0,
        'rollback_count' => $t_row ? (int) $t_row['rollback_cnt'] : 0,
    );
}

/**
 * Get handler-level KPI statistics.
 *
 * @param int $p_handler_id Handler (user) ID
 * @param int $p_date_from Start timestamp (0 = no filter)
 * @param int $p_date_to End timestamp (0 = no filter)
 * @return array Handler stats
 */
function kpi_get_handler_stats( $p_handler_id, $p_date_from = 0, $p_date_to = 0 ) {
    $t_table = plugin_table( 'step_kpi' );

    $t_where = "handler_id = " . db_param() . " AND exited_at > 0";
    $t_params = array( (int) $p_handler_id );

    if( $p_date_from > 0 ) {
        $t_where .= " AND entered_at >= " . db_param();
        $t_params[] = (int) $p_date_from;
    }
    if( $p_date_to > 0 ) {
        $t_where .= " AND entered_at <= " . db_param();
        $t_params[] = (int) $p_date_to;
    }

    db_param_push();
    $t_result = db_query(
        "SELECT AVG(business_minutes) AS avg_bm, COUNT(*) AS cnt
         FROM $t_table WHERE $t_where",
        $t_params
    );
    $t_row = db_fetch_array( $t_result );

    return array(
        'handler_id' => (int) $p_handler_id,
        'avg_business_minutes' => $t_row ? round( (float) $t_row['avg_bm'], 1 ) : 0,
        'count' => $t_row ? (int) $t_row['cnt'] : 0,
    );
}

/**
 * Get full KPI timeline for a specific process instance.
 *
 * @param int $p_instance_id Process instance ID
 * @return array Array of KPI records with step info
 */
function kpi_get_instance_timeline( $p_instance_id ) {
    $t_table = plugin_table( 'step_kpi' );
    $t_step_table = plugin_table( 'step' );

    db_param_push();
    $t_result = db_query(
        "SELECT k.*, s.name AS step_name
         FROM $t_table k
         LEFT JOIN $t_step_table s ON k.step_id = s.id
         WHERE k.instance_id = " . db_param() . "
         ORDER BY k.entered_at ASC, k.id ASC",
        array( (int) $p_instance_id )
    );

    $t_timeline = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_timeline[] = $t_row;
    }
    return $t_timeline;
}

/**
 * Get the current open KPI record for a bug (active step time).
 *
 * @param int $p_bug_id Bug ID
 * @return array|null KPI record or null
 */
function kpi_get_current_step_time( $p_bug_id ) {
    $t_table = plugin_table( 'step_kpi' );

    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE bug_id = " . db_param()
        . " AND exited_at = 0 ORDER BY id DESC LIMIT 1",
        array( (int) $p_bug_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row === false ) {
        return null;
    }

    // Canlı süre hesapla
    $t_now = time();
    $t_entered_at = (int) $t_row['entered_at'];
    $t_row['live_elapsed_minutes'] = max( 0, (int) round( ( $t_now - $t_entered_at ) / 60 ) );

    require_once( dirname( __FILE__ ) . '/sla_api.php' );
    $t_row['live_business_minutes'] = sla_calculate_business_minutes( $t_entered_at, $t_now );

    return $t_row;
}
