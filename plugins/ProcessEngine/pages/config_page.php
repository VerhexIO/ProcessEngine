<?php
/**
 * ProcessEngine - Config Page
 *
 * Plugin configuration form for access levels, SLA settings,
 * business hours, and working days.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();

$t_manage_threshold     = plugin_config_get( 'manage_threshold' );
$t_view_threshold       = plugin_config_get( 'view_threshold' );
$t_action_threshold     = plugin_config_get( 'action_threshold' );
$t_sla_warning_percent  = plugin_config_get( 'sla_warning_percent' );
$t_business_hours_start = plugin_config_get( 'business_hours_start' );
$t_business_hours_end   = plugin_config_get( 'business_hours_end' );
$t_working_days         = plugin_config_get( 'working_days' );
$t_departments          = plugin_config_get( 'departments', '' );

// Access levels for dropdown
$t_access_levels = MantisEnum::getAssocArrayIndexedByValues( config_get( 'access_levels_enum_string' ) );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

<?php
    $t_migrate_result = gpc_get_string( 'migrate', '' );
    $t_migrate_count  = gpc_get_int( 'count', 0 );
    $t_seed_result    = gpc_get_string( 'seed', '' );

    if( $t_migrate_result === 'ok' ) {
        echo '<div class="alert alert-success">'
            . sprintf( plugin_lang_get( 'migrate_success' ), $t_migrate_count )
            . '</div>';
    } elseif( $t_migrate_result === 'none' ) {
        echo '<div class="alert alert-info">'
            . plugin_lang_get( 'migrate_none' )
            . '</div>';
    }
    if( $t_seed_result === 'ok' ) {
        echo '<div class="alert alert-success">'
            . plugin_lang_get( 'seed_success' )
            . '</div>';
    }
?>

    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-cog"></i>
                <?php echo plugin_lang_get( 'config_title' ); ?>
            </h4>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <form method="post" action="<?php echo plugin_page( 'config_update' ); ?>">
                    <?php echo form_security_field( 'ProcessEngine_config_update' ); ?>

                    <table class="table table-bordered table-condensed">
                        <!-- Manage Threshold -->
                        <tr>
                            <td class="category" width="40%">
                                <?php echo plugin_lang_get( 'config_manage_threshold' ); ?>
                            </td>
                            <td>
                                <select name="manage_threshold" class="input-sm">
                                    <?php foreach( $t_access_levels as $t_val => $t_label ) { ?>
                                    <option value="<?php echo $t_val; ?>" <?php echo ( $t_val == $t_manage_threshold ) ? 'selected' : ''; ?>>
                                        <?php echo string_display_line( $t_label ); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>

                        <!-- View Threshold -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_view_threshold' ); ?>
                            </td>
                            <td>
                                <select name="view_threshold" class="input-sm">
                                    <?php foreach( $t_access_levels as $t_val => $t_label ) { ?>
                                    <option value="<?php echo $t_val; ?>" <?php echo ( $t_val == $t_view_threshold ) ? 'selected' : ''; ?>>
                                        <?php echo string_display_line( $t_label ); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>

                        <!-- Action Threshold -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_action_threshold' ); ?>
                                <br /><small><?php echo plugin_lang_get( 'config_action_threshold_help' ); ?></small>
                            </td>
                            <td>
                                <select name="action_threshold" class="input-sm">
                                    <?php foreach( $t_access_levels as $t_val => $t_label ) { ?>
                                    <option value="<?php echo $t_val; ?>" <?php echo ( $t_val == $t_action_threshold ) ? 'selected' : ''; ?>>
                                        <?php echo string_display_line( $t_label ); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>

                        <!-- SLA Warning Percent -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_sla_warning_percent' ); ?>
                            </td>
                            <td>
                                <input type="number" name="sla_warning_percent" class="input-sm"
                                       value="<?php echo (int) $t_sla_warning_percent; ?>"
                                       min="50" max="99" /> %
                            </td>
                        </tr>

                        <!-- Business Hours Start -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_business_hours_start' ); ?>
                            </td>
                            <td>
                                <input type="number" name="business_hours_start" class="input-sm"
                                       value="<?php echo (int) $t_business_hours_start; ?>"
                                       min="0" max="23" />
                            </td>
                        </tr>

                        <!-- Business Hours End -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_business_hours_end' ); ?>
                            </td>
                            <td>
                                <input type="number" name="business_hours_end" class="input-sm"
                                       value="<?php echo (int) $t_business_hours_end; ?>"
                                       min="0" max="23" />
                            </td>
                        </tr>

                        <!-- Working Days -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_working_days' ); ?>
                                <br /><small><?php echo plugin_lang_get( 'config_working_days_help' ); ?></small>
                            </td>
                            <td>
                                <input type="text" name="working_days" class="input-sm"
                                       value="<?php echo string_attribute( $t_working_days ); ?>"
                                       placeholder="1,2,3,4,5" />
                            </td>
                        </tr>

                        <!-- Departments -->
                        <tr>
                            <td class="category">
                                <?php echo plugin_lang_get( 'config_departments' ); ?>
                                <br /><small><?php echo plugin_lang_get( 'config_departments_help' ); ?></small>
                            </td>
                            <td>
                                <input type="text" name="departments" class="form-control input-sm"
                                       value="<?php echo string_attribute( $t_departments ); ?>"
                                       placeholder="Satış, Fiyatlandırma, ArGe, Kalite" />
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> <?php echo plugin_lang_get( 'btn_save' ); ?>
                        </button>
                    </div>
                </form>

                <hr />

                <!-- Seed Data + Navigation + Migration -->
                <div class="row">
                    <div class="col-md-3">
                        <a href="<?php echo plugin_page( 'flow_designer' ); ?>" class="btn btn-sm btn-info">
                            <i class="fa fa-sitemap"></i> <?php echo plugin_lang_get( 'menu_flow_designer' ); ?>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo plugin_page( 'sla_check' ); ?>" class="btn btn-sm btn-warning">
                            <i class="fa fa-clock-o"></i> <?php echo plugin_lang_get( 'sla_check' ); ?>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <form method="post" action="<?php echo plugin_page( 'config_update' ) . '&action=seed'; ?>">
                            <?php echo form_security_field( 'ProcessEngine_config_update' ); ?>
                            <button type="submit" class="btn btn-sm btn-default">
                                <i class="fa fa-database"></i> <?php echo plugin_lang_get( 'seed_data' ); ?>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-3">
                        <form method="post" action="<?php echo plugin_page( 'config_update' ) . '&action=migrate'; ?>">
                            <?php echo form_security_field( 'ProcessEngine_config_update' ); ?>
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fa fa-exchange"></i> <?php echo plugin_lang_get( 'migrate_data' ); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
layout_page_end();
