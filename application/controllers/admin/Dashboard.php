<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
        $this->load->model('leads_model');
        $this->load->model('staff_model');
        $this->config->load('approval');
    }

    /* This is admin dashboard view */
    public function index()
    {
        close_setup_menu();
        $this->load->model('departments_model');
        $this->load->model('todo_model');
        $data['departments'] = $this->departments_model->get();

        $data['todos'] = $this->todo_model->get_todo_items(0);
        // Only show last 5 finished todo items
        $this->todo_model->setTodosLimit(5);
        $data['todos_finished'] = $this->todo_model->get_todo_items(1);
        $data['upcoming_events_next_week'] = $this->dashboard_model->get_upcoming_events_next_week();
        $data['upcoming_events'] = $this->dashboard_model->get_upcoming_events();
        $data['title'] = _l('dashboard_string');

        $this->load->model('contracts_model');
        $data['expiringContracts'] = $this->contracts_model->get_contracts_about_to_expire(get_staff_user_id());

        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['activity_log'] = $this->misc_model->get_activity_log();
        // Tickets charts
        $tickets_awaiting_reply_by_status = $this->dashboard_model->tickets_awaiting_reply_by_status();
        $tickets_awaiting_reply_by_department = $this->dashboard_model->tickets_awaiting_reply_by_department();

        $data['tickets_reply_by_status'] = json_encode($tickets_awaiting_reply_by_status);
        $data['tickets_awaiting_reply_by_department'] = json_encode($tickets_awaiting_reply_by_department);

        $data['tickets_reply_by_status_no_json'] = $tickets_awaiting_reply_by_status;
        $data['tickets_awaiting_reply_by_department_no_json'] = $tickets_awaiting_reply_by_department;

        $data['projects_status_stats'] = json_encode($this->dashboard_model->projects_status_stats());
        $data['leads_status_stats'] = json_encode($this->dashboard_model->leads_status_stats());
        $data['google_ids_calendars'] = $this->misc_model->get_google_calendar_ids();
        $data['bodyclass'] = 'dashboard invoices-total-manual';
        $this->load->model('announcements_model');
        $data['staff_announcements'] = $this->announcements_model->get();
        $data['total_undismissed_announcements'] = $this->announcements_model->get_total_undismissed_announcements();

        $this->load->model('projects_model');
        $data['projects_activity'] = $this->projects_model->get_activity('', hooks()->apply_filters('projects_activity_dashboard_limit', 20));
        add_calendar_assets();
        $this->load->model('utilities_model');
        $this->load->model('estimates_model');
        $data['estimate_statuses'] = $this->estimates_model->get_statuses();

        $this->load->model('proposals_model');
        $data['proposal_statuses'] = $this->proposals_model->get_statuses();

        $wps_currency = 'undefined';
        if (is_using_multiple_currencies()) {
            $wps_currency = $data['base_currency']->id;
        }
        $data['weekly_payment_stats'] = json_encode($this->dashboard_model->get_weekly_payments_statistics($wps_currency));

        $data['dashboard'] = true;

        $data['user_dashboard_visibility'] = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility');

        if (!$data['user_dashboard_visibility']) {
            $data['user_dashboard_visibility'] = [];
        } else {
            $data['user_dashboard_visibility'] = unserialize($data['user_dashboard_visibility']);
        }
        $data['user_dashboard_visibility'] = json_encode($data['user_dashboard_visibility']);

        $data['tickets_report'] = [];

        $data['staffs'] = $this->staff_model->get('', ['active' => 1, 'admin' => 0]);
        $data['statuses'] = $this->leads_model->get_status();
        $data['sources'] = $this->leads_model->get_source();
        $this->db->select('DISTINCT(city)');
        $data['cities'] = $this->db->get(db_prefix().'leads')->result_array();
        $this->db->select('DISTINCT(country)');
        $data['countries'] = $this->db->get(db_prefix().'leads')->result_array();
        $this->db->select('DISTINCT(state)');
        $data['states'] = $this->db->get(db_prefix().'leads')->result_array();
        $this->db->distinct();
        $this->db->select('source');
        $query = $this->db->get(db_prefix().'leads')->result_array();
        $source_ids = array_column($query, 'source');
        if (!empty($source_ids)) {
            $this->db->where_in('id', $source_ids);
            $data['campaign'] = $this->db->get(db_prefix().'leads_sources')->result_array();
        } else {
            $data['campaign'] = [];
        }

        $data['attendance_chart'] = $this->dashboard_model->get_attendance_chart([
            'current_user_id' => get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ]);

        $data['daily_logins'] = $this->dashboard_model->get_daily_logins([
            'current_user_id' => get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ]);

        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');

        // Current month reports
        $data['current_reports'] = $this->db
        ->where('week_start >=', $currentMonthStart)
        ->where('week_end <=', $currentMonthEnd)
        ->order_by('week_start', 'DESC')
        ->get('tblexecutive_weekly_reports')
        ->result();

        $staff = get_staff(get_staff_user_id());

        $approval_roles = $this->config->item('approval_roles');
        $allowed_levels = [];

        if (empty($staff->role)) {
            $allowed_levels = 'ALL';
        } else {
            foreach ($approval_roles as $level => $roles) {
                if (in_array($staff->role, $roles)) {
                    $allowed_levels[] = $level;
                }
            }
        }

        $this->db->select('a.*, l.name, l.company');
        $this->db->from('tblapproval_requests a');
        $this->db->join('tblleads l', 'l.id = a.entity_id', 'left');
        $this->db->where('a.status', 'pending');

        if ($allowed_levels !== 'ALL') {
            if (!empty($allowed_levels)) {
                $this->db->where_in('a.current_level', $allowed_levels);
            } else {
                $this->db->where('1 = 0');
            }
        }

        $this->db->order_by('a.created_at', 'ASC');

        $query = $this->db->get();
        $data['approvals'] = $query->result();

        if (is_admin()) {
            $data['tickets_report'] = (new app\services\TicketsReportByStaff())->filterBy('this_month');
        }

        $data = hooks()->apply_filters('before_dashboard_render', $data);
        $this->load->view('admin/dashboard/dashboard', $data);
    }

    /* Chart weekly payments statistics on home page / ajax */
    public function weekly_payments_statistics($currency)
    {
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->dashboard_model->get_weekly_payments_statistics($currency));
            exit;
        }
    }

    /* Chart monthly payments statistics on home page / ajax */
    public function monthly_payments_statistics($currency)
    {
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->dashboard_model->get_monthly_payments_statistics($currency));
            exit;
        }
    }

    public function ticket_widget($type)
    {
        $data['tickets_report'] = (new app\services\TicketsReportByStaff())->filterBy($type);
        $this->load->view('admin/dashboard/widgets/tickets_report_table', $data);
    }

    private function _build_filters()
    {
        $filters = [
            'country' => $this->input->post('country') ?: '',
            'city' => $this->input->post('city') ?: '',
            'source' => $this->input->post('source') ?: '',
            'view_assigned' => $this->input->post('view_assigned') ?: '',
            'campaign' => $this->input->post('campaign') ?: '',
            'revenue' => $this->input->post('revenue') ?: '',
            'status' => $this->input->post('status') ?: '',
            'date_range' => $this->input->post('date_range') ?: '',
            'current_user_id' => (int) get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ];
        if ((int) $filters['is_admin'] !== 1) {
            $filters['view_assigned'] = $filters['current_user_id'];
        }

        return $filters;
    }

    private function _json($data)
    {
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    // ================================================================
    //  FAST ENDPOINT — summary + charts (loads in ~1s)
    // ================================================================
  public function filter_dashboard()
{
    set_time_limit(60);
    $this->load->model('dashboard_model');

    $filters = $this->_build_filters();

    if ((int) $filters['is_admin'] !== 1) {
        $filters['view_assigned'] = $filters['current_user_id'];
    }

    $summary = $this->dashboard_model->get_summary($filters);

    $summary['today_leads'] = $this->dashboard_model->get_today_leads($filters);
    $summary['today_calls'] = $this->dashboard_model->get_today_calls($filters);

    $data = [
        'summary'           => $summary,
        'is_admin'          => (int) $filters['is_admin'],
        'chart'             => $this->dashboard_model->get_chart_data($filters),
        'table'             => $this->dashboard_model->get_table_data($filters),
        'payments_chart'    => $this->dashboard_model->get_payments_chart($filters),
        'attendance_chart'  => $this->dashboard_model->get_attendance_chart($filters),
        'campaign_chart'    => $this->dashboard_model->get_campaign_chart($filters),
        'pending_followups' => $this->dashboard_model->get_pending_and_followups_count($filters),
    ];

    $this->_json($data);
}

    // ================================================================
    //  HEAVY ENDPOINT — AI + leaderboard (loads after fast, non-blocking)
    // ================================================================
    public function filter_dashboard_heavy()
    {
        set_time_limit(120);
        $this->load->model('dashboard_model');

        $filters = $this->_build_filters();

        if ((int) $filters['is_admin'] !== 1) {
            $filters['view_assigned'] = $filters['current_user_id'];
        }

        $limit = (int) ($this->input->post('limit') ?: 10);
        $offset = (int) ($this->input->post('offset') ?: 0);
        $sort_by = $this->input->post('sort_by') ?: 'revenue';
        $sort_order = $this->input->post('sort_order') ?: 'DESC';

        $leaderboard = $this->dashboard_model->get_leaderboard(
            $filters, $limit, $offset, $sort_by, $sort_order
        );

        $data = [
            'ai_insights' => [
                'best_campaign' => $this->dashboard_model->best_campaign($filters),
                'peak_hours' => $this->dashboard_model->peak_calling_hours($filters),
                'agent_ai' => $this->dashboard_model->agent_performance($filters),
                'weekly_summary' => $this->dashboard_model->weekly_summary($filters),
                'smart_tips' => $this->dashboard_model->generate_insights($filters),  // ← NEW
            ],
            'leaderboard' => $leaderboard['data'],
            'total_leaderboard' => $leaderboard['total'],
            'attendance_stats' => $this->dashboard_model->get_attendance_stats($filters),
            'daily_logins' => $this->dashboard_model->get_daily_logins($filters),
            'payments_today' => $this->dashboard_model->get_payments_today($filters),
            'followups' => $this->dashboard_model->get_followups_today($filters),
        ];

        $this->_json($data);
    }

    // ================================================================
    //  LIVE ATTENDANCE
    // ================================================================
    public function get_live_attendance()
    {
        $this->load->model('dashboard_model');

        $filters = [
            'current_user_id' => get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ];

        $data = $this->dashboard_model->get_live_attendance($filters);
        $this->_json($data);
    }

    // ================================================================
    //  PRODUCTIVITY STATS
    // ================================================================
    public function get_productivity_stats()
    {
        $this->load->model('dashboard_model');

        $start = $this->input->get('start') ?: date('Y-m-d');
        $end = $this->input->get('end') ?: date('Y-m-d');

        $filters = [
            'current_user_id' => get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ];

        $data = $this->dashboard_model->get_productivity_stats(
            $filters, $start.' 00:00:00', $end.' 23:59:59'
        );

        $this->_json($data);
    }

    // ================================================================
    //  CHECK IN / OUT / BREAK
    // ================================================================
    public function manual_check_in()
    {
        $this->load->model('dashboard_model');
        $this->dashboard_model->auto_check_in(get_staff_user_id());
        $this->_json(['status' => 'ok']);
    }

    public function manual_check_out()
    {
        $this->load->model('dashboard_model');
        $this->dashboard_model->auto_check_out(get_staff_user_id());
        $this->_json(['status' => 'ok']);
    }

    public function start_break()
    {
        $this->load->model('dashboard_model');
        $this->dashboard_model->start_break(get_staff_user_id());
        $this->_json(['status' => 'ok']);
    }

    public function end_break()
    {
        $this->load->model('dashboard_model');
        $this->dashboard_model->end_break(get_staff_user_id());
        $this->_json(['status' => 'ok']);
    }

    // ================================================================
    //  EXPORT EXCEL
    // ================================================================
    public function export_excel()
    {
        $filters = [
            'view_assigned' => $this->input->get('view_assigned'),
            'status' => $this->input->get('status'),
            'country' => $this->input->get('country'),
            'city' => $this->input->get('city'),
            'campaign' => $this->input->get('campaign'),
            'revenue' => $this->input->get('revenue'),
            'date_range' => $this->input->get('date_range'),
            'current_user_id' => get_staff_user_id(),
            'is_admin' => is_admin() ? 1 : 0,
        ];

        if (!$filters['is_admin']) {
            $filters['view_assigned'] = $filters['current_user_id'];
        }

        $this->load->model('dashboard_model');
        $rows = $this->dashboard_model->get_dashboard_report($filters);
        $this->_download_excel($rows);
    }

    private function _download_excel($rows)
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=crm_report_'.date('YmdHis').'.xls');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo implode("\t", ['Date', 'Executive', 'Campaign', 'Stages', 'Amount',
            'Total Dialed Call', 'Total Connected Calls',
            'Call > 3 Minutes', 'Total Not Connected Calls'])."\n";

        foreach ($rows as $r) {
            echo implode("\t", [
                $r['date'] ?? '',
                $r['executive'] ?? '',
                $r['campaign'] ?? '',
                $r['status'] ?? '',
                $r['amount'] ?? 0,
                $r['total_dialed_calls'] ?? 0,
                $r['total_connected_calls'] ?? 0,
                $r['call_more_than_3_min'] ?? 0,
                $r['total_not_connected_calls'] ?? 0,
            ])."\n";
        }
        exit;
    }

    // ================================================================
    //  OLDER REPORTS
    // ================================================================
    public function load_older_reports()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Forbidden', 403);
        }

        $reports = $this->db
            ->where('week_start <', date('Y-m-01'))
            ->order_by('week_start', 'DESC')
            ->limit(4, (int) $this->input->post('offset'))
            ->get('tblexecutive_weekly_reports')
            ->result();

        $this->_json($reports);
    }
}
