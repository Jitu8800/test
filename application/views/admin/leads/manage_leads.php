<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
#scrollToggle {
    position: fixed;
    right: 24px;
    bottom: 64px;
    width: 30px;
    height: 30px;
    background: #007bff;
    color: #fff;
    font-size: 22px;
    text-align: center;
    line-height: 32px;
    border-radius: 50%;
    cursor: pointer;
    z-index: 9999;
    font-weight: 700;
}

.buttons-excel {
    display: none !important;
}
</style>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="_buttons tw-mb-2 sm:tw-mb-4">
                    <a href="#" onclick="init_lead(); return false;"
                        class="btn btn-primary mright5 pull-left display-block">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('new_lead'); ?>
                    </a>
                    <?php if (is_admin() || get_option('allow_non_admin_members_to_import_leads') == '1') { ?>
                    <a href="<?php echo admin_url('leads/import'); ?>"
                        class="btn btn-primary pull-left display-block hidden-xs">
                        <i class="fa-solid fa-upload tw-mr-1"></i>
                        <?php echo _l('import_leads'); ?>
                    </a>
                    <?php } ?>
                    <div class="row">

                        <div class="col-sm-5 ">
                            <a href="#" class="btn btn-default btn-with-tooltip" data-toggle="tooltip"
                                data-title="<?php echo _l('leads_summary'); ?>" data-placement="top"
                                onclick="slideToggle('.leads-overview'); return false;">
                                <i class="fa fa-bar-chart"></i>
                            </a>
                            <?php $role = isset($role) ? $role : (isset($staff) && isset($staff->role) ? $staff->role : ''); ?>


                            <?php if ($role === '' || $role === null) { ?>
                            <!-- Toggle shown only when role is empty -->
                            <a href="<?php echo admin_url('leads/switch_kanban/'.($isKanBan ? 0 : 1)); ?>"
                                class="btn btn-default mleft5 hidden-xs" data-toggle="tooltip" data-placement="top"
                                data-title="<?php echo $isKanBan ? _l('switch_to_list_view') : _l('leads_switch_to_kanban'); ?>">
                                <?php if ($isKanBan) { ?>
                                <i class="fa-solid fa-grip-vertical"></i>
                                <?php } else { ?>
                                <i class="fa-solid fa-table-list"></i>
                                <?php } ?>
                            </a>
                            <?php } ?>
                        </div>

                        <div class="col-sm-4 col-xs-12 pull-right leads-search">
                            <?php if ($this->session->userdata('leads_kanban_view') == 'true') { ?>
                            <div data-toggle="tooltip" data-placement="top"
                                data-title="<?php echo _l('search_by_tags'); ?>">
                                <?php echo render_input('search', '', '', 'search', ['data-name' => 'search', 'onkeyup' => 'leads_kanban();', 'placeholder' => _l('leads_search')], [], 'no-margin'); ?>
                            </div>
                            <?php } ?>
                            <?php echo form_hidden('sort_type'); ?>
                            <?php echo form_hidden('sort', get_option('default_leads_kanban_sort') != '' ? get_option('default_leads_kanban_sort_type') : ''); ?>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                    <div class="hide leads-overview tw-mt-2 sm:tw-mt-4 tw-mb-4 sm:tw-mb-0">
                        <h4 class="tw-mt-0 tw-font-semibold tw-text-lg">
                            <?php echo _l('leads_summary'); ?>
                        </h4>
                        <div class="tw-flex tw-flex-wrap tw-flex-col lg:tw-flex-row tw-w-full tw-gap-3 lg:tw-gap-6">
                            <?php
                           foreach ($summary as $status) { ?>
                            <div
                                class="lg:tw-border-r lg:tw-border-solid lg:tw-border-neutral-300 tw-flex-1 tw-flex tw-items-center last:tw-border-r-0">
                                <span class="tw-font-semibold tw-mr-3 rtl:tw-ml-3 tw-text-lg">
                                    <?php
                                          if (isset($status['percent'])) {
                                              echo '<span data-toggle="tooltip" data-title="'.$status['total'].'">'.$status['percent'].'%</span>';
                                          } else {
                                              // Is regular status
                                              echo $status['total'] ?? '';
                                          }
                               ?>
                                </span>
                                <span style="color:<?php echo $status['color']; ?>"
                                    class="<?php echo isset($status['junk']) || isset($status['lost']) ? 'text-danger' : ''; ?>">
                                    <?php echo $status['name']; ?>
                                </span>
                            </div>
                            <?php } ?>
                        </div>

                    </div>
                </div>
                <div class="<?php echo $isKanBan ? '' : 'panel_s'; ?>">
                    <div class="<?php echo $isKanBan ? '' : 'panel-body'; ?>">
                        <div class="tab-content">
                            <?php
                        if ($isKanBan) { ?>
                            <div class="active kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                                <div class="kanban-leads-sort">
                                    <span class="bold"><?php echo _l('leads_sort_by'); ?>: </span>
                                    <a href="#" onclick="leads_kanban_sort('dateadded'); return false"
                                        class="dateadded">
                                        <?php if (get_option('default_leads_kanban_sort') == 'dateadded') {
                                            echo '<i class="kanban-sort-icon fa fa-sort-amount-'.strtolower(get_option('default_leads_kanban_sort_type')).'"></i> ';
                                        } ?><?php echo _l('leads_sort_by_datecreated'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="leads_kanban_sort('leadorder');return false;"
                                        class="leadorder">
                                        <?php if (get_option('default_leads_kanban_sort') == 'leadorder') {
                                            echo '<i class="kanban-sort-icon fa fa-sort-amount-'.strtolower(get_option('default_leads_kanban_sort_type')).'"></i> ';
                                        } ?><?php echo _l('leads_sort_by_kanban_order'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="leads_kanban_sort('lastcontact');return false;"
                                        class="lastcontact">
                                        <?php if (get_option('default_leads_kanban_sort') == 'lastcontact') {
                                            echo '<i class="kanban-sort-icon fa fa-sort-amount-'.strtolower(get_option('default_leads_kanban_sort_type')).'"></i> ';
                                        } ?><?php echo _l('leads_sort_by_lastcontact'); ?>
                                    </a>
                                </div>
                                <div class="row">
                                    <div class="container-fluid leads-kan-ban">
                                        <div id="kan-ban"></div>
                                    </div>
                                </div>
                            </div>
                            <?php } else { ?>
                            <div class="row" id="leads-table">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <p class="bold"><?php echo _l('filter_by'); ?></p>
                                        </div>
                                        <?php if (has_permission('leads', '', 'view')) { ?>
                                        <div class="col-md-3 leads-filter-column">

                                            <?php
$selected = [];

                                           if ($this->input->get('view_assigned')) {
                                               $selected = $this->input->get('view_assigned');
                                           }

                                           echo '<div id="leads-filter-assigned">';

                                           echo render_select(
                                               'view_assigned[]',
                                               $staff,
                                               ['staffid', ['firstname', 'lastname']],
                                               '',
                                               $selected,
                                               [
                                                   'data-width' => '100%',
                                                   'data-none-selected-text' => _l('leads_dt_assigned'),
                                                   'multiple' => true,
                                                   'data-actions-box' => true,
                                               ],
                                               [],
                                               'no-mbot',
                                               '',
                                               false
                                           );

                                           echo '</div>';
                                           ?>

                                        </div>
                                        <?php } ?>
                                        <div class="col-md-3 leads-filter-column">
                                            <?php
                                                                                               $selected = [];
                                if ($this->input->get('status')) {
                                    $selected[] = $this->input->get('status');
                                } else {
                                    array_unshift($statuses, [
                                        'id'         => '0',
                                        'name'       => 'Unassigned',
                                        'isdefault'  => 0,
                                        'color'      => '#999999',
                                    ]);
                                    $selected = [];

                                    foreach ($statuses as $key => $status) {

                                        if ($status['isdefault'] == 0) {
                                            $selected[] = (int) $status['id'];
                                        } else {
                                            $statuses[$key]['option_attributes'] = [
                                                'data-subtext' => _l('leads_converted_to_client')
                                            ];
                                        }
                                    }
                                }
                                echo '<div id="leads-filter-status">';
                                echo render_select('view_status[]', $statuses, ['id', 'name'], '', $selected, ['data-width' => '100%', 'data-none-selected-text' => _l('leads_all'), 'multiple' => true, 'data-actions-box' => true], [], 'no-mbot', '', false);
                                echo '</div>';
                                ?>
                                        </div>


                                        <!-- Sub Status Filter -->
                                        <div class="col-md-3 leads-filter-column">
                                            <?php
                                $selected = [];
                                echo '<div id="leads-filter-substatus">';

                                echo render_select(
                                    'sub_status[]',
                                    $this->leads_model->get_sub_statuses(),
                                    ['id', 'sub_name'],
                                    '',
                                    $selected,
                                    [
                                        'data-width' => '100%',
                                        'multiple' => true,
                                        'data-actions-box' => true,
                                    ],
                                    [],
                                    'no-mbot',
                                    '',
                                    false
                                );

                                echo '</div>';
                                ?>
                                        </div>



                                        <div class="col-md-3 leads-filter-column">
                                            <?php
                                    echo render_select('view_source', $sources, ['id', 'name'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('leads_source')], [], 'no-mbot');
                                ?>
                                        </div>
                                        <div class="col-md-3 leads-filter-column" style="margin-top:20px">
                                            <div class="select-placeholder">
                                                <select name="custom_view"
                                                    title="<?php echo _l('additional_filters'); ?>" id="custom_view"
                                                    class="selectpicker" data-width="100%">
                                                    <option value=""></option>
                                                    <option value="lost"><?php echo _l('lead_lost'); ?></option>
                                                    <option value="junk"><?php echo _l('lead_junk'); ?></option>
                                                    <option value="public"><?php echo _l('lead_public'); ?></option>
                                                    <option value="contacted_today">
                                                        <?php echo _l('lead_add_edit_contacted_today'); ?></option>
                                                    <option value="created_today"><?php echo _l('created_today'); ?>
                                                    </option>
                                                    <?php if (has_permission('leads', '', 'edit')) { ?>
                                                    <option value="not_assigned"><?php echo _l('leads_not_assigned'); ?>
                                                    </option>
                                                    <?php } ?>
                                                    <?php if (isset($consent_purposes)) { ?>
                                                    <optgroup label="<?php echo _l('gdpr_consent'); ?>">
                                                        <?php foreach ($consent_purposes as $purpose) { ?>
                                                        <option value="consent_<?php echo $purpose['id']; ?>">
                                                            <?php echo $purpose['name']; ?>
                                                        </option>
                                                        <?php } ?>
                                                    </optgroup>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-3 leads-filter-column" style="margin-top:20px">
                                            <?php
                                            $card_status = [
                                                ['id' => 'Approved', 'label' => 'Approved'],
                                                ['id' => 'Pending', 'label' => 'Pending'],
                                                ['id' => 'Declined', 'label' => 'Declined'],
                                                ['id' => 'Processing', 'label' => 'Processing'],
                                                ['id' => 'Shipped', 'label' => 'Shipped'],
                                                ['id' => 'Delivered', 'label' => 'Delivered'],
                                                ['id' => 'Canceled', 'label' => 'Canceled'],
                                            ];
                                echo render_select('card_status', $card_status, ['id', 'label'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('Card Status')], [], 'no-mbot');
                                ?>
                                        </div>


                                        <div class="col-md-3 leads-filter-column" style="margin-top:20px">
                                            <?php
                                 $payment_mode = [
                                     ['id' => 'Cash', 'label' => 'Cash'],
                                     ['id' => 'Bank_Transfer', 'label' => 'Bank Transfer'],
                                     ['id' => 'Cheque', 'label' => 'Cheque'],
                                     ['id' => 'Credit_Card', 'label' => 'Credit Card'],
                                     ['id' => 'Debit_Card', 'label' => 'Debit Card'],
                                     ['id' => 'PayPal', 'label' => 'PayPal'],
                                     ['id' => 'Stripe', 'label' => 'Stripe'],
                                     ['id' => 'Apple_Pay', 'label' => 'Apple Pay'],
                                     ['id' => 'Google_Pay', 'label' => 'Google Pay'],
                                     ['id' => 'Cryptocurrency', 'label' => 'Cryptocurrency'],
                                     ['id' => 'Gift_Card', 'label' => 'Gift Card'],
                                     ['id' => 'Net_Banking', 'label' => 'Net Banking'],
                                     ['id' => 'COD_C3X', 'label' => 'COD C3X'],
                                 ];
                                echo render_select('payment_mode', $payment_mode, ['id', 'label'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('Payment Mode')], [], 'no-mbot');
                                ?>
                                        </div>


                                        <div class="col-md-3 leads-filter-column" style="margin-top:20px">
                                            <div class="date-range-placeholder">
                                                <input type="text" name="date_range" id="date_range"
                                                    title="<?php echo _l('filter_by_date_range'); ?>"
                                                    class="form-control" placeholder="Select Date Range">
                                            </div>
                                        </div>


                                    </div>

                                    <hr class="hr-panel-separator" />




                                    <div class="col-md-12" style="margin-top:15px;">
                                        <button id="startDialer" class="btn btn-success"
                                            style="float:right;margin:1rem;">Start Calling</button>

                                        <button id="pauseDialer" class="btn btn-warning"
                                            style="display:none;float:right;margin:1rem;">
                                            Pause
                                        </button>

                                        <button id="resumeDialer" class="btn btn-primary"
                                            style="display:none;float:right;margin:1rem;">
                                            Resume
                                        </button>

                                        <div id="dialerStatus" style="display:none;margin-top:20px; font-weight:bold;">
                                        </div>
                                    </div>

                                    <div id="dialerConfig" data-staff-id="<?php echo get_staff_user_id(); ?>"
                                        data-base-url="<?php echo base_url(); ?>"></div>




                                    <hr class="hr-panel-separator" />
                                </div>
                                <div class="clearfix"></div>

                                <div class="col-md-12">
                                    <a href="#" data-toggle="modal" data-table=".table-leads"
                                        data-target="#leads_bulk_actions"
                                        class="hide bulk-actions-btn table-btn"><?php echo _l('bulk_actions'); ?></a>
                                    <div class="modal fade bulk_actions" id="leads_bulk_actions" tabindex="-1"
                                        role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close"><span
                                                            aria-hidden="true">&times;</span></button>
                                                    <h4 class="modal-title"><?php echo _l('bulk_actions'); ?></h4>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if (has_permission('leads', '', 'delete')) { ?>
                                                    <div class="checkbox checkbox-danger">
                                                        <input type="checkbox" name="mass_delete" id="mass_delete">
                                                        <label
                                                            for="mass_delete"><?php echo _l('mass_delete'); ?></label>
                                                    </div>
                                                    <hr class="mass_delete_separator" />
                                                    <?php } ?>
                                                    <div id="bulk_change">
                                                        <div class="form-group">
                                                            <div class="checkbox checkbox-primary checkbox-inline">
                                                                <input type="checkbox" name="leads_bulk_mark_lost"
                                                                    id="leads_bulk_mark_lost" value="1">
                                                                <label for="leads_bulk_mark_lost">
                                                                    <?php echo _l('lead_mark_as_lost'); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <?php echo render_select('move_to_status_leads_bulk', $statuses, ['id', 'name'], 'ticket_single_change_status'); ?>
                                                        <?php
                                             echo render_select('move_to_source_leads_bulk', $sources, ['id', 'name'], 'lead_source');
                                echo render_datetime_input('leads_bulk_last_contact', 'leads_dt_last_contact');
                                echo render_select('assign_to_leads_bulk', $staff, ['staffid', ['firstname', 'lastname']], 'leads_dt_assigned');
                                ?>
                                                        <div class="form-group">
                                                            <?php echo '<p><b><i class="fa fa-tag" aria-hidden="true"></i> '._l('tags').':</b></p>'; ?>
                                                            <input type="text" class="tagsinput" id="tags_bulk"
                                                                name="tags_bulk" value="" data-role="tagsinput">
                                                        </div>
                                                        <hr />
                                                        <div class="form-group no-mbot">
                                                            <div class="radio radio-primary radio-inline">
                                                                <input type="radio" name="leads_bulk_visibility"
                                                                    id="leads_bulk_public" value="public">
                                                                <label for="leads_bulk_public">
                                                                    <?php echo _l('lead_public'); ?>
                                                                </label>
                                                            </div>
                                                            <div class="radio radio-primary radio-inline">
                                                                <input type="radio" name="leads_bulk_visibility"
                                                                    id="leads_bulk_private" value="private">
                                                                <label for="leads_bulk_private">
                                                                    <?php echo _l('private'); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-default"
                                                        data-dismiss="modal"><?php echo _l('close'); ?></button>
                                                    <a href="#" class="btn btn-primary"
                                                        onclick="leads_bulk_action(this); return false;"><?php echo _l('confirm'); ?></a>
                                                </div>
                                            </div>
                                            <!-- /.modal-content -->
                                        </div>
                                        <!-- /.modal-dialog -->
                                    </div>
                                    <!-- /.modal -->
                                    <?php

                              $table_data = [];
                                $_table_data = [
                                    '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="leads"><label></label></div>',
                                    [
                                        'name' => _l('the_number_sign'),
                                        'th_attrs' => ['class' => 'toggleable', 'id' => 'th-number'],
                                    ],
                                    [
                                        'name' => _l('leads_dt_name'),
                                        'th_attrs' => ['class' => 'toggleable', 'id' => 'th-name'],
                                    ],
                                ];
                                if (is_gdpr() && get_option('gdpr_enable_consent_for_leads') == '1') {
                                    $_table_data[] = [
                                        'name' => _l('gdpr_consent').' ('._l('gdpr_short').')',
                                        'th_attrs' => ['id' => 'th-consent', 'class' => 'not-export'],
                                    ];
                                }

                                $_table_data[] = [
                                    'name' => _l('leads_dt_email'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-email'],
                                ];

                                $_table_data[] = [
                                    'name' => _l('leads_dt_phonenumber'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-phone'],
                                ];

                                $_table_data[] = [
                                    'name' => _l('leads_dt_assigned'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-assigned'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('leads_dt_status'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-status'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('Sub Status'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-source'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('Company'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-company'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('Website'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-website'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('leads_source'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-source'],
                                ];

                                $_table_data[] = [
                                    'name' => _l('City'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-city'],
                                ];

                                $_table_data[] = [
                                    'name' => _l('Address'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-city'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('Source Campaign'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-source-campaign'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('leads_dt_last_contact'),
                                    'th_attrs' => ['class' => 'toggleable', 'id' => 'th-last-contact'],
                                ];
                                $_table_data[] = [
                                    'name' => _l('leads_dt_datecreated'),
                                    'th_attrs' => ['class' => 'date-created toggleable', 'id' => 'th-date-created'],
                                ];

                                foreach ($_table_data as $_t) {
                                    array_push($table_data, $_t);
                                }
                                $custom_fields = get_custom_fields('leads', ['show_on_table' => 1]);
                                foreach ($custom_fields as $field) {
                                    array_push($table_data, [
                                        'name' => $field['name'],
                                        'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
                                    ]);
                                }
                                $table_data = hooks()->apply_filters('leads_table_columns', $table_data);
                                ?>
                                    <div class="panel-table-full">
                                        <!-- ADD THIS -->
                                        <button id="btnExportLeads" class="btn btn-success btn-sm"
                                            style="margin-bottom:8px; font-size:9px">
                                            <i class="fa fa-file-excel-o"></i> Export Excel
                                        </button>

                                        <?php render_datatable($table_data, 'leads', ['customizable-table number-index-2'], [
        'id' => 'table-leads',
        'data-last-order-identifier' => 'leads',
        'data-default-order' => get_table_last_order('leads'),
    ]); ?>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <div id="scrollToggle">↓</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script id="hidden-columns-table-leads" type="text/json">
<?php echo get_staff_meta(get_staff_user_id(), 'hidden-columns-table-leads'); ?>
</script>
<?php include_once APPPATH.'views/admin/leads/status.php'; ?>
<?php init_tail(); ?>
<script>
var openLeadID = '<?php echo $leadid; ?>';
$(function() {
    leads_kanban();
    $('#leads_bulk_mark_lost').on('change', function() {
        $('#move_to_status_leads_bulk').prop('disabled', $(this).prop('checked') == true);
        $('#move_to_status_leads_bulk').selectpicker('refresh')
    });
    $('#move_to_status_leads_bulk').on('change', function() {
        if ($(this).selectpicker('val') != '') {
            $('#leads_bulk_mark_lost').prop('disabled', true);
            $('#leads_bulk_mark_lost').prop('checked', false);
        } else {
            $('#leads_bulk_mark_lost').prop('disabled', false);
        }
    });
});
</script>

<script>
let atBottom = false;

document.addEventListener("DOMContentLoaded", function() {

    const btn = document.getElementById("scrollToggle");

    btn.addEventListener("click", function() {

        if (!atBottom) {

            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: "smooth"
            });

            btn.innerHTML = "↑";
            atBottom = true;

        } else {

            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });

            btn.innerHTML = "↓";
            atBottom = false;

        }

    });

});
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr("#date_range", {
        mode: "range",
        dateFormat: "Y-m-d",
        onClose: function(selectedDates, dateStr, instance) {
            // Trigger the table reload with the selected date range value
            table_leads.DataTable().ajax.reload();
        }
    });
});
</script>


<script>
// ======================================================
// AUDIO PLAYER
// ======================================================
window.playRecording = function(url) {
    if (!url) return;

    const source = document.getElementById("audioSource");
    const audio = document.getElementById("audioPlayer");

    if (!source || !audio) return;

    source.src = url;
    audio.load();
    audio.play();

    document.getElementById("audioPlayerBox").style.display = "block";
};

window.closeAudioPlayer = function() {
    const audio = document.getElementById("audioPlayer");
    audio.pause();
    audio.currentTime = 0;

    document.getElementById("audioPlayerBox").style.display = "none";
};
</script>



<script>
window.playRecording = function(url) {
    if (!url) return;
    const source = document.getElementById("audioSource");
    const audio = document.getElementById("audioPlayer");
    if (!source || !audio) {
        console.error('Audio elements not found');
        return;
    }
    source.src = url;
    audio.load();
    audio.play().catch(() => {});
    const box = document.getElementById("audioPlayerBox");
    if (box) box.style.display = "block";
};

window.closeAudioPlayer = function() {
    const audio = document.getElementById("audioPlayer");
    if (!audio) return;
    audio.pause();
    audio.currentTime = 0;
    const box = document.getElementById("audioPlayerBox");
    if (box) box.style.display = "none";
};
</script>



<script>
$(document).on('click', '.open-whatsapp', function(e) {
    e.preventDefault();
    const phone = $(this).data('phone');
    localStorage.setItem('whatsapp_active_phone', phone);
    window.open($(this).attr('href'), '_blank');
});


$(document).ready(function() {
    const phone = localStorage.getItem('whatsapp_active_phone');
    if (phone) {
        $('.lead[data-phone="' + phone.replace(/\D/g, '') + '"]').click();
        localStorage.removeItem('whatsapp_active_phone');
    }

});


$('#btnExportLeads').on('click', function() {
    var assigned = $("[name='view_assigned[]']").val() || [];
    var status = $("[name='view_status[]']").val() || [];
    var sub_status = $("[name='sub_status[]']").val() || [];
    var source = $("[name='view_source']").val() || '';
    var card_status = $("[name='card_status']").val() || '';
    var payment_mode = $("[name='payment_mode']").val() || '';
    var date_range = $("[name='date_range']").val() || '';
    var custom_view = $("[name='custom_view']").val() || '';

    // Check if ANY filter is selected
    var hasFilter = (
        assigned.length > 0 ||
        status.length > 0 ||
        sub_status.length > 0 ||
        source !== '' ||
        card_status !== '' ||
        payment_mode !== '' ||
        date_range !== '' ||
        custom_view !== ''
    );

    if (!hasFilter) {
        alert('Please select at least one filter before exporting.');
        return;
    }

    var params = {
        assigned,
        status,
        sub_status,
        source,
        card_status,
        payment_mode,
        date_range,
        custom_view
    };

    window.location.href = admin_url + 'leads/export_excel?' + $.param(params);
});
</script>

</body>

</html>