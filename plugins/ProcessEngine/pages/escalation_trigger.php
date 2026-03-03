<?php
/**
 * ProcessEngine - Escalation Trigger (Bottleneck View)
 *
 * Shows steps that have the most SLA violations (bottlenecks).
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/sla_api.php' );

$t_bottlenecks = sla_get_bottlenecks();

layout_page_header( plugin_lang_get( 'bottlenecks' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-exclamation-triangle"></i>
                <?php echo plugin_lang_get( 'bottlenecks' ); ?>
            </h4>
            <div class="widget-toolbar">
                <a href="<?php echo plugin_page( 'sla_check' ); ?>" class="btn btn-sm btn-white">
                    <i class="fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'btn_back' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo plugin_lang_get( 'col_step' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_department' ); ?></th>
                                <th>SLA Aşım Sayısı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_bottlenecks ) ) { ?>
                            <tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td></tr>
                            <?php } else {
                                $i = 1;
                                foreach( $t_bottlenecks as $t_row ) {
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo string_display_line( $t_row['step_name'] ); ?></td>
                                <td><?php echo string_display_line( $t_row['department'] ); ?></td>
                                <td>
                                    <span class="pe-sla-badge pe-sla-exceeded">
                                        <?php echo (int)$t_row['exceeded_count']; ?>
                                    </span>
                                </td>
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

<?php
layout_page_end();
