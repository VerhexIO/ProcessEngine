<?php
/**
 * ProcessEngine - Flow Designer Page
 *
 * No-code visual flow editor with SVG canvas, drag-drop nodes,
 * and transition drawing.
 */

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

require_once( dirname( __DIR__ ) . '/core/flow_api.php' );
require_once( dirname( __DIR__ ) . '/core/process_api.php' );

$t_flow_id = gpc_get_int( 'flow_id', 0 );
$t_action = gpc_get_string( 'action', 'list' );

// Handle new flow creation
if( $t_action === 'new' ) {
    $t_flow_id = flow_create( 'Yeni Akış' );
    print_header_redirect( plugin_page( 'flow_designer', true ) . '&flow_id=' . $t_flow_id );
}

// Handle unpublish
if( $t_action === 'unpublish' && $t_flow_id > 0 ) {
    form_security_validate( 'ProcessEngine_flow_unpublish' );
    $t_unpublished = flow_unpublish( $t_flow_id );
    form_security_purge( 'ProcessEngine_flow_unpublish' );
    if( $t_unpublished === 'has_instances' ) {
        layout_page_header( plugin_lang_get( 'flow_designer_title' ) );
        layout_page_begin();
        echo '<div class="alert alert-danger" style="margin: 20px;">'
            . plugin_lang_get( 'flow_unpublish_has_instances' )
            . '</div>';
        layout_page_end();
        exit;
    }
    print_header_redirect( plugin_page( 'flow_designer', true ) . '&flow_id=' . $t_flow_id );
}

// Handle delete
if( $t_action === 'delete' && $t_flow_id > 0 ) {
    form_security_validate( 'ProcessEngine_flow_delete' );
    $t_deleted = flow_delete( $t_flow_id );
    form_security_purge( 'ProcessEngine_flow_delete' );
    if( $t_deleted === false ) {
        layout_page_header( plugin_lang_get( 'flow_designer_title' ) );
        layout_page_begin();
        echo '<div class="alert alert-danger" style="margin: 20px;">'
            . plugin_lang_get( 'flow_delete_blocked' )
            . '</div>';
        layout_page_end();
        exit;
    }
    if( $t_deleted === 'referenced' ) {
        layout_page_header( plugin_lang_get( 'flow_designer_title' ) );
        layout_page_begin();
        echo '<div class="alert alert-danger" style="margin: 20px;">'
            . plugin_lang_get( 'flow_delete_referenced' )
            . '</div>';
        layout_page_end();
        exit;
    }
    print_header_redirect( plugin_page( 'flow_designer', true ) );
}

layout_page_header( plugin_lang_get( 'flow_designer_title' ) );
layout_page_begin();

// If no flow selected, show flow list
if( $t_flow_id === 0 ) {
    $t_flows = flow_get_all();
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'flow_list_title' ); ?>
            </h4>
            <div class="widget-toolbar">
                <a href="<?php echo plugin_page( 'flow_designer' ) . '&action=new'; ?>" class="btn btn-primary btn-white btn-sm">
                    <i class="fa fa-plus"></i> <?php echo plugin_lang_get( 'new_flow' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main no-padding">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo plugin_lang_get( 'col_flow_name' ); ?></th>
                                <th><?php echo plugin_lang_get( 'flow_project' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_status' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_updated' ); ?></th>
                                <th><?php echo plugin_lang_get( 'col_actions' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if( empty( $t_flows ) ) { ?>
                            <tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'no_data' ); ?></td></tr>
                            <?php } else {
                                foreach( $t_flows as $t_flow ) {
                                    $t_status_labels = array(
                                        0 => plugin_lang_get( 'flow_status_draft' ),
                                        1 => plugin_lang_get( 'flow_status_pending' ),
                                        2 => plugin_lang_get( 'flow_status_active' ),
                                    );
                                    $t_status_label = isset( $t_status_labels[(int)$t_flow['status']] )
                                        ? $t_status_labels[(int)$t_flow['status']]
                                        : $t_flow['status'];
                                    $t_status_class = '';
                                    if( (int)$t_flow['status'] === 2 ) $t_status_class = 'label-success';
                                    elseif( (int)$t_flow['status'] === 1 ) $t_status_class = 'label-warning';
                                    else $t_status_class = 'label-default';
                            ?>
                            <tr>
                                <td><?php echo (int)$t_flow['id']; ?></td>
                                <td>
                                    <a href="<?php echo plugin_page( 'flow_designer' ) . '&flow_id=' . (int)$t_flow['id']; ?>">
                                        <?php echo string_display_line( $t_flow['name'] ); ?>
                                    </a>
                                </td>
                                <td><?php
                                    $t_proj_id = (int) $t_flow['project_id'];
                                    if( $t_proj_id > 0 && project_exists( $t_proj_id ) ) {
                                        echo string_display_line( project_get_name( $t_proj_id ) );
                                    } else {
                                        echo plugin_lang_get( 'all_projects' );
                                    }
                                ?></td>
                                <td><span class="label <?php echo $t_status_class; ?>"><?php echo $t_status_label; ?></span></td>
                                <td><?php echo $t_flow['updated_at'] ? date( 'Y-m-d H:i', $t_flow['updated_at'] ) : '-'; ?></td>
                                <td>
                                    <?php if( (int) $t_flow['status'] === 2 ) { ?>
                                    <a href="<?php echo plugin_page( 'flow_designer' ) . '&flow_id=' . (int)$t_flow['id']; ?>" class="btn btn-xs btn-info">
                                        <i class="fa fa-eye"></i> <?php echo plugin_lang_get( 'btn_view' ); ?>
                                    </a>
                                    <form method="post" action="<?php echo plugin_page( 'flow_designer' ) . '&action=unpublish&flow_id=' . (int)$t_flow['id']; ?>" style="display:inline;">
                                        <?php echo form_security_field( 'ProcessEngine_flow_unpublish' ); ?>
                                        <button type="submit" class="btn btn-xs btn-warning" onclick="return confirm('<?php echo plugin_lang_get( 'flow_unpublish_confirm' ); ?>');">
                                            <i class="fa fa-pause"></i> <?php echo plugin_lang_get( 'btn_unpublish' ); ?>
                                        </button>
                                    </form>
                                    <?php } else { ?>
                                    <a href="<?php echo plugin_page( 'flow_designer' ) . '&flow_id=' . (int)$t_flow['id']; ?>" class="btn btn-xs btn-primary">
                                        <i class="fa fa-pencil"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
                                    </a>
                                    <?php } ?>
                                    <form method="post" action="<?php echo plugin_page( 'flow_designer' ) . '&action=delete&flow_id=' . (int)$t_flow['id']; ?>" style="display:inline;">
                                        <?php echo form_security_field( 'ProcessEngine_flow_delete' ); ?>
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?php echo plugin_lang_get( 'confirm_delete' ); ?>');">
                                            <i class="fa fa-trash"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
                                        </button>
                                    </form>
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
} else {
    // Flow Designer Canvas
    $t_flow = flow_get( $t_flow_id );
    if( $t_flow === null ) {
        echo '<div class="alert alert-danger">Flow not found.</div>';
        layout_page_end();
        return;
    }
    $t_steps = flow_get_steps( $t_flow_id );
    $t_transitions = flow_get_transitions( $t_flow_id );

    // Her adıma subprocess_targets verisini ekle
    foreach( $t_steps as &$t_s ) {
        $t_s['subprocess_targets'] = flow_get_subprocess_targets( (int) $t_s['id'] );
    }
    unset( $t_s );

    // MantisBT status enum for dropdown
    $t_status_enum = MantisEnum::getAssocArrayIndexedByValues( config_get( 'status_enum_string' ) );
?>
<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>

    <!-- Flow Header -->
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-sitemap"></i>
                <?php echo plugin_lang_get( 'flow_designer_title' ); ?>:
                <span id="pe-flow-name-display"><?php echo string_display_line( $t_flow['name'] ); ?></span>
            </h4>
            <div class="widget-toolbar">
                <a href="<?php echo plugin_page( 'flow_designer' ); ?>" class="btn btn-sm btn-white">
                    <i class="fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'btn_back' ); ?>
                </a>
            </div>
        </div>
        <div class="widget-body">
            <div class="widget-main">
                <?php $t_is_active = ( (int) $t_flow['status'] === FLOW_STATUS_ACTIVE ); ?>
                <!-- Flow metadata -->
                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-3">
                        <label><?php echo plugin_lang_get( 'flow_name' ); ?></label>
                        <input type="text" id="pe-flow-name" class="form-control input-sm" value="<?php echo string_attribute( $t_flow['name'] ); ?>" <?php echo $t_is_active ? 'readonly' : ''; ?> />
                    </div>
                    <div class="col-md-1">
                        <label><?php echo plugin_lang_get( 'flow_project' ); ?></label>
                        <select id="pe-flow-project" class="form-control input-sm" <?php echo $t_is_active ? 'disabled' : ''; ?>>
                            <option value="0"><?php echo plugin_lang_get( 'all_projects' ); ?></option>
                            <?php
                            $t_projects = project_get_all_rows();
                            foreach( $t_projects as $t_proj ) { ?>
                            <option value="<?php echo (int) $t_proj['id']; ?>"
                                <?php echo ( (int) $t_flow['project_id'] === (int) $t_proj['id'] ) ? 'selected' : ''; ?>>
                                <?php echo string_display_line( $t_proj['name'] ); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2" style="padding-top: 22px;">
                        <span class="label <?php
                            if((int)$t_flow['status'] === 2) echo 'label-success';
                            elseif((int)$t_flow['status'] === 1) echo 'label-warning';
                            else echo 'label-default';
                        ?>"><?php
                            $t_sl = array(
                                0 => plugin_lang_get( 'flow_status_draft' ),
                                1 => plugin_lang_get( 'flow_status_pending' ),
                                2 => plugin_lang_get( 'flow_status_active' ),
                            );
                            echo isset($t_sl[(int)$t_flow['status']]) ? $t_sl[(int)$t_flow['status']] : $t_flow['status'];
                        ?></span>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-12">
                        <label><?php echo plugin_lang_get( 'flow_description' ); ?></label>
                        <textarea id="pe-flow-desc" class="form-control" rows="3" placeholder="<?php echo plugin_lang_get( 'flow_description_placeholder' ); ?>" <?php echo $t_is_active ? 'readonly' : ''; ?>><?php echo string_attribute( $t_flow['description'] ); ?></textarea>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="btn-toolbar" style="margin-bottom: 10px;">
                    <?php if( (int) $t_flow['status'] === FLOW_STATUS_ACTIVE ) { ?>
                    <div class="alert alert-info" style="display:inline-block; margin:0; padding:6px 12px;">
                        <i class="fa fa-lock"></i> <?php echo plugin_lang_get( 'flow_readonly_notice' ); ?>
                    </div>
                    <form method="post" action="<?php echo plugin_page( 'flow_designer' ) . '&action=unpublish&flow_id=' . (int) $t_flow['id']; ?>" style="display:inline;">
                        <?php echo form_security_field( 'ProcessEngine_flow_unpublish' ); ?>
                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('<?php echo plugin_lang_get( 'flow_unpublish_confirm' ); ?>');">
                            <i class="fa fa-pause"></i> <?php echo plugin_lang_get( 'btn_unpublish' ); ?>
                        </button>
                    </form>
                    <?php } else { ?>
                    <button id="pe-btn-add-step" class="btn btn-sm btn-success">
                        <i class="fa fa-plus"></i> <?php echo plugin_lang_get( 'btn_add_step' ); ?>
                    </button>
                    <button id="pe-btn-save" class="btn btn-sm btn-primary">
                        <i class="fa fa-save"></i> <?php echo plugin_lang_get( 'btn_save' ); ?>
                    </button>
                    <button id="pe-btn-validate" class="btn btn-sm btn-warning">
                        <i class="fa fa-check-circle"></i> <?php echo plugin_lang_get( 'btn_validate' ); ?>
                    </button>
                    <button id="pe-btn-publish" class="btn btn-sm btn-danger">
                        <i class="fa fa-rocket"></i> <?php echo plugin_lang_get( 'btn_publish' ); ?>
                    </button>
                    <?php } ?>
                    <span id="pe-status-msg" class="label" style="margin-left: 10px; display: none;"></span>
                </div>

                <!-- SVG Canvas -->
                <div id="pe-canvas-wrapper" style="border: 1px solid #ddd; background: #f9f9f9; overflow: auto; position: relative; height: 500px;">
                    <svg id="pe-canvas" width="2000" height="1000" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                                <polygon points="0 0, 10 3.5, 0 7" fill="#666" />
                            </marker>
                        </defs>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step Edit Modal -->
<div id="pe-step-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo plugin_lang_get( 'btn_edit' ); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pe-modal-step-id" />
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_name' ); ?></label>
                    <input type="text" id="pe-modal-name" class="form-control input-sm" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_department' ); ?></label>
                    <select id="pe-modal-department" class="form-control input-sm">
                        <option value="">--</option>
                        <?php
                        $t_dept_list = process_get_departments();
                        foreach( $t_dept_list as $t_dept ) { ?>
                        <option value="<?php echo string_attribute( $t_dept ); ?>"><?php echo string_display_line( $t_dept ); ?></option>
                        <?php } ?>
                        <option value="__other__"><?php echo plugin_lang_get( 'dept_other' ); ?></option>
                    </select>
                    <input type="text" id="pe-modal-department-custom" class="form-control input-sm" style="display:none; margin-top:5px;" placeholder="<?php echo plugin_lang_get( 'dept_custom_placeholder' ); ?>" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_sla_hours' ); ?></label>
                    <input type="number" id="pe-modal-sla" class="form-control input-sm" min="0" value="0" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_role' ); ?></label>
                    <select id="pe-modal-role" class="form-control input-sm">
                        <option value="">--</option>
                        <option value="reporter">Reporter</option>
                        <option value="updater">Updater</option>
                        <option value="developer">Developer</option>
                        <option value="manager">Manager</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_mantis_status' ); ?></label>
                    <select id="pe-modal-mantis-status" class="form-control input-sm">
                        <?php foreach( $t_status_enum as $t_val => $t_label ) { ?>
                        <option value="<?php echo $t_val; ?>"><?php echo string_display_line( $t_label ); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_handler' ); ?></label>
                    <select id="pe-modal-handler" class="form-control input-sm">
                        <option value="0"><?php echo plugin_lang_get( 'no_auto_assign' ); ?></option>
                        <?php
                        $t_project_id_for_users = (int) $t_flow['project_id'];
                        if( $t_project_id_for_users > 0 ) {
                            $t_users = project_get_all_user_rows( $t_project_id_for_users );
                        } else {
                            $t_users = project_get_all_user_rows( ALL_PROJECTS );
                        }
                        foreach( $t_users as $t_user ) {
                            $t_uid = (int) $t_user['id'];
                            $t_uname = user_get_name( $t_uid );
                        ?>
                        <option value="<?php echo $t_uid; ?>"><?php echo string_display_line( $t_uname ); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <hr style="margin: 10px 0;" />

                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_type' ); ?></label>
                    <select id="pe-modal-step-type" class="form-control input-sm">
                        <option value="normal"><?php echo plugin_lang_get( 'step_type_normal' ); ?></option>
                        <option value="subprocess"><?php echo plugin_lang_get( 'step_type_subprocess' ); ?></option>
                    </select>
                </div>
                <div id="pe-subprocess-fields" style="display:none;">
                    <div class="form-group">
                        <label><?php echo plugin_lang_get( 'subprocess_targets' ); ?></label>
                        <div id="pe-subprocess-targets-list"></div>
                        <button type="button" id="pe-btn-add-target" class="btn btn-xs btn-success" style="margin-top:5px;">
                            <i class="fa fa-plus"></i> <?php echo plugin_lang_get( 'add_target' ); ?>
                        </button>
                    </div>
                    <div class="form-group" id="pe-legacy-subprocess-fields" style="display:none;">
                        <label><?php echo plugin_lang_get( 'child_flow' ); ?></label>
                        <select id="pe-modal-child-flow" class="form-control input-sm">
                            <option value="0">--</option>
                            <?php
                            $t_all_flows = flow_get_all();
                            foreach( $t_all_flows as $t_af ) {
                                if( (int) $t_af['id'] === $t_flow_id ) continue;
                            ?>
                            <option value="<?php echo (int) $t_af['id']; ?>"><?php echo string_display_line( $t_af['name'] ); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group" id="pe-legacy-subprocess-project" style="display:none;">
                        <label><?php echo plugin_lang_get( 'child_project' ); ?></label>
                        <select id="pe-modal-child-project" class="form-control input-sm">
                            <option value="0"><?php echo plugin_lang_get( 'all_projects' ); ?></option>
                            <?php foreach( $t_projects as $t_proj ) { ?>
                            <option value="<?php echo (int) $t_proj['id']; ?>"><?php echo string_display_line( $t_proj['name'] ); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo plugin_lang_get( 'wait_mode' ); ?></label>
                        <select id="pe-modal-wait-mode" class="form-control input-sm">
                            <option value="all"><?php echo plugin_lang_get( 'wait_mode_all' ); ?></option>
                            <option value="any"><?php echo plugin_lang_get( 'wait_mode_any' ); ?></option>
                        </select>
                    </div>
                </div>
                <!-- Subprocess hedef şablonları (JS tarafından kullanılır) -->
                <script type="text/template" id="pe-target-row-template">
                    <div class="pe-target-row" style="border:1px solid #ddd; padding:5px; margin-bottom:5px; border-radius:3px; background:#f9f9f9;">
                        <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                            <input type="text" class="form-control input-sm pe-target-label" placeholder="<?php echo string_attribute( plugin_lang_get( 'target_label_placeholder' ) ); ?>" style="width:120px;" />
                            <select class="form-control input-sm pe-target-flow" style="width:180px;">
                                <option value="0">-- <?php echo plugin_lang_get( 'child_flow' ); ?> --</option>
                                <?php foreach( $t_all_flows as $t_af ) {
                                    if( (int) $t_af['id'] === $t_flow_id ) continue;
                                ?>
                                <option value="<?php echo (int) $t_af['id']; ?>"><?php echo string_display_line( $t_af['name'] ); ?></option>
                                <?php } ?>
                            </select>
                            <select class="form-control input-sm pe-target-project" style="width:150px;">
                                <option value="0"><?php echo plugin_lang_get( 'all_projects' ); ?></option>
                                <?php foreach( $t_projects as $t_proj ) { ?>
                                <option value="<?php echo (int) $t_proj['id']; ?>"><?php echo string_display_line( $t_proj['name'] ); ?></option>
                                <?php } ?>
                            </select>
                            <button type="button" class="btn btn-xs btn-danger pe-target-remove"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>
                </script>

                <hr style="margin: 10px 0;" />

                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'start_trigger' ); ?></label>
                    <select id="pe-modal-start-trigger" class="form-control input-sm">
                        <option value="auto"><?php echo plugin_lang_get( 'start_trigger_auto' ); ?></option>
                        <option value="on_assign"><?php echo plugin_lang_get( 'start_trigger_on_assign' ); ?></option>
                        <option value="manual"><?php echo plugin_lang_get( 'start_trigger_manual' ); ?></option>
                        <option value="on_create"><?php echo plugin_lang_get( 'start_trigger_on_create' ); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'completion_criteria' ); ?></label>
                    <select id="pe-modal-completion-criteria" class="form-control input-sm">
                        <option value="manual"><?php echo plugin_lang_get( 'completion_manual' ); ?></option>
                        <option value="on_status"><?php echo plugin_lang_get( 'completion_on_status' ); ?></option>
                        <option value="on_resolve"><?php echo plugin_lang_get( 'completion_on_resolve' ); ?></option>
                    </select>
                </div>
                <div class="form-group" id="pe-completion-status-group" style="display:none;">
                    <label><?php echo plugin_lang_get( 'completion_status' ); ?></label>
                    <select id="pe-modal-completion-status" class="form-control input-sm">
                        <option value="0">--</option>
                        <?php foreach( $t_status_enum as $t_val => $t_label ) { ?>
                        <option value="<?php echo $t_val; ?>"><?php echo string_display_line( $t_label ); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'step_instructions_label' ); ?></label>
                    <textarea id="pe-modal-step-instructions" class="form-control input-sm" rows="3"
                        placeholder="<?php echo plugin_lang_get( 'step_instructions_placeholder' ); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-default" data-dismiss="modal"><?php echo plugin_lang_get( 'btn_cancel' ); ?></button>
                <button type="button" id="pe-modal-save" class="btn btn-sm btn-primary"><?php echo plugin_lang_get( 'btn_save' ); ?></button>
                <button type="button" id="pe-modal-delete" class="btn btn-sm btn-danger"><?php echo plugin_lang_get( 'btn_delete' ); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Transition Edit Modal -->
<div id="pe-transition-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo plugin_lang_get( 'transition_label' ); ?></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pe-trans-modal-id" />
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'transition_label' ); ?></label>
                    <input type="text" id="pe-trans-modal-label" class="form-control input-sm" />
                </div>
                <div class="form-group">
                    <label><?php echo plugin_lang_get( 'condition_type' ); ?></label>
                    <select id="pe-trans-modal-condition-type" class="form-control input-sm">
                        <option value=""><?php echo plugin_lang_get( 'condition_none' ); ?></option>
                        <option value="field_equals"><?php echo plugin_lang_get( 'condition_field_equals' ); ?></option>
                        <option value="status_is"><?php echo plugin_lang_get( 'condition_status_is' ); ?></option>
                    </select>
                </div>
                <div id="pe-trans-condition-fields" style="display:none;">
                    <div class="form-group">
                        <label><?php echo plugin_lang_get( 'condition_field_label' ); ?></label>
                        <input type="text" id="pe-trans-modal-cond-field" class="form-control input-sm" placeholder="priority, severity..." />
                    </div>
                    <div class="form-group">
                        <label><?php echo plugin_lang_get( 'condition_value_label' ); ?></label>
                        <input type="text" id="pe-trans-modal-cond-value" class="form-control input-sm" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-default" data-dismiss="modal"><?php echo plugin_lang_get( 'btn_cancel' ); ?></button>
                <button type="button" id="pe-trans-modal-save" class="btn btn-sm btn-primary"><?php echo plugin_lang_get( 'btn_save' ); ?></button>
                <button type="button" id="pe-trans-modal-delete" class="btn btn-sm btn-danger"><?php echo plugin_lang_get( 'btn_delete' ); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF token for AJAX requests -->
<input type="hidden" id="pe-csrf-token" value="<?php echo form_security_token( 'ProcessEngine_flow_editor' ); ?>" />

<!-- Pass data to JS via data attributes (CSP-safe, no inline script) -->
<div id="pe-config"
     data-flow-id="<?php echo (int) $t_flow_id; ?>"
     data-save-url="<?php echo string_attribute( plugin_page( 'flow_save' ) ); ?>"
     data-validate-url="<?php echo string_attribute( plugin_page( 'flow_validate' ) ); ?>"
     data-publish-url="<?php echo string_attribute( plugin_page( 'flow_publish' ) ); ?>"
     data-project-id="<?php echo (int) $t_flow['project_id']; ?>"
     data-steps="<?php echo string_attribute( json_encode( $t_steps ) ); ?>"
     data-transitions="<?php echo string_attribute( json_encode( $t_transitions ) ); ?>"
     style="display:none;"></div>
<link rel="stylesheet" href="<?php echo plugin_file( 'flow_designer.css' ); ?>" />
<script src="<?php echo plugin_file( 'flow_designer.js' ); ?>"></script>

<?php
}
layout_page_end();
