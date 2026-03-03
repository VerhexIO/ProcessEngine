<?php
/**
 * ProcessEngine - Dashboard Page
 *
 * Shows summary cards, filterable request table, and process overview.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'view_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/process_api.php' );
require_once( dirname( __DIR__ ) . '/core/subprocess_api.php' );

$t_dept_list = process_get_departments();

layout_page_header( plugin_lang_get( 'dashboard_title' ) );
layout_page_begin();

$t_stats = process_get_dashboard_stats();
$t_filter = gpc_get_string( 'filter', 'all' );
$t_department = gpc_get_string( 'department', '' );
$t_bugs = process_get_dashboard_bugs( $t_filter, $t_department );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card">
                        <div class="pe-card-value"><?php echo $t_stats['total']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'total_requests' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-blue">
                        <div class="pe-card-value"><?php echo $t_stats['active']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'active_processes' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-red">
                        <div class="pe-card-value"><?php echo $t_stats['sla_exceeded']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'sla_exceeded' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-purple">
                        <div class="pe-card-value"><?php echo $t_stats['avg_time']; ?>h</div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'avg_resolution_time' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-green">
                        <div class="pe-card-value"><?php echo $t_stats['today']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'updated_today' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-orange">
                        <div class="pe-card-value"><?php echo $t_stats['pending']; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'pending_approvals' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="widget-box">
                <div class="widget-body">
                    <div class="widget-main pe-card pe-card-purple">
                        <div class="pe-card-value"><?php echo isset( $t_stats['waiting_subprocesses'] ) ? $t_stats['waiting_subprocesses'] : 0; ?></div>
                        <div class="pe-card-label"><?php echo plugin_lang_get( 'pending_subprocesses' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-10"></div>

    <!-- Filter Buttons + Request Table -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-list"></i>
                <?php echo plugin_lang_get( 'dashboard_title' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-toolbox padding-8">
                <div class="btn-group" style="margin-right: 15px;">
                    <?php
                    $t_filters = array( 'all', 'active', 'sla_exceeded', 'completed' );
                    foreach( $t_filters as $t_f ) {
                        $t_active_class = ( $t_filter === $t_f ) ? 'btn-primary' : 'btn-white';
                        $t_label = plugin_lang_get( 'filter_' . $t_f );
                        $t_url = plugin_page( 'dashboard' ) . '&filter=' . $t_f . ( $t_department !== '' ? '&department=' . urlencode( $t_department ) : '' );
                        echo '<a href="' . $t_url . '" class="btn btn-sm ' . $t_active_class . '">' . $t_label . '</a> ';
                    }
                    ?>
                </div>
                <?php if( access_has_global_level( plugin_config_get( 'manage_threshold' ) ) ) { ?>
                <button class="btn btn-sm btn-warning pe-sla-global-check" style="margin-right:10px;">
                    <i class="fa fa-refresh"></i> <?php echo plugin_lang_get( 'sla_global_check' ); ?>
                </button>
                <?php } ?>
                <div class="btn-group">
                    <select id="pe-dept-filter" class="form-control input-sm" style="display:inline-block; width:auto;" onchange="window.location.href='<?php echo plugin_page( 'dashboard' ) . '&filter=' . urlencode( $t_filter ); ?>&department=' + encodeURIComponent(this.value);">
                        <option value=""><?php echo plugin_lang_get( 'all_departments' ); ?></option>
                        <?php
                        foreach( $t_dept_list as $t_dept ) {
                            $t_selected = ( $t_department === $t_dept ) ? 'selected' : '';
                            echo '<option value="' . string_attribute( $t_dept ) . '" ' . $t_selected . '>' . string_display_line( $t_dept ) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'col_bug_id' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_summary' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_current_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_department' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_progress' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_handler' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_sla_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_subprocess' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_updated' ); ?></th>
                                <?php if( access_has_global_level( plugin_config_get( 'action_threshold' ) ) ) { ?>
                                <th><?php echo plugin_lang_get( 'col_actions' ); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $t_can_action = access_has_global_level( plugin_config_get( 'action_threshold' ) );
                            $t_colspan = $t_can_action ? 10 : 9;
                            ?>
                            <?php if( empty( $t_bugs ) ) { ?>
                            <tr>
                                <td colspan="<?php echo $t_colspan; ?>" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td>
                            </tr>
                            <?php } else {
                                foreach( $t_bugs as $t_bug_row ) {
                                    $t_sla_class = 'pe-sla-normal';
                                    if( $t_bug_row['sla_status'] === 'WARNING' ) {
                                        $t_sla_class = 'pe-sla-warning';
                                    } else if( $t_bug_row['sla_status'] === 'EXCEEDED' ) {
                                        $t_sla_class = 'pe-sla-exceeded';
                                    }
                                    $t_inst_status = isset( $t_bug_row['instance_status'] ) ? $t_bug_row['instance_status'] : '';
                                    $t_row_class = '';
                                    if( $t_inst_status === 'COMPLETED' || $t_inst_status === 'CANCELLED' ) {
                                        $t_row_class = ' class="pe-row-faded"';
                                    }
                            ?>
                            <tr<?php echo $t_row_class; ?>>
                                <td>
                                    <a href="<?php echo string_get_bug_view_url( $t_bug_row['bug_id'] ); ?>">
                                        <?php echo bug_format_id( $t_bug_row['bug_id'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo string_display_line( $t_bug_row['summary'] ); ?></td>
                                <td>
                                    <?php
                                    if( $t_inst_status === 'WAITING' ) {
                                        echo '<span class="pe-status-badge pe-badge-waiting-step">';
                                        echo '<i class="fa fa-hourglass-half"></i> ';
                                        echo plugin_lang_get( 'subprocess_waiting_label' );
                                        echo '</span>';
                                    } else {
                                        echo string_display_line( $t_bug_row['step_name'] );
                                    }
                                    ?>
                                </td>
                                <td><?php echo string_display_line( $t_bug_row['department'] ); ?></td>
                                <td>
                                    <?php
                                    $t_pct = isset( $t_bug_row['progress_pct'] ) ? (int) $t_bug_row['progress_pct'] : 0;
                                    $t_bar_class = 'pe-progress-bar-fill';
                                    if( $t_pct >= 100 ) {
                                        $t_bar_class .= ' pe-progress-complete';
                                    } else if( $t_inst_status === 'WAITING' ) {
                                        $t_bar_class .= ' pe-progress-waiting';
                                    }
                                    ?>
                                    <div class="pe-progress-bar-wrapper">
                                        <div class="<?php echo $t_bar_class; ?>" style="width: <?php echo $t_pct; ?>%;">
                                            <?php echo $t_pct; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo string_display_line( isset( $t_bug_row['handler_name'] ) ? $t_bug_row['handler_name'] : '-' ); ?></td>
                                <td>
                                    <span class="pe-sla-badge <?php echo $t_sla_class; ?>">
                                        <?php echo string_display_line( $t_bug_row['sla_status'] ); ?>
                                    </span>
                                </td>
                                <td class="pe-subprocess-col">
                                    <?php
                                    $t_sub_info = isset( $t_bug_row['subprocess_info'] ) ? $t_bug_row['subprocess_info'] : null;
                                    $t_has_children = isset( $t_bug_row['has_children'] ) ? $t_bug_row['has_children'] : false;
                                    $t_is_child = isset( $t_bug_row['is_child'] ) ? $t_bug_row['is_child'] : false;
                                    $t_show_tree = ( $t_has_children || $t_is_child );
                                    if( $t_sub_info !== null && $t_sub_info['total'] > 0 ) {
                                        echo '<span class="pe-sub-summary">';
                                        echo sprintf( plugin_lang_get( 'child_summary' ), $t_sub_info['completed'], $t_sub_info['total'] );
                                        echo '</span> ';
                                        // Durum rozetleri
                                        if( isset( $t_sub_info['active'] ) && $t_sub_info['active'] > 0 ) {
                                            echo '<span class="pe-status-badge pe-badge-active">' . $t_sub_info['active'] . ' ' . plugin_lang_get( 'badge_active' ) . '</span> ';
                                        }
                                        if( isset( $t_sub_info['waiting'] ) && $t_sub_info['waiting'] > 0 ) {
                                            echo '<span class="pe-status-badge pe-badge-waiting">' . $t_sub_info['waiting'] . ' ' . plugin_lang_get( 'badge_waiting' ) . '</span> ';
                                        }
                                    }
                                    if( $t_show_tree ) {
                                        echo '<a href="' . plugin_page( 'process_tree' ) . '&bug_id=' . $t_bug_row['bug_id'] . '" title="' . plugin_lang_get( 'view_process_tree' ) . '">';
                                        echo '<i class="fa fa-sitemap"></i></a>';
                                    } else if( $t_sub_info === null || $t_sub_info['total'] === 0 ) {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date( 'Y-m-d H:i', $t_bug_row['updated_at'] ); ?></td>
                                <?php if( $t_can_action ) { ?>
                                <td class="pe-actions-col">
                                    <?php if( $t_inst_status === 'ACTIVE' ) { ?>
                                    <button class="btn btn-xs btn-primary pe-action-advance"
                                            data-bug-id="<?php echo $t_bug_row['bug_id']; ?>"
                                            title="<?php echo plugin_lang_get( 'action_advance_confirm' ); ?>">
                                        <i class="fa fa-forward"></i>
                                    </button>
                                    <?php } else if( $t_inst_status === 'WAITING' ) { ?>
                                    <span class="pe-waiting-label-sm">
                                        <i class="fa fa-hourglass-half"></i>
                                    </span>
                                    <?php } ?>
                                    <?php if( $t_bug_row['sla_status'] !== 'NORMAL' && $t_inst_status !== 'COMPLETED' && $t_inst_status !== 'CANCELLED' ) { ?>
                                    <button class="btn btn-xs btn-warning pe-action-sla"
                                            data-bug-id="<?php echo $t_bug_row['bug_id']; ?>"
                                            title="<?php echo plugin_lang_get( 'action_sla_refreshed' ); ?>">
                                        <i class="fa fa-clock-o"></i>
                                    </button>
                                    <?php } ?>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="pe-action-url" value="<?php echo plugin_page( 'dashboard_action' ); ?>" />
<input type="hidden" id="pe-security-token" value="<?php echo form_security_token( 'ProcessEngine_dashboard_action' ); ?>" />

<?php
layout_page_end();
