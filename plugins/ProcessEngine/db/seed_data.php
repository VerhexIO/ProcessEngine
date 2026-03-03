<?php
/**
 * ProcessEngine - Seed Data
 *
 * Inserts 4 default flows:
 * 1. Fiyat Talebi (Price Request) - 5 steps (AKTİF)
 * 2. Urun Gelistirme (Product Development) - 4 steps (TASLAK)
 * 3. Hiyerarşik Fiyat Talebi - 5 steps with subprocess (AKTİF)
 * 4. Satınalma İnceleme - 3 steps, alt akış (AKTİF)
 *
 * Called from seed_data page endpoint.
 */

/**
 * Load seed data into the database.
 * Safe to call multiple times - checks for existing data.
 */
function process_seed_load() {
    $t_flow_table = plugin_table( 'flow_definition' );
    $t_step_table = plugin_table( 'step' );
    $t_transition_table = plugin_table( 'transition' );

    // Check if data already exists
    $t_result = db_query( "SELECT COUNT(*) AS cnt FROM $t_flow_table" );
    $t_row = db_fetch_array( $t_result );
    if( (int) $t_row['cnt'] > 0 ) {
        return false; // Data already seeded
    }

    $t_now = time();
    $t_user_id = auth_get_current_user_id();

    // ---- Flow 1: Fiyat Talebi (Price Request) ----
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Fiyat Talebi',
            'Satış departmanından gelen fiyat taleplerinin fiyatlandırma, satınalma ve onay sürecinden geçirilmesi.',
            2, // AKTIF
            0, // Global
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow1_id = db_insert_id( $t_flow_table );

    // Flow 1 Steps — handler_id: 0 = otomatik atama yok
    // Format: name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id
    $t_flow1_steps = array(
        array( 'Talep Oluşturma',    'Satış',            10, 8,  1, 'reporter',  100, 100, 0 ),
        array( 'Fiyat Analizi',      'Fiyatlandırma',    20, 16, 2, 'updater',   300, 100, 0 ),
        array( 'Tedarikçi Kontrolü', 'Satınalma',        30, 24, 3, 'updater',   500, 100, 0 ),
        array( 'Yönetim Onayı',     'Yönetim',          50, 8,  4, 'manager',   700, 100, 0 ),
        array( 'Teklif Hazırlama',   'Satış Operasyon',  80, 16, 5, 'updater',   900, 100, 0 ),
    );

    $t_flow1_step_ids = array();
    foreach( $t_flow1_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow1_id,
                $t_step[0], // name
                $t_step[1], // department
                $t_step[2], // mantis_status
                $t_step[3], // sla_hours
                $t_step[4], // step_order
                $t_step[5], // role
                $t_step[6], // position_x
                $t_step[7], // position_y
                $t_step[8], // handler_id
            )
        );
        $t_flow1_step_ids[] = db_insert_id( $t_step_table );
    }

    // Flow 1 Transitions (linear: 1→2→3→4→5)
    for( $i = 0; $i < count( $t_flow1_step_ids ) - 1; $i++ ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow1_id,
                $t_flow1_step_ids[$i],
                $t_flow1_step_ids[$i + 1],
                '',
                '',
            )
        );
    }

    // ---- Flow 2: Ürün Geliştirme (Product Development) ----
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Ürün Geliştirme',
            'Yeni ürün geliştirme sürecinin ArGe, kalite ve üretim aşamalarından geçirilmesi.',
            0, // TASLAK
            0,
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow2_id = db_insert_id( $t_flow_table );

    // Flow 2 Steps
    $t_flow2_steps = array(
        array( 'Talep ve Analiz',   'Satış',   10, 24, 1, 'reporter',  100, 100, 0 ),
        array( 'ArGe Tasarım',      'ArGe',    30, 40, 2, 'developer', 300, 100, 0 ),
        array( 'Kalite Kontrolü',   'Kalite',  50, 16, 3, 'updater',   500, 100, 0 ),
        array( 'Üretim Onayı',      'Yönetim', 80, 8,  4, 'manager',   700, 100, 0 ),
    );

    $t_flow2_step_ids = array();
    foreach( $t_flow2_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow2_id,
                $t_step[0],
                $t_step[1],
                $t_step[2],
                $t_step[3],
                $t_step[4],
                $t_step[5],
                $t_step[6],
                $t_step[7],
                $t_step[8],
            )
        );
        $t_flow2_step_ids[] = db_insert_id( $t_step_table );
    }

    // Flow 2 Transitions (linear: 1→2→3→4)
    for( $i = 0; $i < count( $t_flow2_step_ids ) - 1; $i++ ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow2_id,
                $t_flow2_step_ids[$i],
                $t_flow2_step_ids[$i + 1],
                '',
                '',
            )
        );
    }

    // ---- Flow 3: Hiyerarşik Fiyat Talebi (Subprocess Demo) ----
    // Ana akış: 5 adım, 2'si subprocess (adım 3 ve 5)
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Hiyerarşik Fiyat Talebi',
            'Alt süreç (subprocess) destekli fiyat talebi akışı. Satınalma inceleme ve teknik değerlendirme adımları alt süreç olarak çalışır.',
            2, // AKTİF
            0,
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow3_id = db_insert_id( $t_flow_table );

    // Flow 3 Steps — Subprocess adımları step_type='subprocess' olarak işaretlenir
    $t_flow3_steps = array(
        // name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id, step_type, child_flow_id, child_project_id, wait_mode
        array( 'Talep Oluşturma',        'Satış',       10, 4,  1, 'reporter', 100, 100, 0, 'normal',     null, null, 'all' ),
        array( 'Fiyat Analizi',           'Fiyatlandırma',20, 8,  2, 'updater', 300, 100, 0, 'normal',     null, null, 'all' ),
        array( 'Satınalma İnceleme',      'Satınalma',   30, 16, 3, 'updater', 500, 100, 0, 'subprocess', null, null, 'all' ), // child_flow_id will be set to Flow 1
        array( 'Yönetim Onayı',           'Yönetim',     50, 4,  4, 'manager', 700, 100, 0, 'normal',     null, null, 'all' ),
        array( 'Teklif Hazırlama',        'Satış Operasyon', 80, 8, 5, 'updater', 900, 100, 0, 'normal', null, null, 'all' ),
    );

    $t_flow3_step_ids = array();
    foreach( $t_flow3_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id, step_type, child_flow_id, child_project_id, wait_mode)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ")",
            array(
                $t_flow3_id,
                $t_step[0],
                $t_step[1],
                $t_step[2],
                $t_step[3],
                $t_step[4],
                $t_step[5],
                $t_step[6],
                $t_step[7],
                $t_step[8],
                $t_step[9],
                $t_step[10],
                $t_step[11],
                $t_step[12],
            )
        );
        $t_flow3_step_ids[] = db_insert_id( $t_step_table );
    }

    // ---- Flow 4: Satınalma İnceleme (Alt Akış) ----
    // Flow 3'ün subprocess adımı bu akışı referans edecek
    db_param_push();
    db_query(
        "INSERT INTO $t_flow_table (name, description, status, project_id, created_by, created_at, updated_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array(
            'Satınalma İnceleme',
            'Alt süreç olarak kullanılan satınalma inceleme akışı. Tedarikçi araştırma, teklif toplama ve satınalma onayı adımlarını içerir.',
            2, // AKTİF
            0, // Global
            $t_user_id,
            $t_now,
            $t_now,
        )
    );
    $t_flow4_id = db_insert_id( $t_flow_table );

    // Flow 4 Steps — 3 adımlı basit lineer akış
    $t_flow4_steps = array(
        array( 'Tedarikçi Araştırma',  'Satınalma',   10, 8,  1, 'updater',  100, 100, 0 ),
        array( 'Teklif Toplama',       'Satınalma',   30, 12, 2, 'updater',  300, 100, 0 ),
        array( 'Satınalma Onayı',      'Yönetim',     50, 4,  3, 'manager',  500, 100, 0 ),
    );

    $t_flow4_step_ids = array();
    foreach( $t_flow4_steps as $t_step ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_step_table (flow_id, name, department, mantis_status, sla_hours, step_order, role, position_x, position_y, handler_id)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow4_id,
                $t_step[0],
                $t_step[1],
                $t_step[2],
                $t_step[3],
                $t_step[4],
                $t_step[5],
                $t_step[6],
                $t_step[7],
                $t_step[8],
            )
        );
        $t_flow4_step_ids[] = db_insert_id( $t_step_table );
    }

    // Flow 4 Transitions (linear: 1→2→3)
    for( $i = 0; $i < count( $t_flow4_step_ids ) - 1; $i++ ) {
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow4_id,
                $t_flow4_step_ids[$i],
                $t_flow4_step_ids[$i + 1],
                '',
                '',
            )
        );
    }

    // Subprocess adımının child_flow_id'sini Flow 4'e bağla (Satınalma İnceleme)
    db_param_push();
    db_query(
        "UPDATE $t_step_table SET child_flow_id = " . db_param() . " WHERE id = " . db_param(),
        array( $t_flow4_id, $t_flow3_step_ids[2] ) // 3. adım (Satınalma İnceleme)
    );

    // Flow 3 Transitions (linear: 1→2→3→4→5)
    for( $i = 0; $i < count( $t_flow3_step_ids ) - 1; $i++ ) {
        $t_label = '';
        $t_cond_type = '';
        // Geçiş 2→3: "Devam" etiketi
        if( $i === 1 ) {
            $t_label = 'Devam';
        }
        db_param_push();
        db_query(
            "INSERT INTO $t_transition_table (flow_id, from_step_id, to_step_id, condition_field, condition_value, condition_type, label)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array(
                $t_flow3_id,
                $t_flow3_step_ids[$i],
                $t_flow3_step_ids[$i + 1],
                '',
                '',
                $t_cond_type,
                $t_label,
            )
        );
    }

    return true;
}
