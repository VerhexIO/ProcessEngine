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

        // Başlangıç adımı subprocess ise çocuk oluştur (ertelenmiş)
        if( isset( $t_step['step_type'] ) && $t_step['step_type'] === 'subprocess' ) {
            $t_step_data = $t_step;
            register_shutdown_function( function() use ( $p_bug_id, $t_step_data, $t_inst_id ) {
                require_once( __DIR__ . '/core/subprocess_api.php' );
                subprocess_handle_step( $p_bug_id, $t_step_data, $t_inst_id );
            });
        }

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

                        // Subprocess adımı mı? Çocuk sorun oluştur
                        if( isset( $t_step_data['step_type'] ) && $t_step_data['step_type'] === 'subprocess' ) {
                            subprocess_handle_step( $t_bug_id, $t_step_data, $t_inst_id );
                        }

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
        return $t_css;
    }

    /**
     * Hook: EVENT_VIEW_BUG_EXTRA - show process info, stepper, timeline, and subprocess tree info
     */
    public function on_view_bug_extra( $p_event, $p_bug_id ) {
        if( !access_has_global_level( plugin_config_get( 'view_threshold' ) ) ) {
            return;
        }

        require_once( __DIR__ . '/core/process_api.php' );

        $t_logs = process_get_logs_for_bug( $p_bug_id );
        if( empty( $t_logs ) ) {
            return;
        }

        $t_progress = process_get_flow_progress( $p_bug_id );

        // 1. Süreç Bilgi Paneli
        $this->render_process_info_panel( $p_bug_id, $t_progress );

        // 2. Görsel Adım Çubuğu
        if( $t_progress !== null ) {
            $this->render_step_progress_bar( $t_progress );
        }

        // 3. Ebeveyn/Çocuk Süreç Paneli
        $this->render_subprocess_panel( $p_bug_id );

        // 4. Süreç Zaman Çizelgesi
        $this->render_process_timeline( $t_logs );
    }

    /**
     * Render the process info panel (current step, department, progress, SLA, handler)
     */
    private function render_process_info_panel( $p_bug_id, $t_progress ) {
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

        // SLA kalan süre
        $t_sla_text = '-';
        $t_sla_class = '';
        if( $t_progress['current_sla'] !== null ) {
            $t_sla = $t_progress['current_sla'];
            if( $t_sla['remaining_sec'] > 0 ) {
                $t_sla_text = $t_sla['remaining_hrs'] . ' ' . plugin_lang_get( 'hours' );
            } else {
                $t_sla_text = plugin_lang_get( 'sla_overdue' );
                $t_sla_class = 'pe-sla-overdue-text';
            }
        }
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
                        <div class="pe-info-value"><?php echo string_display_line( $t_step_name ); ?></div>
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
            </div>
        </div>
    </div>
</div>
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
                <?php if( $t_has_parent && $t_parent_bug_id > 0 && bug_exists( $t_parent_bug_id ) ) { ?>
                <div class="pe-subprocess-parent">
                    <i class="fa fa-level-up"></i>
                    <strong><?php echo plugin_lang_get( 'parent_process' ); ?>:</strong>
                    <a href="<?php echo string_get_bug_view_url( $t_parent_bug_id ); ?>">
                        <?php echo bug_format_id( $t_parent_bug_id ); ?>
                    </a>
                    <?php if( $t_parent_step_name !== '' ) { ?>
                        — <?php echo string_display_line( $t_parent_step_name ); ?>
                    <?php } ?>
                </div>
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
                            <?php foreach( $t_children as $t_child ) {
                                $t_child_bug_id = (int) $t_child['bug_id'];
                                $t_child_exists = bug_exists( $t_child_bug_id );
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
                                    <?php if( $t_child_exists ) { ?>
                                        <a href="<?php echo string_get_bug_view_url( $t_child_bug_id ); ?>">
                                            <?php echo bug_format_id( $t_child_bug_id ); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo $t_child_bug_id; ?>
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
     * Render the process timeline table
     */
    private function render_process_timeline( $t_logs ) {
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-cogs"></i>
                <?php echo plugin_lang_get( 'process_timeline' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-striped">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'col_date' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_from_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_to_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_user' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_note' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach( $t_logs as $t_log ) { ?>
                            <tr>
                                <td><?php echo date( 'Y-m-d H:i', $t_log['created_at'] ); ?></td>
                                <td><span class="process-status"><?php echo (int)$t_log['from_status'] === 0 ? '-' : get_enum_element( 'status', $t_log['from_status'] ); ?></span></td>
                                <td><span class="process-status"><?php echo get_enum_element( 'status', $t_log['to_status'] ); ?></span></td>
                                <td><?php echo ( (int) $t_log['user_id'] > 0 && user_exists( (int) $t_log['user_id'] ) ) ? user_get_name( $t_log['user_id'] ) : '-'; ?></td>
                                <td><?php echo string_display_line( $t_log['step_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_log['note'] ); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }
}
