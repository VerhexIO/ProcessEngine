<?php
/**
 * ProcessEngine - Flow API
 *
 * CRUD operations for flow definitions, steps, and transitions.
 * Graph validation algorithms (cycle detection, reachability).
 */

// Flow status constants
define( 'FLOW_STATUS_DRAFT',   0 );
define( 'FLOW_STATUS_PENDING', 1 );
define( 'FLOW_STATUS_ACTIVE',  2 );

/**
 * Get all flow definitions.
 *
 * @return array Array of flow rows
 */
function flow_get_all() {
    $t_table = plugin_table( 'flow_definition' );
    $t_result = db_query( "SELECT * FROM $t_table ORDER BY id DESC" );
    $t_flows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_flows[] = $t_row;
    }
    return $t_flows;
}

/**
 * Get a single flow by ID.
 *
 * @param int $p_flow_id Flow ID
 * @return array|null Flow row or null
 */
function flow_get( $p_flow_id ) {
    $t_table = plugin_table( 'flow_definition' );
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE id = " . db_param(),
        array( (int) $p_flow_id )
    );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Create a new flow definition.
 *
 * @param string $p_name Flow name
 * @param string $p_description Description
 * @param int $p_project_id Project ID (0 for global)
 * @return int New flow ID
 */
function flow_create( $p_name, $p_description = '', $p_project_id = 0 ) {
    $t_table = plugin_table( 'flow_definition' );
    $t_now = time();
    db_param_push();
    db_query(
        "INSERT INTO $t_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            $p_name,
            $p_description,
            FLOW_STATUS_DRAFT,
            (int) $p_project_id,
            (int) auth_get_current_user_id(),
            $t_now,
            $t_now,
        )
    );
    return db_insert_id( $t_table );
}

/**
 * Update flow metadata (name, description, project_id).
 *
 * @param int $p_flow_id Flow ID
 * @param string $p_name New name
 * @param string $p_description New description
 * @param int $p_project_id Project ID (0 for global)
 */
function flow_update( $p_flow_id, $p_name, $p_description = '', $p_project_id = -1 ) {
    $t_table = plugin_table( 'flow_definition' );

    if( $p_project_id >= 0 ) {
        db_param_push();
        db_query(
            "UPDATE $t_table SET name = " . db_param() . ", description = " . db_param()
            . ", project_id = " . db_param() . ", updated_at = " . db_param() . " WHERE id = " . db_param(),
            array( $p_name, $p_description, (int) $p_project_id, time(), (int) $p_flow_id )
        );
    } else {
        db_param_push();
        db_query(
            "UPDATE $t_table SET name = " . db_param() . ", description = " . db_param()
            . ", updated_at = " . db_param() . " WHERE id = " . db_param(),
            array( $p_name, $p_description, time(), (int) $p_flow_id )
        );
    }
}

/**
 * Delete a flow and all its steps and transitions.
 *
 * @param int $p_flow_id Flow ID
 */
function flow_delete( $p_flow_id ) {
    $t_flow_table = plugin_table( 'flow_definition' );
    $t_step_table = plugin_table( 'step' );
    $t_transition_table = plugin_table( 'transition' );
    $t_id = (int) $p_flow_id;

    // Aktif instance koruması: bu akışa ait ACTIVE/WAITING instance varsa silme
    $t_inst_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_result = db_query(
        "SELECT COUNT(*) AS cnt FROM $t_inst_table WHERE flow_id = " . db_param()
        . " AND status IN ('ACTIVE', 'WAITING')",
        array( $t_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false && (int) $t_row['cnt'] > 0 ) {
        return false;
    }

    // Alt akış referans koruması: başka akışların subprocess adımları bu akışı referans ediyorsa silme
    db_param_push();
    $t_ref_result = db_query(
        "SELECT COUNT(*) AS cnt FROM $t_step_table WHERE child_flow_id = " . db_param()
        . " AND flow_id != " . db_param(),
        array( $t_id, $t_id )
    );
    $t_ref_row = db_fetch_array( $t_ref_result );
    if( $t_ref_row !== false && (int) $t_ref_row['cnt'] > 0 ) {
        return 'referenced';
    }

    flow_delete_subprocess_targets_by_flow( $t_id );
    db_param_push();
    db_query( "DELETE FROM $t_transition_table WHERE flow_id = " . db_param(), array( $t_id ) );
    db_param_push();
    db_query( "DELETE FROM $t_step_table WHERE flow_id = " . db_param(), array( $t_id ) );
    db_param_push();
    db_query( "DELETE FROM $t_flow_table WHERE id = " . db_param(), array( $t_id ) );

    return true;
}

/**
 * Unpublish a flow: set ACTIVE flow back to DRAFT.
 * Only allowed if no ACTIVE/WAITING instances exist.
 *
 * @param int $p_flow_id Flow ID
 * @return bool|string True on success, false if not active, 'has_instances' if blocked
 */
function flow_unpublish( $p_flow_id ) {
    $t_flow = flow_get( $p_flow_id );
    if( $t_flow === null || (int) $t_flow['status'] !== FLOW_STATUS_ACTIVE ) {
        return false;
    }

    // Aktif instance varsa pasife alma
    $t_inst_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_result = db_query(
        "SELECT COUNT(*) AS cnt FROM $t_inst_table WHERE flow_id = " . db_param()
        . " AND status IN ('ACTIVE', 'WAITING')",
        array( (int) $p_flow_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false && (int) $t_row['cnt'] > 0 ) {
        return 'has_instances';
    }

    $t_table = plugin_table( 'flow_definition' );
    db_param_push();
    db_query(
        "UPDATE $t_table SET status = " . db_param() . ", updated_at = " . db_param()
        . " WHERE id = " . db_param(),
        array( FLOW_STATUS_DRAFT, time(), (int) $p_flow_id )
    );

    return true;
}

/**
 * Get all steps for a flow.
 *
 * @param int $p_flow_id Flow ID
 * @return array Array of step rows
 */
function flow_get_steps( $p_flow_id ) {
    $t_table = plugin_table( 'step' );
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE flow_id = " . db_param() . " ORDER BY step_order ASC",
        array( (int) $p_flow_id )
    );
    $t_steps = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_steps[] = $t_row;
    }
    return $t_steps;
}

/**
 * Add a step to a flow.
 *
 * @param int $p_flow_id Flow ID
 * @param array $p_data Step data (name, department, mantis_status, sla_hours, step_order, role, position_x, position_y)
 * @return int New step ID
 */
function flow_add_step( $p_flow_id, $p_data ) {
    $t_table = plugin_table( 'step' );
    db_param_push();
    db_query(
        "INSERT INTO $t_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id, step_type, child_flow_id, child_project_id, wait_mode, start_trigger, completion_criteria, completion_status, step_instructions)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            (int) $p_flow_id,
            $p_data['name'],
            isset( $p_data['department'] ) ? $p_data['department'] : '',
            isset( $p_data['mantis_status'] ) ? (int) $p_data['mantis_status'] : 10,
            isset( $p_data['sla_hours'] ) ? (int) $p_data['sla_hours'] : 0,
            isset( $p_data['step_order'] ) ? (int) $p_data['step_order'] : 0,
            isset( $p_data['role'] ) ? $p_data['role'] : '',
            isset( $p_data['position_x'] ) ? (int) $p_data['position_x'] : 0,
            isset( $p_data['position_y'] ) ? (int) $p_data['position_y'] : 0,
            isset( $p_data['handler_id'] ) ? (int) $p_data['handler_id'] : 0,
            isset( $p_data['step_type'] ) ? $p_data['step_type'] : 'normal',
            isset( $p_data['child_flow_id'] ) && $p_data['child_flow_id'] ? (int) $p_data['child_flow_id'] : null,
            isset( $p_data['child_project_id'] ) && $p_data['child_project_id'] ? (int) $p_data['child_project_id'] : null,
            isset( $p_data['wait_mode'] ) ? $p_data['wait_mode'] : 'all',
            isset( $p_data['start_trigger'] ) ? $p_data['start_trigger'] : 'auto',
            isset( $p_data['completion_criteria'] ) ? $p_data['completion_criteria'] : 'manual',
            isset( $p_data['completion_status'] ) ? (int) $p_data['completion_status'] : 0,
            isset( $p_data['step_instructions'] ) ? $p_data['step_instructions'] : null,
        )
    );

    $t_id = db_insert_id( $t_table );
    flow_touch( $p_flow_id );

    return $t_id;
}

/**
 * Update a step.
 *
 * @param int $p_step_id Step ID
 * @param array $p_data Updated fields
 */
function flow_update_step( $p_step_id, $p_data ) {
    $t_table = plugin_table( 'step' );

    $t_sets = array();
    $t_params = array();

    $t_fields = array( 'name', 'department', 'mantis_status', 'sla_hours', 'step_order', 'role', 'position_x', 'position_y', 'handler_id', 'step_type', 'child_flow_id', 'child_project_id', 'wait_mode', 'start_trigger', 'completion_criteria', 'completion_status', 'step_instructions' );
    foreach( $t_fields as $t_field ) {
        if( isset( $p_data[$t_field] ) ) {
            $t_sets[] = "$t_field = " . db_param();
            $t_params[] = $p_data[$t_field];
        }
    }

    if( !empty( $t_sets ) ) {
        $t_params[] = (int) $p_step_id;
        db_param_push();
        db_query(
            "UPDATE $t_table SET " . implode( ', ', $t_sets ) . " WHERE id = " . db_param(),
            $t_params
        );
    }
}

/**
 * Delete a step and its transitions.
 *
 * @param int $p_step_id Step ID
 */
function flow_delete_step( $p_step_id ) {
    $t_step_table = plugin_table( 'step' );
    $t_transition_table = plugin_table( 'transition' );
    $t_id = (int) $p_step_id;

    db_param_push();
    db_query(
        "DELETE FROM $t_transition_table WHERE from_step_id = " . db_param() . " OR to_step_id = " . db_param(),
        array( $t_id, $t_id )
    );
    db_param_push();
    db_query( "DELETE FROM $t_step_table WHERE id = " . db_param(), array( $t_id ) );
}

/**
 * Get all transitions for a flow.
 *
 * @param int $p_flow_id Flow ID
 * @return array Array of transition rows
 */
function flow_get_transitions( $p_flow_id ) {
    $t_table = plugin_table( 'transition' );
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE flow_id = " . db_param(),
        array( (int) $p_flow_id )
    );
    $t_transitions = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_transitions[] = $t_row;
    }
    return $t_transitions;
}

/**
 * Add a transition between two steps.
 *
 * @param int $p_flow_id Flow ID
 * @param int $p_from_step_id Source step ID
 * @param int $p_to_step_id Target step ID
 * @param string $p_condition_field Optional condition field
 * @param string $p_condition_value Optional condition value
 * @return int New transition ID
 */
function flow_add_transition( $p_flow_id, $p_from_step_id, $p_to_step_id, $p_condition_field = '', $p_condition_value = '', $p_condition_type = '', $p_label = '' ) {
    $t_table = plugin_table( 'transition' );
    db_param_push();
    db_query(
        "INSERT INTO $t_table (flow_id, from_step_id, to_step_id, condition_field, condition_value, condition_type, label)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            (int) $p_flow_id,
            (int) $p_from_step_id,
            (int) $p_to_step_id,
            $p_condition_field,
            $p_condition_value,
            $p_condition_type,
            $p_label,
        )
    );
    $t_id = db_insert_id( $t_table );
    flow_touch( $p_flow_id );
    return $t_id;
}

/**
 * Delete a transition.
 *
 * @param int $p_transition_id Transition ID
 */
function flow_delete_transition( $p_transition_id ) {
    $t_table = plugin_table( 'transition' );
    db_param_push();
    db_query( "DELETE FROM $t_table WHERE id = " . db_param(), array( (int) $p_transition_id ) );
}

/**
 * Update flow's updated_at timestamp.
 *
 * @param int $p_flow_id Flow ID
 */
function flow_touch( $p_flow_id ) {
    $t_table = plugin_table( 'flow_definition' );
    db_param_push();
    db_query(
        "UPDATE $t_table SET updated_at = " . db_param() . " WHERE id = " . db_param(),
        array( time(), (int) $p_flow_id )
    );
}

/**
 * Validate a flow graph.
 *
 * Checks:
 * 1. Exactly 1 start node (no incoming transitions)
 * 2. At least 1 end node (no outgoing transitions)
 * 3. No cycles (DFS)
 * 4. All nodes reachable from start (BFS)
 *
 * @param int $p_flow_id Flow ID
 * @return array ['valid' => bool, 'errors' => array of error strings]
 */
function flow_validate( $p_flow_id ) {
    $t_steps = flow_get_steps( $p_flow_id );
    $t_transitions = flow_get_transitions( $p_flow_id );
    $t_errors = array();

    if( empty( $t_steps ) ) {
        return array( 'valid' => false, 'errors' => array( 'No steps defined.' ) );
    }

    // Build adjacency list and incoming count
    $t_adj = array();      // step_id => [target_step_ids]
    $t_incoming = array(); // step_id => count of incoming edges
    $t_step_ids = array();

    foreach( $t_steps as $t_step ) {
        $t_id = (int) $t_step['id'];
        $t_step_ids[] = $t_id;
        $t_adj[$t_id] = array();
        $t_incoming[$t_id] = 0;
    }

    foreach( $t_transitions as $t_tr ) {
        $t_from = (int) $t_tr['from_step_id'];
        $t_to = (int) $t_tr['to_step_id'];
        if( isset( $t_adj[$t_from] ) ) {
            $t_adj[$t_from][] = $t_to;
        }
        if( isset( $t_incoming[$t_to] ) ) {
            $t_incoming[$t_to]++;
        }
    }

    // 1. Find start nodes (no incoming transitions)
    $t_start_nodes = array();
    foreach( $t_step_ids as $t_id ) {
        if( $t_incoming[$t_id] === 0 ) {
            $t_start_nodes[] = $t_id;
        }
    }

    if( count( $t_start_nodes ) === 0 ) {
        $t_errors[] = plugin_lang_get( 'validation_no_start' );
    } else if( count( $t_start_nodes ) > 1 ) {
        $t_errors[] = plugin_lang_get( 'validation_multiple_start' );
    }

    // 2. Find end nodes (no outgoing transitions)
    $t_end_nodes = array();
    foreach( $t_step_ids as $t_id ) {
        if( empty( $t_adj[$t_id] ) ) {
            $t_end_nodes[] = $t_id;
        }
    }

    if( count( $t_end_nodes ) === 0 ) {
        $t_errors[] = plugin_lang_get( 'validation_no_end' );
    }

    // 3. Cycle detection (DFS)
    if( flow_has_cycle( $t_step_ids, $t_adj ) ) {
        $t_errors[] = plugin_lang_get( 'validation_cycle' );
    }

    // 4. Reachability (BFS from start node)
    if( count( $t_start_nodes ) === 1 ) {
        $t_reachable = flow_bfs_reachable( $t_start_nodes[0], $t_adj );
        $t_unreachable = array_diff( $t_step_ids, $t_reachable );
        if( !empty( $t_unreachable ) ) {
            $t_errors[] = plugin_lang_get( 'validation_unreachable' );
        }
    }

    // 5. Subprocess doğrulaması (çoklu hedef desteği ile)
    foreach( $t_steps as $t_step ) {
        if( isset( $t_step['step_type'] ) && $t_step['step_type'] === 'subprocess' ) {
            $t_effective_targets = flow_get_effective_subprocess_targets( (int) $t_step['id'] );
            if( empty( $t_effective_targets ) ) {
                $t_errors[] = plugin_lang_get( 'subprocess_invalid_flow' );
            } else {
                foreach( $t_effective_targets as $t_eff_tgt ) {
                    $t_eff_flow_id = (int) $t_eff_tgt['child_flow_id'];
                    if( $t_eff_flow_id <= 0 || flow_get( $t_eff_flow_id ) === null ) {
                        $t_errors[] = plugin_lang_get( 'subprocess_invalid_flow' );
                        break;
                    }
                    $t_eff_project_id = (int) $t_eff_tgt['child_project_id'];
                    if( $t_eff_project_id > 0 && !project_exists( $t_eff_project_id ) ) {
                        $t_errors[] = plugin_lang_get( 'subprocess_invalid_project' );
                        break;
                    }
                }
            }
        }
    }

    // 6. Subprocess zincirinde döngü kontrolü
    require_once( dirname( __FILE__ ) . '/subprocess_api.php' );
    if( !subprocess_validate_no_cycle( $p_flow_id ) ) {
        $t_errors[] = plugin_lang_get( 'subprocess_cycle_detected' );
    }

    return array(
        'valid'  => empty( $t_errors ),
        'errors' => $t_errors,
    );
}

/**
 * DFS cycle detection.
 *
 * @param array $p_nodes All node IDs
 * @param array $p_adj Adjacency list
 * @return bool True if cycle exists
 */
function flow_has_cycle( $p_nodes, $p_adj ) {
    $t_white = 0; // unvisited
    $t_gray = 1;  // in progress
    $t_black = 2; // done

    $t_color = array();
    foreach( $p_nodes as $t_id ) {
        $t_color[$t_id] = $t_white;
    }

    foreach( $p_nodes as $t_id ) {
        if( $t_color[$t_id] === $t_white ) {
            if( flow_dfs_visit( $t_id, $p_adj, $t_color, $t_white, $t_gray, $t_black ) ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * DFS visit helper for cycle detection.
 */
function flow_dfs_visit( $p_node, &$p_adj, &$p_color, $p_white, $p_gray, $p_black ) {
    $p_color[$p_node] = $p_gray;

    if( isset( $p_adj[$p_node] ) ) {
        foreach( $p_adj[$p_node] as $t_neighbor ) {
            if( !isset( $p_color[$t_neighbor] ) ) {
                continue;
            }
            if( $p_color[$t_neighbor] === $p_gray ) {
                return true; // Back edge = cycle
            }
            if( $p_color[$t_neighbor] === $p_white ) {
                if( flow_dfs_visit( $t_neighbor, $p_adj, $p_color, $p_white, $p_gray, $p_black ) ) {
                    return true;
                }
            }
        }
    }

    $p_color[$p_node] = $p_black;
    return false;
}

/**
 * BFS to find all reachable nodes from a start node.
 *
 * @param int $p_start_id Start node ID
 * @param array $p_adj Adjacency list
 * @return array Array of reachable node IDs
 */
function flow_bfs_reachable( $p_start_id, $p_adj ) {
    $t_visited = array();
    $t_queue = array( $p_start_id );
    $t_visited[$p_start_id] = true;

    while( !empty( $t_queue ) ) {
        $t_current = array_shift( $t_queue );
        if( isset( $p_adj[$t_current] ) ) {
            foreach( $p_adj[$t_current] as $t_neighbor ) {
                if( !isset( $t_visited[$t_neighbor] ) ) {
                    $t_visited[$t_neighbor] = true;
                    $t_queue[] = $t_neighbor;
                }
            }
        }
    }

    return array_keys( $t_visited );
}

/**
 * Compute topological order for flow steps using Kahn's algorithm.
 * Uses the transition graph to determine correct step ordering.
 *
 * @param int $p_flow_id Flow ID
 * @return array Map of step_id => order_index (0-based)
 */
function flow_compute_topological_order( $p_flow_id ) {
    $t_steps = flow_get_steps( $p_flow_id );
    $t_transitions = flow_get_transitions( $p_flow_id );

    if( empty( $t_steps ) ) {
        return array();
    }

    // Build adjacency list and incoming count
    $t_adj = array();
    $t_incoming = array();
    $t_step_orders = array(); // step_id => original step_order (tiebreaker)

    foreach( $t_steps as $t_step ) {
        $t_id = (int) $t_step['id'];
        $t_adj[$t_id] = array();
        $t_incoming[$t_id] = 0;
        $t_step_orders[$t_id] = isset( $t_step['step_order'] ) ? (int) $t_step['step_order'] : 0;
    }

    foreach( $t_transitions as $t_tr ) {
        $t_from = (int) $t_tr['from_step_id'];
        $t_to = (int) $t_tr['to_step_id'];
        if( isset( $t_adj[$t_from] ) && isset( $t_incoming[$t_to] ) ) {
            $t_adj[$t_from][] = $t_to;
            $t_incoming[$t_to]++;
        }
    }

    // Kahn's algorithm: start with nodes that have no incoming edges
    $t_queue = array();
    foreach( $t_incoming as $t_id => $t_count ) {
        if( $t_count === 0 ) {
            $t_queue[] = $t_id;
        }
    }
    // Sort queue by step_order for deterministic tiebreaking
    usort( $t_queue, function( $a, $b ) use ( $t_step_orders ) {
        return $t_step_orders[$a] - $t_step_orders[$b];
    } );

    $t_order = array();
    $t_idx = 0;

    while( !empty( $t_queue ) ) {
        $t_current = array_shift( $t_queue );
        $t_order[$t_current] = $t_idx++;

        // Collect neighbors and reduce incoming counts
        $t_next_batch = array();
        foreach( $t_adj[$t_current] as $t_neighbor ) {
            $t_incoming[$t_neighbor]--;
            if( $t_incoming[$t_neighbor] === 0 ) {
                $t_next_batch[] = $t_neighbor;
            }
        }
        // Sort newly available nodes by step_order (tiebreaker)
        usort( $t_next_batch, function( $a, $b ) use ( $t_step_orders ) {
            return $t_step_orders[$a] - $t_step_orders[$b];
        } );
        foreach( $t_next_batch as $t_nb ) {
            $t_queue[] = $t_nb;
        }
    }

    // If some nodes were not processed (cycle), assign them high order
    foreach( $t_steps as $t_step ) {
        $t_id = (int) $t_step['id'];
        if( !isset( $t_order[$t_id] ) ) {
            $t_order[$t_id] = $t_idx++;
        }
    }

    return $t_order;
}

/**
 * Publish a flow: set status to ACTIVE.
 * Deactivates any previously active flow for the same project.
 *
 * @param int $p_flow_id Flow ID
 * @return bool True on success
 */
function flow_publish( $p_flow_id ) {
    $t_flow = flow_get( $p_flow_id );
    if( $t_flow === null ) {
        return false;
    }

    // Validate first
    $t_validation = flow_validate( $p_flow_id );
    if( !$t_validation['valid'] ) {
        return false;
    }

    $t_table = plugin_table( 'flow_definition' );
    $t_project_id = (int) $t_flow['project_id'];

    // Deactivate previously active flows for this project
    db_param_push();
    db_query(
        "UPDATE $t_table SET status = " . db_param() . ", updated_at = " . db_param()
        . " WHERE project_id = " . db_param() . " AND status = " . db_param() . " AND id != " . db_param(),
        array( FLOW_STATUS_DRAFT, time(), $t_project_id, FLOW_STATUS_ACTIVE, (int) $p_flow_id )
    );

    // Activate this flow
    db_param_push();
    db_query(
        "UPDATE $t_table SET status = " . db_param() . ", updated_at = " . db_param() . " WHERE id = " . db_param(),
        array( FLOW_STATUS_ACTIVE, time(), (int) $p_flow_id )
    );

    return true;
}

/**
 * Save complete flow state from designer (steps + transitions).
 * Replaces all existing steps and transitions for the flow.
 *
 * @param int $p_flow_id Flow ID
 * @param array $p_steps Array of step data
 * @param array $p_transitions Array of transition data (using temp IDs mapped to real IDs)
 * @return array Map of temp_id => real_id for steps
 */
function flow_save_complete( $p_flow_id, $p_steps, $p_transitions ) {
    // Aktif akış koruması: status=2 (AKTİF) ise kaydetmeye izin verme
    $t_flow = flow_get( $p_flow_id );
    if( $t_flow !== null && (int) $t_flow['status'] === FLOW_STATUS_ACTIVE ) {
        return false;
    }

    $t_step_table = plugin_table( 'step' );
    $t_transition_table = plugin_table( 'transition' );
    $t_flow_id = (int) $p_flow_id;

    // Delete existing subprocess targets, transitions and steps
    flow_delete_subprocess_targets_by_flow( $t_flow_id );
    db_param_push();
    db_query( "DELETE FROM $t_transition_table WHERE flow_id = " . db_param(), array( $t_flow_id ) );
    db_param_push();
    db_query( "DELETE FROM $t_step_table WHERE flow_id = " . db_param(), array( $t_flow_id ) );

    // Insert new steps
    $t_id_map = array(); // temp_id => real_id
    foreach( $p_steps as $t_step ) {
        $t_temp_id = isset( $t_step['temp_id'] ) ? $t_step['temp_id'] : '';
        $t_real_id = flow_add_step( $t_flow_id, $t_step );
        if( $t_temp_id !== '' ) {
            $t_id_map[$t_temp_id] = $t_real_id;
        }
    }

    // Insert new transitions (resolve temp IDs)
    foreach( $p_transitions as $t_tr ) {
        $t_from = $t_tr['from_step_id'];
        $t_to = $t_tr['to_step_id'];

        // Resolve temp IDs to real IDs
        if( isset( $t_id_map[$t_from] ) ) {
            $t_from = $t_id_map[$t_from];
        }
        if( isset( $t_id_map[$t_to] ) ) {
            $t_to = $t_id_map[$t_to];
        }

        flow_add_transition(
            $t_flow_id,
            $t_from,
            $t_to,
            isset( $t_tr['condition_field'] ) ? $t_tr['condition_field'] : '',
            isset( $t_tr['condition_value'] ) ? $t_tr['condition_value'] : '',
            isset( $t_tr['condition_type'] ) ? $t_tr['condition_type'] : '',
            isset( $t_tr['label'] ) ? $t_tr['label'] : ''
        );
    }

    // Topolojik sıralama hesapla ve step_order güncelle
    $t_topo = flow_compute_topological_order( $t_flow_id );
    if( !empty( $t_topo ) ) {
        $t_step_table_up = plugin_table( 'step' );
        foreach( $t_topo as $t_step_id => $t_order_idx ) {
            db_param_push();
            db_query(
                "UPDATE $t_step_table_up SET step_order = " . db_param() . " WHERE id = " . db_param(),
                array( $t_order_idx, (int) $t_step_id )
            );
        }
    }

    // Subprocess hedeflerini kaydet (çoklu hedef desteği)
    foreach( $p_steps as $t_step ) {
        $t_temp_id = isset( $t_step['temp_id'] ) ? $t_step['temp_id'] : '';
        $t_real_step_id = isset( $t_id_map[$t_temp_id] ) ? $t_id_map[$t_temp_id] : 0;
        if( $t_real_step_id <= 0 ) {
            continue;
        }
        if( isset( $t_step['subprocess_targets'] ) && is_array( $t_step['subprocess_targets'] ) && !empty( $t_step['subprocess_targets'] ) ) {
            foreach( $t_step['subprocess_targets'] as $t_target ) {
                flow_add_subprocess_target(
                    $t_real_step_id,
                    isset( $t_target['child_flow_id'] ) ? (int) $t_target['child_flow_id'] : 0,
                    isset( $t_target['child_project_id'] ) ? (int) $t_target['child_project_id'] : 0,
                    isset( $t_target['target_label'] ) ? $t_target['target_label'] : ''
                );
            }
        }
    }

    flow_touch( $t_flow_id );
    return $t_id_map;
}

// ---- Subprocess Target CRUD ----

/**
 * Get all subprocess targets for a step.
 *
 * @param int $p_step_id Step ID
 * @return array Array of target rows
 */
function flow_get_subprocess_targets( $p_step_id ) {
    $t_table = plugin_table( 'subprocess_target' );
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE step_id = " . db_param() . " ORDER BY id ASC",
        array( (int) $p_step_id )
    );
    $t_targets = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_targets[] = $t_row;
    }
    return $t_targets;
}

/**
 * Get a single subprocess target by ID.
 *
 * @param int $p_target_id Target ID
 * @return array|null Target row or null
 */
function flow_get_subprocess_target( $p_target_id ) {
    $t_table = plugin_table( 'subprocess_target' );
    db_param_push();
    $t_result = db_query(
        "SELECT * FROM $t_table WHERE id = " . db_param(),
        array( (int) $p_target_id )
    );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Add a subprocess target to a step.
 *
 * @param int $p_step_id Step ID
 * @param int $p_child_flow_id Child flow ID
 * @param int $p_child_project_id Child project ID
 * @param string $p_target_label Label
 * @return int New target ID
 */
function flow_add_subprocess_target( $p_step_id, $p_child_flow_id, $p_child_project_id = 0, $p_target_label = '' ) {
    $t_table = plugin_table( 'subprocess_target' );
    db_param_push();
    db_query(
        "INSERT INTO $t_table (step_id, child_flow_id, child_project_id, target_label)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            (int) $p_step_id,
            (int) $p_child_flow_id,
            (int) $p_child_project_id,
            $p_target_label,
        )
    );
    return db_insert_id( $t_table );
}

/**
 * Delete all subprocess targets for a step.
 *
 * @param int $p_step_id Step ID
 */
function flow_delete_subprocess_targets( $p_step_id ) {
    $t_table = plugin_table( 'subprocess_target' );
    db_param_push();
    db_query(
        "DELETE FROM $t_table WHERE step_id = " . db_param(),
        array( (int) $p_step_id )
    );
}

/**
 * Delete all subprocess targets for a flow (used in flow_save_complete).
 *
 * @param int $p_flow_id Flow ID
 */
function flow_delete_subprocess_targets_by_flow( $p_flow_id ) {
    $t_target_table = plugin_table( 'subprocess_target' );
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    db_query(
        "DELETE FROM $t_target_table WHERE step_id IN (SELECT id FROM $t_step_table WHERE flow_id = " . db_param() . ")",
        array( (int) $p_flow_id )
    );
}

/**
 * Get effective subprocess targets for a step.
 * Falls back to step's child_flow_id/child_project_id if no targets defined.
 *
 * @param int $p_step_id Step ID
 * @return array Array of target data (child_flow_id, child_project_id, target_label)
 */
function flow_get_effective_subprocess_targets( $p_step_id ) {
    $t_targets = flow_get_subprocess_targets( $p_step_id );
    if( !empty( $t_targets ) ) {
        return $t_targets;
    }

    // Fallback: step tablosundaki eski tek hedef alanları
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_result = db_query(
        "SELECT child_flow_id, child_project_id FROM $t_step_table WHERE id = " . db_param(),
        array( (int) $p_step_id )
    );
    $t_row = db_fetch_array( $t_result );
    if( $t_row !== false && (int) $t_row['child_flow_id'] > 0 ) {
        return array( array(
            'id' => 0,
            'step_id' => $p_step_id,
            'child_flow_id' => $t_row['child_flow_id'],
            'child_project_id' => $t_row['child_project_id'],
            'target_label' => '',
        ) );
    }

    return array();
}
