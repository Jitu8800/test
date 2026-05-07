<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- Buttons extension CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="_buttons tw-mb-2 sm:tw-mb-4">
                    <!-- <a href="#" onclick="init_lead(); return false;"
                        class="btn btn-primary mright5 pull-left display-block">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php //echo _l('new_lead'); ?>
                    </a> -->
                    <!-- <?php if (is_admin() || get_option('allow_non_admin_members_to_import_leads') == '1') { ?>
                        <a href="<?php echo admin_url('leads/import'); ?>"
                            class="btn btn-primary pull-left display-block hidden-xs">
                            <i class="fa-solid fa-upload tw-mr-1"></i>
                            <?php // echo _l('import_leads'); ?>
                        </a>
                    <?php } ?> -->
                    <!-- <div class="row">
                        <div class="col-sm-5 ">
                            <a href="#" class="btn btn-default btn-with-tooltip" data-toggle="tooltip"
                                data-title="<?php echo _l('leads_summary'); ?>" data-placement="top"
                                onclick="slideToggle('.leads-overview'); return false;"><i
                                    class="fa fa-bar-chart"></i></a>
                            <a href="<?php echo admin_url('leads/switch_kanban/' . $switch_kanban); ?>"
                                class="btn btn-default mleft5 hidden-xs" data-toggle="tooltip" data-placement="top"
                                data-title="<?php echo $switch_kanban == 1 ? _l('leads_switch_to_kanban') : _l('switch_to_list_view'); ?>">
                                <?php if ($switch_kanban == 1) { ?>
                                    <i class="fa-solid fa-grip-vertical"></i>
                                <?php } else { ?>
                                    <i class="fa-solid fa-table-list"></i>
                                <?php }
                                ; ?>
                            </a>
                        </div>

                        <div class="col-sm-4 col-xs-12 pull-right leads-search">
                            <?php if ($this->session->userdata('leads_kanban_view') == 'true') { ?>
                                <div data-toggle="tooltip" data-placement="top"
                                    data-title="<?php echo _l('search_by_tags'); ?>">
                                    <?php echo render_input('search', '', '', 'search', ['data-name' => 'search', 'onkeyup' => 'leads_kanban();', 'placeholder' => _l('leads_search')], [], 'no-margin') ?>
                                </div>
                            <?php } ?>
                            <?php echo form_hidden('sort_type'); ?>
                            <?php echo form_hidden('sort', (get_option('default_leads_kanban_sort') != '' ? get_option('default_leads_kanban_sort_type') : '')); ?>
                        </div>
                    </div> -->
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
                                            echo '<span data-toggle="tooltip" data-title="' . $status['total'] . '">' . $status['percent'] . '%</span>';
                                        } else {
                                            // Is regular status
                                            echo $status['total'];
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

                    <?php
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Query for top performer
$sql = "
    SELECT assigned, staffname, lead_count, total_SPL, converted_lead, total_lead, total_payment
    FROM (
        SELECT 
            tblleads.assigned, 
            CONCAT(tblstaff.firstname, ' ', tblstaff.lastname) AS staffname, 
            COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS lead_count,
            SUM(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN tblleads.lead_value ELSE 0 END) AS total_SPL,
            COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS converted_lead,
            COUNT(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN 1 END) AS total_lead,
            SUM(CASE WHEN tblleads.status = 10 THEN tblleads.total_payment ELSE 0 END) AS total_payment
        FROM tblleads 
        JOIN tblstaff ON tblleads.assigned = tblstaff.staffid 
        WHERE MONTH(tblleads.last_status_change) = ?
          AND YEAR(tblleads.last_status_change) = ?
        GROUP BY tblleads.assigned 
        ORDER BY lead_count DESC 
        LIMIT 1
    ) AS highest_lead_count
";

// Execute the top performer query with month and year parameters
$topPerformerQuery = $this->db->query($sql, [$selectedMonth, $selectedYear]);
$topPerformer = $topPerformerQuery->row();

// Query for lowest performer
$sql_low = "
    SELECT assigned, staffname, lead_count, total_SPL, converted_lead, total_lead, total_payment
    FROM (
        SELECT 
            tblleads.assigned, 
            CONCAT(tblstaff.firstname, ' ', tblstaff.lastname) AS staffname, 
            COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS lead_count,
            SUM(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN tblleads.lead_value ELSE 0 END) AS total_SPL,
            COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS converted_lead,
            COUNT(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN 1 END) AS total_lead,
            SUM(CASE WHEN tblleads.status = 10 THEN tblleads.total_payment ELSE 0 END) AS total_payment
        FROM tblleads 
        JOIN tblstaff ON tblleads.assigned = tblstaff.staffid 
        WHERE MONTH(tblleads.last_status_change) = ?
          AND YEAR(tblleads.last_status_change) = ?
";

// Exclude the top performer
if ($topPerformer) {
    $sql_low .= " AND tblleads.assigned != " . (int)$topPerformer->assigned;
}

$sql_low .= "
        GROUP BY tblleads.assigned 
        ORDER BY lead_count ASC 
        LIMIT 1
    ) AS lowest_lead_count
";

// Execute the lowest performer query
$lowestPerformerQuery = $this->db->query($sql_low, [$selectedMonth, $selectedYear]);
$lowestPerformer = $lowestPerformerQuery->row();

// Display the results
$filterResults = array_filter([$topPerformer, $lowestPerformer]);

if ($filterResults) {
    ?>
    <div style="display: flex; gap: 20px; padding: 20px;">
        <?php foreach ($filterResults as $index => $row): 
            $total_SPL = $row->converted_lead > 0 ? $row->total_SPL / $row->converted_lead : 0;
        ?>
        <div style="flex: 1; padding: 20px; border-radius: 8px; color: white; background-color: <?= $index === 0 ? '#28a745' : '#dc3545' ?>; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); transition: transform 0.2s ease-in-out;"
            onmouseover="this.style.transform='scale(1.05)'"
            onmouseout="this.style.transform='scale(1)'">
            <h2 style="margin: 0; font-size: 1.8em;">
                <?= $index === 0 ? 'Top Performer' : 'Top Loser' ?>
            </h2>
            <h3 style="margin: 10px 0 20px; font-size: 1.5em;">
                <?= htmlspecialchars($row->staffname); ?>
            </h3>
            <p style="font-size: 0.9em; line-height: 1.8; color: #f1f1f1;">
                <strong>Total Revenue :</strong> <?= '₹ ' . htmlspecialchars($row->total_payment); ?> &nbsp; | &nbsp;
                <strong>Total CPL:</strong> <?= '₹ ' . round($total_SPL); ?> &nbsp; | &nbsp;
                <strong>Converted Calls:</strong> <?= htmlspecialchars($row->converted_lead); ?> &nbsp; | &nbsp;
                <strong>Total Calls:</strong> <?= htmlspecialchars($row->total_lead); ?> &nbsp; | &nbsp;
                <strong>Total SPL:</strong> <?= '₹ ' . htmlspecialchars($row->total_SPL); ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php 
} else { 
    ?>
    <div style="display: flex; gap: 20px; padding: 20px;">
        <div style="flex: 1; padding: 10px; border-radius: 8px; color: white; background-color: #dc3545;">
            <h2 style="margin: 0; font-size: 1.8em; text-align:center;">No data found!</h2>
        </div>
    </div>
    <?php 
}
?>

<style>.filter-form ::placeholder {
	font-size: 14px;
	font-weight: 400;
	color: #f2f2f2;
}

.filter-form {
	font-family: Inter, Sans-Serif;
	margin-left: 20px;
	margin-bottom: 10px;
	display: flex;
	gap: 20px;
	align-items: baseline;
	justify-content: center;
}

.filter-form label {
	color: #323232;
}

.filter-form select {
	font-size: 14px;
	font-weight: 400;
	padding: 5px 8px;
	border: 1px solid #9b9b9b;
	border-radius: 4px;
	background: #f6f6f6;
}

.filter-form input {
	background: rgb(40, 167, 69);
	padding: 4px 40px;
	font-size: 16px;
	color: #fff;
	font-weight: 400;
	border: none;
	border-radius: 4px
}

</style>

<form class="filter-form" method="GET">
    <div style="margin-bottom: 10px;">
        <label for="month">Select Month:</label>
        <select id="month" name="month" required>
            <?php
            $currentMonth = date('m');
            $selectedMonth = $_GET['month'] ?? $currentMonth;
            for ($m = 1; $m <= 12; $m++) {
                $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
                $monthName = date('F', mktime(0, 0, 0, $m, 1));
                $selected = ($monthValue == $selectedMonth) ? 'selected' : '';
                echo "<option value='$monthValue' $selected>$monthName</option>";
            }
            ?>
        </select>
    </div>

    <div style="margin-bottom: 10px;">
        <label for="year">Select Year:</label>
        <select id="year" name="year" required>
            <?php
            $currentYear = date('Y');
            $selectedYear = $_GET['year'] ?? $currentYear;
            for ($y = $currentYear; $y >= ($currentYear - 10); $y--) {
                $selected = ($y == $selectedYear) ? 'selected' : '';
                echo "<option value='$y' $selected>$y</option>";
            }
            ?>
        </select>
    </div>

    <input type="submit" value="Apply" class="btn-submit">
</form>



<div class="performance-description-container">
    <h4 style="text-align: center; margin-bottom: 20px;margin-top: 0;">Lead Performance/Month</h4>
    <div class="performance-levels">
        <div class="level-box poor">
            <span class="lead-range">Less than 100</span>
            <span class="performance-label">Poor</span>
        </div>
        <div class="level-box average">
            <span class="lead-range">100 - 120</span>
            <span class="performance-label">Average</span>
        </div>
        <div class="level-box good">
            <span class="lead-range">121 - 150</span>
            <span class="performance-label">Good</span>
        </div>
        <div class="level-box very-good">
            <span class="lead-range">151 - 160</span>
            <span class="performance-label">Very Good</span>
        </div>
        <div class="level-box extra-ordinary">
            <span class="lead-range">More than 160</span>
            <span class="performance-label">Extra Ordinary</span>
        </div>
    </div>
</div>


<style>
    .performance-description-container {
    max-width: auto;
    margin: 0 auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 10px;
    background-color: #f9f9f9;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.performance-levels {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.level-box {
    flex: 1;
    padding: 10px;
    min-height: 65px;
    text-align: center;
    color: #fff;
    border-radius: 8px;
    font-weight: bold;
    transition: transform 0.2s;
}

.level-box:hover {
    transform: scale(1.05);
}

.poor {
    background-color: #e74c3c; /* Red */
}

.average {
    background-color: #f39c12; /* Orange */
}

.good {
    background-color: #3498db; /* Blue */
}

.very-good {
    background-color: #2ecc71; /* Green */
}

.extra-ordinary {
    background-color: #8e44ad; /* Purple */
}

.lead-range {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}

.performance-label {
    font-size: 15px;
}

    </style>


<?php 

if (isset($_GET['month']) && isset($_GET['year'])) {
    $selectedMonth = $_GET['month'];
    $selectedYear = $_GET['year'];
    
    $startDate = "$selectedYear-$selectedMonth-01";
    $endDate = date("Y-m-t", strtotime($startDate));
}
?>

              
                </div>
                <div class="<?php echo $isKanBan ? '' : 'panel_s'; ?>">
                    <div class="<?php echo $isKanBan ? '' : 'panel-body'; ?>">
                        <div class="tab-content">
                            <?php
                            if ($isKanBan) { ?>
                            <div class="active kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                                <div class="kanban-leads-sort">
                                    <span class="bold">
                                        <?php echo _l('leads_sort_by'); ?>:
                                    </span>
                                    <a href="#" onclick="leads_kanban_sort('dateadded'); return false"
                                        class="dateadded">
                                        <?php if (get_option('default_leads_kanban_sort') == 'dateadded') {
                                                echo '<i class="kanban-sort-icon fa fa-sort-amount-' . strtolower(get_option('default_leads_kanban_sort_type')) . '"></i> ';
                                            } ?>
                                        <?php echo _l('leads_sort_by_datecreated'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="leads_kanban_sort('leadorder');return false;"
                                        class="leadorder">
                                        <?php if (get_option('default_leads_kanban_sort') == 'leadorder') {
                                                echo '<i class="kanban-sort-icon fa fa-sort-amount-' . strtolower(get_option('default_leads_kanban_sort_type')) . '"></i> ';
                                            } ?>
                                        <?php echo _l('leads_sort_by_kanban_order'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="leads_kanban_sort('lastcontact');return false;"
                                        class="lastcontact">
                                        <?php if (get_option('default_leads_kanban_sort') == 'lastcontact') {
                                                echo '<i class="kanban-sort-icon fa fa-sort-amount-' . strtolower(get_option('default_leads_kanban_sort_type')) . '"></i> ';
                                            } ?>
                                        <?php echo _l('leads_sort_by_lastcontact'); ?>
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
                                    <div class="row" style="display:none">
                                        <!-- <div class="col-md-12">
                                                <p class="bold"><?php echo _l('filter_by'); ?></p>
                                            </div> -->


                                        <?php     
                                            $user = $this->session->userdata();
                                            
                                            $userRoles = get_staff($user['staff_user_id']);
                                        
                                            if ($userRoles->role == 3) {
                                                $currentUser = [['staffid' => $userRoles->staffid, 'firstname' => $userRoles->firstname, 'lastname' => $userRoles->lastname]];
                                                ?>
                                        <div class="col-md-3 leads-filter-column">
                                            <?php echo render_select('view_assigned', $currentUser, ['staffid', ['firstname', 'lastname']], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('leads_dt_assigned')], [], 'no-mbot'); ?>
                                        </div>
                                        <?php
                                            } else {
                                                if (has_permission('leads', '', 'view')) { ?>
                                        <div class="col-md-3 leads-filter-column">
                                            <?php echo render_select('view_assigned', $staff, ['staffid', ['firstname', 'lastname']], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('leads_dt_assigned')], [], 'no-mbot'); ?>
                                        </div>
                                        <?php }
                                            }
                                            ?>

                                        <div class="col-md-3 leads-filter-column">
                                            <?php
                                                $selected = [];
                                                if ($this->input->get('status')) {
                                                    $selected[] = $this->input->get('status');
                                                } else {
                                                    foreach ($statuses as $key => $status) {
                                                        if ($status['isdefault'] == 0) {
                                                            $selected[] = $status['id'];
                                                        } else {
                                                            $statuses[$key]['option_attributes'] = ['data-subtext' => _l('leads_converted_to_client')];
                                                        }
                                                    }
                                                }
                                                echo '<div id="leads-filter-status">';
                                                echo render_select('view_status[]', $statuses, ['id', 'name'], '', $selected, ['data-width' => '100%', 'data-none-selected-text' => _l('leads_all'), 'multiple' => true, 'data-actions-box' => true], [], 'no-mbot', '', false);
                                                echo '</div>';
                                                ?>
                                        </div>
                                        <div class="col-md-3 leads-filter-column">
                                            <?php
                                                echo render_select('view_source', $sources, ['id', 'name'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('leads_source')], [], 'no-mbot');
                                                ?>
                                        </div>
                                        <div class="col-md-3 leads-filter-column">
                                            <div class="select-placeholder">
                                                <select name="custom_view"
                                                    title="<?php echo _l('additional_filters'); ?>" id="custom_view"
                                                    class="selectpicker" data-width="100%">
                                                    <option value=""></option>
                                                    <option value="lost">
                                                        <?php echo _l('lead_lost'); ?>
                                                    </option>
                                                    <option value="junk">
                                                        <?php echo _l('lead_junk'); ?>
                                                    </option>
                                                    <option value="public">
                                                        <?php echo _l('lead_public'); ?>
                                                    </option>
                                                    <option value="contacted_today">
                                                        <?php echo _l('lead_add_edit_contacted_today'); ?>
                                                    </option>
                                                    <option value="created_today">
                                                        <?php echo _l('created_today'); ?>
                                                    </option>
                                                    <?php if (has_permission('leads', '', 'edit')) { ?>
                                                    <option value="not_assigned">
                                                        <?php echo _l('leads_not_assigned'); ?>
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
                                        <div class="col-md-12 row" style="margin-top:15px">

                                            <div class="col-md-3 leads-filter-column">
                                                <?php
                                                $card_status = [
                                                    ['id' => 'Approved', 'label' => 'Approved'],
                                                    ['id' => 'Pending', 'label' => 'Pending'],
                                                    ['id' => 'Declined', 'label' => 'Declined'],
                                                    ['id' => 'Processing', 'label' => 'Processing'],
                                                    ['id' => 'Shipped', 'label' => 'Shipped'],
                                                    ['id' => 'Delivered', 'label' => 'Delivered'],
                                                    ['id' => 'Canceled', 'label' => 'Canceled']
                                                ];
                                                echo render_select('card_status', $card_status, ['id', 'label'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('Card Status')], [], 'no-mbot');
                                                ?>
                                            </div>

                                            <div class="col-md-3 leads-filter-column">
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
                                                    ['id' => 'Net_Banking', 'label' => 'Net Banking']
                                                ];
                                                echo render_select('payment_mode', $payment_mode, ['id', 'label'], '', '', ['data-width' => '100%', 'data-none-selected-text' => _l('Payment Mode')], [], 'no-mbot');
                                                ?>
                                            </div>

                                        </div>
                                    </div>
                                    <hr class="hr-panel-separator" />
                                </div>
                                <div class="clearfix"></div>

                                <div class="col-md-12">
                                    <a href="#" data-toggle="modal" data-table=".table-leads"
                                        data-target="#leads_bulk_actions" class="hide bulk-actions-btn table-btn">
                                        <?php echo _l('bulk_actions'); ?>
                                    </a>
                                    <div class="modal fade bulk_actions" id="leads_bulk_actions" tabindex="-1"
                                        role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close"><span
                                                            aria-hidden="true">&times;</span></button>
                                                    <h4 class="modal-title">
                                                        <?php echo _l('bulk_actions'); ?>
                                                    </h4>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if (has_permission('leads', '', 'delete')) { ?>
                                                    <div class="checkbox checkbox-danger">
                                                        <input type="checkbox" name="mass_delete" id="mass_delete">
                                                        <label for="mass_delete">
                                                            <?php echo _l('mass_delete'); ?>
                                                        </label>
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
                                                            <?php echo '<p><b><i class="fa fa-tag" aria-hidden="true"></i> ' . _l('tags') . ':</b></p>'; ?>
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
                                                    <button type="button" class="btn btn-default" data-dismiss="modal">
                                                        <?php echo _l('close'); ?>
                                                    </button>
                                                    <a href="#" class="btn btn-primary"
                                                        onclick="leads_bulk_action(this); return false;">
                                                        <?php echo _l('confirm'); ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <!-- /.modal-content -->
                                        </div>
                                        <!-- /.modal-dialog -->
                                    </div>

                                    <div class="panel-table-full">
    <?php
        // Get selected month and year from the form input
        $selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m'); 
        $selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

        // Build the WHERE clause for filtering by selected month and year
        $dateFilter = "WHERE MONTH(tblleads.last_status_change) = ? AND YEAR(tblleads.last_status_change) = ?";
        $params = [$selectedMonth, $selectedYear];

        // Main query for the data
        $sql = "SELECT 
                    CONCAT(tblstaff.firstname, ' ', tblstaff.lastname) AS staff_name,
                    SUM(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN tblleads.lead_value ELSE 0 END) AS total_SPL,
                    COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS converted_lead,
                    COUNT(CASE WHEN tblleads.status IN (3,5,6,8,9,10,12) THEN 1 END) AS total_lead,
                    SUM(CASE WHEN tblleads.status = 10 THEN tblleads.total_payment ELSE 0 END) AS total_payment
                FROM tblleads
                JOIN tblstaff ON tblleads.assigned = tblstaff.staffid
                $dateFilter
                GROUP BY tblstaff.staffid";

        $query = $this->db->query($sql, $params);
        $datas = $query->result();

        // Performance query
        $sqlPerformance = "SELECT 
            CONCAT(tblstaff.firstname, ' ', tblstaff.lastname) AS staffname,
            MONTHNAME(MAX(tblleads.last_status_change)) AS month_name,
            WEEK(tblleads.last_status_change, 1) AS week_number,
            GROUP_CONCAT(DISTINCT DAYNAME(tblleads.last_status_change) ORDER BY tblleads.last_status_change) AS day_names,
            COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) AS converted_lead,
            ROUND((COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) / 50) * 100, 2) AS performance_percentage,
            CASE 
                WHEN COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) < 100 THEN 'Poor'
                WHEN COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) BETWEEN 100 AND 120 THEN 'Average'
                WHEN COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) > 120 AND COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) <= 150 THEN 'Good'
                WHEN COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) > 150 AND COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) <= 160 THEN 'Very Good'
                WHEN COUNT(CASE WHEN tblleads.status = 10 THEN 1 END) > 160 THEN 'Extra Ordinary'
                ELSE 'No Data'
            END AS performance,
            DATEDIFF(NOW(), MAX(tblleads.last_status_change)) AS days_since_last_status_change
        FROM tblleads
        JOIN tblstaff ON tblleads.assigned = tblstaff.staffid
        $dateFilter
        GROUP BY tblleads.assigned, MONTH(tblleads.last_status_change), WEEK(tblleads.last_status_change, 1)
        ORDER BY month_name, week_number";

        $queryPerformance = $this->db->query($sqlPerformance, $params);
        $performance = $queryPerformance->result();

        // Data integration for display
        foreach ($datas as $data) {
            foreach ($performance as $perf) {
                if ($data->staff_name === $perf->staffname) {
                    $data->month_name = $perf->month_name;
                    $data->day_names = $perf->day_names;
                    $data->performance = $perf->performance;
                    $data->performance_percentage = $perf->performance_percentage;
                    $data->days_since_last_status_change = $perf->days_since_last_status_change;
                }
            }
        }
    ?>
 

    <!-- HTML Table Display -->
    <div class="table-responsive">
        <table id="example" class="customizable-table" style="width:100%">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Total Calls</th>
                    <th>Total Converted Calls</th>
                    <th>Total SPL (₹)</th>
                    <th>Total CPL (₹)</th>
                    <th>Total Revenue (₹)</th>
                    <th>Performance</th>
                    <th>Performance Graph %</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($datas): $i=1; ?>
                    <?php foreach ($datas as $data):  ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($data->staff_name); ?></td>
                            <td><?= htmlspecialchars($data->total_lead); ?></td>
                            <td><?= htmlspecialchars($data->converted_lead); ?></td>
                            <td><?= '₹ '.htmlspecialchars(number_format($data->total_SPL, 2)); ?></td>
                            <td><?= '₹ '.htmlspecialchars(number_format(round($data->total_SPL / max($data->converted_lead, 1), 2), 2)); ?></td>
                            <td><?= '₹ '.htmlspecialchars(number_format($data->total_payment, 2)); ?></td>
                            <td>
    <div style="width: 100%; background-color: #ddd; border-radius: 5px; height: 20px;">
        <div 
            style="width: 100%; background-color: 
            <?php 
                // Assign colors based on performance level
                switch ($data->performance) {
                    case 'Poor':
                        echo 'red';
                        break;
                    case 'Average':
                        echo 'yellow';
                        break;
                    case 'Good':
                        echo 'green';
                        break;
                    case 'Very Good':
                        echo 'darkgreen';
                        break;
                    case 'Extra Ordinary':
                        echo 'purple';
                        break;
                    default:
                        echo '#ddd'; // Default gray if no match
                }
            ?>; height: 100%; border-radius: 5px;">
            <span style="color: white;">
                <?= htmlspecialchars($data->performance); ?>
            </span>
        </div>
    </div>
</td>
                            <td>
                                <div style="width: 100%; background-color: #ddd; border-radius: 5px; height: 20px;">
                                    <div style="width: <?= htmlspecialchars($data->performance_percentage) ?>%; background-color: <?= ($data->performance_percentage >= 75) ? 'green' : (($data->performance_percentage >= 50) ? 'yellow' : 'red'); ?>; height: 100%; border-radius: 5px;">
                                        <span style="margin-left: 5px; color: white;"><?= htmlspecialchars($data->performance_percentage) . '%' ?></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $i++; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


                                </div>
                            </div>
                            <?php } ?>
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
<?php include_once(APPPATH . 'views/admin/leads/status.php'); ?>
<style>
    #example {
        width: 100%;
    }

    #example th,
    #example td {
        text-align: center;
        /* Center the text */
    }
</style>
<?php init_tail(); ?>
<script src="https://code.jquery.com/jquery-3.7.1.js"></script>

<!-- DataTables CSS -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- Buttons extension JS -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<!-- JSZip for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- Buttons HTML5 export JS -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
 $(document).ready(function () {
    $('#example').DataTable({
        dom: 'Bfrtip', // Enables the Buttons control above the table
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Data Export', // Customizes the file name
                text: 'Export to Excel', // Button text
                className: 'btn-sm btn-success' // Optional styling class
            }
        ]
    });
});

</script>

<script>
    var openLeadID = '<?php echo $leadid; ?>';
    $(function () {

        leads_kanban();
        $('#leads_bulk_mark_lost').on('change', function () {
            $('#move_to_status_leads_bulk').prop('disabled', $(this).prop('checked') == true);
            $('#move_to_status_leads_bulk').selectpicker('refresh')
        });
        $('#move_to_status_leads_bulk').on('change', function () {
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
    // function getCurrentLocation() {
    //         if (navigator.geolocation) {
    //             navigator.geolocation.getCurrentPosition(
    //                 (position) => {
    //                     const lat = position.coords.latitude;
    //                     const long = position.coords.longitude;
    //                     displayLocation(lat, long);
    //                 },
    //                 (error) => {
    //                     console.error('Error getting location:', error);
    //                     alert('Unable to retrieve your location. Please ensure location services are enabled.');
    //                 }
    //             );
    //         } else {
    //             alert('Geolocation is not supported by this browser.');
    //         }
    //     }

    //     function displayLocation(lat, lng) {
    //         //      lat = 28.5823155; // Use your actual latitude
    //         //  lng = 77.3245269; // Use your actual longitude
    //     const apiUrl = `http://localhost/sales-crm/api/leadcontroller/getGoogleAddress?lat=${lat}&lng=${lng}`;

    //     fetch(apiUrl)
    //         .then(response => response.json())
    //         .then(data => {
    //             if (data.address) {
    //                 console.log('asdfasdfsdfasdf', data.address)
    //                 document.getElementById('address').innerText = `Address: ${data.address}`;
    //             } else {
    //                 document.getElementById('address').innerText = `Error: ${data.error}`;
    //             }
    //         })
    //         .catch(error => {
    //             console.error('Error fetching the address:', error);
    //         });
    //     }

    //     window.onload = () => {
    //         getCurrentLocation();
    //     };
</script>
</script>
</body>



</html>