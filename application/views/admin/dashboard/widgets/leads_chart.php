<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="widget<?php if (!is_staff_member()) { echo ' hide'; } ?>" id="widget-<?php echo create_widget_id(); ?>"
    data-name="<?php echo _l('s_chart', _l('leads')); ?>">

    <?php if (is_staff_member()) { ?>
    <div class="row">
        <div class="col-md-12">
            <div class="panel_s">
                <div class="panel-body padding-10">
                    <div class="widget-dragger"></div>

                    <p
                        class="tw-font-medium tw-flex tw-items-center tw-mb-0 tw-space-x-1.5 rtl:tw-space-x-reverse tw-p-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="tw-w-6 tw-h-6 tw-text-neutral-500">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                        <span class="tw-text-neutral-700"><?php echo _l('home_lead_overview'); ?></span>
                    </p>

                    <hr class="-tw-mx-3 tw-mt-3 tw-mb-6">

                    <!-- ✅ Existing Working Chart (User Calls Overview) -->
                    <div class="customChart">
                        <div class="card-header card-header-custom d-flex align-items-center">
                            <i class="fa-solid fa-square-phone"></i>
                            <span style="font-weight: 500;">Overview of the User's Calls</span>
                        </div>
                        <br />
                        <canvas id="leadPieChart" width="200" height="200"></canvas>
                    </div>

                    <?php
        // ============================
        // Existing Call Chart Data
        // ============================
        $CI =& get_instance();
        $CI->load->database();
        
        $statuses = $CI->db->get(db_prefix() . 'leads_status')->result_array();
        $statusIds = array_column($statuses, 'id');
        $statusIdsString = !empty($statusIds) ? implode(',', $statusIds) : '0';
        
        $staff_user_id = get_staff_user_id();

        $query = $CI->db->query("
            SELECT assigned, COUNT(*) as lead_count 
            FROM tblleads 
            WHERE status IN ($statusIdsString) 
            GROUP BY assigned
        ");
        $result = $query->result();
        $finalData = [];
        foreach($result as $row) {
            $name = get_staff($row->assigned);
            $finalData[] = [
                'name' => $name->firstname . ' ' . $name->lastname,
                'count' => $row->lead_count
            ];
        }
        $names = array_column($finalData, 'name');
        $counts = array_column($finalData, 'count');
        ?>

                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                    const leadNames = <?php echo json_encode($names); ?>;
                    const leadCounts = <?php echo json_encode($counts); ?>;
                    const ctx1 = document.getElementById('leadPieChart').getContext('2d');
                    new Chart(ctx1, {
                        type: 'pie',
                        data: {
                            labels: leadNames,
                            datasets: [{
                                label: 'Calls Completed Today',
                                data: leadCounts,
                                backgroundColor: [
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                                ],
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                    </script>

                    <hr>

                    <?php
                    $CI = &get_instance();
                    $staff_user_id = get_staff_user_id();

                    // ============================
                    // 1️⃣ Lead Status Chart Data
                    // ============================
                    $where_condition = is_admin() ? '' : 'WHERE l.assigned = ' . (int)$staff_user_id;

                    $query_status = $CI->db->query("
                        SELECT s.name, s.color, COUNT(l.id) as lead_count
                        FROM tblleads_status s
                        LEFT JOIN tblleads l ON l.status = s.id
                        $where_condition
                        GROUP BY s.id
                        ORDER BY s.statusorder ASC
                    ");
                    $status_result = $query_status->result();

                    $status_labels = [];
                    $status_counts = [];
                    $status_colors = [];

                    foreach ($status_result as $row) {
                        $status_labels[] = $row->name;
                        $status_counts[] = (int)$row->lead_count;
                        $status_colors[] = $row->color ?: '#cccccc';
                    }

                    // ============================
                    // 2️⃣ Lead Substatus Chart Data
                    // ============================
                    // Join with tblleads.sub_status
                    $query_substatus = $CI->db->query("
                        SELECT ss.sub_name, ss.color, COUNT(l.id) as lead_count
                        FROM tblleads_substatus ss
                        LEFT JOIN tblleads l ON l.sub_status = ss.id
                        " . (is_admin() ? "" : "WHERE l.assigned = " . (int)$staff_user_id) . "
                        GROUP BY ss.id
                        ORDER BY ss.substatusorder ASC
                    ");

                    $substatus_result = $query_substatus->result();

                    $substatus_labels = [];
                    $substatus_counts = [];
                    $substatus_colors = [];

                    foreach ($substatus_result as $row) {
                        $substatus_labels[] = $row->sub_name;
                        $substatus_counts[] = (int)$row->lead_count;
                        $substatus_colors[] = $row->color ?: '#999999';
                    }
                    ?>

                    <!-- ✅ Chart 1: Lead Status Overview -->
                    <div class="customChart">
                        <div class="card-header card-header-custom d-flex align-items-center">
                            <i class="fa-solid fa-chart-pie"></i>
                            <span style="font-weight: 500; margin-left: 8px;">Lead Status Overview</span>
                        </div>
                        <br />
                        <canvas id="leadStatusPieChart" width="400" height="400"></canvas>
                    </div>

                    <!-- ✅ Chart 2: Lead Substatus Overview -->
                    <div class="customChart">
                        <div class="card-header card-header-custom d-flex align-items-center">
                            <i class="fa-solid fa-chart-pie"></i>
                            <span style="font-weight: 500; margin-left: 8px;">Lead Substatus Overview</span>
                        </div>
                        <br />
                        <canvas id="leadSubStatusPieChart" width="400" height="400"></canvas>
                    </div>

                    <!-- ✅ Combined Script -->
                    <script>
                    (function() {
                        // -------------------------
                        // Lead Status Chart
                        // -------------------------
                        const leadStatusLabels = <?php echo json_encode($status_labels); ?>;
                        const leadStatusCounts = <?php echo json_encode($status_counts); ?>;
                        const leadStatusColors = <?php echo json_encode($status_colors); ?>;

                        const leadStatusCtx = document.getElementById('leadStatusPieChart').getContext('2d');

                        if (window.leadStatusPieChartInstance) {
                            window.leadStatusPieChartInstance.destroy();
                        }

                        window.leadStatusPieChartInstance = new Chart(leadStatusCtx, {
                            type: 'pie',
                            data: {
                                labels: leadStatusLabels,
                                datasets: [{
                                    data: leadStatusCounts,
                                    backgroundColor: leadStatusColors,
                                    borderWidth: 1,
                                    hoverOffset: 8
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.raw || 0;
                                                return ' ' + label + ': ' + value + ' leads';
                                            },
                                            labelColor: function(context) {
                                                const color = context.chart.data.datasets[0]
                                                    .backgroundColor[context.dataIndex];
                                                return {
                                                    borderColor: color,
                                                    backgroundColor: color,
                                                    borderWidth: 2,
                                                    borderRadius: 2
                                                };
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        // -------------------------
                        // Lead Substatus Chart
                        // -------------------------
                        const subStatusLabels = <?php echo json_encode($substatus_labels); ?>;
                        const subStatusCounts = <?php echo json_encode($substatus_counts); ?>;
                        const subStatusColors = <?php echo json_encode($substatus_colors); ?>;

                        const leadSubStatusCtx = document.getElementById('leadSubStatusPieChart').getContext('2d');

                        if (window.leadSubStatusPieChartInstance) {
                            window.leadSubStatusPieChartInstance.destroy();
                        }

                        window.leadSubStatusPieChartInstance = new Chart(leadSubStatusCtx, {
                            type: 'pie',
                            data: {
                                labels: subStatusLabels,
                                datasets: [{
                                    data: subStatusCounts,
                                    backgroundColor: subStatusColors,
                                    borderWidth: 1,
                                    hoverOffset: 8
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.raw || 0;
                                                return ' ' + label + ': ' + value + ' leads';
                                            },
                                            labelColor: function(context) {
                                                const color = context.chart.data.datasets[0]
                                                    .backgroundColor[context.dataIndex];
                                                return {
                                                    borderColor: color,
                                                    backgroundColor: color,
                                                    borderWidth: 2,
                                                    borderRadius: 2
                                                };
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    })();
                    </script>

                    <style>
                    .customChart {
                        background-color: #fff;
                        padding: 15px;
                        margin-top: 10px;
                        border-radius: 10px;
                        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                    }
                    </style>


                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <style>
    .customChart {
        background-color: #fff;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    </style>