<?php
/**
 * ProcessEngine - Process Tree Page
 *
 * Displays the hierarchical process tree for a given bug.
 * Shows parent-child relationships with visibility control.
 *
 * URL: ?bug_id=X
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'view_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/process_api.php' );
require_once( dirname( __DIR__ ) . '/core/subprocess_api.php' );
require_once( dirname( __DIR__ ) . '/core/sla_api.php' );

$t_bug_id = gpc_get_int( 'bug_id', 0 );
if( $t_bug_id <= 0 || !bug_exists( $t_bug_id ) ) {
    error_parameters( $t_bug_id );
    trigger_error( ERROR_BUG_NOT_FOUND, ERROR );
}

// Kök bug'ı bul (ağacın en üstü)
$t_root_bug_id = subprocess_find_root_bug_id( $t_bug_id );

// Ağaç yapısını oluştur
$t_tree = subprocess_get_tree( $t_root_bug_id );

// Kök bug erişim kontrolü
$t_root_label = '';
if( bug_exists( $t_root_bug_id ) ) {
    $t_root_project = bug_get_field( $t_root_bug_id, 'project_id' );
    if( access_has_project_level( plugin_config_get( 'view_threshold' ), $t_root_project ) ) {
        $t_root_label = bug_format_id( $t_root_bug_id );
    } else {
        $t_root_label = '#' . $t_root_bug_id;
    }
} else {
    $t_root_label = '#' . $t_root_bug_id;
}

$t_view_threshold = plugin_config_get( 'view_threshold' );

layout_page_header( plugin_lang_get( 'process_tree' ) );
layout_page_begin();

/**
 * Görünürlük seviyesini belirle.
 * view_threshold kullanarak proje bazlı erişim kontrolü yapar.
 *
 * @param int $p_bug_id Bug ID
 * @param int $p_view_threshold Plugin view threshold
 * @return string 'full', 'metadata', veya 'hidden'
 */
if( !function_exists( 'process_tree_get_visibility' ) ) {
function process_tree_get_visibility( $p_bug_id, $p_view_threshold = VIEWER ) {
    if( !bug_exists( $p_bug_id ) ) {
        return 'hidden';
    }
    $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
    if( access_has_project_level( $p_view_threshold, $t_project_id ) ) {
        return 'full';
    }
    // Kullanıcı projeye erişebiliyor ama yeterli seviye yok → metadata
    if( access_has_project_level( VIEWER, $t_project_id ) ) {
        return 'metadata';
    }
    return 'hidden';
}
}

/**
 * Ağaç düğümünü render et (recursive).
 *
 * @param array $p_node Ağaç düğümü (subprocess_get_tree çıktısı)
 * @param int   $p_target_bug_id Hedef bug ID (vurgulama için)
 * @param int   $p_view_threshold Plugin view threshold
 * @param int   $p_depth Derinlik seviyesi
 */
if( !function_exists( 'process_tree_render_node' ) ) {
function process_tree_render_node( $p_node, $p_target_bug_id, $p_view_threshold = VIEWER, $p_depth = 0 ) {
    if( $p_node === null || $p_depth > 10 ) {
        return;
    }

    $t_bug_id = (int) $p_node['bug_id'];
    $t_instance = $p_node['instance'];
    $t_visibility = process_tree_get_visibility( $t_bug_id, $p_view_threshold );

    if( $t_visibility === 'hidden' ) {
        return;
    }

    $t_status = $t_instance['status'];
    $t_is_target = ( $t_bug_id === $p_target_bug_id );
    $t_has_children = !empty( $p_node['children'] );

    // Durum sınıfı
    $t_status_class = 'pe-tree-status-active';
    if( $t_status === 'COMPLETED' ) {
        $t_status_class = 'pe-tree-status-completed';
    } else if( $t_status === 'WAITING' ) {
        $t_status_class = 'pe-tree-status-waiting';
    } else if( $t_status === 'CANCELLED' ) {
        $t_status_class = 'pe-tree-status-cancelled';
    }

    // İlerleme bilgisi
    $t_progress_pct = 0;
    $t_step_name = '-';
    $t_department = '-';

    // Adım bilgisini al
    $t_step_table = plugin_table( 'step' );

    if( (int) $t_instance['current_step_id'] > 0 ) {
        db_param_push();
        $t_sq = "SELECT name, department FROM $t_step_table WHERE id = " . db_param();
        $t_sr = db_query( $t_sq, array( (int) $t_instance['current_step_id'] ) );
        $t_step_row = db_fetch_array( $t_sr );
        if( $t_step_row !== false ) {
            $t_step_name = $t_step_row['name'];
            $t_department = $t_step_row['department'];
        }
    }

    // Toplam adım ve ilerleme hesapla
    db_param_push();
    $t_total_q = "SELECT COUNT(*) AS cnt FROM $t_step_table WHERE flow_id = " . db_param();
    $t_total_r = db_query( $t_total_q, array( (int) $t_instance['flow_id'] ) );
    $t_total_row = db_fetch_array( $t_total_r );
    $t_total_steps = ( $t_total_row !== false ) ? (int) $t_total_row['cnt'] : 0;

    if( $t_total_steps > 0 && $t_status === 'COMPLETED' ) {
        $t_progress_pct = 100;
    } else if( $t_total_steps > 0 ) {
        $t_log_table = plugin_table( 'log' );
        db_param_push();
        $t_lq = "SELECT COUNT(DISTINCT step_id) AS cnt FROM $t_log_table WHERE bug_id = " . db_param() . " AND step_id > 0";
        $t_lr = db_query( $t_lq, array( $t_bug_id ) );
        $t_log_row = db_fetch_array( $t_lr );
        $t_visited = ( $t_log_row !== false ) ? (int) $t_log_row['cnt'] : 0;
        $t_progress_pct = min( 100, round( $t_visited / $t_total_steps * 100 ) );
    }

    // SLA bilgisi
    $t_sla_table = plugin_table( 'sla_tracking' );
    db_param_push();
    $t_sla_q = "SELECT sla_status, deadline_at FROM $t_sla_table WHERE bug_id = " . db_param() . " AND completed_at IS NULL ORDER BY id DESC LIMIT 1";
    $t_sla_r = db_query( $t_sla_q, array( $t_bug_id ) );
    $t_sla_row = db_fetch_array( $t_sla_r );
    $t_sla_status = ( $t_sla_row !== false ) ? $t_sla_row['sla_status'] : '';
    $t_sla_deadline = ( $t_sla_row !== false ) ? (int) $t_sla_row['deadline_at'] : 0;

    // Çocuk özeti
    $t_child_count = count( $p_node['children'] );
    $t_child_completed = 0;
    $t_child_active = 0;
    $t_child_waiting = 0;
    foreach( $p_node['children'] as $t_ch ) {
        $t_ch_status = $t_ch['instance']['status'];
        if( $t_ch_status === 'COMPLETED' ) {
            $t_child_completed++;
        } else if( $t_ch_status === 'WAITING' ) {
            $t_child_waiting++;
        } else if( $t_ch_status === 'ACTIVE' ) {
            $t_child_active++;
        }
    }

    // wait_mode bilgisi (mevcut adımdan)
    $t_wait_mode = '';
    if( (int) $t_instance['current_step_id'] > 0 ) {
        db_param_push();
        $t_wm_q = "SELECT wait_mode, step_type FROM $t_step_table WHERE id = " . db_param();
        $t_wm_r = db_query( $t_wm_q, array( (int) $t_instance['current_step_id'] ) );
        $t_wm_row = db_fetch_array( $t_wm_r );
        if( $t_wm_row !== false && $t_wm_row['step_type'] === 'subprocess' ) {
            $t_wait_mode = isset( $t_wm_row['wait_mode'] ) ? $t_wm_row['wait_mode'] : 'all';
        }
    }

    // Durum etiketi
    $t_status_label = plugin_lang_get( 'instance_' . strtolower( $t_status ) );
?>
    <div class="pe-tree-node <?php echo $t_is_target ? 'pe-tree-node-target' : ''; ?>" data-bug-id="<?php echo $t_bug_id; ?>" data-depth="<?php echo $p_depth; ?>">
        <div class="pe-tree-card <?php echo $t_status_class; ?>">
            <div class="pe-tree-card-header">
                <span class="pe-tree-status-badge <?php echo $t_status_class; ?>"><?php echo string_display_line( $t_status_label ); ?></span>
                <?php if( $t_has_children ) { ?>
                <button class="pe-tree-toggle btn btn-xs btn-default" title="Daralt/Genişlet">
                    <i class="fa fa-chevron-down"></i>
                </button>
                <?php } ?>
            </div>
            <div class="pe-tree-card-body">
                <?php if( $t_visibility === 'full' ) { ?>
                    <div class="pe-tree-bug-id">
                        <a href="<?php echo string_get_bug_view_url( $t_bug_id ); ?>">
                            <?php echo bug_format_id( $t_bug_id ); ?>
                        </a>
                    </div>
                    <div class="pe-tree-summary"><?php echo string_display_line( bug_get_field( $t_bug_id, 'summary' ) ); ?></div>
                <?php } else { ?>
                    <div class="pe-tree-bug-id pe-tree-restricted">
                        <i class="fa fa-lock"></i>
                        <?php echo plugin_lang_get( 'tree_restricted_access' ); ?>
                    </div>
                <?php } ?>
                <div class="pe-tree-info-row">
                    <div class="pe-tree-info-item">
                        <span class="pe-tree-info-label"><?php echo plugin_lang_get( 'current_step' ); ?></span>
                        <span class="pe-tree-info-value">
                            <?php if( $t_status === 'WAITING' ) { ?>
                                <span class="pe-status-badge pe-badge-waiting-step"><i class="fa fa-hourglass-half"></i> <?php echo plugin_lang_get( 'subprocess_waiting_label' ); ?></span>
                            <?php } else { ?>
                                <?php echo string_display_line( $t_step_name ); ?>
                            <?php } ?>
                        </span>
                    </div>
                    <div class="pe-tree-info-item">
                        <span class="pe-tree-info-label"><?php echo plugin_lang_get( 'col_department' ); ?></span>
                        <span class="pe-tree-info-value"><?php echo string_display_line( $t_department ); ?></span>
                    </div>
                    <div class="pe-tree-info-item">
                        <span class="pe-tree-info-label"><?php echo plugin_lang_get( 'col_progress' ); ?></span>
                        <span class="pe-tree-info-value">
                            <div class="pe-tree-progress">
                                <div class="pe-tree-progress-fill <?php echo ( $t_progress_pct >= 100 ) ? 'pe-progress-complete' : ( $t_status === 'WAITING' ? 'pe-progress-waiting' : '' ); ?>" style="width: <?php echo $t_progress_pct; ?>%;">
                                    <?php echo $t_progress_pct; ?>%
                                </div>
                            </div>
                        </span>
                    </div>
                    <?php if( $t_sla_status !== '' ) { ?>
                    <div class="pe-tree-info-item">
                        <span class="pe-tree-info-label"><?php echo plugin_lang_get( 'col_sla_status' ); ?></span>
                        <span class="pe-tree-info-value">
                            <?php
                            $t_sla_badge_class = 'pe-sla-normal';
                            if( $t_sla_status === 'WARNING' ) {
                                $t_sla_badge_class = 'pe-sla-warning';
                            } else if( $t_sla_status === 'EXCEEDED' ) {
                                $t_sla_badge_class = 'pe-sla-exceeded';
                            }
                            ?>
                            <span class="pe-sla-badge <?php echo $t_sla_badge_class; ?>"><?php echo string_display_line( $t_sla_status ); ?></span>
                            <?php if( $t_sla_deadline > 0 && $t_sla_status !== '' ) {
                                $t_remaining = $t_sla_deadline - time();
                                if( $t_remaining > 0 ) {
                                    echo ' <small>' . round( $t_remaining / 3600, 1 ) . 'h</small>';
                                } else {
                                    echo ' <small class="pe-sla-overdue-text">-' . round( abs( $t_remaining ) / 3600, 1 ) . 'h</small>';
                                }
                            } ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>
                <?php if( $t_child_count > 0 ) { ?>
                <div class="pe-tree-child-summary">
                    <span class="pe-tree-info-label"><?php echo plugin_lang_get( 'child_processes' ); ?></span>
                    <span class="pe-sub-summary"><?php echo sprintf( plugin_lang_get( 'child_summary' ), $t_child_completed, $t_child_count ); ?></span>
                    <?php if( $t_child_active > 0 ) { ?>
                        <span class="pe-status-badge pe-badge-active"><?php echo $t_child_active; ?> <?php echo plugin_lang_get( 'badge_active' ); ?></span>
                    <?php } ?>
                    <?php if( $t_child_waiting > 0 ) { ?>
                        <span class="pe-status-badge pe-badge-waiting"><?php echo $t_child_waiting; ?> <?php echo plugin_lang_get( 'badge_waiting' ); ?></span>
                    <?php } ?>
                    <?php if( $t_wait_mode !== '' ) { ?>
                        <span class="pe-tree-wait-mode">
                            <i class="fa fa-clock-o"></i>
                            <?php echo plugin_lang_get( 'wait_mode_' . $t_wait_mode ); ?>
                        </span>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php if( $t_has_children ) { ?>
        <div class="pe-tree-children">
            <?php foreach( $p_node['children'] as $t_child ) {
                process_tree_render_node( $t_child, $p_target_bug_id, $p_view_threshold, $p_depth + 1 );
            } ?>
        </div>
        <?php } ?>
    </div>
<?php
}
}
?>

<link rel="stylesheet" href="<?php echo plugin_file( 'process_tree.css' ); ?>" />

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Geri dön butonu -->
    <div class="pe-tree-toolbar">
        <a href="<?php echo string_get_bug_view_url( $t_bug_id ); ?>" class="btn btn-sm btn-default">
            <i class="fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'btn_back' ); ?>
        </a>
        <a href="<?php echo plugin_page( 'dashboard' ); ?>" class="btn btn-sm btn-default">
            <i class="fa fa-dashboard"></i> <?php echo plugin_lang_get( 'menu_dashboard' ); ?>
        </a>
    </div>

    <div class="space-10"></div>

    <!-- Ağaç Başlığı -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'process_tree' ); ?>
                — <?php echo $t_root_label; ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <?php if( $t_tree === null ) { ?>
                    <p class="center"><?php echo plugin_lang_get( 'no_process_data' ); ?></p>
                <?php } else { ?>
                    <div class="pe-tree-container">
                        <?php process_tree_render_node( $t_tree, $t_bug_id, $t_view_threshold ); ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo plugin_file( 'process_tree.js' ); ?>"></script>

<?php
layout_page_end();
