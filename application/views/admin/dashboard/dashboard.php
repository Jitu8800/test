<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div id="wrapper">
    <div class="screen-options-area"></div>
    <div class="screen-options-btn">
        <button type="button" class="btn btn-sm btn-primary tw-mr-2" onclick="toggleAIInsights(event)">🤖 AI</button>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="tw-w-5 tw-h-5 tw-mr-1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <?php echo _l('dashboard_options'); ?>
    </div>

    <div class="content">
        <div class="row">
            <?php $this->load->view('admin/includes/alerts'); ?>
            <?php hooks()->do_action('before_start_render_dashboard_content'); ?>
            <div class="clearfix"></div>

            <?php
            $staff_id = get_staff_user_id();
$sql = 'SELECT tt.id AS id, tt.task_id, tt.start_time, tt.end_time, tt.staff_id,
                        tt.hourly_rate, tt.note, task.id AS task_ref_id, task.name, task.description
                        FROM tbltaskstimers AS tt
                        JOIN tbltasks AS task ON task.id = tt.task_id
                        WHERE tt.staff_id = ? AND tt.end_time IS NULL
                        ORDER BY tt.start_time DESC';
$startedTimers = $this->db->query($sql, [$staff_id])->result();
?>

            <style>
          
            .dashboard-shell {
                background: #f7fafb;
                padding: 28px;
                min-height: 100vh;
            }

            .dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }
            .header-title {
                font-size: 25px !important;
                font-weight: 700;
                color: #6741d9;
            }

            /* Search / Export bar */
            .dashboard-search-box {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background-color: #fff;
                padding: 10px 14px;
                margin-bottom: 12px;
                display: flex;
                justify-content: flex-end;
                align-items: center;
            }
            .dashboard-search-box input {
                width: 240px;
                height: 36px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                padding: 6px 10px;
                font-size: 14px;
                color: #111827;
            }
            #exportReportBtn { margin-left: 20px;
            background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
            color:#fff;
         }

            .dashboard-filter-box {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background-color: #fff;
                padding: 10px 16px;
                margin-bottom: 24px;
                overflow-x: auto;
            }
            .filter-row {
                display: flex;
                align-items: center;
                flex-wrap: nowrap;
                gap: 16px;
                min-width: max-content;
            }
            .filter-item {
                display: flex;
                align-items: center;
                white-space: nowrap;
            }
            .filter-item label {
                font-size: 13px;
                color: #374151;
                font-weight: 600;
                margin-right: 6px;
            }
            .filter-row select,
            .filter-row input[type="date"] {
                border: 1px solid #d1d5db;
                border-radius: 6px;
                padding: 5px 8px;
                background-color: #fff;
                font-size: 13px;
                color: #111827;
                height: 34px;
                min-width: 130px;
            }
            .filter-row select:focus,
            .filter-row input:focus {
                border-color: #6741d9;
                box-shadow: 0 0 0 1px #6741d9;
                outline: none;
            }

            /* ── GRADIENT SUMMARY CARDS ────────────────────────────────── */
            .sc-wrap {
                background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
                border-radius: 20px;
                padding: 28px;
                display: flex;
                align-items: center;
                gap: 20px;
                margin-bottom: 24px;
            }
            .sc-balance {
                min-width: 200px;
                color: #fff;
                flex-shrink: 0;
            }
            .sc-balance-title {
                font-size: 13px;
                font-weight: 500;
                opacity: 0.75;
                margin-bottom: 6px;
            }
            .sc-balance-amount {
                font-size: 32px;
                font-weight: 700;
                line-height: 1.1;
                margin-bottom: 18px;
                color: #fff;
            }
            .sc-leads-suffix {
                font-size: 14px;
                opacity: 0.6;
                font-weight: 400;
            }
            .sc-balance-row {
                display: flex;
                gap: 24px;
            }
            .sc-balance-item-label {
                font-size: 12px;
                opacity: 0.65;
                color: #fff;
                margin-bottom: 4px;
            }
            .sc-balance-item-val {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 15px;
                font-weight: 600;
                color: #fff;
            }
            .sc-icon {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                color: #fff;
                flex-shrink: 0;
            }
            .sc-icon-up   { background: rgba(255,255,255,0.25); }
            .sc-icon-down { background: rgba(255,255,255,0.15); }
            .sc-cards {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 14px;
                flex: 1;
            }
            .sc-card {
                background: rgba(255,255,255,0.18);
                border: 1px solid rgba(255,255,255,0.22);
                border-radius: 16px;
                padding: 16px;
                color: #fff;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sc-card-label {
                font-size: 11px;
                font-weight: 600;
                opacity: 0.7;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }
            .sc-card-value {
                font-size: 22px;
                font-weight: 700;
                line-height: 1;
            }
            .sc-card-sub {
                font-size: 12px;
                opacity: 0.65;
                display: flex;
                align-items: center;
                gap: 5px;
                margin-top: 2px;
            }
            .sc-dot {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                flex-shrink: 0;
            }
            .sc-dot-green { background: #69db7c; }
            .sc-dot-blue  { background: #a5d8ff; }
            .sc-dot-red   { background: #ff8787; }
            .sc-split {
                display: flex;
                align-items: stretch;
                flex: 1;
            }
            .sc-half {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sc-half:last-child { padding-left: 14px; }
            .sc-divider {
                width: 1px;
                background: rgba(255,255,255,0.25);
                margin: 0 4px;
                align-self: stretch;
            }
            @media (max-width: 992px) {
                .sc-wrap { flex-direction: column; }
                .sc-cards { grid-template-columns: repeat(2, 1fr); width: 100%; }
                .sc-balance { width: 100%; }
            }
            @media (max-width: 576px) {
                .sc-cards { grid-template-columns: 1fr; }
            }

            /* ── CARD BOX (chart cards) ─────────────────────────────────── */
            .card-box {
                background: #ffffff;
                border: 1px solid #e6e9ee;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 2px 0 rgba(0,0,0,0.01);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .card-box .table-responsive {
                max-height: 200px;
                overflow-y: auto;
                overflow-x: auto;
            }
            #latestLeadsTable { width: max-content; min-width: 100%; }

            .card-heading { font-weight: 700; color: #111827; margin-bottom: 6px; }
            .card-sub     { color: #6b7280; font-size: 13px; margin-bottom: 10px; }

            /* Chart containers */
            .chart-container {
                position: relative;
                height: 450px;
                width: 100%;
                overflow: hidden;
                flex-shrink: 0;
            }
            .chart-container canvas {
                position: absolute;
                top: 0; left: 0;
                width: 100% !important;
                height: 100% !important;
            }
            
            .chart-toggle-btn {
                display: block;
                margin: 8px auto 0;
                padding: 5px 16px;
                background: transparent;
                border: 1px solid #6741d9;
                border-radius: 4px;
                color: #6741d9;
                font-size: 13px;
                cursor: pointer;
                transition: background 0.2s, color 0.2s;
            }
            .chart-toggle-btn:hover { background:linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);color:#fff }

            /* ── SECTION CARDS ──────────────────────────────────────────── */
            .card {
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 3px 8px rgba(0,0,0,0.12);
                margin-top: 20px;
            }
            .card-body  { padding: 20px; }
            .card-header { padding: 0; }

            .custom-header {
                background:linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
                padding: 18px 20px;
                border-radius: 6px 6px 0 0;
            }
            .custom-header .live {
                font-size: 18px;
                font-weight: 700;
                color: #fff;
                letter-spacing: 0.5px;
            }

            /* Tables */
            .table th {
                background: #f8f9fc;
                font-weight: 600;
                font-size: 14px;
                padding: 12px;
            }
            .table td { padding: 12px; font-size: 14px; }
            .table > tbody > tr > td,
            .table > tfoot  > tr > td { text-transform: capitalize; }
            .table-light th,
            .table-light td { border-top: 1px solid #eef2f6; }

            /* Leaderboard */
            .leaderboard-table th,
            .leaderboard-table td { vertical-align: middle; border-top: 1px solid #eef2f6; }

            /* Attendance */
            #liveAttendance { background: white; border-radius: 12px; overflow: hidden; }
            #liveAttendance thead tr { background: #f3f6fa !important; }
            #liveAttendance th { font-size: 14px; padding: 14px; color: #374151; font-weight: 700; border-bottom: 1px solid #e5e7eb; }
            #liveAttendance td { padding: 14px; font-size: 14px; border-bottom: 1px solid #eef1f4; color: #4b5563; }
            #liveAttendance tbody tr:hover { background: #f9fafb; }

            /* ── AI SECTION ─────────────────────────────────────────────── */
            .ai-wrap {
                width: 100%;
                margin: 30px auto;
                background: #eef3fa;
                border-radius: 18px;
                padding: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,.25);
            }
            .ai-row { display: flex; gap: 10px; }
            .ai-card {
                border-radius: 16px;
                padding: 22px;
                color: #fff;
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
            .ai-card.left  { width: 36%; background: linear-gradient(180deg, #3b5bdb 0%, #6741d9 100%); }
            .ai-card.right { width: 64%; background: linear-gradient(180deg, #3b5bdb 0%, #6741d9 100%); }
            .card-title {
                font-size: 18px; font-weight: 600;
                margin-bottom: 16px; padding-bottom: 10px;
                border-bottom: 1px solid rgba(255,255,255,.35);
            }
            .item { margin: 16px 0; font-size: 15px; line-height: 1.5; }
            .item b { font-size: 18px; font-weight: 700; }
            .item span { margin-right: 6px; }
            .item.success { color: #dcfce7; }
            .sub { font-size: 12px; opacity: .9; margin-left: 24px; }
            .best-time {
                background: rgba(255,255,255,.18);
                padding: 14px; border-radius: 10px;
                margin: 14px 0 20px;
                font-size: 16px; font-weight: 600;
            }
            .best-time span { color: #fde68a; font-size: 18px; }
            .chart-title { text-align: center; font-size: 14px; font-weight: 500; margin-bottom: 14px; opacity: .95; }
            .bars { display: flex; align-items: flex-end; justify-content: space-between; height: 160px; padding: 0 8px; }
            .bar {
                width: 46px; position: relative;
                font-size: 12px; text-align: center; color: #1f2937;
                border-radius: 6px 6px 0 0;
                background: linear-gradient(180deg, #e5e7eb, #9ca3af);
                box-shadow: inset -4px 0 0 rgba(0,0,0,.15), inset 4px 0 0 rgba(255,255,255,.4), 0 10px 14px rgba(0,0,0,.25);
                animation: grow 1.2s cubic-bezier(.22,1,.36,1) forwards;
                display: flex; flex-direction: column; justify-content: flex-end;
            }
            .bar::before { content: ""; position: absolute; top: 0; left: 4px; right: 4px; height: 6px; background: rgba(255,255,255,.55); border-radius: 6px 6px 0 0; }
            .bar::after  { content: ""; position: absolute; top: 0; right: -6px; width: 6px; height: 100%; background: linear-gradient(180deg, #9ca3af, #6b7280); transform: skewY(-8deg); border-radius: 0 4px 0 0; }
            .bar small { font-size: 11px; padding: 6px 0; }
            .bar.highlight { background: linear-gradient(180deg, #fde047, #f59e0b); color: #111827; box-shadow: inset -4px 0 0 rgba(0,0,0,.2), inset 4px 0 0 rgba(255,255,255,.55), 0 12px 18px rgba(245,158,11,.5); }
            .bar.highlight::after { background: linear-gradient(180deg, #f59e0b, #b45309); }
            @keyframes grow { from { height: 0; } to { height: var(--h); } }
            .ai-tip { margin-top: 10px; background: linear-gradient(90deg, #e6f0ff, #cbdffd); color: #1e3a8a; padding: 20px 22px; border-radius: 14px; font-size: 15px; line-height: 1.55; }
            .ai-tip b { font-size: 17px; }
            .ai-header { position: relative; text-align: center; }
            .ai-header span { font-size: 16px !important; font-weight: 600; color: #1e3a8a; letter-spacing: 0.2px; }
            .ai-header::after { content: ""; display: block; width: 60px; height: 1px; margin: 6px auto 0; background-color: #dbeafe; }
            #ai_loading { padding: 20px; text-align: center; color: #888; font-size: 13px; }
            #aiInsights { transition: all 0.35s ease; }

            /* Smart tips */
            #ai_tips_list .smart-tip {
                display: flex; align-items: flex-start; gap: 10px;
                background: #f8f9fa; border-left: 3px solid #4e73df;
                border-radius: 4px; padding: 10px 14px; margin-bottom: 8px;
                font-size: 13px; line-height: 1.5;
            }
            #ai_tips_list .smart-tip .tip-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
            #ai_tips_list .smart-tip .tip-text { flex: 1; }

            /* ── REPORTS ─────────────────────────────────────────────────── */
            .report-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
            .report-month { margin-bottom: 12px; }
            .month-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .week-report { display: flex; justify-content: space-between; align-items: center; background: #f9fafb; border-radius: 8px; margin-bottom: 8px; padding: 8px 12px; }
            .week-date { font-size: 13px; color: #6b7280; }
            .report-link { font-size: 13px; color: #2563eb; text-decoration: none; display: flex; align-items: center; gap: 6px; }
            .report-link:hover { text-decoration: underline; }
            .btn-outline { width: 100%; margin-top: 10px; padding: 8px; border: 1px solid #d1d5db; border-radius: 8px; background: transparent; font-size: 13px; cursor: pointer; }
            .btn-outline:hover { background: #f3f4f6; }

            /* Productivity charts */
            .productivity-chart-box { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 10px 20px rgba(0,0,0,0.06); height: 420px; }
            .productivity-chart-heading { font-size: 17px; font-weight: 700; margin-bottom: 14px; color: #1f2937; }

            /* Timer cards */
            .timer-card {
                width: 23%; margin-right: 2%; margin-bottom: 22px; float: left;
                background: white; border-radius: 16px; padding: 20px;
                box-shadow: 0 10px 20px rgba(0,0,0,0.06);
                transition: all 0.25s ease;
            }
            .timer-card:hover { transform: translateY(-4px); box-shadow: 0 16px 24px rgba(0,0,0,0.12); }
            .timer-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; color: #333; }
            .timer-info { font-size: 0.9em; color: #777; margin-bottom: 10px; }
            .timer-actions { display: flex; justify-content: space-between; align-items: center; }
            .timer-delete { color: #dc3545; cursor: pointer; }
            .timer-delete:hover { color: #bd2130; }
            .btn-stop-timer { font-size: 0.9em; color: #fff; background-color: #6741d9; border: none; border-radius: 5px; padding: 5px 10px; }
            .btn-stop-timer:hover { background-color: #6741d9; }
            .btn-sm, .btn-group > .btn-sm, .btn-group-sm > .btn { float: right; }
            .col-md-3 { padding: 5px; }
            .clearfix::after { content: ""; display: table; clear: both; }
            </style>

            <!-- ── AI Insights Panel ─────────────────────────────────────────── -->
            <div class="ai-wrap collapse" id="aiInsights">
                <div class="ai-header">
                    <span>AI-Driven Performance Insights</span>
                </div>
                <div class="ai-row">
                    <div class="ai-card left">
                        <div class="card-title">Top Performing Campaign</div>
                        <div class="item"><span>🏆</span><span id="ai_campaign_name">--</span></div>
                        <div class="item success">
                            <span>✔</span> Success Rate: <b id="ai_success_rate">--%</b>
                            <div class="sub" id="ai_weekly_change"></div>
                        </div>
                        <div class="item"><span>📞</span> Total Calls: <b id="ai_total_calls">--</b></div>
                        <div class="item"><span>✔</span> Successful Calls: <b id="ai_success_calls">--</b></div>
                    </div>
                    <div class="ai-card right">
                        <div class="card-title">Peak Calling Hours</div>
                        <div class="best-time">⏰ Best Time to Call: <span id="ai_best_time">--</span></div>
                        <div class="chart-title">Call Success Rate by Hour</div>
                        <div class="bars" id="ai_peak_bars"></div>
                    </div>
                </div>
                <div id="ai_tips_section" style="display:none; margin-top:15px;">
                    <div style="font-weight:600; margin-bottom:10px; font-size:14px;">💡 Smart Tips</div>
                    <div id="ai_tips_list"></div>
                </div>
                <div id="ai_loading" style="display:none;">
                    <i class="fa fa-spinner fa-spin"></i> Analysing your data...
                </div>
                <div class="ai-tip" id="ai_tip"></div>
            </div>

            <div class="clearfix"></div>

            <!-- ====== MAIN DASHBOARD ====== -->
            <div class="content">
                <div class="dashboard-shell container-fluid">

                    <!-- Header -->
                    <div class="dashboard-header d-flex justify-content-between align-items-center mb-3">
                        <div class="header-title">Businessbay Dashboard</div>
                        <div class="dashboard-search-box">
                            <input type="text" id="searchBox" placeholder="Search..." />
                            <button class="btn" type="button" id="exportReportBtn">
                                <i class="fa fa-file-alt"></i> Export Reports
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="dashboard-filter-box">
                        <div class="filter-row">
                            <div class="filter-item">
                                <input type="text" name="date_range" id="date_range" class="form-control flatpickr-input active" placeholder="Select Date Range" readonly="readonly">
                            </div>
                            <div class="filter-item">
                                <label for="filterExecutive">Executive</label>
                                <select id="filterExecutive">
                                    <option value="">All</option>
                                    <?php foreach ($staffs as $staff) { ?>
                                    <option value="<?php echo $staff['staffid']; ?>"><?php echo $staff['firstname'].' '.$staff['lastname']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label for="filterStatus">Status</label>
                                <select id="filterStatus">
                                    <option value="">All</option>
                                    <?php foreach ($statuses as $status) { ?>
                                    <option value="<?php echo $status['id']; ?>"><?php echo $status['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label for="filterCountry">Country</label>
                                <select id="filterCountry">
                                    <option value="">All</option>
                                    <?php foreach ($countries as $country) {
                                        if ($country['country'] == '') {
                                            continue;
                                        }
                                        $val = htmlspecialchars($country['country'], ENT_QUOTES, 'UTF-8');
                                        $label = $country['country_name'] ?? $val; ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label for="filterCity">City</label>
                                <select id="filterCity">
                                    <option value="">All</option>
                                    <?php foreach ($cities as $city) {
                                        if ($city['city'] == '') {
                                            continue;
                                        } ?>
                                    <option value="<?php echo $city['city']; ?>"><?php echo $city['city']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label for="filterCampaign">Campaign</label>
                                <select id="filterCampaign">
                                    <option value="">All</option>
                                    <?php foreach ($campaign as $c) { ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="filter-item">
                                <label for="filterRevenue">Revenue</label>
                                <select id="filterRevenue">
                                    <option value="">All</option>
                                    <option>Last 7 Days</option>
                                    <option>Last 30 Days</option>
                                    <option>This Month</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ── GRADIENT SUMMARY CARDS ────────────────────────────── -->
                    <div class="sc-wrap">
                        <div class="sc-balance">
                            <div class="sc-balance-title">Dashboard Overview</div>
                            <div class="sc-balance-amount">
                                <span id="totalLeads">0</span> <span class="sc-leads-suffix">leads</span>
                            </div>
                            <div class="sc-balance-row">
                                <div class="sc-balance-item">
                                    <div class="sc-balance-item-label">Total Calls</div>
                                    <div class="sc-balance-item-val">
                                        <div class="sc-icon sc-icon-up">↑</div>
                                        <span id="totalCalls">0</span>
                                    </div>
                                </div>
                                <div class="sc-balance-item">
                                    <div class="sc-balance-item-label">Follow-ups</div>
                                    <div class="sc-balance-item-val">
                                        <div class="sc-icon sc-icon-down">↓</div>
                                        <span id="totalFollow">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sc-cards">
                            <div class="sc-card">
                                <div class="sc-split">
    <div class="sc-half">
        <div class="sc-card-label">Leads</div>
        <div class="sc-card-value" id="totalLeads2">0</div>
        <div class="sc-card-sub">
            <span class="sc-dot sc-dot-green"></span>
            Today: <span id="todayLeads2">0</span>
        </div>
    </div>
    <div class="sc-divider"></div>
    <div class="sc-half">
        <div class="sc-card-label">Calls</div>
        <div class="sc-card-value" id="totalCalls2">0</div>
        <div class="sc-card-sub">
            <span class="sc-dot sc-dot-blue"></span>
            Today: <span id="todayCalls2">0</span>
        </div>
    </div>
</div>
                            </div>
                            <div class="sc-card">
                                <div class="sc-card-label">Executives</div>
                                <div class="sc-card-value" id="totalExecutives">0</div>
                                <div class="sc-card-sub">
                                    <span class="sc-dot sc-dot-green"></span>
                                    <span id="todayExecutives">0</span> Logged in today
                                </div>
                            </div>
                            <div class="sc-card">
                                <div class="sc-card-label">Payments</div>
                                <div class="sc-card-value" id="totalPayout">₹0</div>
                                <div class="sc-card-sub">
                                    <span class="sc-dot sc-dot-blue"></span>
                                    <span id="todayPayout">₹0</span> Today
                                </div>
                            </div>
                            <div class="sc-card">
                                <div class="sc-card-label">Pending Follow-ups</div>
                                <div class="sc-card-value" id="totalFollow2">0</div>
                                <div class="sc-card-sub">
                                    <span class="sc-dot sc-dot-red"></span>
                                    <span id="todayFollow2">0</span> Due today
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card-box h-100 d-flex flex-column">
                                <div class="card-heading">Leads</div>
                                <div class="card-sub">Leads by Status</div>
                                <div class="chart-container"><canvas id="leadsPieChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-box h-100 d-flex flex-column">
                                <div class="card-heading">Attendance</div>
                                <div class="card-sub">Present / Late / Absent</div>
                                <div class="chart-container mt-3"><canvas id="attendancePie"></canvas></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-box h-100 d-flex flex-column">
                                <div class="card-heading">Payments</div>
                                <div class="card-sub">Daily / Monthly Revenue</div>
                                <div class="chart-container"><canvas id="paymentsPie"></canvas></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-box h-100 d-flex flex-column">
                                <div class="card-heading">Campaigns</div>
                                <div class="card-sub">Campaign Performance</div>
                                <div class="chart-container"><canvas id="campaignPie"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Report -->
                    <div class="card">
                        <div class="card-header custom-header d-flex justify-content-between align-items-center">
                            <span class="live" data-default-name="<?php echo get_staff_full_name(get_staff_user_id()); ?>">
                                <?php echo get_staff_full_name(get_staff_user_id()); ?> Report
                            </span>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#staffReportCollapse">View</button>
                        </div>
                        <div id="staffReportCollapse" class="collapse">
                            <div class="table-responsive mt-3">
                                <table class="table table-bordered" id="latestLeadsTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>City</th>
                                            <th>Executive</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Recording</th>
                                            <th>Answered By</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div id="inlineAudioWrap" style="display:none; margin-top:10px;">
                                <audio id="callPlayer" controls style="width:100%;">
                                    <source id="callPlayerSource" src="" type="audio/mpeg">
                                </audio>
                            </div>
                        </div>
                    </div>

                    <!-- Live Attendance -->
                    <div class="card">
                        <div class="card-header custom-header d-flex align-items-center">
                            <span class="live">Live Attendance & Productivity</span>
                            <button class="btn btn-sm btn-outline-primary ml-auto" type="button" data-toggle="collapse" data-target="#attendanceCollapse">View</button>
                        </div>
                        <div id="attendanceCollapse" class="collapse">
                            <div class="card-body">
                                <table class="table table-bordered" id="liveAttendance">
                                    <thead>
                                        <tr>
                                            <th>Executive</th>
                                            <th>Status</th>
                                            <th>Total Work</th>
                                            <th>Break Time</th>
                                            <th>Working Time</th>
                                            <?php if (!is_admin()) { ?><th>Actions</th><?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody id="attBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Performance & Reports -->
                    <div class="card">
                        <div class="card-header custom-header d-flex align-items-center justify-content-between">
                            <span class="live">Performance & Reports</span>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#performanceCollapse">View</button>
                        </div>
                        <div id="performanceCollapse" class="collapse">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="card-box">
                                            <div class="card-heading">Performance Leaderboard</div>
                                            <div class="table-responsive mt-3">
                                                <table class="table leaderboard-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Rank</th>
                                                            <th data-sort="executive" class="sortable" style="cursor:pointer">Executive ↕</th>
                                                            <th data-sort="conversion" class="sortable" style="cursor:pointer">Conversion ↕</th>
                                                            <th data-sort="revenue" class="sortable" style="cursor:pointer">Revenue ↕</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="leaderboard-body"></tbody>
                                                </table>
                                                <nav><ul class="pagination" id="leaderboard-pagination"></ul></nav>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card-box">
                                            <div class="card-heading">Current Month Reports</div>
                                            <div class="report-card">
                                                <?php
                                                $lastMonth = '';
foreach ($current_reports as $row) {
    $monthLabel = date('F Y', strtotime($row->week_start));
    if ($lastMonth !== $monthLabel) {
        if ($lastMonth !== '') {
            echo '</div>';
        }
        echo '<div class="report-month"><div class="month-title">'.$monthLabel.'</div>';
        $lastMonth = $monthLabel;
    }
    ?>
                                                <div class="week-report">
                                                    <span class="week-date">
                                                        <?php echo date('d M', strtotime($row->week_start)); ?> –
                                                        <?php echo date('d M Y', strtotime($row->week_end)); ?>
                                                    </span>
                                                    <a href="<?php echo base_url($row->file_path); ?>" class="report-link" target="_blank">
                                                        Executive Report <i class="fa fa-download"></i>
                                                    </a>
                                                </div>
                                                <?php } if ($lastMonth) {
                                                    echo '</div>';
                                                } ?>
                                            </div>
                                            <button id="showOlderBtn" class="btn-outline">Load More...</button>
                                            <div id="olderReports"></div>
                                            <button id="loadMoreBtn" class="btn-outline" style="display:none;">Load More</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Productivity Charts -->
                    <div class="row g-3 mb-4" style="margin-top:20px">
                        <div class="col-md-6">
                            <div class="productivity-chart-box">
                                <div class="productivity-chart-heading">Top productive users</div>
                                <div id="topProductiveChart" style="height:400px;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="productivity-chart-box">
                                <div class="productivity-chart-heading">Top unproductive users</div>
                                <div id="topUnproductiveChart" style="height:400px;"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Approval Center -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4 class="no-margin">Approval Center</h4>
                            <hr class="hr-panel-heading" />
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th><th>Lead</th><th>Company</th>
                                        <th>Level</th><th>Requested By</th><th>Created</th><th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvals as $a) { ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $a->entity_type)); ?></td>
                                        <td><?php echo $a->name; ?></td>
                                        <td><?php echo $a->company; ?></td>
                                        <td>Level <?php echo $a->current_level; ?> / <?php echo $a->total_levels; ?></td>
                                        <td><?php echo get_staff_full_name($a->requested_by); ?></td>
                                        <td><?php echo _dt($a->created_at); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('approvals/approve/'.$a->id); ?>" class="btn btn-success btn-sm">Approve</a>
                                            <a href="<?php echo admin_url('approvals/reject/'.$a->id); ?>"  class="btn btn-danger  btn-sm">Reject</a>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8" data-container="left-8"><?php render_dashboard_widgets('left-8'); ?></div>
            <div class="col-md-4" data-container="right-4"><?php render_dashboard_widgets('right-4'); ?></div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>

<script>app.calendarIDs = '<?php echo json_encode($google_ids_calendars); ?>';</script>
<?php init_tail(); ?>
<?php $this->load->view('admin/utilities/calendar_template'); ?>
<?php $this->load->view('admin/dashboard/dashboard_js'); ?>

<!-- BLOCK 1: Core utilities -->
<script>
const IS_ADMIN  = <?php echo is_admin() ? 'true' : 'false'; ?>;
const CURRENT_UID = <?php echo (int) get_staff_user_id(); ?>;

function escapeHtml(s) {
    return s ? String(s).replace(/[&<>"'`]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#x60;'}[m])) : '';
}
</script>

<!-- BLOCK 2: Main dashboard logic -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    let leadsPie = null, paymentsPie = null, attendancePie = null, campaignPie = null;

    function debounce(fn, delay) {
        let t;
        return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); };
    }

    function formatDuration(sec) {
        if (!sec || isNaN(sec)) return '00:00';
        return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    }

    // Flatpickr
    if (document.querySelector('#date_range')) {
        flatpickr('#date_range', { mode: 'range', dateFormat: 'Y-m-d', onClose: debounce(fetchDashboardData, 300) });
    }

    // Filter listeners
    ['#filterExecutive','#filterStatus','#filterCountry','#filterCity','#filterCampaign','#filterRevenue']
        .forEach(sel => { const el = document.querySelector(sel); if (el) el.addEventListener('change', debounce(fetchDashboardData, 400)); });

    // Export
    document.querySelector('#exportReportBtn')?.addEventListener('click', function () {
        const f = getFilters();
        if (!f.date_range) { alert('Please select a date range before exporting.'); return; }
        window.location.href = '<?php echo admin_url('dashboard/export_excel'); ?>?' + $.param(f);
    });

    // Report title follows executive filter
    const execFilter  = document.querySelector('#filterExecutive');
    const reportTitle = document.querySelector('.card-header .live');
    const defaultName = reportTitle?.getAttribute('data-default-name') || '';
    if (execFilter && reportTitle) {
        execFilter.addEventListener('change', function () {
            reportTitle.textContent = this.value ? this.options[this.selectedIndex].text + ' Report' : defaultName + ' Report';
        });
    }

    let isFetching = false, pendingFetch = false;

    function getFilters() {
        return {
            view_assigned : $('#filterExecutive').val() || '',
            status        : $('#filterStatus').val()    || '',
            country       : $('#filterCountry').val()   || '',
            city          : $('#filterCity').val()       || '',
            campaign      : $('#filterCampaign').val()  || '',
            revenue       : $('#filterRevenue').val()   || '',
            date_range    : $('#date_range').val()       || ''
        };
    }

    function fetchDashboardData() {
        if (isFetching) { pendingFetch = true; return; }
        isFetching = true;
        $.ajax({
            url      : '<?php echo admin_url('dashboard/filter_dashboard'); ?>',
            type     : 'POST',
            data     : getFilters(),
            dataType : 'json',
            timeout  : 15000,
            success  : function (res) {
                if (!res) return;
                updateSummary(res.summary || {});
                updateTable(res.table || []);
                renderLeadsPie(res.chart || { labels: [], values: [], colors: [] });
                renderAttendancePie(res.attendance_chart || []);
                renderPaymentsChart(res.payments_chart || []);
                renderCampaignChart(res.campaign_chart || []);
                fetchDashboardHeavy();
            },
            error    : function (xhr, status, err) { console.error('Dashboard load failed', status, err); },
            complete : function () {
                isFetching = false;
                if (pendingFetch) { pendingFetch = false; fetchDashboardData(); }
            }
        });
    }

    function fetchDashboardHeavy() {
        $('#ai_loading').show();
        $('#ai_tips_section').hide();
        $.ajax({
            url      : '<?php echo admin_url('dashboard/filter_dashboard_heavy'); ?>',
            type     : 'POST',
            data     : getFilters(),
            dataType : 'json',
            timeout  : 30000,
            success  : function (res) {
                if (!res) return;
                if (res.ai_insights)                                      { renderAI(res.ai_insights); renderSmartTips(res.ai_insights.smart_tips || []); }
                if (res.leaderboard  && typeof renderLeaderboard  === 'function') renderLeaderboard(res.leaderboard, res.total_leaderboard);
                if (res.attendance_stats && typeof renderAttendanceStats === 'function') renderAttendanceStats(res.attendance_stats);
                if (res.payments_today   && typeof renderPaymentsToday   === 'function') renderPaymentsToday(res.payments_today);
                if (res.followups        && typeof renderFollowups        === 'function') renderFollowups(res.followups);
            },
            error    : function (xhr, status, err) { console.warn('Heavy dashboard failed:', status, err); },
            complete : function () { $('#ai_loading').hide(); }
        });
    }

    fetchDashboardData();
    setInterval(fetchDashboardData, 60000);

    // Summary cards
    function updateSummary(s) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        const fmt = v => '₹' + parseFloat(v || 0).toLocaleString('en-IN');

        const leads = s.total_leads ?? 0;
        const calls = s.total_calls ?? 0;

        set('totalLeads',       leads);
        set('totalLeads2',      leads);
        set('totalCalls',       calls);
        set('totalCalls2',      calls);
        set('totalExecutives',  s.total_executives ?? 0);
        set('todayExecutives',  s.today_executives ?? 0);
        set('totalFollow',      s.total_follow     ?? 0);
        set('todayFollow',      s.today_follow     ?? 0);
        set('totalFollow2',     s.total_follow     ?? 0);
        set('todayFollow2',     s.today_follow     ?? 0);
        set('todayLeads2',     s.today_leads      ?? 0); // ✅ added
        set('todayCalls2',     s.today_calls      ?? 0); // ✅ added

        const payout = document.getElementById('totalPayout');
        if (payout) payout.textContent = fmt(s.total_payment);
        const todayPayout = document.getElementById('todayPayout');
        if (todayPayout) todayPayout.textContent = fmt(s.today_payment);
    }

    // Leads table
    function updateTable(leads) {
        const tbody = document.querySelector('#latestLeadsTable tbody');
        if (!tbody) return;
        if (!Array.isArray(leads) || !leads.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No leads found</td></tr>';
            return;
        }
        tbody.innerHTML = leads.map(l => {
            const rec = l.recording_url || '';
            return `<tr>
                <td>${escapeHtml(l.name           || '-')}</td>
                <td>${escapeHtml(l.city           || '-')}</td>
                <td>${escapeHtml(l.assigned_name  || '-')}</td>
                <td>${formatDuration(l.duration)}</td>
                <td>${escapeHtml(l.call_status || l.status || '-')}</td>
                <td class="text-center">${rec ? `<button class="play-btn btn btn-xs btn-default" data-url="${escapeHtml(rec)}">▶️</button>` : '-'}</td>
                <td>${escapeHtml(l.answered_agent_name || '-')}</td>
            </tr>`;
        }).join('');
        tbody.querySelectorAll('.play-btn').forEach(btn =>
            btn.addEventListener('click', () => playRecording(btn.dataset.url))
        );
    }

    window.playRecording = function (url) {
        if (!url) { alert('Recording not available'); return; }
        const src = document.getElementById('callPlayerSource');
        const pl  = document.getElementById('callPlayer');
        if (!src || !pl) return;
        src.src = url; pl.load(); pl.play().catch(() => {});
        const wrap = document.getElementById('inlineAudioWrap');
        if (wrap) wrap.style.display = 'block';
    };

    // Charts
    const COLORS = ['#6741d9','#D7B105','#e51429','#FF7A59','#7D3C98','#1ABC9C','#F39C12','#2011e8'];
    const CHART_PREVIEW_LIMIT = 15;
    const chartDataStore = {};

    function defaultColor(i) { return COLORS[i % COLORS.length]; }

    function upsertToggleButton(canvasId, totalCount, onToggle) {
        if (totalCount <= CHART_PREVIEW_LIMIT) { document.getElementById(`${canvasId}-toggle`)?.remove(); return null; }
        let btn = document.getElementById(`${canvasId}-toggle`);
        if (!btn) {
            btn = document.createElement('button');
            btn.id = `${canvasId}-toggle`;
            btn.className = 'chart-toggle-btn';
            btn.dataset.expanded = 'false';
            document.getElementById(canvasId)?.parentElement?.insertAdjacentElement('afterend', btn);
            btn.addEventListener('click', () => {
                const expanded = btn.dataset.expanded === 'true';
                btn.dataset.expanded = String(!expanded);
                btn.textContent = !expanded ? 'Show Less ▲' : `Load More ▼ (${totalCount - CHART_PREVIEW_LIMIT} hidden)`;
                onToggle(!expanded);
            });
        }
        if (btn.dataset.expanded !== 'true') {
            btn.dataset.expanded = 'false';
            btn.textContent = `Load More ▼ (${totalCount - CHART_PREVIEW_LIMIT} hidden)`;
        }
        return btn;
    }

    function upsertPie(instance, canvasId, labels, values, colors, legendPos, showAll = false) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return instance;
        const slice = showAll ? labels.length : Math.min(CHART_PREVIEW_LIMIT, labels.length);
        const vLabels = labels.slice(0, slice), vValues = values.slice(0, slice), vColors = colors.slice(0, slice);
        if (instance) {
            instance.data.labels = vLabels;
            instance.data.datasets[0].data = vValues;
            instance.data.datasets[0].backgroundColor = vColors;
            instance.update('none'); return instance;
        }
        return new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: { labels: vLabels, datasets: [{ data: vValues, backgroundColor: vColors }] },
            options: { responsive: true, maintainAspectRatio: false, animation: { duration: 400 }, plugins: { legend: { position: legendPos || 'top' } } }
        });
    }

    function renderPieChart(canvasId, labels, values, colors, legendPos, instanceRef, setInstance) {
        chartDataStore[canvasId] = { labels, values, colors, legendPos };
        const btn = document.getElementById(`${canvasId}-toggle`);
        const showAll = btn?.dataset.expanded === 'true';
        const chart = upsertPie(instanceRef, canvasId, labels, values, colors, legendPos, showAll);
        setInstance(chart);
        upsertToggleButton(canvasId, labels.length, (expanded) => {
            const d = chartDataStore[canvasId];
            setInstance(upsertPie(chart, canvasId, d.labels, d.values, d.colors, d.legendPos, expanded));
        });
    }

    function renderLeadsPie(d) {
        const labels = d.labels || [], values = d.values || [];
        const colors = d.colors?.length ? d.colors : labels.map((_, i) => defaultColor(i));
        renderPieChart('leadsPieChart', labels, values, colors, 'top', leadsPie, c => { leadsPie = c; });
    }
    function renderAttendancePie(data) {
        if (!data?.length) return;
        renderPieChart('attendancePie', data.map(r => r.label), data.map(r => r.value || 0), data.map((_, i) => defaultColor(i)), 'bottom', attendancePie, c => { attendancePie = c; });
    }
    function renderPaymentsChart(data) {
        if (!data?.length) return;
        renderPieChart('paymentsPie', data.map(r => r.label), data.map(r => r.value || 0), data.map((_, i) => defaultColor(i)), 'bottom', paymentsPie, c => { paymentsPie = c; });
    }
    function renderCampaignChart(data) {
        if (!data?.length) return;
        renderPieChart('campaignPie', data.map(r => r.label), data.map(r => r.value || 0), data.map((_, i) => defaultColor(i)), 'bottom', campaignPie, c => { campaignPie = c; });
    }

    // AI Insights
    function renderAI(res) {
        if (!res?.best_campaign) return;
        $('#ai_campaign_name').text(res.best_campaign.campaign_name || '--');
        $('#ai_success_rate').text((res.best_campaign.success_rate || 0) + '%');
        $('#ai_total_calls').text(res.best_campaign.total_calls || 0);
        $('#ai_success_calls').text(Math.round((res.best_campaign.total_calls || 0) * (res.best_campaign.success_rate || 0) / 100));
        if (res.weekly_summary) {
            const diff = +((res.best_campaign.success_rate || 0) - (res.weekly_summary.success_rate || 0)).toFixed(1);
            $('#ai_weekly_change').text((diff >= 0 ? '+' : '') + diff + '% vs last week avg');
        }
        if (res.peak_hours?.length) {
            const validHours = res.peak_hours.filter(h => h.total_calls >= 5);
            const topTwo = validHours.slice().sort((a, b) => b.rate - a.rate).slice(0, 2).map(h => h.hour);
            let barsHtml = '', bestHours = [];
            res.peak_hours.forEach(h => {
                const isPeak = topTwo.includes(h.hour);
                if (isPeak) bestHours.push(h.hour + ':00');
                barsHtml += `<div class="bar ${isPeak ? 'highlight' : ''}" style="--h:${h.rate}%">${h.total_calls > 0 ? h.rate + '%' : '--'}<small>${h.hour}h</small></div>`;
            });
            bestHours.sort();
            $('#ai_peak_bars').html(barsHtml);
            if (bestHours.length) {
                $('#ai_best_time').text(bestHours.join(' – '));
                $('#ai_tip').html('💡 <b>Smart Tip</b><br>Your best hours are <b>' + bestHours.join(' & ') + '</b>. Schedule your senior agents during these windows for maximum conversions.');
            }
        }
    }

    function renderSmartTips(tips) {
        if (!tips?.length) return;
        $('#ai_tips_list').html(tips.map(t => `<div class="smart-tip"><span class="tip-icon">${t.icon}</span><span class="tip-text">${t.text}</span></div>`).join(''));
        $('#ai_tips_section').show();
    }

    window.toggleAIInsights = function (e) { e.preventDefault(); e.stopImmediatePropagation(); $('#aiInsights').collapse('toggle'); };
});
</script>

<!-- BLOCK 3: Leaderboard -->
<script>
let lbPage = 1, lbLimit = 5, lbSort = 'revenue', lbOrder = 'DESC';

function loadLeaderboard() {
    $.post('<?php echo admin_url('dashboard/filter_dashboard_heavy'); ?>',
        { limit: lbLimit, offset: (lbPage - 1) * lbLimit, sort_by: lbSort, sort_order: lbOrder },
        function (res) {
            const base = (lbPage - 1) * lbLimit;
            let rows = '';
            if (res.leaderboard?.length) {
                res.leaderboard.forEach((r, i) => {
                    rows += `<tr><td>${base + i + 1}</td><td>${escapeHtml(r.executive)}</td><td>${r.conversion}%</td><td>₹${parseFloat(r.revenue || 0).toLocaleString('en-IN')}</td></tr>`;
                });
            } else {
                rows = '<tr><td colspan="4" class="text-center text-muted">No data found</td></tr>';
            }
            $('#leaderboard-body').html(rows);
            const total = Math.ceil((res.total_leaderboard || 0) / lbLimit);
            let pag = '';
            for (let i = 1; i <= total; i++) pag += `<li class="page-item ${i === lbPage ? 'active' : ''}"><a class="page-link" href="#">${i}</a></li>`;
            $('#leaderboard-pagination').html(pag);
        }, 'json');
}

$(document).on('click', '.sortable', function () {
    const s = $(this).data('sort');
    lbOrder = (lbSort === s && lbOrder === 'ASC') ? 'DESC' : 'ASC';
    lbSort = s;
    loadLeaderboard();
});
$(document).on('click', '.page-link', function (e) { e.preventDefault(); lbPage = parseInt($(this).text()); loadLeaderboard(); });
loadLeaderboard();
</script>

<!-- BLOCK 4: Live Attendance -->
<script>
let liveData = [];

function loadAttendanceFromServer() {
    $.get('<?php echo admin_url('dashboard/get_live_attendance'); ?>', function (res) {
        liveData = Array.isArray(res) ? res : [];
        renderAttendance();
    });
}

function fmtSec(sec) {
    sec = Math.max(0, parseInt(sec) || 0);
    return Math.floor(sec / 3600) + 'H : ' + String(Math.floor((sec % 3600) / 60)).padStart(2, '0') + 'M';
}

function renderAttendance() {
    const now = Math.floor(Date.now() / 1000);
    let html = '';
    liveData.forEach(r => {
        let total = parseInt(r.total_work_seconds ?? 0);
        let breakSec = parseInt(r.break_time_seconds ?? 0);
        if (r.status === 'CHECKED_IN' && r.check_in_time) {
            const lastIn = Math.floor(new Date(r.check_in_time).getTime() / 1000);
            if (!r.includes_live) {
                total += Math.max(0, now - lastIn);
                if (r.is_on_break == 1 && r.break_start_time) {
                    breakSec += Math.max(0, now - Math.floor(new Date(r.break_start_time).getTime() / 1000));
                }
            }
        }
        const productive = Math.max(0, total - breakSec);
        const actionsCell = IS_ADMIN ? '' : `<td>${buildAttBtns(r)}</td>`;
        html += `<tr>
            <td>${escapeHtml(r.executive || '')}</td>
            <td>${escapeHtml(r.status    || '')}</td>
            <td>${fmtSec(total)}</td>
            <td>${fmtSec(breakSec)}</td>
            <td>${fmtSec(productive)}</td>
            ${actionsCell}
        </tr>`;
    });
    document.getElementById('attBody').innerHTML = html;
}

function buildAttBtns(r) {
    if (IS_ADMIN || r.staffid != CURRENT_UID) return '';
    const checkBtn = r.status === 'CHECKED_IN'
        ? '<button onclick="manualCheckOut()" class="btn btn-danger  btn-sm">Check Out</button>'
        : '<button onclick="manualCheckIn()"  class="btn btn-success btn-sm">Check In</button>';
    const breakBtn = r.is_on_break == 1
        ? '<button onclick="endBreak()"   class="btn btn-warning btn-sm">End Break</button>'
        : '<button onclick="startBreak()" class="btn btn-info    btn-sm">Start Break</button>';
    return checkBtn + ' ' + breakBtn;
}

function manualCheckIn()  { $.get('<?php echo admin_url('dashboard/manual_check_in'); ?>', loadAttendanceFromServer); }
function manualCheckOut() { $.get('<?php echo admin_url('dashboard/manual_check_out'); ?>', loadAttendanceFromServer); }
function startBreak()     { $.get('<?php echo admin_url('dashboard/start_break'); ?>', loadAttendanceFromServer); }
function endBreak()       { $.get('<?php echo admin_url('dashboard/end_break'); ?>', loadAttendanceFromServer); }

setInterval(loadAttendanceFromServer, 15000);
setInterval(renderAttendance, 1000);
loadAttendanceFromServer();
</script>

<!-- BLOCK 5: Productivity Charts -->
<script>
let prodChart = null, unpChart = null;

function secToHM(sec) {
    sec = Math.max(0, parseInt(sec) || 0);
    return Math.floor(sec / 3600) + 'H : ' + Math.floor((sec % 3600) / 60) + 'M';
}

function buildCharts(data) {
    if (!Array.isArray(data) || !data.length) return;
    data.forEach(d => {
        d.productive_seconds   = parseInt(d.productive_seconds   || 0);
        d.unproductive_seconds = parseInt(d.unproductive_seconds || 0);
        d.neutral_seconds      = parseInt(d.neutral_seconds      || 0);
    });
    const TOP = 8;
    const topProd = data.slice().sort((a, b) => b.productive_seconds   - a.productive_seconds).slice(0, TOP);
    const topUnp  = data.slice().sort((a, b) => b.unproductive_seconds - a.unproductive_seconds).slice(0, TOP);
    topProd.forEach(x => x.unproductive_seconds = -Math.abs(x.unproductive_seconds));
    topUnp.forEach( x => x.unproductive_seconds = -Math.abs(x.unproductive_seconds));

    function makeOpts(list) {
        return {
            chart:       { type: 'bar', height: 420, stacked: true, toolbar: { show: false }, animations: { enabled: false } },
            plotOptions: { bar: { horizontal: false, borderRadius: 4 } },
            dataLabels:  { enabled: true, formatter: s => secToHM(Math.abs(s)), style: { fontSize: '13px', fontWeight: 'bold' } },
            xaxis:       { categories: list.map(x => x.executive), labels: { rotate: -45 } },
            yaxis:       { labels: { formatter: s => secToHM(Math.abs(s)) } },
            tooltip:     { y: { formatter: s => secToHM(Math.abs(s)) } },
            series: [
                { name: 'Neutral',      data: list.map(x => x.neutral_seconds),      color: '#999'     },
                { name: 'Unproductive', data: list.map(x => x.unproductive_seconds), color: '#ee1c1c'  },
                { name: 'Productive',   data: list.map(x => x.productive_seconds),   color: '#6741d9'  }
            ]
        };
    }

    if (prodChart) { prodChart.updateOptions(makeOpts(topProd), false, false); prodChart.updateSeries(makeOpts(topProd).series); }
    else { prodChart = new ApexCharts(document.querySelector('#topProductiveChart'),   makeOpts(topProd)); prodChart.render(); }

    if (unpChart)  { unpChart.updateOptions(makeOpts(topUnp),  false, false); unpChart.updateSeries(makeOpts(topUnp).series);  }
    else { unpChart  = new ApexCharts(document.querySelector('#topUnproductiveChart'), makeOpts(topUnp));  unpChart.render();  }
}

$(function () {
    const today = new Date().toISOString().slice(0, 10);
    function refreshProd() {
        $.get('<?php echo admin_url('dashboard/get_productivity_stats'); ?>', { start: today, end: today }, buildCharts);
    }
    refreshProd();
    setInterval(refreshProd, 60000);
});
</script>

<!-- BLOCK 6: Older Reports -->
<script>
let reportOffset = 0, reportLastMonth = '';

$('#showOlderBtn').on('click', function () {
    reportOffset = 0; reportLastMonth = '';
    $('#olderReports').html('');
    loadOlderReports();
    $(this).hide();
});
$('#loadMoreBtn').on('click', loadOlderReports);

function loadOlderReports() {
    $.ajax({
        url: '<?php echo admin_url('dashboard/load_older_reports'); ?>',
        type: 'POST', data: { offset: reportOffset }, dataType: 'json',
        success: function (data) {
            if (!data?.length) { $('#loadMoreBtn').hide(); return; }
            let html = '';
            data.forEach(row => {
                const monthLabel = new Date(row.week_start).toLocaleString('default', { month: 'long', year: 'numeric' });
                if (reportLastMonth !== monthLabel) {
                    if (reportLastMonth) html += '</div>';
                    html += `<div class="report-month"><div class="month-title">${monthLabel}</div>`;
                    reportLastMonth = monthLabel;
                }
                html += `<div class="week-report">
                    <span class="week-date">${fmtDate(row.week_start)} – ${fmtDate(row.week_end)}</span>
                    <a href="<?php echo base_url(); ?>${row.file_path}" class="report-link" target="_blank">Executive Report <i class="fa fa-download"></i></a>
                </div>`;
            });
            html += '</div>';
            $('#olderReports').append(html);
            reportOffset += data.length;
            $('#loadMoreBtn').show();
        },
        error: function (x, s, e) { console.error('Older reports failed', s, e); }
    });
}

function fmtDate(str) {
    return new Date(str).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

</body>
</html>