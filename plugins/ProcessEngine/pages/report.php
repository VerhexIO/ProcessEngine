<?php
/**
 * ProcessEngine - Report Page (Faz 12)
 *
 * Full-featured report page with filters, summary cards, charts,
 * detail table and CSV export.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'view_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/process_api.php' );
require_once( dirname( __DIR__ ) . '/core/flow_api.php' );

// Filtreleri oku
$t_date_from_str = gpc_get_string( 'date_from', '' );
$t_date_to_str   = gpc_get_string( 'date_to', '' );
$t_project_id    = (int) gpc_get_string( 'project_id', '0' );
$t_department    = gpc_get_string( 'department', '' );
$t_flow_id       = (int) gpc_get_string( 'flow_id', '0' );
$t_status_filter = gpc_get_string( 'status', '' );
$t_page_raw      = gpc_get_string( 'page', '1' );
$t_page          = max( 1, (int) $t_page_raw );
$t_csv           = ( gpc_get_string( 'csv', '' ) !== '' );

$t_date_from = 0;
$t_date_to = 0;
if( $t_date_from_str !== '' ) {
    $t_date_from = strtotime( $t_date_from_str );
}
if( $t_date_to_str !== '' ) {
    $t_date_to = strtotime( $t_date_to_str . ' 23:59:59' );
}

$t_filters = array(
    'date_from'  => $t_date_from,
    'date_to'    => $t_date_to,
    'project_id' => $t_project_id,
    'department' => $t_department,
    'flow_id'    => $t_flow_id,
    'status'     => $t_status_filter,
    'page'       => $t_page,
    'per_page'   => 25,
);

// CSV Export — HTML render öncesi kontrol et, gereksiz sorguyu atla
if( $t_csv ) {
    $t_filters['per_page'] = 10000;
    $t_filters['page'] = 1;
    $t_csv_data = process_get_report_data( $t_filters );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="process_report_' . date( 'Ymd_His' ) . '.csv"' );
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";

    $t_csv_out = fopen( 'php://output', 'w' );
    // Header
    fputcsv( $t_csv_out, array(
        plugin_lang_get( 'report_col_bug_id' ),
        plugin_lang_get( 'col_summary' ),
        plugin_lang_get( 'report_col_flow' ),
        plugin_lang_get( 'report_col_step' ),
        plugin_lang_get( 'report_col_department' ),
        plugin_lang_get( 'report_col_handler' ),
        plugin_lang_get( 'report_col_status' ),
        plugin_lang_get( 'report_col_duration' ),
        plugin_lang_get( 'report_col_sla_status' ),
        plugin_lang_get( 'report_col_wait_reason' ),
    ) );
    foreach( $t_csv_data['rows'] as $t_row ) {
        fputcsv( $t_csv_out, array(
            $t_row['bug_id'],
            $t_row['summary'],
            $t_row['flow_name'],
            $t_row['step_name'],
            $t_row['department'],
            $t_row['handler_name'],
            $t_row['instance_status'],
            $t_row['duration_hrs'] !== null ? $t_row['duration_hrs'] : '-',
            $t_row['sla_status'],
            $t_row['wait_reason'],
        ) );
    }
    fclose( $t_csv_out );
    exit;
}

// HTML render için rapor verisi (CSV modundan sonra çağır)
$t_report = process_get_report_data( $t_filters );

// Grafik verileri
$t_dept_perf   = process_get_department_performance( $t_filters );
$t_step_stats  = process_get_step_duration_stats( $t_filters );
$t_monthly     = process_get_monthly_trend( $t_filters );

// Erişilebilir projeler
$t_user_id = auth_get_current_user_id();
$t_accessible = user_get_accessible_projects( $t_user_id );
$t_projects = array();
foreach( $t_accessible as $t_pid ) {
    $t_projects[$t_pid] = project_get_name( $t_pid );
}

// Akış listesi
$t_flows = flow_get_all();

// Departmanlar
$t_dept_list = process_get_departments();

// Sayfalama
$t_total_pages = max( 1, ceil( $t_report['total'] / 25 ) );

layout_page_header( plugin_lang_get( 'report_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Başlık & CSV -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-bar-chart"></i>
                <?php echo plugin_lang_get( 'report_title' ); ?>
            </h4>
            <div class="widget-toolbar">
                <?php
                $t_csv_url = plugin_page( 'report' ) . '&csv=1'
                    . ( $t_date_from_str !== '' ? '&date_from=' . urlencode( $t_date_from_str ) : '' )
                    . ( $t_date_to_str !== '' ? '&date_to=' . urlencode( $t_date_to_str ) : '' )
                    . ( $t_project_id > 0 ? '&project_id=' . $t_project_id : '' )
                    . ( $t_department !== '' ? '&department=' . urlencode( $t_department ) : '' )
                    . ( $t_flow_id > 0 ? '&flow_id=' . $t_flow_id : '' )
                    . ( $t_status_filter !== '' ? '&status=' . urlencode( $t_status_filter ) : '' );
                ?>
                <a href="<?php echo $t_csv_url; ?>" class="btn btn-xs btn-success">
                    <i class="fa fa-download"></i> <?php echo plugin_lang_get( 'report_btn_csv' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <!-- Filtreler -->
            <div class="widget-toolbox padding-8">
                <form method="get" action="<?php echo helper_mantis_url( 'plugin.php' ); ?>" class="form-inline" style="display:inline;">
                    <input type="hidden" name="page" value="ProcessEngine/report" />
                    <label><?php echo plugin_lang_get( 'report_filter_date_from' ); ?>:</label>
                    <input type="date" name="date_from" value="<?php echo string_attribute( $t_date_from_str ); ?>" class="input-sm" />
                    <label><?php echo plugin_lang_get( 'report_filter_date_to' ); ?>:</label>
                    <input type="date" name="date_to" value="<?php echo string_attribute( $t_date_to_str ); ?>" class="input-sm" />

                    <select name="project_id" class="input-sm">
                        <option value="0"><?php echo plugin_lang_get( 'all_projects' ); ?></option>
                        <?php foreach( $t_projects as $t_pid => $t_pname ) { ?>
                        <option value="<?php echo (int) $t_pid; ?>" <?php echo ( $t_project_id === (int) $t_pid ) ? 'selected' : ''; ?>>
                            <?php echo string_display_line( $t_pname ); ?>
                        </option>
                        <?php } ?>
                    </select>

                    <select name="department" class="input-sm">
                        <option value=""><?php echo plugin_lang_get( 'all_departments' ); ?></option>
                        <?php foreach( $t_dept_list as $t_dept ) { ?>
                        <option value="<?php echo string_attribute( $t_dept ); ?>" <?php echo ( $t_department === $t_dept ) ? 'selected' : ''; ?>>
                            <?php echo string_display_line( $t_dept ); ?>
                        </option>
                        <?php } ?>
                    </select>

                    <select name="flow_id" class="input-sm">
                        <option value="0"><?php echo plugin_lang_get( 'filter_all' ); ?></option>
                        <?php foreach( $t_flows as $t_fl ) { ?>
                        <option value="<?php echo (int) $t_fl['id']; ?>" <?php echo ( $t_flow_id === (int) $t_fl['id'] ) ? 'selected' : ''; ?>>
                            <?php echo string_display_line( $t_fl['name'] ); ?>
                        </option>
                        <?php } ?>
                    </select>

                    <select name="status" class="input-sm">
                        <option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
                        <option value="active" <?php echo ( $t_status_filter === 'active' ) ? 'selected' : ''; ?>>
                            <?php echo plugin_lang_get( 'report_status_active' ); ?>
                        </option>
                        <option value="completed" <?php echo ( $t_status_filter === 'completed' ) ? 'selected' : ''; ?>>
                            <?php echo plugin_lang_get( 'report_status_completed' ); ?>
                        </option>
                        <option value="sla_exceeded" <?php echo ( $t_status_filter === 'sla_exceeded' ) ? 'selected' : ''; ?>>
                            <?php echo plugin_lang_get( 'report_status_sla_exceeded' ); ?>
                        </option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa fa-filter"></i> <?php echo plugin_lang_get( 'report_btn_filter' ); ?>
                    </button>
                </form>
            </div>

            <!-- Özet Kartları -->
            <div class="widget-main">
                <div class="row" style="margin-bottom:15px;">
                    <div class="col-md-3 col-sm-6">
                        <div class="pe-report-card">
                            <div class="pe-report-card-value"><?php echo $t_report['summary']['total']; ?></div>
                            <div class="pe-report-card-label"><?php echo plugin_lang_get( 'report_summary_total' ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="pe-report-card pe-report-card-blue">
                            <div class="pe-report-card-value"><?php echo $t_report['summary']['avg_duration']; ?>h</div>
                            <div class="pe-report-card-label"><?php echo plugin_lang_get( 'report_summary_avg_duration' ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="pe-report-card pe-report-card-green">
                            <div class="pe-report-card-value"><?php echo $t_report['summary']['sla_compliance']; ?>%</div>
                            <div class="pe-report-card-label"><?php echo plugin_lang_get( 'report_summary_sla_compliance' ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="pe-report-card pe-report-card-orange">
                            <div class="pe-report-card-value"><?php echo $t_report['summary']['active']; ?></div>
                            <div class="pe-report-card-label"><?php echo plugin_lang_get( 'report_summary_active' ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row" style="margin-bottom:15px;">
                    <div class="col-md-6">
                        <div class="pe-chart-box">
                            <h5><?php echo plugin_lang_get( 'report_chart_dept_performance' ); ?></h5>
                            <div class="pe-chart-wrapper">
                                <canvas id="pe-chart-dept"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pe-chart-box">
                            <h5><?php echo plugin_lang_get( 'report_chart_sla_distribution' ); ?></h5>
                            <div class="pe-chart-wrapper">
                                <canvas id="pe-chart-sla"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom:15px;">
                    <div class="col-md-6">
                        <div class="pe-chart-box">
                            <h5><?php echo plugin_lang_get( 'report_chart_step_duration' ); ?></h5>
                            <div class="pe-chart-wrapper">
                                <canvas id="pe-chart-steps"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pe-chart-box">
                            <h5><?php echo plugin_lang_get( 'report_chart_monthly_trend' ); ?></h5>
                            <div class="pe-chart-wrapper">
                                <canvas id="pe-chart-trend"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detay Tablosu -->
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'report_col_bug_id' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_summary' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_flow' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_department' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_handler' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_duration' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_sla_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'report_col_wait_reason' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_report['rows'] ) ) { ?>
                            <tr>
                                <td colspan="10" class="center"><?php echo plugin_lang_get( 'report_no_data' ); ?></td>
                            </tr>
                            <?php } else {
                                foreach( $t_report['rows'] as $t_row ) {
                                    $t_sla_class = 'pe-sla-normal';
                                    if( $t_row['sla_status'] === 'WARNING' ) {
                                        $t_sla_class = 'pe-sla-warning';
                                    } else if( $t_row['sla_status'] === 'EXCEEDED' ) {
                                        $t_sla_class = 'pe-sla-exceeded';
                                    }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo string_get_bug_view_url( $t_row['bug_id'] ); ?>">
                                        <?php echo bug_format_id( $t_row['bug_id'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo string_display_line( $t_row['summary'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['flow_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['step_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['department'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['handler_name'] ); ?></td>
                                <td>
                                    <?php
                                    $t_status_label = $t_row['instance_status'];
                                    if( $t_row['instance_status'] === 'ACTIVE' ) {
                                        $t_status_label = plugin_lang_get( 'instance_active' );
                                    } else if( $t_row['instance_status'] === 'WAITING' ) {
                                        $t_status_label = plugin_lang_get( 'instance_waiting' );
                                    } else if( $t_row['instance_status'] === 'COMPLETED' ) {
                                        $t_status_label = plugin_lang_get( 'instance_completed' );
                                    }
                                    echo string_display_line( $t_status_label );
                                    ?>
                                </td>
                                <td><?php echo ( $t_row['duration_hrs'] !== null ) ? $t_row['duration_hrs'] . 'h' : '-'; ?></td>
                                <td>
                                    <span class="pe-sla-badge <?php echo $t_sla_class; ?>">
                                        <?php echo string_display_line( $t_row['sla_status'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo string_display_line( $t_row['wait_reason'] ); ?></td>
                            </tr>
                            <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sayfalama -->
                <?php if( $t_total_pages > 1 ) { ?>
                <div class="pe-pagination center">
                    <?php
                    $t_base_url = plugin_page( 'report' )
                        . ( $t_date_from_str !== '' ? '&date_from=' . urlencode( $t_date_from_str ) : '' )
                        . ( $t_date_to_str !== '' ? '&date_to=' . urlencode( $t_date_to_str ) : '' )
                        . ( $t_project_id > 0 ? '&project_id=' . $t_project_id : '' )
                        . ( $t_department !== '' ? '&department=' . urlencode( $t_department ) : '' )
                        . ( $t_flow_id > 0 ? '&flow_id=' . $t_flow_id : '' )
                        . ( $t_status_filter !== '' ? '&status=' . urlencode( $t_status_filter ) : '' );
                    for( $i = 1; $i <= $t_total_pages; $i++ ) {
                        $t_active_class = ( $i === $t_page ) ? 'btn-primary' : 'btn-white';
                        echo '<a href="' . $t_base_url . '&page=' . $i . '" class="btn btn-xs ' . $t_active_class . '">' . $i . '</a> ';
                    }
                    ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js data (CSP uyumlu: inline script yok, data-* attribute ile aktarım) -->
<div id="pe-report-data"
     style="display:none;"
     data-dept-perf="<?php echo string_attribute( json_encode( $t_dept_perf ) ); ?>"
     data-step-stats="<?php echo string_attribute( json_encode( $t_step_stats ) ); ?>"
     data-monthly="<?php echo string_attribute( json_encode( $t_monthly ) ); ?>"
     data-sla-distribution="<?php echo string_attribute( json_encode( array(
         'normal'   => max( 0, $t_report['summary']['sla_compliance'] ),
         'warning'  => 0,
         'exceeded' => max( 0, 100 - $t_report['summary']['sla_compliance'] ),
     ) ) ); ?>"
     data-label-normal="<?php echo string_attribute( plugin_lang_get( 'sla_normal' ) ); ?>"
     data-label-warning="<?php echo string_attribute( plugin_lang_get( 'sla_warning' ) ); ?>"
     data-label-exceeded="<?php echo string_attribute( plugin_lang_get( 'sla_exceeded' ) ); ?>"
     data-label-avg-duration="<?php echo string_attribute( plugin_lang_get( 'report_summary_avg_duration' ) ); ?>"
     data-label-process-count="<?php echo string_attribute( plugin_lang_get( 'report_summary_total' ) ); ?>"
     data-label-sla-exceeded="<?php echo string_attribute( plugin_lang_get( 'sla_exceeded' ) ); ?>"
></div>

<?php
layout_page_end();
