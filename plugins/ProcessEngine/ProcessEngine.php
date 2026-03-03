<?php
/**
 * ProcessEngine Plugin for MantisBT
 *
 * Automates workflow management for inter-departmental processes
 * (price requests, product development, etc.)
 *
 * Compatible with MantisBT 2.24.2 (Schema 210)
 */

class ProcessEnginePlugin extends MantisPlugin {

    /**
     * Plugin registration
     */
    public function register() {
        $this->name        = plugin_lang_get( 'plugin_title' );
        $this->description = plugin_lang_get( 'plugin_description' );
        $this->page        = 'config_page';

        $this->version     = '1.0.0';
        $this->requires    = array(
            'MantisCore' => '2.24.0',
        );

        $this->author  = 'VerhexIO';
        $this->contact = 'info@verhex.io';
        $this->url     = 'https://github.com/VerhexIO/ProcessEngine';
    }

    /**
     * Default configuration
     */
    public function config() {
        return array(
            'manage_threshold'     => MANAGER,
            'view_threshold'       => REPORTER,
            'action_threshold'     => DEVELOPER,
            'sla_warning_percent'  => 80,
            'business_hours_start' => 9,
            'business_hours_end'   => 18,
            'working_days'         => '1,2,3,4,5',
            'departments'          => '',
        );
    }

    /**
     * Register custom events
     */
    public function events() {
        return array(
            'EVENT_PROCESSENGINE_STATUS_CHANGED'  => EVENT_TYPE_EXECUTE,
            'EVENT_PROCESSENGINE_ESCALATION'      => EVENT_TYPE_EXECUTE,
            'EVENT_PROCESSENGINE_CHILD_CREATED'   => EVENT_TYPE_EXECUTE,
            'EVENT_PROCESSENGINE_CHILD_COMPLETED' => EVENT_TYPE_EXECUTE,
        );
    }

    /**
     * Register event hooks
     */
    public function hooks() {
        return array(
            'EVENT_REPORT_BUG'        => 'on_bug_report',
            'EVENT_UPDATE_BUG'        => 'on_bug_update',
            'EVENT_BUG_DELETED'       => 'on_bug_delete',
            'EVENT_MENU_MAIN'         => 'on_menu_main',
            'EVENT_LAYOUT_RESOURCES'  => 'on_layout_resources',
            'EVENT_VIEW_BUG_EXTRA'    => 'on_view_bug_extra',
            'EVENT_MENU_MANAGE'       => 'on_menu_manage',
            // Not: MantisBT 2.24.x'te EVENT_RELATIONSHIP_ADDED ateşlenmiyor.
            // İleride MantisBT bu event'i eklerse aşağıdaki satırı açın:
            // 'EVENT_RELATIONSHIP_ADDED' => 'on_relationship_added',
        );
    }

    /**
     * Database schema - creates 5 tables
     */
    public function schema() {
        $t_schema = array();

        // 0: flow_definition table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'flow_definition' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                name            C(128)  NOTNULL DEFAULT '',
                description     XL,
                status          I2      NOTNULL DEFAULT '0',
                project_id      I       NOTNULL UNSIGNED DEFAULT '0',
                created_by      I       NOTNULL UNSIGNED DEFAULT '0',
                created_at      I       NOTNULL UNSIGNED DEFAULT '0',
                updated_at      I       NOTNULL UNSIGNED DEFAULT '0'
            " )
        );

        // 1: step table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'step' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                name            C(128)  NOTNULL DEFAULT '',
                department      C(64)   DEFAULT '',
                mantis_status   I2      NOTNULL DEFAULT '10',
                sla_hours       I       NOTNULL UNSIGNED DEFAULT '0',
                step_order      I       NOTNULL UNSIGNED DEFAULT '0',
                role            C(64)   DEFAULT '',
                position_x      I       NOTNULL DEFAULT '0',
                position_y      I       NOTNULL DEFAULT '0'
            " )
        );

        // 2: transition table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'transition' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                from_step_id    I       NOTNULL UNSIGNED DEFAULT '0',
                to_step_id      I       NOTNULL UNSIGNED DEFAULT '0',
                condition_field C(128)  DEFAULT '',
                condition_value C(255)  DEFAULT ''
            " )
        );

        // 3: log table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'log' ), "
                id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                bug_id          I       NOTNULL UNSIGNED DEFAULT '0',
                flow_id         I       NOTNULL UNSIGNED DEFAULT '0',
                step_id         I       NOTNULL UNSIGNED DEFAULT '0',
                from_status     I2      NOTNULL DEFAULT '0',
                to_status       I2      NOTNULL DEFAULT '0',
                user_id         I       NOTNULL UNSIGNED DEFAULT '0',
                note            XL,
                created_at      I       NOTNULL UNSIGNED DEFAULT '0'
            " )
        );

        // 4: sla_tracking table
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'sla_tracking' ), "
                id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                bug_id              I       NOTNULL UNSIGNED DEFAULT '0',
                step_id             I       NOTNULL UNSIGNED DEFAULT '0',
                flow_id             I       NOTNULL UNSIGNED DEFAULT '0',
                sla_hours           I       NOTNULL UNSIGNED DEFAULT '0',
                started_at          I       NOTNULL UNSIGNED DEFAULT '0',
                deadline_at         I       NOTNULL UNSIGNED DEFAULT '0',
                completed_at        I       UNSIGNED DEFAULT NULL,
                sla_status          C(16)   NOTNULL DEFAULT 'NORMAL',
                notified_warning    I2      NOTNULL DEFAULT '0',
                notified_exceeded   I2      NOTNULL DEFAULT '0',
                escalation_level    I2      NOTNULL DEFAULT '0'
            " )
        );

        // 5: Index on log.bug_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_log_bug', plugin_table( 'log' ), 'bug_id' )
        );

        // 6: Index on sla_tracking.bug_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_sla_bug', plugin_table( 'sla_tracking' ), 'bug_id' )
        );

        // 7: Index on step.flow_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_step_flow', plugin_table( 'step' ), 'flow_id' )
        );

        // 8: Index on transition.flow_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_trans_flow', plugin_table( 'transition' ), 'flow_id' )
        );

        // 9: Add handler_id column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "handler_id I UNSIGNED DEFAULT '0'" )
        );

        // 10: process_instance table - süreç örnekleri ve ebeveyn-çocuk ilişkisi
        $t_schema[] = array(
            'CreateTableSQL',
            array( plugin_table( 'process_instance' ), "
                id                  I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
                bug_id              I       NOTNULL UNSIGNED DEFAULT '0',
                flow_id             I       NOTNULL UNSIGNED DEFAULT '0',
                current_step_id     I       NOTNULL UNSIGNED DEFAULT '0',
                parent_instance_id  I       UNSIGNED DEFAULT NULL,
                parent_step_id      I       UNSIGNED DEFAULT NULL,
                status              C(16)   NOTNULL DEFAULT 'ACTIVE',
                created_at          I       NOTNULL UNSIGNED DEFAULT '0',
                completed_at        I       UNSIGNED DEFAULT NULL
            " )
        );

        // 11: Add step_type column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "step_type C(16) NOTNULL DEFAULT 'normal'" )
        );

        // 12: Add child_flow_id column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "child_flow_id I UNSIGNED DEFAULT NULL" )
        );

        // 13: Add child_project_id column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "child_project_id I UNSIGNED DEFAULT NULL" )
        );

        // 14: Add wait_mode column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "wait_mode C(16) NOTNULL DEFAULT 'all'" )
        );

        // 15: Add condition_type column to transition table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'transition' ), "condition_type C(16) DEFAULT ''" )
        );

        // 16: Add label column to transition table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'transition' ), "label C(128) DEFAULT ''" )
        );

        // 17: Index on process_instance.bug_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_inst_bug', plugin_table( 'process_instance' ), 'bug_id' )
        );

        // 18: Index on process_instance.parent_instance_id
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_inst_parent', plugin_table( 'process_instance' ), 'parent_instance_id' )
        );

        // 19: Index on process_instance.status
        $t_schema[] = array(
            'CreateIndexSQL',
            array( 'idx_pe_inst_status', plugin_table( 'process_instance' ), 'status' )
        );

        // 20: Add event_type column to log table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'log' ), "event_type C(32) DEFAULT 'status_change'" )
        );

        // 21: Add transition_label column to log table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'log' ), "transition_label C(128) DEFAULT ''" )
        );

        // 22: Add note_required column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "note_required I2 NOTNULL DEFAULT '0'" )
        );

        // 23: Add start_trigger column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "start_trigger C(32) NOTNULL DEFAULT 'auto'" )
        );

        // 24: Add completion_criteria column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "completion_criteria C(32) NOTNULL DEFAULT 'manual'" )
        );

        // 25: Add completion_status column to step table
        $t_schema[] = array(
            'AddColumnSQL',
            array( plugin_table( 'step' ), "completion_status I2 NOTNULL DEFAULT '0'" )
        );

        return $t_schema;
    }

    /**
     * Plugin install - run seed data
     */
    public function install() {
        $t_seed_file = __DIR__ . '/db/seed_data.php';
        // Seed data will be loaded separately via the seed page
        return true;
    }

    /**
     * Hook: EVENT_REPORT_BUG - start process tracking when a bug is created
     */
    public function on_bug_report( $p_event, $p_bug_data, $p_bug_id ) {
        require_once( __DIR__ . '/core/process_api.php' );
        require_once( __DIR__ . '/core/sla_api.php' );
        require_once( __DIR__ . '/core/subprocess_api.php' );

        $t_project_id = $p_bug_data->project_id;
        $t_flow = process_get_active_flow_for_project( $t_project_id );
        if( $t_flow === null ) {
            return $p_bug_data;
        }

        // İlk adımı bul (gelen geçişi olmayan adım)
        $t_step = process_find_start_step( $t_flow['id'] );
        if( $t_step === null ) {
            return $p_bug_data;
        }

        // Süreç loguna başlangıç kaydı yaz
        process_log_initial( $p_bug_id, $t_flow['id'], $t_step );

        // Process instance kaydı oluştur
        $t_inst_id = subprocess_create_instance( $p_bug_id, (int) $t_flow['id'], (int) $t_step['id'] );

        // SLA takibini başlat
        if( (int) $t_step['sla_hours'] > 0 ) {
            sla_start_tracking( $p_bug_id, (int) $t_step['id'], (int) $t_flow['id'], (int) $t_step['sla_hours'] );
        }

        // Başlangıç adımının handler_id'si varsa otomatik ata
        if( isset( $t_step['handler_id'] )
            && (int) $t_step['handler_id'] > 0
            && user_exists( (int) $t_step['handler_id'] )
        ) {
            $p_bug_data->handler_id = (int) $t_step['handler_id'];
        }

        // Faz 11: Başlangıç adımı subprocess ise otomatik oluşturma YAPILMAZ.
        // Kullanıcı dashboard veya bug view üzerinden manuel olarak oluşturmalıdır.

        return $p_bug_data;
    }

    /**
     * Hook: EVENT_UPDATE_BUG - log status changes and trigger SLA tracking
     *
     * MantisBT EVENT_UPDATE_BUG (EVENT_TYPE_EXECUTE) parametreleri:
     *   event_signal( 'EVENT_UPDATE_BUG', array( $t_existing_bug, $t_updated_bug ) )
     * Callback: on_bug_update( $p_event, $p_existing_bug, $p_updated_bug )
     *   - $p_existing_bug: BugData nesnesi (güncelleme öncesi)
     *   - $p_updated_bug:  BugData nesnesi (güncelleme sonrası)
     */
    public function on_bug_update( $p_event, $p_existing_bug, $p_updated_bug ) {
        require_once( __DIR__ . '/core/process_api.php' );
        require_once( __DIR__ . '/core/subprocess_api.php' );

        $t_bug_id = (int) $p_existing_bug->id;
        $t_old_status = (int) $p_existing_bug->status;
        $t_new_status = (int) $p_updated_bug->status;

        if( $t_old_status != $t_new_status ) {
            // Akış dışı geçiş kontrolü
            $t_project_id = (int) $p_existing_bug->project_id;
            $t_flow = process_get_active_flow_for_project( $t_project_id );
            $t_note = '';
            if( $t_flow !== null && !process_transition_exists( $t_flow['id'], $t_old_status, $t_new_status ) ) {
                $t_note = plugin_lang_get( 'out_of_flow_transition' );
            }

            process_log_status_change( $t_bug_id, $t_old_status, $t_new_status, $t_note );

            // SLA tracking: complete old step, start new step
            require_once( __DIR__ . '/core/sla_api.php' );
            if( $t_flow !== null ) {
                sla_complete_tracking( $t_bug_id );
                $t_step = process_find_step_by_status( $t_flow['id'], $t_new_status );
                if( $t_step !== null && (int) $t_step['sla_hours'] > 0 ) {
                    sla_start_tracking( $t_bug_id, (int) $t_step['id'], (int) $t_flow['id'], (int) $t_step['sla_hours'] );
                }

                // Process instance güncelleme + subprocess mantığı (ertelenmiş)
                if( $t_step !== null ) {
                    $t_step_id = (int) $t_step['id'];
                    $t_flow_id = (int) $t_flow['id'];
                    $t_step_data = $t_step; // Closure için kopyala
                    register_shutdown_function( function() use ( $t_bug_id, $t_step_id, $t_flow_id, $t_step_data ) {
                        require_once( __DIR__ . '/core/subprocess_api.php' );
                        $t_inst = subprocess_get_instance( $t_bug_id );
                        if( $t_inst === null ) {
                            return;
                        }

                        $t_inst_id = (int) $t_inst['id'];
                        subprocess_update_current_step( $t_inst_id, $t_step_id );

                        // Faz 11: Subprocess adımına gelindiğinde otomatik çocuk oluşturma YAPILMAZ.
                        // Kullanıcı manuel olarak "Şimdi Aç" butonuyla oluşturacak.

                        // Bitiş adımı kontrolü: bu adımdan çıkan geçiş var mı?
                        subprocess_check_and_complete( $t_bug_id, $t_flow_id, $t_step_id );
                    });
                }

                // Otomatik sorumlu atama: yeni adımın handler_id'si varsa ata
                // NOT: bug_set_field() ve bugnote_add() hook içinde çağrılamaz
                // — MantisBT bug cache'ini bozar. Ertelenmiş güncelleme kullanıyoruz.
                if( $t_step !== null
                    && isset( $t_step['handler_id'] )
                    && (int) $t_step['handler_id'] > 0
                    && user_exists( (int) $t_step['handler_id'] )
                ) {
                    $t_handler_id = (int) $t_step['handler_id'];
                    register_shutdown_function( function() use ( $t_bug_id, $t_handler_id ) {
                        if( bug_exists( $t_bug_id ) ) {
                            $t_bug_table = db_get_table( 'bug' );
                            db_param_push();
                            db_query(
                                "UPDATE $t_bug_table SET handler_id = " . db_param() . " WHERE id = " . db_param(),
                                array( $t_handler_id, $t_bug_id )
                            );
                        }
                    });
                }
            }
        }
    }

    /**
     * Hook: EVENT_BUG_DELETED - orphan temizliği
     * Silinen bug'a ait instance ve SLA kayıtlarını temizler.
     *
     * @param string $p_event Event name
     * @param int    $p_bug_id Deleted bug ID
     */
    public function on_bug_delete( $p_event, $p_bug_id ) {
        $t_bug_id = (int) $p_bug_id;
        $t_inst_table = plugin_table( 'process_instance' );
        $t_sla_table = plugin_table( 'sla_tracking' );

        // 1. Bu bug'a ait instance'ları CANCELLED yap
        db_param_push();
        db_query(
            "UPDATE $t_inst_table SET status = 'CANCELLED', completed_at = " . db_param()
            . " WHERE bug_id = " . db_param() . " AND status IN ('ACTIVE', 'WAITING')",
            array( time(), $t_bug_id )
        );

        // 2. Açık SLA kayıtlarını kapat
        db_param_push();
        db_query(
            "UPDATE $t_sla_table SET completed_at = " . db_param()
            . " WHERE bug_id = " . db_param() . " AND completed_at IS NULL",
            array( time(), $t_bug_id )
        );

        // 3. Bu bug ebeveyn ise: çocuk instance'ları da CANCELLED yap
        db_param_push();
        $t_result = db_query(
            "SELECT id FROM $t_inst_table WHERE bug_id = " . db_param(),
            array( $t_bug_id )
        );
        while( $t_row = db_fetch_array( $t_result ) ) {
            $t_parent_id = (int) $t_row['id'];
            db_param_push();
            db_query(
                "UPDATE $t_inst_table SET status = 'CANCELLED', completed_at = " . db_param()
                . " WHERE parent_instance_id = " . db_param() . " AND status IN ('ACTIVE', 'WAITING')",
                array( time(), $t_parent_id )
            );
        }
    }

    /**
     * Hook: EVENT_RELATIONSHIP_ADDED - link manual child to subprocess
     *
     * @param string $p_event        Event name
     * @param int    $p_relationship_id  Relationship ID (from bug_relationship_table)
     */
    public function on_relationship_added( $p_event, $p_relationship_id ) {
        // MantisBT bug_relationship_table'dan ilişkiyi oku
        $t_rel_table = db_get_table( 'bug_relationship' );
        db_param_push();
        $t_query = "SELECT * FROM $t_rel_table WHERE id = " . db_param();
        $t_result = db_query( $t_query, array( (int) $p_relationship_id ) );
        $t_rel = db_fetch_array( $t_result );
        if( $t_rel === false ) {
            return;
        }

        // BUG_DEPENDANT (2) = parent-of: source=parent, dest=child
        $t_type = (int) $t_rel['relationship_type'];
        if( $t_type !== BUG_DEPENDANT ) {
            return;
        }

        $t_parent_bug_id = (int) $t_rel['source_bug_id'];
        $t_child_bug_id = (int) $t_rel['destination_bug_id'];

        require_once( __DIR__ . '/core/process_api.php' );
        require_once( __DIR__ . '/core/subprocess_api.php' );

        subprocess_link_manual_child( $t_child_bug_id, $t_parent_bug_id );
    }

    /**
     * Hook: EVENT_MENU_MAIN - add "Process Panel" to main menu
     */
    public function on_menu_main( $p_event ) {
        if( access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
            return array(
                array(
                    'title' => plugin_lang_get( 'menu_dashboard' ),
                    'url'   => plugin_page( 'dashboard' ),
                    'icon'  => 'fa-cogs',
                ),
            );
        }
        return array();
    }

    /**
     * Hook: EVENT_MENU_MANAGE - add config link to admin menu
     */
    public function on_menu_manage( $p_event ) {
        if( access_has_global_level( plugin_config_get( 'manage_threshold' ) ) ) {
            return array(
                '<a href="' . plugin_page( 'config_page' ) . '">'
                . plugin_lang_get( 'menu_config' )
                . '</a>'
            );
        }
        return array();
    }

    /**
     * Hook: EVENT_LAYOUT_RESOURCES - load CSS and JS assets
     */
    public function on_layout_resources( $p_event ) {
        $t_css = '<link rel="stylesheet" href="' . plugin_file( 'process_panel.css' ) . '" />' . "\n";
        $t_js = '<script src="' . plugin_file( 'process_panel.js' ) . '"></script>' . "\n";
        return $t_css . $t_js;
    }

    /**
     * Hook: EVENT_VIEW_BUG_EXTRA - show process info, stepper, timeline, and subprocess tree info
     */
    public function on_view_bug_extra( $p_event, $p_bug_id ) {
        if( !access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
            return;
        }

        require_once( __DIR__ . '/core/process_api.php' );
        require_once( __DIR__ . '/core/subprocess_api.php' );

        $t_logs = process_get_logs_for_bug( $p_bug_id );
        if( empty( $t_logs ) ) {
            return;
        }

        $t_progress = process_get_flow_progress( $p_bug_id );

        // Instance ve sonraki adım bilgisi
        $t_instance = subprocess_get_instance( $p_bug_id );
        $t_next_step_info = null;
        if( $t_instance !== null && $t_progress !== null && $t_progress['flow'] !== null ) {
            $t_valid_trans = process_get_valid_transitions(
                (int) $t_progress['flow']['id'],
                (int) $t_instance['current_step_id'],
                $p_bug_id
            );
            if( !empty( $t_valid_trans ) ) {
                $t_next_step_id = (int) $t_valid_trans[0]['to_step_id'];
                $t_step_table = plugin_table( 'step' );
                db_param_push();
                $t_ns_result = db_query(
                    "SELECT * FROM $t_step_table WHERE id = " . db_param(),
                    array( $t_next_step_id )
                );
                $t_ns_row = db_fetch_array( $t_ns_result );
                if( $t_ns_row !== false ) {
                    $t_next_step_info = $t_ns_row;
                }
            }
        }

        // 1. Süreç Bilgi Paneli
        $this->render_process_info_panel( $p_bug_id, $t_progress, $t_instance, $t_next_step_info );

        // 2. Görsel Adım Çubuğu
        if( $t_progress !== null ) {
            $this->render_step_progress_bar( $t_progress );
        }

        // 3. Ebeveyn/Çocuk Süreç Paneli
        $this->render_subprocess_panel( $p_bug_id );

        // 4. Birleşik Süreç Zaman Çizelgesi
        $t_timeline = process_get_unified_timeline( $p_bug_id );
        $this->render_process_timeline( $t_timeline );
    }

    /**
     * Render the process info panel (current step, department, progress, SLA, handler)
     */
    private function render_process_info_panel( $p_bug_id, $t_progress, $p_instance = null, $p_next_step = null ) {
        if( $t_progress === null ) {
            return;
        }

        $t_current_index = $t_progress['current_step_index'];
        $t_total = $t_progress['total_steps'];
        $t_current_step = ( $t_current_index >= 0 && isset( $t_progress['steps'][$t_current_index] ) )
            ? $t_progress['steps'][$t_current_index] : null;

        $t_step_name = $t_current_step ? $t_current_step['name'] : '-';
        $t_department = $t_current_step ? $t_current_step['department'] : '-';
        $t_handler_id = $t_current_step ? $t_current_step['handler_id'] : 0;
        $t_handler_name = ( $t_handler_id > 0 && user_exists( $t_handler_id ) ) ? user_get_name( $t_handler_id ) : '-';

        // Tamamlanan adım sayısını hesapla
        $t_completed = 0;
        foreach( $t_progress['steps'] as $s ) {
            if( $s['status'] === 'completed' ) $t_completed++;
        }
        $t_progress_num = $t_current_index >= 0 ? ( $t_current_index + 1 ) : $t_completed;

        // Instance durumu
        $t_inst_status = ( $p_instance !== null ) ? $p_instance['status'] : '';

        // SLA kalan süre
        $t_sla_text = '-';
        $t_sla_class = '';
        if( $t_inst_status === 'WAITING' ) {
            $t_sla_text = plugin_lang_get( 'subprocess_waiting' );
            $t_sla_class = 'pe-sla-waiting-text';
        } else if( $t_progress['current_sla'] !== null ) {
            $t_sla = $t_progress['current_sla'];
            if( $t_sla['remaining_sec'] > 0 ) {
                $t_sla_text = $t_sla['remaining_hrs'] . ' ' . plugin_lang_get( 'hours' );
            } else {
                $t_sla_text = plugin_lang_get( 'sla_overdue' );
                $t_sla_class = 'pe-sla-overdue-text';
            }
        }

        // Buton gösterim koşulları
        $t_can_action = access_has_global_level( plugin_config_get( 'action_threshold' ) );
        $t_show_advance = ( $t_can_action && $t_inst_status === 'ACTIVE' && $p_next_step !== null );
        $t_show_waiting = ( $t_inst_status === 'WAITING' );
        $t_next_is_subprocess = ( $p_next_step !== null && isset( $p_next_step['step_type'] ) && $p_next_step['step_type'] === 'subprocess' );

        // Mevcut adım subprocess mi?
        $t_current_is_subprocess = false;
        $t_current_step_data = null;
        if( $p_instance !== null && (int) $p_instance['current_step_id'] > 0 ) {
            $t_cs_table = plugin_table( 'step' );
            db_param_push();
            $t_cs_r = db_query( "SELECT * FROM $t_cs_table WHERE id = " . db_param(), array( (int) $p_instance['current_step_id'] ) );
            $t_cs_row = db_fetch_array( $t_cs_r );
            if( $t_cs_row !== false ) {
                $t_current_step_data = $t_cs_row;
                if( isset( $t_cs_row['step_type'] ) && $t_cs_row['step_type'] === 'subprocess' ) {
                    $t_current_is_subprocess = true;
                }
            }
        }

        // Subprocess adımında çocuk var mı kontrol et (yarı-manuel)
        $t_subprocess_needs_child = false;
        $t_subprocess_target_project = '';
        if( $t_current_is_subprocess && $p_instance !== null ) {
            require_once( __DIR__ . '/core/subprocess_api.php' );
            $t_sp_children = subprocess_get_children( (int) $p_instance['id'], (int) $p_instance['current_step_id'] );
            if( empty( $t_sp_children ) ) {
                $t_subprocess_needs_child = true;
                // "Adımı İlerlet" butonunu gizle — önce çocuk oluşturulmalı
                $t_show_advance = false;
                // Hedef proje adını bul
                $t_sp_project_id = isset( $t_current_step_data['child_project_id'] ) ? (int) $t_current_step_data['child_project_id'] : 0;
                if( $t_sp_project_id > 0 && project_exists( $t_sp_project_id ) ) {
                    $t_subprocess_target_project = project_get_name( $t_sp_project_id );
                } else {
                    $t_subprocess_target_project = project_get_name( bug_get_field( $p_bug_id, 'project_id' ) );
                }
            }
        }

        // Not zorunluluğu bilgisi
        $t_note_required = ( $t_current_step_data !== null && isset( $t_current_step_data['note_required'] ) && (int) $t_current_step_data['note_required'] === 1 );
        $t_current_step_name_for_modal = $t_current_step ? $t_current_step['name'] : '';
        $t_next_step_name_for_modal = ( $p_next_step !== null ) ? $p_next_step['name'] : '';
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-info-circle"></i>
                <?php echo plugin_lang_get( 'process_info' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <div class="pe-info-panel">
                    <div class="pe-info-item">
                        <div class="pe-info-label"><?php echo plugin_lang_get( 'current_step' ); ?></div>
                        <div class="pe-info-value">
                            <?php echo string_display_line( $t_step_name ); ?>
                            <?php if( $t_current_is_subprocess ) { ?>
                                <span class="pe-badge-subprocess-step"><i class="fa fa-sitemap"></i> <?php echo plugin_lang_get( 'subprocess_step' ); ?></span>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="pe-info-item">
                        <div class="pe-info-label"><?php echo plugin_lang_get( 'col_department' ); ?></div>
                        <div class="pe-info-value"><?php echo string_display_line( $t_department ); ?></div>
                    </div>
                    <div class="pe-info-item">
                        <div class="pe-info-label"><?php echo plugin_lang_get( 'step_progress' ); ?></div>
                        <div class="pe-info-value"><?php echo sprintf( plugin_lang_get( 'step_of' ), $t_progress_num, $t_total ); ?></div>
                    </div>
                    <div class="pe-info-item">
                        <div class="pe-info-label"><?php echo plugin_lang_get( 'sla_remaining' ); ?></div>
                        <div class="pe-info-value <?php echo $t_sla_class; ?>"><?php echo $t_sla_text; ?></div>
                    </div>
                    <div class="pe-info-item">
                        <div class="pe-info-label"><?php echo plugin_lang_get( 'responsible' ); ?></div>
                        <div class="pe-info-value"><?php echo string_display_line( $t_handler_name ); ?></div>
                    </div>
                </div>
                <?php if( $p_next_step !== null && $t_inst_status !== 'COMPLETED' && $t_inst_status !== 'CANCELLED' ) { ?>
                <div class="pe-info-next-step">
                    <span class="pe-info-label"><?php echo plugin_lang_get( 'next_step' ); ?>:</span>
                    <strong><?php echo string_display_line( $p_next_step['name'] ); ?></strong>
                    <?php if( $t_next_is_subprocess ) { ?>
                        <span class="pe-badge-subprocess-hint"><i class="fa fa-sitemap"></i> <?php echo plugin_lang_get( 'next_step_subprocess' ); ?></span>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php if( $t_subprocess_needs_child && $t_can_action ) { ?>
                <div class="pe-subprocess-action-panel">
                    <h5><i class="fa fa-exclamation-triangle"></i> <?php echo plugin_lang_get( 'subprocess_needs_child' ); ?></h5>
                    <div class="pe-target-info">
                        <?php echo plugin_lang_get( 'child_project' ); ?>: <strong><?php echo string_display_line( $t_subprocess_target_project ); ?></strong>
                    </div>
                    <div class="pe-subprocess-actions">
                        <button class="btn btn-sm btn-warning pe-create-subprocess"
                                data-bug-id="<?php echo (int) $p_bug_id; ?>">
                            <i class="fa fa-plus-circle"></i> <?php echo plugin_lang_get( 'btn_create_subprocess' ); ?>
                        </button>
                        <span class="pe-or-divider"><?php echo plugin_lang_get( 'or' ); ?></span>
                        <input type="text" class="pe-link-child-input" placeholder="<?php echo plugin_lang_get( 'link_child_placeholder' ); ?>" data-bug-id="<?php echo (int) $p_bug_id; ?>" />
                        <button class="btn btn-sm btn-default pe-link-child-btn" data-bug-id="<?php echo (int) $p_bug_id; ?>">
                            <i class="fa fa-link"></i> <?php echo plugin_lang_get( 'btn_link_child' ); ?>
                        </button>
                    </div>
                </div>
                <?php } ?>
                <div class="pe-info-actions">
                    <?php if( $t_show_advance ) { ?>
                    <button class="btn btn-sm btn-primary pe-bugview-advance"
                            data-bug-id="<?php echo (int) $p_bug_id; ?>"
                            data-is-subprocess="<?php echo $t_next_is_subprocess ? '1' : '0'; ?>"
                            data-note-required="<?php echo $t_note_required ? '1' : '0'; ?>"
                            data-current-step="<?php echo string_attribute( $t_current_step_name_for_modal ); ?>"
                            data-next-step="<?php echo string_attribute( $t_next_step_name_for_modal ); ?>">
                        <i class="fa fa-forward"></i>
                        <?php echo plugin_lang_get( 'btn_advance_step' ); ?>
                    </button>
                    <?php } else if( $t_show_waiting ) { ?>
                    <span class="pe-waiting-label">
                        <i class="fa fa-hourglass-half"></i>
                        <?php echo plugin_lang_get( 'subprocess_waiting' ); ?>
                    </span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if( $t_show_advance || $t_subprocess_needs_child ) { ?>
<input type="hidden" id="pe-bugview-token" value="<?php echo form_security_token( 'ProcessEngine_dashboard_action' ); ?>" />
<input type="hidden" id="pe-bugview-action-url" value="<?php echo plugin_page( 'dashboard_action' ); ?>" />
<?php } ?>
<?php if( $t_show_advance ) { ?>
<!-- İlerleme Modalı -->
<div class="pe-modal-overlay" id="pe-advance-overlay"></div>
<div class="pe-modal" id="pe-advance-modal">
    <div class="pe-modal-header">
        <?php echo plugin_lang_get( 'modal_advance_title' ); ?>
        <button class="pe-modal-close" id="pe-modal-close">&times;</button>
    </div>
    <div class="pe-modal-body">
        <div class="pe-modal-step-flow">
            <strong id="pe-modal-current-step"></strong>
            <i class="fa fa-arrow-right"></i>
            <strong id="pe-modal-next-step"></strong>
        </div>
        <div class="pe-modal-note-label">
            <?php echo plugin_lang_get( 'modal_note_label' ); ?>
            <span class="pe-badge-required" id="pe-modal-required-badge" style="display:none;"><?php echo plugin_lang_get( 'required' ); ?></span>
        </div>
        <textarea id="pe-modal-note" class="form-control" rows="4" placeholder="<?php echo plugin_lang_get( 'modal_note_placeholder' ); ?>"></textarea>
        <div class="pe-modal-error" id="pe-modal-error"></div>
    </div>
    <div class="pe-modal-footer">
        <button class="btn btn-default" id="pe-modal-cancel"><?php echo plugin_lang_get( 'btn_cancel' ); ?></button>
        <button class="btn btn-primary" id="pe-modal-confirm">
            <i class="fa fa-forward"></i> <?php echo plugin_lang_get( 'btn_advance_step' ); ?>
        </button>
    </div>
</div>
<?php } ?>
<?php
    }

    /**
     * Render the visual step progress bar (stepper)
     */
    private function render_step_progress_bar( $t_progress ) {
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-tasks"></i>
                <?php echo plugin_lang_get( 'step_progress' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <div class="pe-stepper">
                    <?php foreach( $t_progress['steps'] as $i => $t_step ) {
                        $t_circle_class = 'pe-step-pending';
                        if( $t_step['status'] === 'completed' ) {
                            $t_circle_class = 'pe-step-completed';
                        } else if( $t_step['status'] === 'current' ) {
                            $t_circle_class = 'pe-step-current';
                        }
                        $t_is_last = ( $i === count( $t_progress['steps'] ) - 1 );
                    ?>
                    <div class="pe-stepper-item <?php echo $t_circle_class; ?>">
                        <div class="pe-step-circle"><?php echo ( $i + 1 ); ?></div>
                        <div class="pe-step-label"><?php echo string_display_line( $t_step['name'] ); ?></div>
                        <div class="pe-step-dept"><?php echo string_display_line( $t_step['department'] ); ?></div>
                        <?php if( !$t_is_last ) { ?>
                        <div class="pe-step-connector"></div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }

    /**
     * Render subprocess panel: parent link, child list, and tree button
     */
    private function render_subprocess_panel( $p_bug_id ) {
        require_once( __DIR__ . '/core/subprocess_api.php' );

        $t_instance = subprocess_get_instance( $p_bug_id );
        if( $t_instance === null ) {
            // Tamamlanmış olanları da kontrol et
            $t_all = subprocess_get_all_instances( $p_bug_id );
            if( !empty( $t_all ) ) {
                $t_instance = $t_all[0];
            }
        }

        if( $t_instance === null ) {
            return;
        }

        $t_has_parent = ( $t_instance['parent_instance_id'] !== null );
        $t_children = subprocess_get_children( (int) $t_instance['id'] );
        $t_has_children = !empty( $t_children );

        // Ebeveyn veya çocuk yoksa panel gösterme
        if( !$t_has_parent && !$t_has_children ) {
            return;
        }

        // Ebeveyn bilgisi
        $t_parent_bug_id = 0;
        $t_parent_step_name = '';
        if( $t_has_parent ) {
            $t_parent_inst = subprocess_get_instance_by_id( (int) $t_instance['parent_instance_id'] );
            if( $t_parent_inst !== null ) {
                $t_parent_bug_id = (int) $t_parent_inst['bug_id'];
                // Ebeveyn adım adını bul
                if( $t_instance['parent_step_id'] !== null ) {
                    $t_step_table = plugin_table( 'step' );
                    db_param_push();
                    $t_sq = "SELECT name FROM $t_step_table WHERE id = " . db_param();
                    $t_sr = db_query( $t_sq, array( (int) $t_instance['parent_step_id'] ) );
                    $t_step_row = db_fetch_array( $t_sr );
                    if( $t_step_row !== false ) {
                        $t_parent_step_name = $t_step_row['name'];
                    }
                }
            }
        }

        // Çocuk sayıları
        $t_child_total = count( $t_children );
        $t_child_completed = 0;
        foreach( $t_children as $t_child ) {
            if( $t_child['status'] === INSTANCE_STATUS_COMPLETED ) {
                $t_child_completed++;
            }
        }
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'subprocess' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <?php if( $t_has_parent && $t_parent_bug_id > 0 && bug_exists( $t_parent_bug_id ) ) {
                    $t_parent_project_id = bug_get_field( $t_parent_bug_id, 'project_id' );
                    $t_parent_visibility = 'hidden';
                    $t_view_threshold = plugin_config_get( 'view_threshold' );
                    if( access_has_project_level( $t_view_threshold, $t_parent_project_id ) ) {
                        $t_parent_visibility = 'full';
                    } else if( access_has_project_level( VIEWER, $t_parent_project_id ) ) {
                        $t_parent_visibility = 'metadata';
                    }
                ?>
                <?php if( $t_parent_visibility !== 'hidden' ) { ?>
                <div class="pe-subprocess-parent">
                    <i class="fa fa-level-up"></i>
                    <strong><?php echo plugin_lang_get( 'parent_process' ); ?>:</strong>
                    <?php if( $t_parent_visibility === 'full' ) { ?>
                    <a href="<?php echo string_get_bug_view_url( $t_parent_bug_id ); ?>">
                        <?php echo bug_format_id( $t_parent_bug_id ); ?>
                    </a>
                    <?php } else { ?>
                    <?php echo bug_format_id( $t_parent_bug_id ); ?>
                    <?php } ?>
                    <?php if( $t_parent_step_name !== '' ) { ?>
                        — <?php echo string_display_line( $t_parent_step_name ); ?>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php } ?>

                <?php if( $t_has_children ) { ?>
                <div class="pe-subprocess-children">
                    <strong><?php echo plugin_lang_get( 'child_processes' ); ?>:</strong>
                    <span class="pe-subprocess-summary">
                        <?php echo sprintf( plugin_lang_get( 'child_summary' ), $t_child_completed, $t_child_total ); ?>
                    </span>
                    <table class="table table-condensed table-striped" style="margin-top: 6px;">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'col_bug_id' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'current_step' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $t_child_view_threshold = plugin_config_get( 'view_threshold' );
                            foreach( $t_children as $t_child ) {
                                $t_child_bug_id = (int) $t_child['bug_id'];
                                $t_child_exists = bug_exists( $t_child_bug_id );

                                // Proje erişim kontrolü
                                $t_child_visibility = 'hidden';
                                if( $t_child_exists ) {
                                    $t_child_project = bug_get_field( $t_child_bug_id, 'project_id' );
                                    if( access_has_project_level( $t_child_view_threshold, $t_child_project ) ) {
                                        $t_child_visibility = 'full';
                                    } else if( access_has_project_level( VIEWER, $t_child_project ) ) {
                                        $t_child_visibility = 'metadata';
                                    }
                                }
                                if( $t_child_visibility === 'hidden' ) {
                                    continue;
                                }

                                $t_child_status_label = plugin_lang_get( 'instance_' . strtolower( $t_child['status'] ) );
                                // Çocuk adım adı
                                $t_c_step_name = '-';
                                if( (int) $t_child['current_step_id'] > 0 ) {
                                    $t_step_table = plugin_table( 'step' );
                                    db_param_push();
                                    $t_csq = "SELECT name FROM $t_step_table WHERE id = " . db_param();
                                    $t_csr = db_query( $t_csq, array( (int) $t_child['current_step_id'] ) );
                                    $t_cs_row = db_fetch_array( $t_csr );
                                    if( $t_cs_row !== false ) {
                                        $t_c_step_name = $t_cs_row['name'];
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <?php if( $t_child_exists && $t_child_visibility === 'full' ) { ?>
                                        <a href="<?php echo string_get_bug_view_url( $t_child_bug_id ); ?>">
                                            <?php echo bug_format_id( $t_child_bug_id ); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo bug_format_id( $t_child_bug_id ); ?>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="pe-sla-badge pe-tree-status-<?php echo strtolower( $t_child['status'] ); ?>-badge">
                                        <?php echo string_display_line( $t_child_status_label ); ?>
                                    </span>
                                </td>
                                <td><?php echo string_display_line( $t_c_step_name ); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>

                <div class="pe-subprocess-tree-link" style="margin-top: 8px;">
                    <a href="<?php echo plugin_page( 'process_tree' ) . '&bug_id=' . (int) $p_bug_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fa fa-sitemap"></i>
                        <?php echo plugin_lang_get( 'view_process_tree' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }

    /**
     * Render the unified process timeline (card-based)
     */
    private function render_process_timeline( $t_events ) {
        // Olay tipi ikon ve renk haritası
        $t_event_icons = array(
            'process_started'      => array( 'icon' => 'fa-play-circle',         'color' => '#3498db' ),
            'status_change'        => array( 'icon' => 'fa-exchange',            'color' => '#2ecc71' ),
            'step_advanced'        => array( 'icon' => 'fa-forward',             'color' => '#27ae60' ),
            'subprocess_created'   => array( 'icon' => 'fa-sitemap',             'color' => '#9b59b6' ),
            'subprocess_completed' => array( 'icon' => 'fa-check-circle',        'color' => '#8e44ad' ),
            'parent_advanced'      => array( 'icon' => 'fa-level-up',            'color' => '#2980b9' ),
            'out_of_flow'          => array( 'icon' => 'fa-exclamation-triangle', 'color' => '#e67e22' ),
            'note_added'           => array( 'icon' => 'fa-comment',             'color' => '#7f8c8d' ),
        );
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-clock-o"></i>
                <?php echo plugin_lang_get( 'process_timeline' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <div class="pe-timeline">
                    <?php foreach( $t_events as $t_event ) {
                        $t_type = $t_event['event_type'];
                        $t_icon_info = isset( $t_event_icons[$t_type] ) ? $t_event_icons[$t_type] : array( 'icon' => 'fa-circle', 'color' => '#95a5a6' );
                        $t_user_name = ( (int) $t_event['user_id'] > 0 && user_exists( (int) $t_event['user_id'] ) )
                            ? user_get_name( $t_event['user_id'] ) : '-';
                        $t_type_label = plugin_lang_get( 'event_type_' . $t_type );
                    ?>
                    <div class="pe-timeline-item">
                        <div class="pe-timeline-icon" style="background-color: <?php echo $t_icon_info['color']; ?>;">
                            <i class="fa <?php echo $t_icon_info['icon']; ?>"></i>
                        </div>
                        <div class="pe-timeline-card">
                            <div class="pe-timeline-header">
                                <span class="pe-timeline-type"><?php echo string_display_line( $t_type_label ); ?></span>
                                <?php if( $t_event['transition_label'] !== '' ) { ?>
                                    <span class="pe-timeline-label"><?php echo string_display_line( $t_event['transition_label'] ); ?></span>
                                <?php } ?>
                                <span class="pe-timeline-meta">
                                    <?php echo $t_user_name; ?> &mdash; <?php echo date( 'Y-m-d H:i', $t_event['created_at'] ); ?>
                                </span>
                            </div>
                            <?php if( $t_event['source'] === 'process_log' && (int) $t_event['from_status'] > 0 ) { ?>
                            <div class="pe-timeline-status">
                                <span class="process-status"><?php echo get_enum_element( 'status', $t_event['from_status'] ); ?></span>
                                <i class="fa fa-arrow-right"></i>
                                <span class="process-status"><?php echo get_enum_element( 'status', $t_event['to_status'] ); ?></span>
                                <?php if( $t_event['step_name'] !== '' ) { ?>
                                    <span class="pe-timeline-step"><?php echo string_display_line( $t_event['step_name'] ); ?></span>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <?php if( $t_event['note'] !== '' ) { ?>
                            <div class="pe-timeline-note"><?php echo string_display_line( $t_event['note'] ); ?></div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }
}
