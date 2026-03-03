<?php
/**
 * ProcessEngine - SLA API
 *
 * SLA tracking, deadline calculation with business hours/days,
 * status checking, and cron-based monitoring.
 */

// SLA status constants
define( 'SLA_STATUS_NORMAL',   'NORMAL' );
define( 'SLA_STATUS_WARNING',  'WARNING' );
define( 'SLA_STATUS_EXCEEDED', 'EXCEEDED' );

/**
 * Start SLA tracking for a bug at a specific step.
 *
 * @param int $p_bug_id Bug ID
 * @param int $p_step_id Step ID
 * @param int $p_flow_id Flow ID
 * @param int $p_sla_hours SLA hours
 */
function sla_start_tracking( $p_bug_id, $p_step_id, $p_flow_id, $p_sla_hours ) {
    if( $p_sla_hours <= 0 ) {
        return; // No SLA defined for this step
    }

    // Complete any existing open SLA for this bug
    sla_complete_tracking( $p_bug_id );

    $t_table = plugin_table( 'sla_tracking' );
    $t_now = time();
    $t_deadline = sla_calculate_deadline( $t_now, $p_sla_hours );

    db_param_push();
    db_query(
        "INSERT INTO $t_table (bug_id, step_id, flow_id, sla_hours, started_at, deadline_at, sla_status)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            (int) $p_bug_id,
            (int) $p_step_id,
            (int) $p_flow_id,
            (int) $p_sla_hours,
            $t_now,
            $t_deadline,
            SLA_STATUS_NORMAL,
        )
    );
}

/**
 * Complete (close) any open SLA tracking for a bug.
 *
 * @param int $p_bug_id Bug ID
 */
function sla_complete_tracking( $p_bug_id ) {
    $t_table = plugin_table( 'sla_tracking' );
    db_param_push();
    db_query(
        "UPDATE $t_table SET completed_at = " . db_param()
        . " WHERE bug_id = " . db_param() . " AND completed_at IS NULL",
        array( time(), (int) $p_bug_id )
    );
}

/**
 * Calculate SLA deadline considering business hours and working days.
 *
 * @param int $p_start_time Unix timestamp start
 * @param int $p_sla_hours SLA hours to add (business hours only)
 * @return int Unix timestamp of deadline
 */
function sla_calculate_deadline( $p_start_time, $p_sla_hours ) {
    $t_bh_start = (int) plugin_config_get( 'business_hours_start' );
    $t_bh_end = (int) plugin_config_get( 'business_hours_end' );
    $t_working_days_str = plugin_config_get( 'working_days' );
    $t_working_days = array_map( 'intval', explode( ',', $t_working_days_str ) );

    $t_hours_per_day = $t_bh_end - $t_bh_start;
    if( $t_hours_per_day <= 0 ) {
        $t_hours_per_day = 8; // fallback
    }

    $t_remaining = $p_sla_hours;
    $t_current = $p_start_time;

    // Advance to next working moment if currently outside business hours
    $t_current = sla_advance_to_business_time( $t_current, $t_bh_start, $t_bh_end, $t_working_days );

    while( $t_remaining > 0 ) {
        $t_hour = (int) date( 'G', $t_current );
        $t_hours_left_today = $t_bh_end - $t_hour;

        if( $t_hours_left_today <= 0 ) {
            // Move to next working day
            $t_current = sla_next_working_day_start( $t_current, $t_bh_start, $t_working_days );
            continue;
        }

        if( $t_remaining <= $t_hours_left_today ) {
            // Deadline is today
            $t_current += $t_remaining * 3600;
            $t_remaining = 0;
        } else {
            // Consume today's remaining hours
            $t_remaining -= $t_hours_left_today;
            $t_current = sla_next_working_day_start( $t_current, $t_bh_start, $t_working_days );
        }
    }

    return $t_current;
}

/**
 * Advance timestamp to the next business time if currently outside.
 *
 * @param int $p_time Current timestamp
 * @param int $p_bh_start Business hours start
 * @param int $p_bh_end Business hours end
 * @param array $p_working_days Working day numbers (1=Mon, 7=Sun)
 * @return int Adjusted timestamp
 */
function sla_advance_to_business_time( $p_time, $p_bh_start, $p_bh_end, $p_working_days ) {
    $t_time = $p_time;
    $t_dow = (int) date( 'N', $t_time ); // 1=Mon, 7=Sun
    $t_hour = (int) date( 'G', $t_time );

    // If not a working day, advance to next working day
    if( !in_array( $t_dow, $p_working_days ) ) {
        return sla_next_working_day_start( $t_time, $p_bh_start, $p_working_days );
    }

    // Before business hours
    if( $t_hour < $p_bh_start ) {
        return mktime( $p_bh_start, 0, 0, date( 'n', $t_time ), date( 'j', $t_time ), date( 'Y', $t_time ) );
    }

    // After business hours
    if( $t_hour >= $p_bh_end ) {
        return sla_next_working_day_start( $t_time, $p_bh_start, $p_working_days );
    }

    return $t_time;
}

/**
 * Get the start of the next working day.
 *
 * @param int $p_time Current timestamp
 * @param int $p_bh_start Business hours start
 * @param array $p_working_days Working day numbers
 * @return int Timestamp of next working day start
 */
function sla_next_working_day_start( $p_time, $p_bh_start, $p_working_days ) {
    $t_time = $p_time + 86400; // Next day
    for( $i = 0; $i < 10; $i++ ) { // Max 10 days lookahead
        $t_dow = (int) date( 'N', $t_time );
        if( in_array( $t_dow, $p_working_days ) ) {
            return mktime( $p_bh_start, 0, 0, date( 'n', $t_time ), date( 'j', $t_time ), date( 'Y', $t_time ) );
        }
        $t_time += 86400;
    }
    // Fallback
    return $t_time;
}

/**
 * Run SLA check on all active trackings.
 * Called by cron job or manual trigger.
 * Updates statuses, sends notifications, triggers escalations.
 */
function sla_run_check() {
    require_once( __DIR__ . '/notification_api.php' );
    require_once( __DIR__ . '/escalation_api.php' );

    $t_table = plugin_table( 'sla_tracking' );
    $t_step_table = plugin_table( 'step' );
    $t_warning_pct = (int) plugin_config_get( 'sla_warning_percent' ) / 100.0;
    $t_now = time();

    // Get all active (uncompleted) SLA trackings
    $t_result = db_query(
        "SELECT st.*, s.name AS step_name, s.department
         FROM $t_table st
         LEFT JOIN $t_step_table s ON st.step_id = s.id
         WHERE st.completed_at IS NULL"
    );

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_id = (int) $t_row['id'];
        $t_bug_id = (int) $t_row['bug_id'];

        // Bug silinmişse SLA'yı kapat
        if( !bug_exists( $t_bug_id ) ) {
            sla_update_field( $t_id, 'completed_at', time() );
            continue;
        }

        $t_deadline = (int) $t_row['deadline_at'];
        $t_started = (int) $t_row['started_at'];
        $t_sla_hours = (int) $t_row['sla_hours'];
        $t_sla_seconds = $t_sla_hours * 3600;
        $t_elapsed = $t_now - $t_started;
        $t_step_name = $t_row['step_name'];

        $t_new_status = $t_row['sla_status'];
        $t_escalation = (int) $t_row['escalation_level'];

        // Check warning threshold
        if( $t_elapsed >= $t_sla_seconds * $t_warning_pct && $t_now < $t_deadline ) {
            if( $t_new_status !== SLA_STATUS_WARNING && $t_new_status !== SLA_STATUS_EXCEEDED ) {
                $t_new_status = SLA_STATUS_WARNING;
                if( !(int) $t_row['notified_warning'] ) {
                    notification_send_sla_warning( $t_bug_id, $t_step_name, $t_sla_hours, $t_elapsed );
                    sla_update_field( $t_id, 'notified_warning', 1 );
                }
            }
        }

        // Check exceeded
        if( $t_now >= $t_deadline ) {
            $t_new_status = SLA_STATUS_EXCEEDED;
            if( !(int) $t_row['notified_exceeded'] ) {
                notification_send_sla_exceeded( $t_bug_id, $t_step_name, $t_sla_hours, $t_elapsed );
                sla_update_field( $t_id, 'notified_exceeded', 1 );
            }

            // Escalation Level 1: 1.5x SLA
            if( $t_elapsed >= $t_sla_seconds * 1.5 && $t_escalation < 1 ) {
                escalation_trigger( $t_bug_id, $t_step_name, 1 );
                sla_update_field( $t_id, 'escalation_level', 1 );
                $t_escalation = 1;
            }

            // Escalation Level 2: 2x SLA
            if( $t_elapsed >= $t_sla_seconds * 2 && $t_escalation < 2 ) {
                escalation_trigger( $t_bug_id, $t_step_name, 2 );
                sla_update_field( $t_id, 'escalation_level', 2 );
            }
        }

        // Update SLA status if changed
        if( $t_new_status !== $t_row['sla_status'] ) {
            sla_update_field( $t_id, 'sla_status', $t_new_status );
        }
    }
}

/**
 * Update a single field on an SLA tracking record.
 *
 * @param int $p_id SLA tracking ID
 * @param string $p_field Field name
 * @param mixed $p_value New value
 */
function sla_update_field( $p_id, $p_field, $p_value ) {
    $t_table = plugin_table( 'sla_tracking' );
    // Whitelist of allowed fields
    $t_allowed = array( 'sla_status', 'notified_warning', 'notified_exceeded', 'escalation_level', 'completed_at' );
    if( !in_array( $p_field, $t_allowed ) ) {
        return;
    }
    db_param_push();
    db_query(
        "UPDATE $t_table SET $p_field = " . db_param() . " WHERE id = " . db_param(),
        array( $p_value, (int) $p_id )
    );
}

/**
 * Get all active SLA trackings (for dashboard display).
 *
 * @return array Array of SLA tracking rows with step info
 */
function sla_get_active_trackings() {
    $t_table = plugin_table( 'sla_tracking' );
    $t_step_table = plugin_table( 'step' );
    $t_result = db_query(
        "SELECT st.*, s.name AS step_name, s.department
         FROM $t_table st
         LEFT JOIN $t_step_table s ON st.step_id = s.id
         WHERE st.completed_at IS NULL
         ORDER BY st.deadline_at ASC"
    );

    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_rows[] = $t_row;
    }
    return $t_rows;
}

/**
 * Get SLA tree summary for a root bug and all its children.
 * Aggregates SLA data across the entire process tree.
 *
 * @param int $p_root_bug_id Root bug ID
 * @return array Summary with total/exceeded/warning/normal counts and worst status
 */
function sla_get_tree_summary( $p_root_bug_id ) {
    require_once( dirname( __FILE__ ) . '/subprocess_api.php' );

    $t_tree = subprocess_get_tree( $p_root_bug_id );
    $t_summary = array(
        'total'    => 0,
        'normal'   => 0,
        'warning'  => 0,
        'exceeded' => 0,
        'worst'    => SLA_STATUS_NORMAL,
    );

    if( $t_tree === null ) {
        return $t_summary;
    }

    sla_aggregate_tree_node( $t_tree, $t_summary );
    return $t_summary;
}

/**
 * Recursively aggregate SLA data from a tree node.
 *
 * @param array $p_node Tree node
 * @param array &$p_summary Summary accumulator (by reference)
 */
function sla_aggregate_tree_node( $p_node, &$p_summary ) {
    $t_bug_id = (int) $p_node['bug_id'];

    // Bu bug'ın aktif SLA'sını al
    $t_sla_table = plugin_table( 'sla_tracking' );
    db_param_push();
    $t_query = "SELECT sla_status FROM $t_sla_table
        WHERE bug_id = " . db_param() . "
        AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1";
    $t_result = db_query( $t_query, array( $t_bug_id ) );
    $t_row = db_fetch_array( $t_result );

    if( $t_row !== false ) {
        $p_summary['total']++;
        $t_status = $t_row['sla_status'];
        if( $t_status === SLA_STATUS_EXCEEDED ) {
            $p_summary['exceeded']++;
            $p_summary['worst'] = SLA_STATUS_EXCEEDED;
        } else if( $t_status === SLA_STATUS_WARNING ) {
            $p_summary['warning']++;
            if( $p_summary['worst'] !== SLA_STATUS_EXCEEDED ) {
                $p_summary['worst'] = SLA_STATUS_WARNING;
            }
        } else {
            $p_summary['normal']++;
        }
    }

    // Çocukları da işle
    if( !empty( $p_node['children'] ) ) {
        foreach( $p_node['children'] as $t_child ) {
            sla_aggregate_tree_node( $t_child, $p_summary );
        }
    }
}

/**
 * Get bottleneck steps (steps with most SLA exceeded).
 *
 * @return array Array of step data with exceeded counts
 */
function sla_get_bottlenecks() {
    $t_table = plugin_table( 'sla_tracking' );
    $t_step_table = plugin_table( 'step' );
    $t_result = db_query(
        "SELECT s.name AS step_name, s.department, COUNT(*) AS exceeded_count
         FROM $t_table st
         JOIN $t_step_table s ON st.step_id = s.id
         WHERE st.sla_status = 'EXCEEDED'
         GROUP BY st.step_id
         ORDER BY exceeded_count DESC
         LIMIT 10"
    );

    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_rows[] = $t_row;
    }
    return $t_rows;
}
