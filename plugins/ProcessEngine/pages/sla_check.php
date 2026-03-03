<?php
/**
 * ProcessEngine - Manual SLA Check Page
 *
 * Allows admins to manually trigger SLA check and view active SLA trackings.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/sla_api.php' );

// Handle manual SLA check trigger
$t_action = gpc_get_string( 'action', '' );
$t_message = '';
if( $t_action === 'run_check' ) {
    form_security_validate( 'ProcessEngine_sla_check' );
    sla_run_check();
    form_security_purge( 'ProcessEngine_sla_check' );
    $t_message = 'SLA check completed.';
}

$t_trackings = sla_get_active_trackings();

layout_page_header( plugin_lang_get( 'sla_check' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <?php if( $t_message ) { ?>
    <div class="alert alert-success"><?php echo string_display_line( $t_message ); ?></div>
    <?php } ?>

    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-clock-o"></i>
                <?php echo plugin_lang_get( 'sla_dashboard' ); ?>
            </h4>
            <div class="widget-toolbar">
                <form method="post" action="<?php echo plugin_page( 'sla_check' ) . '&action=run_check'; ?>" style="display:inline;">
                    <?php echo form_security_field( 'ProcessEngine_sla_check' ); ?>
                    <button type="submit" class="btn btn-sm btn-warning btn-white">
                        <i class="fa fa-refresh"></i> <?php echo plugin_lang_get( 'sla_check' ); ?>
                    </button>
                </form>
                <a href="<?php echo plugin_page( 'escalation_trigger' ); ?>" class="btn btn-sm btn-danger btn-white">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo plugin_lang_get( 'bottlenecks' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th><?php echo plugin_lang_get( 'col_bug_id' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_department' ); ?></th>
                                <th>SLA (h)</th>
                                <th>Deadline</th>
                                <th><?php echo plugin_lang_get( 'col_sla_status' ); ?></th>
                                <th>Escalation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_trackings ) ) { ?>
                            <tr><td colspan="7" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td></tr>
                            <?php } else {
                                foreach( $t_trackings as $t_row ) {
                                    $t_sla_class = 'pe-sla-normal';
                                    if( $t_row['sla_status'] === 'WARNING' ) $t_sla_class = 'pe-sla-warning';
                                    elseif( $t_row['sla_status'] === 'EXCEEDED' ) $t_sla_class = 'pe-sla-exceeded';
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo string_get_bug_view_url( (int)$t_row['bug_id'] ); ?>">
                                        <?php echo bug_format_id( (int)$t_row['bug_id'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo string_display_line( $t_row['step_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['department'] ); ?></td>
                                <td><?php echo (int)$t_row['sla_hours']; ?></td>
                                <td><?php echo date( 'Y-m-d H:i', (int)$t_row['deadline_at'] ); ?></td>
                                <td><span class="pe-sla-badge <?php echo $t_sla_class; ?>"><?php echo $t_row['sla_status']; ?></span></td>
                                <td>Lv<?php echo (int)$t_row['escalation_level']; ?></td>
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

<script src="<?php echo plugin_file( 'sla_dashboard.js' ); ?>"></script>

<?php
layout_page_end();
