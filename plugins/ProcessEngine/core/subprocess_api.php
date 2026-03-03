<?php
/**
 * ProcessEngine - Subprocess (Hierarchical Process) API
 *
 * Provides functions for managing process instances, parent-child
 * relationships, process trees, and subprocess lifecycle.
 */

/**
 * Instance status constants
 */
define( 'INSTANCE_STATUS_ACTIVE',    'ACTIVE' );
define( 'INSTANCE_STATUS_WAITING',   'WAITING' );
define( 'INSTANCE_STATUS_COMPLETED', 'COMPLETED' );
define( 'INSTANCE_STATUS_CANCELLED', 'CANCELLED' );

/**
 * Create a new process instance.
 *
 * @param int      $p_bug_id           Bug ID
 * @param int      $p_flow_id          Flow ID
 * @param int      $p_step_id          Current step ID
 * @param int|null $p_parent_inst_id   Parent instance ID (NULL for root)
 * @param int|null $p_parent_step_id   Parent step that spawned this child
 * @return int     New instance ID
 */
function subprocess_create_instance( $p_bug_id, $p_flow_id, $p_step_id, $p_parent_inst_id = null, $p_parent_step_id = null ) {
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "INSERT INTO $t_table
        ( bug_id, flow_id, current_step_id, parent_instance_id, parent_step_id, status, created_at )
        VALUES ( " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . " )";
    db_query( $t_query, array(
        (int) $p_bug_id,
        (int) $p_flow_id,
        (int) $p_step_id,
        $p_parent_inst_id !== null ? (int) $p_parent_inst_id : null,
        $p_parent_step_id !== null ? (int) $p_parent_step_id : null,
        INSTANCE_STATUS_ACTIVE,
        time(),
    ) );
    return db_insert_id( $t_table );
}

/**
 * Get the active process instance for a bug.
 *
 * @param int $p_bug_id Bug ID
 * @return array|null Instance row or null
 */
function subprocess_get_instance( $p_bug_id ) {
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE bug_id = " . db_param() . "
        AND status IN ('ACTIVE', 'WAITING')
        ORDER BY id DESC LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Get a process instance by its ID.
 *
 * @param int $p_instance_id Instance ID
 * @return array|null Instance row or null
 */
function subprocess_get_instance_by_id( $p_instance_id ) {
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table WHERE id = " . db_param();
    $t_result = db_query( $t_query, array( (int) $p_instance_id ) );
    $t_row = db_fetch_array( $t_result );
    return ( $t_row !== false ) ? $t_row : null;
}

/**
 * Get all instances for a bug (including completed).
 *
 * @param int $p_bug_id Bug ID
 * @return array Array of instance rows
 */
function subprocess_get_all_instances( $p_bug_id ) {
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE bug_id = " . db_param() . "
        ORDER BY id DESC";
    $t_result = db_query( $t_query, array( (int) $p_bug_id ) );
    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_rows[] = $t_row;
    }
    return $t_rows;
}

/**
 * Update instance status.
 *
 * @param int    $p_instance_id Instance ID
 * @param string $p_status      New status (ACTIVE, WAITING, COMPLETED, CANCELLED)
 */
function subprocess_update_instance_status( $p_instance_id, $p_status ) {
    $t_table = plugin_table( 'process_instance' );

    db_param_push();
    $t_set = "status = " . db_param();
    $t_params = array( $p_status );

    if( $p_status === INSTANCE_STATUS_COMPLETED || $p_status === INSTANCE_STATUS_CANCELLED ) {
        $t_set .= ", completed_at = " . db_param();
        $t_params[] = time();
    }

    $t_params[] = (int) $p_instance_id;
    db_query( "UPDATE $t_table SET $t_set WHERE id = " . db_param(), $t_params );
}

/**
 * Update the current_step_id of an instance.
 *
 * @param int $p_instance_id Instance ID
 * @param int $p_step_id     New current step ID
 */
function subprocess_update_current_step( $p_instance_id, $p_step_id ) {
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    db_query(
        "UPDATE $t_table SET current_step_id = " . db_param() . " WHERE id = " . db_param(),
        array( (int) $p_step_id, (int) $p_instance_id )
    );
}

/**
 * Get the full process tree starting from a given bug.
 * Returns a nested structure: each node has 'instance', 'bug', and 'children'.
 *
 * @param int $p_bug_id   Root bug ID
 * @param int $p_max_depth Maximum recursion depth (default 10)
 * @return array|null Tree structure or null
 */
function subprocess_get_tree( $p_bug_id, $p_max_depth = 10 ) {
    if( $p_max_depth <= 0 ) {
        return null;
    }

    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance === null ) {
        // Tamamlanmış instance'ları da kontrol et
        $t_all = subprocess_get_all_instances( $p_bug_id );
        if( !empty( $t_all ) ) {
            $t_instance = $t_all[0];
        }
    }

    if( $t_instance === null ) {
        return null;
    }

    $t_node = array(
        'instance' => $t_instance,
        'bug_id'   => (int) $t_instance['bug_id'],
        'children' => array(),
    );

    // Çocuk instance'ları bul
    $t_table = plugin_table( 'process_instance' );
    db_param_push();
    $t_query = "SELECT * FROM $t_table
        WHERE parent_instance_id = " . db_param() . "
        ORDER BY id ASC";
    $t_result = db_query( $t_query, array( (int) $t_instance['id'] ) );

    while( $t_child = db_fetch_array( $t_result ) ) {
        $t_child_bug_id = (int) $t_child['bug_id'];
        if( bug_exists( $t_child_bug_id ) ) {
            $t_child_tree = subprocess_get_tree( $t_child_bug_id, $p_max_depth - 1 );
            if( $t_child_tree !== null ) {
                $t_node['children'][] = $t_child_tree;
            }
        }
    }

    return $t_node;
}

/**
 * Get the ancestry chain from a bug up to the root process.
 *
 * @param int $p_bug_id Bug ID
 * @return array Array of instance rows from child to root
 */
function subprocess_get_ancestry( $p_bug_id ) {
    $t_ancestry = array();
    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance === null ) {
        $t_all = subprocess_get_all_instances( $p_bug_id );
        if( !empty( $t_all ) ) {
            $t_instance = $t_all[0];
        }
    }

    $t_visited = array();
    while( $t_instance !== null ) {
        $t_inst_id = (int) $t_instance['id'];
        if( isset( $t_visited[$t_inst_id] ) ) {
            break; // Döngü koruması
        }
        $t_visited[$t_inst_id] = true;
        $t_ancestry[] = $t_instance;

        if( $t_instance['parent_instance_id'] === null ) {
            break;
        }
        $t_instance = subprocess_get_instance_by_id( (int) $t_instance['parent_instance_id'] );
    }

    return $t_ancestry;
}

/**
 * Check if an instance is completed (reached an end step).
 *
 * @param int $p_instance_id Instance ID
 * @return bool True if completed
 */
function subprocess_is_completed( $p_instance_id ) {
    $t_instance = subprocess_get_instance_by_id( $p_instance_id );
    if( $t_instance === null ) {
        return false;
    }
    return ( $t_instance['status'] === INSTANCE_STATUS_COMPLETED );
}

/**
 * Get child instances of a parent instance.
 *
 * @param int      $p_parent_instance_id Parent instance ID
 * @param int|null $p_parent_step_id     Optional: filter by parent step ID
 * @return array Array of child instance rows
 */
function subprocess_get_children( $p_parent_instance_id, $p_parent_step_id = null ) {
    $t_table = plugin_table( 'process_instance' );

    db_param_push();
    $t_params = array( (int) $p_parent_instance_id );
    $t_where = "parent_instance_id = " . db_param();
    if( $p_parent_step_id !== null ) {
        $t_where .= " AND parent_step_id = " . db_param();
        $t_params[] = (int) $p_parent_step_id;
    }

    $t_query = "SELECT * FROM $t_table WHERE $t_where ORDER BY id ASC";
    $t_result = db_query( $t_query, $t_params );

    $t_rows = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_rows[] = $t_row;
    }
    return $t_rows;
}

/**
 * Find the root bug ID by traversing up the ancestry.
 *
 * @param int $p_bug_id Bug ID
 * @return int Root bug ID
 */
function subprocess_find_root_bug_id( $p_bug_id ) {
    $t_ancestry = subprocess_get_ancestry( $p_bug_id );
    if( empty( $t_ancestry ) ) {
        return $p_bug_id;
    }
    $t_root = end( $t_ancestry );
    return (int) $t_root['bug_id'];
}

/**
 * Check if a bug has an active process instance.
 *
 * @param int $p_bug_id Bug ID
 * @return bool
 */
function subprocess_has_instance( $p_bug_id ) {
    return ( subprocess_get_instance( $p_bug_id ) !== null );
}

/**
 * Validate that adding a subprocess reference won't create a cycle.
 * Used during flow validation.
 *
 * @param int   $p_flow_id  Flow ID being checked
 * @param array $p_visited  Already visited flow IDs (for recursion)
 * @return bool True if no cycle detected
 */
function subprocess_validate_no_cycle( $p_flow_id, $p_visited = array() ) {
    if( in_array( (int) $p_flow_id, $p_visited ) ) {
        return false; // Döngü tespit edildi
    }

    $p_visited[] = (int) $p_flow_id;

    // Bu akıştaki subprocess adımlarını bul
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT child_flow_id FROM $t_step_table
        WHERE flow_id = " . db_param() . "
        AND step_type = 'subprocess'
        AND child_flow_id IS NOT NULL";
    $t_result = db_query( $t_query, array( (int) $p_flow_id ) );

    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_child_flow_id = (int) $t_row['child_flow_id'];
        if( $t_child_flow_id > 0 ) {
            if( !subprocess_validate_no_cycle( $t_child_flow_id, $p_visited ) ) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Migrate existing log data to process_instance table.
 * Creates instance records for bugs that have log entries but no instance.
 *
 * @return int Number of instances created
 */
function subprocess_migrate_existing_data() {
    require_once( dirname( __FILE__ ) . '/process_api.php' );

    $t_log_table = plugin_table( 'log' );
    $t_inst_table = plugin_table( 'process_instance' );

    // Mevcut instance'ı olmayan tüm benzersiz bug'ları bul
    $t_query = "SELECT DISTINCT l.bug_id, l.flow_id
        FROM $t_log_table l
        WHERE l.bug_id NOT IN (SELECT bug_id FROM $t_inst_table)
        ORDER BY l.bug_id ASC";
    $t_result = db_query( $t_query );

    $t_count = 0;
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_bug_id = (int) $t_row['bug_id'];
        $t_flow_id = (int) $t_row['flow_id'];

        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }

        // Sorunun mevcut MantisBT durumuna göre akıştaki eşleşen adımı bul
        // (Log'daki step_id eski/geçersiz olabilir — akış silinip yeniden oluşturulmuşsa)
        $t_bug_mantis_status = bug_get_field( $t_bug_id, 'status' );
        $t_step_table = plugin_table( 'step' );
        db_param_push();
        $t_step_query = "SELECT id FROM $t_step_table
            WHERE flow_id = " . db_param() . " AND mantis_status = " . db_param() . "
            ORDER BY step_order ASC LIMIT 1";
        $t_step_result = db_query( $t_step_query, array( $t_flow_id, (int) $t_bug_mantis_status ) );
        $t_step_row = db_fetch_array( $t_step_result );
        $t_step_id = ( $t_step_row !== false ) ? (int) $t_step_row['id'] : 0;

        // Bug durumuna göre instance durumunu belirle
        $t_bug_status = bug_get_field( $t_bug_id, 'status' );
        $t_inst_status = ( $t_bug_status >= config_get( 'bug_resolved_status_threshold' ) ) ? INSTANCE_STATUS_COMPLETED : INSTANCE_STATUS_ACTIVE;
        $t_completed_at = ( $t_inst_status === INSTANCE_STATUS_COMPLETED ) ? time() : null;

        db_param_push();
        $t_ins_query = "INSERT INTO $t_inst_table
            ( bug_id, flow_id, current_step_id, parent_instance_id, parent_step_id, status, created_at, completed_at )
            VALUES ( " . db_param() . ", " . db_param() . ", " . db_param() . ", NULL, NULL, "
            . db_param() . ", " . db_param() . ", " . db_param() . " )";
        db_query( $t_ins_query, array(
            $t_bug_id,
            $t_flow_id,
            $t_step_id,
            $t_inst_status,
            time(),
            $t_completed_at,
        ) );
        $t_count++;
    }

    return $t_count;
}

// ============================================================
// Faz 2: Alt Süreç Oluşturma ve Yaşam Döngüsü Yönetimi
// ============================================================

/**
 * Create a child issue for a subprocess step.
 * Uses MantisBT bug_create() to properly trigger all hooks.
 *
 * @param int $p_parent_bug_id    Parent bug ID
 * @param int $p_child_flow_id    Child flow ID
 * @param int $p_child_project_id Target project ID for the child
 * @param int $p_parent_inst_id   Parent instance ID
 * @param int $p_parent_step_id   Parent step ID that triggers subprocess
 * @return int|null Child bug ID or null on failure
 */
function subprocess_create_child_issue( $p_parent_bug_id, $p_child_flow_id, $p_child_project_id, $p_parent_inst_id, $p_parent_step_id ) {
    if( !bug_exists( $p_parent_bug_id ) ) {
        return null;
    }

    // Hedef proje geçerli mi kontrol et
    if( !project_exists( $p_child_project_id ) ) {
        return null;
    }

    // Hedef akış geçerli mi kontrol et
    require_once( dirname( __FILE__ ) . '/flow_api.php' );
    $t_child_flow = flow_get( $p_child_flow_id );
    if( $t_child_flow === null ) {
        return null;
    }

    // Ebeveyn bug bilgilerini oku
    $t_parent_bug = bug_get( $p_parent_bug_id );

    // Ebeveyn adım bilgisini oku
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_step_query = "SELECT * FROM $t_step_table WHERE id = " . db_param();
    $t_step_result = db_query( $t_step_query, array( (int) $p_parent_step_id ) );
    $t_parent_step = db_fetch_array( $t_step_result );
    $t_step_name = ( $t_parent_step !== false ) ? $t_parent_step['name'] : '';

    // Hedef projede varsayılan kategori bul
    $t_category_id = 0;
    $t_categories = category_get_all_rows( $p_child_project_id );
    if( !empty( $t_categories ) ) {
        $t_category_id = $t_categories[0]['id'];
    }

    // Çocuk bug'ı oluştur (doğrudan DB INSERT — EVENT_REPORT_BUG hook'u
    // subprocess_create_instance'ı tekrar çağıracağı için döngü riski var.
    // Bu yüzden çocuk instance'ı burada manuel oluşturuyoruz.)
    $t_bug_table = db_get_table( 'bug' );
    $t_now = time();
    $t_summary = sprintf( '[Alt Süreç] %s — #%d', $t_step_name, $p_parent_bug_id );
    $t_description = sprintf(
        "Ebeveyn talep: #%d\nAdım: %s\nAkış: %s",
        $p_parent_bug_id,
        $t_step_name,
        $t_child_flow['name']
    );

    // Çocuk akışın başlangıç adımını bul
    require_once( dirname( __FILE__ ) . '/process_api.php' );
    $t_start_step = process_find_start_step( $p_child_flow_id );
    $t_start_status = ( $t_start_step !== null ) ? (int) $t_start_step['mantis_status'] : 10;

    // NOT: MantisBT'de description bug_text tablosunda saklanır, bug tablosunda description sütunu YOK
    db_param_push();
    $t_query = "INSERT INTO $t_bug_table
        ( project_id, reporter_id, handler_id, priority, severity, reproducibility,
          status, resolution, category_id, date_submitted, last_updated,
          summary )
        VALUES ( " . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . ", "
        . db_param() . ", " . db_param() . ", " . db_param() . " )";
    db_query( $t_query, array(
        (int) $p_child_project_id,
        (int) auth_get_current_user_id(),
        0,  // handler_id — adım handler'ı varsa aşağıda atanacak
        (int) $t_parent_bug->priority,
        (int) $t_parent_bug->severity,
        10, // reproducibility = always
        $t_start_status,
        10, // resolution = open
        $t_category_id,
        $t_now,
        $t_now,
        $t_summary,
    ) );
    $t_child_bug_id = db_insert_id( $t_bug_table );

    if( $t_child_bug_id <= 0 ) {
        return null;
    }

    // bug_text tablosuna da kayıt ekle (MantisBT gereği)
    $t_bug_text_table = db_get_table( 'bug_text' );
    db_param_push();
    db_query(
        "INSERT INTO $t_bug_text_table (description, steps_to_reproduce, additional_information)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array( $t_description, '', '' )
    );
    $t_bug_text_id = db_insert_id( $t_bug_text_table );

    // bug tablosundaki bug_text_id'yi güncelle
    db_param_push();
    db_query(
        "UPDATE $t_bug_table SET bug_text_id = " . db_param() . " WHERE id = " . db_param(),
        array( $t_bug_text_id, $t_child_bug_id )
    );

    // Process instance oluştur (çocuk için)
    $t_child_step_id = ( $t_start_step !== null ) ? (int) $t_start_step['id'] : 0;
    $t_child_inst_id = subprocess_create_instance(
        $t_child_bug_id,
        $p_child_flow_id,
        $t_child_step_id,
        $p_parent_inst_id,
        $p_parent_step_id
    );

    // Process log kaydı oluştur
    if( $t_start_step !== null ) {
        process_log_initial( $t_child_bug_id, $p_child_flow_id, $t_start_step );
    }

    // SLA takibi başlat
    if( $t_start_step !== null && (int) $t_start_step['sla_hours'] > 0 ) {
        require_once( dirname( __FILE__ ) . '/sla_api.php' );
        sla_start_tracking( $t_child_bug_id, $t_child_step_id, $p_child_flow_id, (int) $t_start_step['sla_hours'] );
    }

    // Otomatik sorumlu atama
    if( $t_start_step !== null
        && isset( $t_start_step['handler_id'] )
        && (int) $t_start_step['handler_id'] > 0
        && user_exists( (int) $t_start_step['handler_id'] )
    ) {
        db_param_push();
        db_query(
            "UPDATE $t_bug_table SET handler_id = " . db_param() . " WHERE id = " . db_param(),
            array( (int) $t_start_step['handler_id'], $t_child_bug_id )
        );
    }

    // MantisBT ilişki kur (parent-child)
    $t_rel_table = db_get_table( 'bug_relationship' );
    db_param_push();
    db_query(
        "INSERT INTO $t_rel_table (source_bug_id, destination_bug_id, relationship_type)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array( (int) $p_parent_bug_id, $t_child_bug_id, 2 ) // 2 = BUG_REL_PARENT_OF
    );

    // Ebeveyn instance'ı WAITING durumuna getir
    subprocess_update_instance_status( $p_parent_inst_id, INSTANCE_STATUS_WAITING );

    // Custom event tetikle
    event_signal( 'EVENT_PROCESSENGINE_CHILD_CREATED', array(
        'parent_bug_id'   => $p_parent_bug_id,
        'child_bug_id'    => $t_child_bug_id,
        'child_flow_id'   => $p_child_flow_id,
        'parent_inst_id'  => $p_parent_inst_id,
        'parent_step_id'  => $p_parent_step_id,
    ) );

    return $t_child_bug_id;
}

/**
 * Handle child process completion.
 * Checks wait conditions and advances parent if met.
 *
 * @param int $p_child_instance_id Child instance ID
 */
function subprocess_on_child_completed( $p_child_instance_id, $p_depth = 0 ) {
    // Derinlik limiti — sonsuz recursive'ı önle
    if( $p_depth > 10 ) {
        error_log( 'ProcessEngine: subprocess_on_child_completed derinlik limiti aşıldı (>10)' );
        return;
    }

    $t_child_inst = subprocess_get_instance_by_id( $p_child_instance_id );
    if( $t_child_inst === null || $t_child_inst['parent_instance_id'] === null ) {
        return;
    }

    $t_parent_inst_id = (int) $t_child_inst['parent_instance_id'];
    $t_parent_step_id = (int) $t_child_inst['parent_step_id'];

    // Bekleme koşulunu kontrol et
    if( subprocess_check_wait_condition( $t_parent_inst_id, $t_parent_step_id ) ) {
        subprocess_advance_parent( $t_parent_inst_id, $t_parent_step_id, $p_depth );
    }

    // Custom event tetikle
    event_signal( 'EVENT_PROCESSENGINE_CHILD_COMPLETED', array(
        'child_instance_id'  => $p_child_instance_id,
        'child_bug_id'       => (int) $t_child_inst['bug_id'],
        'parent_instance_id' => $t_parent_inst_id,
        'parent_step_id'     => $t_parent_step_id,
    ) );
}

/**
 * Check if the wait condition for a parent subprocess step is met.
 *
 * @param int $p_parent_inst_id Parent instance ID
 * @param int $p_parent_step_id Parent step ID (subprocess step)
 * @return bool True if condition is met and parent can advance
 */
function subprocess_check_wait_condition( $p_parent_inst_id, $p_parent_step_id ) {
    // Adımın wait_mode bilgisini oku
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT wait_mode FROM $t_step_table WHERE id = " . db_param();
    $t_result = db_query( $t_query, array( (int) $p_parent_step_id ) );
    $t_step = db_fetch_array( $t_result );
    $t_wait_mode = ( $t_step !== false && isset( $t_step['wait_mode'] ) ) ? $t_step['wait_mode'] : 'all';

    // Bu adımdaki çocuk instance'ları bul
    $t_children = subprocess_get_children( $p_parent_inst_id, $p_parent_step_id );
    if( empty( $t_children ) ) {
        return false;
    }

    if( $t_wait_mode === 'any' ) {
        // Herhangi birisi tamamlanmış mı?
        foreach( $t_children as $t_child ) {
            if( $t_child['status'] === INSTANCE_STATUS_COMPLETED ) {
                return true;
            }
        }
        return false;
    }

    // wait_mode = 'all' (varsayılan)
    foreach( $t_children as $t_child ) {
        if( $t_child['status'] !== INSTANCE_STATUS_COMPLETED ) {
            return false;
        }
    }
    return true;
}

/**
 * Advance parent process to the next step after subprocess completion.
 * Uses register_shutdown_function to avoid hook recursion issues.
 *
 * @param int $p_parent_inst_id Parent instance ID
 * @param int $p_parent_step_id Parent step ID where subprocess was waiting
 */
function subprocess_advance_parent( $p_parent_inst_id, $p_parent_step_id, $p_depth = 0 ) {
    $t_parent_inst = subprocess_get_instance_by_id( $p_parent_inst_id );
    if( $t_parent_inst === null ) {
        return;
    }

    // Race condition koruması: ebeveyn WAITING durumunda değilse ilerleme
    if( $t_parent_inst['status'] !== INSTANCE_STATUS_WAITING ) {
        return;
    }

    $t_parent_bug_id = (int) $t_parent_inst['bug_id'];
    $t_flow_id = (int) $t_parent_inst['flow_id'];

    // Mevcut adımdan çıkan geçerli geçişleri bul (koşullu dallanma)
    require_once( dirname( __FILE__ ) . '/process_api.php' );
    $t_valid_transitions = process_get_valid_transitions( $t_flow_id, $p_parent_step_id, $t_parent_bug_id );
    $t_transition = !empty( $t_valid_transitions ) ? $t_valid_transitions[0] : false;

    if( $t_transition === false ) {
        // Geçiş yok — bu bir bitiş adımı olabilir, instance'ı tamamla
        subprocess_update_instance_status( $p_parent_inst_id, INSTANCE_STATUS_COMPLETED );
        // Ebeveynin de ebeveyni varsa bilgilendir
        if( $t_parent_inst['parent_instance_id'] !== null ) {
            subprocess_on_child_completed( $p_parent_inst_id, $p_depth + 1 );
        }
        return;
    }

    $t_next_step_id = (int) $t_transition['to_step_id'];

    // Sonraki adımın bilgilerini oku
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_step_query = "SELECT * FROM $t_step_table WHERE id = " . db_param();
    $t_step_result = db_query( $t_step_query, array( $t_next_step_id ) );
    $t_next_step = db_fetch_array( $t_step_result );

    if( $t_next_step === false ) {
        return;
    }

    // Instance'ı güncelle
    subprocess_update_current_step( $p_parent_inst_id, $t_next_step_id );
    subprocess_update_instance_status( $p_parent_inst_id, INSTANCE_STATUS_ACTIVE );

    // Ertelenmiş: Ebeveyn bug'ın durumunu güncelle
    $t_new_mantis_status = (int) $t_next_step['mantis_status'];
    $t_user_id = auth_get_current_user_id();
    register_shutdown_function( function() use ( $t_parent_bug_id, $t_new_mantis_status, $t_next_step, $t_flow_id, $t_next_step_id, $p_parent_inst_id, $t_user_id ) {
        if( !bug_exists( $t_parent_bug_id ) ) {
            return;
        }

        $t_current_status = bug_get_field( $t_parent_bug_id, 'status' );
        if( (int) $t_current_status !== $t_new_mantis_status ) {
            // Bug durumunu güncelle (doğrudan DB — hook tetiklememek için)
            $t_bug_table = db_get_table( 'bug' );
            db_param_push();
            db_query(
                "UPDATE $t_bug_table SET status = " . db_param() . ", last_updated = " . db_param()
                . " WHERE id = " . db_param(),
                array( $t_new_mantis_status, time(), $t_parent_bug_id )
            );

            // MantisBT history kaydı ekle
            history_log_event_direct( $t_parent_bug_id, 'status', $t_current_status, $t_new_mantis_status, $t_user_id );

            // Process log kaydı ekle
            require_once( __DIR__ . '/process_api.php' );
            $t_log_table = plugin_table( 'log' );
            db_param_push();
            db_query(
                "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at, event_type, transition_label)
                 VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
                . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
                . db_param() . ", " . db_param() . ")",
                array(
                    $t_parent_bug_id,
                    $t_flow_id,
                    $t_next_step_id,
                    $t_current_status,
                    $t_new_mantis_status,
                    $t_user_id,
                    'Alt süreç tamamlandı, sonraki adıma geçildi',
                    time(),
                    'parent_advanced',
                    '',
                )
            );
        }

        // Faz 11: Sonraki adım subprocess ise otomatik çocuk oluşturma YAPILMAZ.
        // Kullanıcı manuel olarak oluşturacak.

        // Otomatik sorumlu atama
        if( isset( $t_next_step['handler_id'] ) && (int) $t_next_step['handler_id'] > 0
            && user_exists( (int) $t_next_step['handler_id'] )
        ) {
            $t_bug_table = db_get_table( 'bug' );
            db_param_push();
            db_query(
                "UPDATE $t_bug_table SET handler_id = " . db_param() . " WHERE id = " . db_param(),
                array( (int) $t_next_step['handler_id'], $t_parent_bug_id )
            );
        }

        // SLA takibi
        if( (int) $t_next_step['sla_hours'] > 0 ) {
            require_once( __DIR__ . '/sla_api.php' );
            sla_complete_tracking( $t_parent_bug_id );
            sla_start_tracking( $t_parent_bug_id, $t_next_step_id, $t_flow_id, (int) $t_next_step['sla_hours'] );
        }
    });
}

/**
 * Complete a process instance when the bug reaches an end step.
 * An end step is one that has no outgoing transitions.
 *
 * @param int $p_bug_id Bug ID
 * @param int $p_flow_id Flow ID
 * @param int $p_step_id Current step ID
 */
function subprocess_check_and_complete( $p_bug_id, $p_flow_id, $p_step_id ) {
    // Bu adımdan çıkan geçiş var mı?
    $t_trans_table = plugin_table( 'transition' );
    db_param_push();
    $t_query = "SELECT id FROM $t_trans_table
        WHERE flow_id = " . db_param() . "
        AND from_step_id = " . db_param() . "
        LIMIT 1";
    $t_result = db_query( $t_query, array( (int) $p_flow_id, (int) $p_step_id ) );
    $t_row = db_fetch_array( $t_result );

    if( $t_row !== false ) {
        return; // Geçiş var, bitiş adımı değil
    }

    // Bitiş adımı — instance'ı tamamla
    $t_instance = subprocess_get_instance( $p_bug_id );
    if( $t_instance === null ) {
        return;
    }

    subprocess_update_instance_status( (int) $t_instance['id'], INSTANCE_STATUS_COMPLETED );

    // Ebeveyn varsa bilgilendir
    if( $t_instance['parent_instance_id'] !== null ) {
        subprocess_on_child_completed( (int) $t_instance['id'] );
    }
}

/**
 * Handle subprocess step: create child issue when a bug arrives at a subprocess step.
 * Called from on_bug_update or on_bug_report hooks via register_shutdown_function.
 *
 * @param int $p_bug_id Bug ID
 * @param array $p_step Step data (with step_type, child_flow_id, child_project_id)
 * @param int $p_instance_id Instance ID
 */
function subprocess_handle_step( $p_bug_id, $p_step, $p_instance_id ) {
    if( !isset( $p_step['step_type'] ) || $p_step['step_type'] !== 'subprocess' ) {
        return;
    }

    $t_child_flow_id = isset( $p_step['child_flow_id'] ) ? (int) $p_step['child_flow_id'] : 0;
    if( $t_child_flow_id <= 0 ) {
        return;
    }

    $t_child_project_id = isset( $p_step['child_project_id'] ) ? (int) $p_step['child_project_id'] : 0;
    if( $t_child_project_id <= 0 ) {
        // Varsayılan: ebeveyn bug'ın projesi
        if( bug_exists( $p_bug_id ) ) {
            $t_child_project_id = bug_get_field( $p_bug_id, 'project_id' );
        }
    }

    subprocess_create_child_issue(
        $p_bug_id,
        $t_child_flow_id,
        $t_child_project_id,
        $p_instance_id,
        (int) $p_step['id']
    );
}

/**
 * Try to link a manually created child bug to a parent's subprocess step.
 * Called when a MantisBT relationship (child-of) is detected.
 *
 * @param int $p_child_bug_id  Child bug ID
 * @param int $p_parent_bug_id Parent bug ID
 * @return bool True if linked successfully
 */
function subprocess_link_manual_child( $p_child_bug_id, $p_parent_bug_id ) {
    if( !bug_exists( $p_child_bug_id ) || !bug_exists( $p_parent_bug_id ) ) {
        return false;
    }

    // Çocuk zaten bir instance'a sahip mi kontrol et
    $t_child_inst = subprocess_get_instance( $p_child_bug_id );
    if( $t_child_inst !== null && $t_child_inst['parent_instance_id'] !== null ) {
        return false; // Zaten bağlı
    }

    // Ebeveyn instance'ını bul
    $t_parent_inst = subprocess_get_instance( $p_parent_bug_id );
    if( $t_parent_inst === null ) {
        return false;
    }

    // Ebeveynin mevcut adımı subprocess mi kontrol et
    $t_step_table = plugin_table( 'step' );
    db_param_push();
    $t_query = "SELECT * FROM $t_step_table WHERE id = " . db_param();
    $t_result = db_query( $t_query, array( (int) $t_parent_inst['current_step_id'] ) );
    $t_parent_step = db_fetch_array( $t_result );

    if( $t_parent_step === false
        || !isset( $t_parent_step['step_type'] )
        || $t_parent_step['step_type'] !== 'subprocess'
    ) {
        return false; // Ebeveynin mevcut adımı subprocess değil
    }

    if( $t_child_inst !== null ) {
        // Mevcut instance'ı güncelle — ebeveyn bilgisini ekle
        $t_inst_table = plugin_table( 'process_instance' );
        db_param_push();
        db_query(
            "UPDATE $t_inst_table SET parent_instance_id = " . db_param()
            . ", parent_step_id = " . db_param() . " WHERE id = " . db_param(),
            array( (int) $t_parent_inst['id'], (int) $t_parent_inst['current_step_id'], (int) $t_child_inst['id'] )
        );
    } else {
        // Çocuk için yeni instance oluştur
        $t_child_project_id = bug_get_field( $p_child_bug_id, 'project_id' );
        $t_child_flow = process_get_active_flow_for_project( $t_child_project_id );
        if( $t_child_flow === null ) {
            return false;
        }
        $t_child_start = process_find_start_step( (int) $t_child_flow['id'] );
        $t_child_step_id = ( $t_child_start !== null ) ? (int) $t_child_start['id'] : 0;

        subprocess_create_instance(
            $p_child_bug_id,
            (int) $t_child_flow['id'],
            $t_child_step_id,
            (int) $t_parent_inst['id'],
            (int) $t_parent_inst['current_step_id']
        );
    }

    // Ebeveyn'i WAITING durumuna getir
    if( $t_parent_inst['status'] !== INSTANCE_STATUS_WAITING ) {
        subprocess_update_instance_status( (int) $t_parent_inst['id'], INSTANCE_STATUS_WAITING );
    }

    return true;
}
