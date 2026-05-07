<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Kolkata');
    }

    /**
     * @return array
     *               Used in home dashboard page
     *               Return all upcoming events this week
     */
    public function get_upcoming_events()
    {
        $monday_this_week = date('Y-m-d', strtotime('monday this week'));
        $sunday_this_week = date('Y-m-d', strtotime('sunday this week'));

        $this->db->where("(start BETWEEN '$monday_this_week' and '$sunday_this_week')");
        $this->db->where('(userid = '.get_staff_user_id().' OR public = 1)');
        $this->db->order_by('start', 'desc');
        $this->db->limit(6);

        return $this->db->get(db_prefix().'events')->result_array();
    }

    /**
     * @param  integer (optional) Limit upcoming events
     *
     * @return int
     *             Used in home dashboard page
     *             Return total upcoming events next week
     */
    public function get_upcoming_events_next_week()
    {
        $monday_this_week = date('Y-m-d', strtotime('monday next week'));
        $sunday_this_week = date('Y-m-d', strtotime('sunday next week'));
        $this->db->where("(start BETWEEN '$monday_this_week' and '$sunday_this_week')");
        $this->db->where('(userid = '.get_staff_user_id().' OR public = 1)');

        return $this->db->count_all_results(db_prefix().'events');
    }

    /**
     * @return array
     *               Used in home dashboard page, currency passed from javascript (undefined or integer)
     *               Displays weekly payment statistics (chart)
     */
    public function get_weekly_payments_statistics($currency)
    {
        $all_payments = [];
        $has_permission_payments_view = has_permission('payments', '', 'view');
        $this->db->select(db_prefix().'invoicepaymentrecords.id, amount,'.db_prefix().'invoicepaymentrecords.date');
        $this->db->from(db_prefix().'invoicepaymentrecords');
        $this->db->join(db_prefix().'invoices', ''.db_prefix().'invoices.id = '.db_prefix().'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK('.db_prefix().'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE)');
        $this->db->where(''.db_prefix().'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM '.db_prefix().'invoices WHERE addedfrom='.get_staff_user_id().' and addedfrom IN (SELECT staff_id FROM '.db_prefix().'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Current week
        $all_payments[] = $this->db->get()->result_array();
        $this->db->select(db_prefix().'invoicepaymentrecords.id, amount,'.db_prefix().'invoicepaymentrecords.date');
        $this->db->from(db_prefix().'invoicepaymentrecords');
        $this->db->join(db_prefix().'invoices', ''.db_prefix().'invoices.id = '.db_prefix().'invoicepaymentrecords.invoiceid');
        $this->db->where('YEARWEEK('.db_prefix().'invoicepaymentrecords.date) = YEARWEEK(CURRENT_DATE - INTERVAL 7 DAY) ');

        $this->db->where(''.db_prefix().'invoices.status !=', 5);
        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM '.db_prefix().'invoices WHERE addedfrom='.get_staff_user_id().' and addedfrom IN (SELECT staff_id FROM '.db_prefix().'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        // Last Week
        $all_payments[] = $this->db->get()->result_array();

        $chart = [
            'labels' => get_weekdays(),
            'datasets' => [
                [
                    'label' => _l('this_week_payments'),
                    'backgroundColor' => 'rgba(37,155,35,0.2)',
                    'borderColor' => '#84c529',
                    'borderWidth' => 1,
                    'tension' => false,
                    'data' => [
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                    ],
                ],
                [
                    'label' => _l('last_week_payments'),
                    'backgroundColor' => 'rgba(197, 61, 169, 0.5)',
                    'borderColor' => '#c53da9',
                    'borderWidth' => 1,
                    'tension' => false,
                    'data' => [
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                        0,
                    ],
                ],
            ],
        ];

        for ($i = 0; $i < count($all_payments); ++$i) {
            foreach ($all_payments[$i] as $payment) {
                $payment_day = date('l', strtotime($payment['date']));
                $x = 0;
                foreach (get_weekdays_original() as $day) {
                    if ($payment_day == $day) {
                        $chart['datasets'][$i]['data'][$x] += $payment['amount'];
                    }
                    ++$x;
                }
            }
        }

        return $chart;
    }

    /**
     * @return array
     *               Used in home dashboard page, currency passed from javascript (undefined or integer)
     *               Displays monthly payment statistics (chart)
     */
    public function get_monthly_payments_statistics($currency)
    {
        $all_payments = [];
        $has_permission_payments_view = has_permission('payments', '', 'view');
        $this->db->select('SUM(amount) as total, MONTH('.db_prefix().'invoicepaymentrecords.date) as month');
        $this->db->from(db_prefix().'invoicepaymentrecords');
        $this->db->join(db_prefix().'invoices', ''.db_prefix().'invoices.id = '.db_prefix().'invoicepaymentrecords.invoiceid');
        $this->db->where('YEAR('.db_prefix().'invoicepaymentrecords.date) = YEAR(CURRENT_DATE)');
        $this->db->where(''.db_prefix().'invoices.status !=', 5);
        $this->db->group_by('month');

        if ($currency != 'undefined') {
            $this->db->where('currency', $currency);
        }

        if (!$has_permission_payments_view) {
            $this->db->where('invoiceid IN (SELECT id FROM '.db_prefix().'invoices WHERE addedfrom='.get_staff_user_id().' and addedfrom IN (SELECT staff_id FROM '.db_prefix().'staff_permissions WHERE feature="invoices" AND capability="view_own"))');
        }

        $all_payments = $this->db->get()->result_array();

        for ($i = 1; $i <= 12; ++$i) {
            if (!isset($all_payments[$i])) {
                $all_payments[$i]['total'] = 0;
                $all_payments[$i]['month'] = $i;
            }
            $all_payments[$i]['label'] = _l(date('F', mktime(0, 0, 0, $i, 1)));
        }
        usort($all_payments, function ($a, $b) {
            return (int) $a['month'] <=> (int) $b['month'];
        });

        $chart = [
            'labels' => array_column($all_payments, 'label'),
            'datasets' => [
                [
                    'label' => _l('report_sales_type_income'),
                    'backgroundColor' => 'rgba(37,155,35,0.2)',
                    'borderColor' => '#84c529',
                    'borderWidth' => 1,
                    'tension' => false,
                    'data' => array_column($all_payments, 'total'),
                ],
            ],
        ];

        return $chart;
    }

    public function projects_status_stats()
    {
        $this->load->model('projects_model');
        $statuses = $this->projects_model->get_project_statuses();
        $colors = get_system_favourite_colors();

        $chart = [
            'labels' => [],
            'datasets' => [],
        ];

        $_data = [];
        $_data['data'] = [];
        $_data['backgroundColor'] = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink'] = [];

        $has_permission = has_permission('projects', '', 'view');
        $sql = '';
        foreach ($statuses as $status) {
            $sql .= ' SELECT COUNT(*) as total';
            $sql .= ' FROM '.db_prefix().'projects';
            $sql .= ' WHERE status='.$status['id'];
            if (!$has_permission) {
                $sql .= ' AND id IN (SELECT project_id FROM '.db_prefix().'project_members WHERE staff_id='.get_staff_user_id().')';
            }
            $sql .= ' UNION ALL ';
            $sql = trim($sql);
        }

        $result = [];
        if ($sql != '') {
            // Remove the last UNION ALL
            $sql = substr($sql, 0, -10);
            $result = $this->db->query($sql)->result();
        }

        foreach ($statuses as $key => $status) {
            array_push($_data['statusLink'], admin_url('projects?status='.$status['id']));
            array_push($chart['labels'], $status['name']);
            array_push($_data['backgroundColor'], $status['color']);
            array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['color'], -20));
            array_push($_data['data'], $result[$key]->total);
        }

        $chart['datasets'][] = $_data;
        $chart['datasets'][0]['label'] = _l('home_stats_by_project_status');

        return $chart;
    }

    public function leads_status_stats()
    {
        $chart = [
            'labels' => [],
            'datasets' => [],
        ];

        $_data = [];
        $_data['data'] = [];
        $_data['backgroundColor'] = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink'] = [];

        $result = get_leads_summary();

        foreach ($result as $status) {
            if ($status['color'] == '') {
                $status['color'] = '#737373';
            }
            array_push($chart['labels'], $status['name']);
            array_push($_data['backgroundColor'], $status['color']);
            if (!isset($status['junk']) && !isset($status['lost'])) {
                array_push($_data['statusLink'], admin_url('leads?status='.$status['id']));
            }
            array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['color'], -20));
            // array_push($_data['data'], $status['total']);
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    /**
     * Display total tickets awaiting reply by department (chart).
     *
     * @return array
     */
    public function tickets_awaiting_reply_by_department()
    {
        $this->load->model('departments_model');
        $departments = $this->departments_model->get();
        $colors = get_system_favourite_colors();
        $chart = [
            'labels' => [],
            'datasets' => [],
        ];

        $_data = [];
        $_data['data'] = [];
        $_data['backgroundColor'] = [];
        $_data['hoverBackgroundColor'] = [];

        $i = 0;
        foreach ($departments as $department) {
            if (!is_admin()) {
                if (get_option('staff_access_only_assigned_departments') == 1) {
                    $staff_deparments_ids = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                    $departments_ids = [];
                    if (count($staff_deparments_ids) == 0) {
                        $departments = $this->departments_model->get();
                        foreach ($departments as $department) {
                            array_push($departments_ids, $department['departmentid']);
                        }
                    } else {
                        $departments_ids = $staff_deparments_ids;
                    }
                    if (count($departments_ids) > 0) {
                        $this->db->where('department IN (SELECT departmentid FROM '.db_prefix().'staff_departments WHERE departmentid IN ('.implode(',', $departments_ids).') AND staffid="'.get_staff_user_id().'")');
                    }
                }
            }
            $this->db->where_in('status', [
                1,
                2,
                4,
            ]);

            $this->db->where('department', $department['departmentid']);
            $this->db->where(db_prefix().'tickets.merged_ticket_id IS NULL', null, false);
            $total = $this->db->count_all_results(db_prefix().'tickets');

            if ($total > 0) {
                $color = '#333';
                if (isset($colors[$i])) {
                    $color = $colors[$i];
                }
                array_push($chart['labels'], $department['name']);
                array_push($_data['backgroundColor'], $color);
                array_push($_data['hoverBackgroundColor'], adjust_color_brightness($color, -20));
                array_push($_data['data'], $total);
            }
            ++$i;
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    /**
     * Display total tickets awaiting reply by status (chart).
     *
     * @return array
     */
    public function tickets_awaiting_reply_by_status()
    {
        $this->load->model('tickets_model');
        $statuses = $this->tickets_model->get_ticket_status();
        $_statuses_with_reply = [
            1,
            2,
            4,
        ];

        $chart = [
            'labels' => [],
            'datasets' => [],
        ];

        $_data = [];
        $_data['data'] = [];
        $_data['backgroundColor'] = [];
        $_data['hoverBackgroundColor'] = [];
        $_data['statusLink'] = [];

        foreach ($statuses as $status) {
            if (in_array($status['ticketstatusid'], $_statuses_with_reply)) {
                if (!is_admin()) {
                    if (get_option('staff_access_only_assigned_departments') == 1) {
                        $staff_deparments_ids = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                        $departments_ids = [];
                        if (count($staff_deparments_ids) == 0) {
                            $departments = $this->departments_model->get();
                            foreach ($departments as $department) {
                                array_push($departments_ids, $department['departmentid']);
                            }
                        } else {
                            $departments_ids = $staff_deparments_ids;
                        }
                        if (count($departments_ids) > 0) {
                            $this->db->where('department IN (SELECT departmentid FROM '.db_prefix().'staff_departments WHERE departmentid IN ('.implode(',', $departments_ids).') AND staffid="'.get_staff_user_id().'")');
                        }
                    }
                }

                $this->db->where('status', $status['ticketstatusid']);
                $this->db->where(db_prefix().'tickets.merged_ticket_id IS NULL', null, false);
                $total = $this->db->count_all_results(db_prefix().'tickets');
                if ($total > 0) {
                    array_push($chart['labels'], ticket_status_translate($status['ticketstatusid']));
                    array_push($_data['statusLink'], admin_url('tickets/index/'.$status['ticketstatusid']));
                    array_push($_data['backgroundColor'], $status['statuscolor']);
                    array_push($_data['hoverBackgroundColor'], adjust_color_brightness($status['statuscolor'], -20));
                    array_push($_data['data'], $total);
                }
            }
        }

        $chart['datasets'][] = $_data;

        return $chart;
    }

    private $_redis;
    private $_redis_tried = false;

    private function _redis()
    {
        if ($this->_redis_tried) {
            return $this->_redis;
        }
        $this->_redis_tried = true;

        try {
            $r = new Redis();
            $connected = $r->connect('127.0.0.1', 6379, 1.0);
            if ($connected) {
                $this->_redis = $r;
            }
        } catch (Exception $e) {
            $this->_redis = null;
        }

        return $this->_redis;
    }

    private function _cache_get($key)
    {
        $r = $this->_redis();
        if (!$r) {
            return null;
        }
        try {
            $val = $r->get($key);

            return $val !== false ? json_decode($val, true) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function _cache_set($key, $data, $ttl = 30)
    {
        $r = $this->_redis();
        if (!$r) {
            return;
        }
        try {
            $r->setex($key, $ttl, json_encode($data));
        } catch (Exception $e) {
            // silent — cache failure must never break the page
        }
    }

    private function _cache_key($prefix, $filters = [], $extra = [])
    {
        $f = $filters;
        $scope = ($f['is_admin'] ?? 0) ? 'admin' : ($f['view_assigned'] ?? $f['current_user_id'] ?? 0);
        unset($f['current_user_id']);
        ksort($f);

        return $prefix.':'.$scope.':'.md5(json_encode(array_merge($f, $extra)));
    }

    private function _apply_user_scope($filters = [])
    {
        if (!empty($filters['is_admin']) && (int) $filters['is_admin'] === 1) {
            return; // admin sees everything
        }

        if (!empty($filters['view_assigned'])) {
            $this->db->where('l.assigned', (int) $filters['view_assigned']);

            return;
        }

        if (!empty($filters['current_user_id'])) {
            $this->db->where('l.assigned', (int) $filters['current_user_id']);
        }
    }

    private function _apply_filters($filters = [])
    {
        // Date range
        if (!empty($filters['date_range'])) {
            $range = trim($filters['date_range']);
            if (stripos($range, 'to') !== false) {
                $parts = preg_split('/\s+to\s+/i', $range);
                if (count($parts) === 2) {
                    $this->db->where('l.dateadded >=', trim($parts[0]).' 00:00:00');
                    $this->db->where('l.dateadded <=', trim($parts[1]).' 23:59:59');
                }
            } else {
                $this->db->where('l.dateadded >=', $range.' 00:00:00');
                $this->db->where('l.dateadded <=', $range.' 23:59:59');
            }
        }

        // Simple column filters
        foreach (['country', 'source', 'status', 'city'] as $k) {
            if (!empty($filters[$k])) {
                $this->db->where("l.$k", $filters[$k]);
            }
        }

        if (!empty($filters['campaign'])) {
            $this->db->like('l.source_campaign', $filters['campaign']);
        }

        // Revenue period filter
        if (!empty($filters['revenue'])) {
            $now = date('Y-m-d');
            switch ($filters['revenue']) {
                case 'Last 7 Days':
                    $this->db->where('l.payment_date >=', date('Y-m-d', strtotime('-6 days')).' 00:00:00');
                    $this->db->where('l.payment_date <=', "$now 23:59:59");
                    break;
                case 'Last 30 Days':
                    $this->db->where('l.payment_date >=', date('Y-m-d', strtotime('-29 days')).' 00:00:00');
                    $this->db->where('l.payment_date <=', "$now 23:59:59");
                    break;
                case 'This Month':
                    $this->db->where('MONTH(l.payment_date)', date('n'));
                    $this->db->where('YEAR(l.payment_date)', date('Y'));
                    break;
            }
        }
    }

    public function get_summary($filters = [])
{
    $key = $this->_cache_key('sum', $filters);
    $cached = $this->_cache_get($key);
    if ($cached !== null) {
        return $cached;
    }

    $today = date('Y-m-d');
    $isAdmin = (int) ($filters['is_admin'] ?? 0);

    // ── Leads query ──────────────────────────────────────────────
    $this->db->reset_query();
    $this->_apply_filters($filters);
    $this->_apply_user_scope($filters);

    $row = $this->db
        ->select("
            COUNT(l.id)                                                                          AS total_leads,
            COALESCE(SUM(l.payin_amount),  0)                                                    AS total_payin,
            COALESCE(SUM(l.total_payment), 0)                                                    AS total_payment,
            COALESCE(SUM(CASE WHEN DATE(l.payment_date) = '$today' THEN l.total_payment END), 0) AS today_payment,
            SUM(l.status = 12)                                                                   AS total_follow,
            SUM(l.status = 12 AND DATE(l.lastcontact) = '$today')                               AS today_follow
        ")
        ->from('tblleads l')
        ->get()->row_array();

    // ── Calls query — ✅ filtered by staff if not admin ──────────
    $this->db->reset_query();
    $this->db
        ->select('COUNT(c.id) AS total_calls')
        ->from('tblcall_logs c')
        ->join('tblleads l', 'l.id = c.lead_id', 'left');

    // ✅ staff sees only their own calls
    if (!empty($filters['view_assigned'])) {
        $this->db->where('c.staff_id', $filters['view_assigned']);
    }

    $callRow = $this->db->get()->row_array();

    // ── Executives — ✅ only for admin, staff gets 0 ─────────────
    $execCount = 0;
    $todayExec = 0;

    if ($isAdmin === 1) {
        $execKey = 'exec_count_total';
        $execCount = $this->_cache_get($execKey);
        if ($execCount === null) {
            $execCount = (int) $this->db
                ->where(['active' => 1, 'admin' => 0])
                ->count_all_results('tblstaff');
            $this->_cache_set($execKey, $execCount, 300);
        }

        $todayExecKey = 'exec_today_' . $today;
        $todayExec = $this->_cache_get($todayExecKey);
        if ($todayExec === null) {
            $todayExec = (int) $this->db
                ->select('COUNT(DISTINCT t.staff_id) AS cnt', false)
                ->from('tbltaskstimers t')
                ->join('tblstaff s', 's.staffid = t.staff_id', 'inner')
                ->where('s.active', 1)
                ->where('s.admin', 0)
                ->where('DATE(t.check_in_time)', $today)
                ->get()->row()->cnt ?? 0;
            $this->_cache_set($todayExecKey, $todayExec, 60);
        }
    }

    // ── Result ───────────────────────────────────────────────────
    $result = [
        'total_leads'       => (int)   ($row['total_leads']    ?? 0),
        'total_payin'       => (float) ($row['total_payin']    ?? 0),
        'total_payment'     => (float) ($row['total_payment']  ?? 0),
        'today_payment'     => (float) ($row['today_payment']  ?? 0),
        'total_executives'  => (int)   $execCount,   // ✅ 0 for staff
        'today_executives'  => (int)   $todayExec,   // ✅ 0 for staff
        'total_follow'      => (int)   ($row['total_follow']   ?? 0),
        'today_follow'      => (int)   ($row['today_follow']   ?? 0),
        'total_calls'       => (int)   ($callRow['total_calls'] ?? 0), // ✅ staff own calls
    ];

    $this->_cache_set($key, $result, 30);

    return $result;
}

    // ================================================================
    //  PIE CHART: Lead status
    // ================================================================
    public function get_chart_data($filters = [])
    {
        $key = $this->_cache_key('chart', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $rows = $this->db
            ->select('s.name AS label, s.color AS color, COUNT(*) AS value')
            ->from('tblleads l')
            ->join('tblleads_status s', 's.id = l.status', 'left')
            ->group_by('s.id')
            ->get()->result_array();

        $result = [
            'labels' => array_column($rows, 'label'),
            'values' => array_column($rows, 'value'),
            'colors' => array_column($rows, 'color'),
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  LATEST LEADS TABLE
    // ================================================================
    public function get_table_data($filters = [])
    {
        $key = $this->_cache_key('table', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $bad_statuses = [4, 3, 26, 27, 24, 22, 25];

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $rows = $this->db
            ->select("
                l.id, l.name, l.country, l.city,
                CONCAT(s.firstname,' ',s.lastname) AS assigned_name,
                cl.duration, cl.status AS call_status,
                cl.recording_url, cl.answered_agent_name
            ")
            ->from('tblleads l')
            ->join(
                '(
                    SELECT t.* FROM tblcall_logs t
                    INNER JOIN (
                        SELECT lead_id, MAX(id) AS last_id
                        FROM tblcall_logs
                        WHERE duration > 120
                        GROUP BY lead_id
                    ) x ON x.last_id = t.id
                    WHERE t.duration > 120
                ) cl',
                'cl.lead_id = l.id',
                'inner'
            )
            ->join('tblstaff s', 's.staffid = l.assigned', 'left')
            ->where_not_in('l.status', $bad_statuses)
            ->where('cl.duration >', 120)
            ->order_by('l.dateadded', 'DESC')
            ->limit(10)
            ->get()->result_array();

        $this->_cache_set($key, $rows, 15);

        return $rows;
    }

    // ================================================================
    //  PAYMENTS CHART — daily + monthly in 1 query
    // ================================================================
    public function get_payments_chart($filters = [])
    {
        $key = $this->_cache_key('pay_chart', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $today = date('Y-m-d');
        $month = (int) date('n');
        $year = (int) date('Y');

        $row = $this->db
            ->select("
                COALESCE(SUM(CASE WHEN DATE(l.payment_date) = '$today'                                  THEN l.total_payment END), 0) AS daily,
                COALESCE(SUM(CASE WHEN MONTH(l.payment_date) = $month AND YEAR(l.payment_date) = $year THEN l.total_payment END), 0) AS monthly
            ")
            ->from('tblleads l')
            ->get()->row_array();

        $result = [
            ['label' => 'Daily',   'value' => (float) ($row['daily'] ?? 0)],
            ['label' => 'Monthly', 'value' => (float) ($row['monthly'] ?? 0)],
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  PAYMENTS BY USER
    // ================================================================
    public function get_payments_by_user($filters = [])
    {
        $key = $this->_cache_key('pay_user', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $staff_sql = '';
        $bindings = [];

        if (empty($filters['is_admin']) || (int) $filters['is_admin'] !== 1) {
            $uid = (int) ($filters['view_assigned'] ?: $filters['current_user_id']);
            if ($uid) {
                $staff_sql = ' AND s.staffid = ?';
                $bindings[] = $uid;
            }
        }

        $today = date('Y-m-d');
        $curMonth = (int) date('n');
        $curYear = (int) date('Y');

        $sql = "SELECT s.staffid, CONCAT(s.firstname,' ',s.lastname) AS staff_name,
                       COALESCE(SUM(CASE WHEN DATE(l.payment_date) = ?                                  THEN l.total_payment END),0) AS daily,
                       COALESCE(SUM(CASE WHEN MONTH(l.payment_date) = ? AND YEAR(l.payment_date) = ?   THEN l.total_payment END),0) AS monthly
                FROM tblstaff s
                LEFT JOIN tblleads l ON l.assigned = s.staffid
                WHERE s.active = 1 AND s.admin = 0 $staff_sql
                GROUP BY s.staffid ORDER BY s.firstname ASC";

        array_unshift($bindings, $today);
        $bindings[] = $curMonth;
        $bindings[] = $curYear;

        $rows = $this->db->query($sql, $bindings)->result_array();

        $result = [
            'labels' => array_column($rows, 'staff_name'),
            'daily' => array_map(fn ($r) => (float) $r['daily'], $rows),
            'monthly' => array_map(fn ($r) => (float) $r['monthly'], $rows),
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  PENDING + FOLLOWUPS COUNT — 1 query
    // ================================================================
    public function get_pending_and_followups_count($filters = [])
    {
        $key = $this->_cache_key('pend', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $row = $this->db
            ->select('SUM(l.status = 4) AS pending, SUM(l.status = 12) AS followups')
            ->from('tblleads l')
            ->get()->row_array();

        $result = [
            'pending' => (int) ($row['pending'] ?? 0),
            'followups' => (int) ($row['followups'] ?? 0),
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  PAYMENTS TODAY (standalone — used by heavy endpoint)
    // ================================================================
    public function get_payments_today($filters = [])
    {
        $key = $this->_cache_key('pay_today', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $row = $this->db
            ->select('COALESCE(SUM(l.total_payment), 0) AS total')
            ->from('tblleads l')
            ->where('DATE(l.payment_date)', date('Y-m-d'))
            ->get()->row();
        $result = (float) ($row->total ?? 0);

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    public function get_followups_today($filters = [])
    {
        $key = $this->_cache_key('fu_today', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $result = (int) $this->db
            ->from('tblleads l')
            ->where('l.status', 12)
            ->where('DATE(l.lastcontact)', date('Y-m-d'))
            ->count_all_results();

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    public function get_campaign_chart($filters = [])
    {
        $key = $this->_cache_key('camp', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $this->db->reset_query();
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);

        $bad_statuses = [4, 3, 26, 27, 24, 22, 25];

        $result = $this->db
            ->select('ls.name AS label, COUNT(l.id) AS value')
            ->from('tblleads l')                                       // FIX #3: was db_prefix().'leads l'
            ->join('tblleads_sources ls', 'ls.id = l.source', 'inner')
            ->where('l.source IS NOT NULL', null, false)
            ->where_not_in('l.status', $bad_statuses)
            ->group_by('l.source')
            ->order_by('COUNT(l.id)', 'DESC')
            ->get()
            ->result_array();

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  DAILY LOGINS
    // ================================================================
    public function get_daily_logins($filters = [])
    {
        $key = $this->_cache_key('daily_logins', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $whereUser = '';
        if (!empty($filters['is_admin']) && (int) $filters['is_admin'] === 0) {
            $whereUser = ' AND tt.staff_id = '.(int) $filters['current_user_id'];
        } elseif (!empty($filters['view_assigned'])) {
            $whereUser = ' AND tt.staff_id = '.(int) $filters['view_assigned'];
        }

        $sql = "SELECT s.staffid,
                       CONCAT(s.firstname,' ',s.lastname) AS staff_name,
                       MIN(IF(tt.start_time REGEXP '^[0-9]+\$',
                              FROM_UNIXTIME(tt.start_time), tt.start_time)) AS first_login
                FROM tblstaff s
                LEFT JOIN tbltaskstimers tt
                       ON tt.staff_id = s.staffid
                      AND DATE(IF(tt.start_time REGEXP '^[0-9]+\$',
                                  FROM_UNIXTIME(tt.start_time), tt.start_time)) = CURDATE()
                WHERE s.active = 1 AND s.admin = 0 $whereUser
                GROUP BY s.staffid ORDER BY staff_name ASC";

        $result = $this->db->query($sql)->result_array();

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  ATTENDANCE CHART
    // ================================================================
    public function get_attendance_chart($filters = [])
    {
        $key = $this->_cache_key('att_chart', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $daily_logins = $this->get_daily_logins($filters);
        $present = $late = $absent = 0;
        $cutoff = new DateTime('10:00:00');

        foreach ($daily_logins as $row) {
            $first = $row['first_login'] ?? null;
            if ($first && $first !== '') {
                try {
                    $dt = preg_match('/^\d+$/', $first)
                        ? (new DateTime())->setTimestamp((int) $first)
                        : new DateTime($first);
                    (new DateTime($dt->format('H:i:s'))) <= $cutoff ? ++$present : ++$late;
                } catch (Exception $e) {
                    ++$absent;
                }
            } else {
                ++$absent;
            }
        }

        $result = [
            ['label' => 'Present', 'value' => $present],
            ['label' => 'Late',    'value' => $late],
            ['label' => 'Absent',  'value' => $absent],
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    // ================================================================
    //  ATTENDANCE STATS
    // ================================================================
    public function get_attendance_stats($filters = [])
    {
        $key = $this->_cache_key('att_stats', $filters);
        $cached = $this->_cache_get($key);
        if ($cached !== null) {
            return $cached;
        }

        $daily_logins = $this->get_daily_logins($filters);
        $totalStaff = count($daily_logins);
        $times = [];

        foreach ($daily_logins as $row) {
            if (!empty($row['first_login'])) {
                $t = strtotime($row['first_login']);
                if ($t) {
                    $times[] = $t;
                }
            }
        }

        $logged = count($times);
        $result = $logged ? [
            'total_staff' => $totalStaff,
            'logged_today' => $logged,
            'not_logged' => $totalStaff - $logged,
            'avg_login' => date('H:i', (int) round(array_sum($times) / $logged)),
            'earliest_login' => date('H:i', (int) min($times)),
            'latest_login' => date('H:i', (int) max($times)),
        ] : [
            'total_staff' => $totalStaff,
            'logged_today' => 0,
            'not_logged' => $totalStaff,
            'avg_login' => '-',
            'earliest_login' => '-',
            'latest_login' => '-',
        ];

        $this->_cache_set($key, $result, 30);

        return $result;
    }

    public function get_live_attendance($filters)
    {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');

        // Query 1: all active non-admin staff
        $this->db->select("s.staffid, CONCAT(s.firstname,' ',s.lastname) AS executive");
        $this->db->from('tblstaff s');
        $this->db->where(['s.active' => 1, 's.admin' => 0]);
        if ((int) ($filters['is_admin'] ?? 0) === 0) {
            $this->db->where('s.staffid', (int) $filters['current_user_id']);
        }
        $staffs = $this->db->get()->result_array();
        if (empty($staffs)) {
            return [];
        }

        $staffIds = array_column($staffs, 'staffid');

        // Query 2: all timers for all staff in one shot (no N+1)
        $allTimers = $this->db->select('*')
            ->from('tbltaskstimers t')
            ->where_in('t.staff_id', $staffIds)
            ->where('t.check_in_time >=', $today_start)
            ->where('t.check_in_time <=', $today_end)
            ->order_by('t.staff_id, t.id', 'ASC')
            ->get()->result_array();

        $timersByStaff = [];
        foreach ($allTimers as $t) {
            $timersByStaff[$t['staff_id']][] = $t;
        }

        $now = time();
        $result = [];

        foreach ($staffs as $r) {
            $sid = $r['staffid'];
            $timers = $timersByStaff[$sid] ?? [];

            $total_work = $total_break = 0;
            $status = 'CHECKED_OUT';
            $check_in_time = $break_start_time = null;
            $is_on_break = 0;

            foreach ($timers as $t) {
                $in = $t['check_in_time'] ? strtotime($t['check_in_time']) : null;
                $out = $t['check_out_time'] ? strtotime($t['check_out_time']) : null;
                $break_sec = (int) ($t['break_time_seconds'] ?? 0);

                $current_break_start = null;
                if ((int) ($t['is_on_break'] ?? 0) === 1 && strpos($t['note'] ?? '', 'BREAK:') !== false) {
                    $current_break_start = str_replace('BREAK:', '', $t['note']);
                    if ($current_break_start) {
                        $break_sec += max(0, $now - strtotime($current_break_start));
                    }
                }

                if ($in && $out) {
                    $total_work += ($out - $in);
                    $total_break += $break_sec;
                } elseif ($in && !$out) {
                    $total_work += ($now - $in);
                    $total_break += $break_sec;
                    $status = 'CHECKED_IN';
                    $check_in_time = $t['check_in_time'];
                    $break_start_time = $current_break_start;
                    $is_on_break = (int) ($t['is_on_break'] ?? 0);
                }
            }

            $result[] = [
                'staffid' => $sid,
                'executive' => $r['executive'],
                'status' => $status,
                'check_in_time' => $check_in_time,
                'break_start_time' => $break_start_time,
                'total_work_seconds' => $total_work,
                'break_time_seconds' => $total_break,
                'work_time_seconds' => max(0, $total_work - $total_break),
                'idle_time_seconds' => $total_work,
                'is_on_break' => $is_on_break,
                'includes_live' => true, // tells JS not to add live seconds again
            ];
        }

        return $result;
    }

    public function get_productivity_stats($filters, $start_dt, $end_dt)
    {
        $now = time();

        $this->db->select("s.staffid, CONCAT(s.firstname,' ',s.lastname) AS executive", false);
        $this->db->from('tblstaff s');
        $this->db->where(['s.active' => 1, 's.admin' => 0]);
        if (isset($filters['is_admin']) && (int) $filters['is_admin'] === 0) {
            $this->db->where('s.staffid', (int) $filters['current_user_id']);
        }
        $staffs = $this->db->get()->result_array();
        if (empty($staffs)) {
            return [];
        }

        $staffIds = array_column($staffs, 'staffid');

        $allTimers = $this->db->select('*')
            ->from('tbltaskstimers t')
            ->where_in('t.staff_id', $staffIds)
            ->where('t.check_in_time >=', $start_dt)
            ->where('t.check_in_time <=', $end_dt)
            ->order_by('t.staff_id, t.id', 'ASC')
            ->get()->result_array();

        $timersByStaff = [];
        foreach ($allTimers as $t) {
            $timersByStaff[$t['staff_id']][] = $t;
        }

        $result = [];

        foreach ($staffs as $s) {
            $sid = $s['staffid'];
            $timers = $timersByStaff[$sid] ?? [];

            $total_work = $total_break = $total_idle = 0;

            foreach ($timers as $t) {
                $in = $t['check_in_time'] ? strtotime($t['check_in_time']) : null;
                $out = $t['check_out_time'] ? strtotime($t['check_out_time']) : null;
                $break_sec = (int) ($t['break_time_seconds'] ?? 0);
                $idle_sec = (int) ($t['idle_time_seconds'] ?? 0);
                $work_sec = (int) ($t['total_work_seconds'] ?? 0);

                if ($in && !$out) {
                    // Live session — compute from now
                    $work_sec = max(0, $now - $in);
                    if ((int) ($t['is_on_break'] ?? 0) === 1 && strpos($t['note'] ?? '', 'BREAK:') !== false) {
                        $bstart = strtotime(str_replace('BREAK:', '', $t['note']));
                        if ($bstart) {
                            $break_sec += max(0, $now - $bstart);
                        }
                    }
                } elseif ($in && $out && $work_sec === 0) {
                    // Completed session with no stored work_seconds — compute it
                    $work_sec = max(0, ($out - $in) - $break_sec);
                }

                $total_work += $work_sec;
                $total_break += $break_sec;
                $total_idle += $idle_sec;
            }

            // FIX #7: productive = net work (work minus breaks), NOT raw total_work
            $net_productive = max(0, $total_work - $total_break);

            $result[] = [
                'staffid' => (int) $sid,
                'executive' => $s['executive'],
                'productive_seconds' => $net_productive,   // FIX #7: was $total_work
                'unproductive_seconds' => $total_break,      // break time
                'neutral_seconds' => $total_idle,       // idle time
            ];
        }

        return $result;
    }

    // ================================================================
    //  TODAY'S ATTENDANCE
    // ================================================================
    public function get_today_attendance($filters = [])
    {
        $today = date('Y-m-d');
        $sql = "SELECT t.staff_id, CONCAT(s.firstname,' ',s.lastname) AS staff_name,
                         SUM(TIMESTAMPDIFF(SECOND,
                             IF(t.start_time REGEXP '^[0-9]+\$', FROM_UNIXTIME(t.start_time), t.start_time),
                             IF(t.end_time   REGEXP '^[0-9]+\$', FROM_UNIXTIME(t.end_time),   t.end_time)
                         ))/3600 AS hours
                  FROM tbltaskstimers t
                  LEFT JOIN tblstaff s ON s.staffid = t.staff_id
                  WHERE DATE(IF(t.start_time REGEXP '^[0-9]+\$',
                                FROM_UNIXTIME(t.start_time), t.start_time)) = ?
                  AND t.end_time IS NOT NULL";

        if (!empty($filters['is_admin']) && (int) $filters['is_admin'] === 1) {
            // no restriction — admin sees all
        } elseif (!empty($filters['view_assigned'])) {
            $sql .= ' AND t.staff_id = '.(int) $filters['view_assigned'];
        } elseif (!empty($filters['current_user_id'])) {
            $sql .= ' AND t.staff_id = '.(int) $filters['current_user_id'];
        }

        return $this->db->query($sql.' GROUP BY t.staff_id', [$today])->result_array();
    }

    public function get_leaderboard($filters = [], $limit = 10, $offset = 0, $sort_by = 'revenue', $sort_order = 'DESC')
    {
        $key = $this->_cache_key('leaderboard', $filters, ['sb' => $sort_by, 'so' => $sort_order]);
        $cached = $this->_cache_get($key);

        if (!$cached) {
            $this->db->reset_query();

            $this->db
                ->select('s.staffid, CONCAT(s.firstname," ",s.lastname) AS executive,
                          COUNT(l.id)               AS total_leads,
                          SUM(l.status = 6)         AS converted,
                          COALESCE(SUM(l.total_payment), 0) AS revenue')
                ->from('tblstaff s')
                ->join('tblleads l', 'l.assigned = s.staffid', 'left')
                ->where(['s.active' => 1, 's.admin' => 0])
                ->group_by('s.staffid');

            // FIX #4a: apply date / campaign / other filters
            $this->_apply_filters($filters);

            // FIX #4b: was inverted — was checking !is_admin for view_assigned
            if (!empty($filters['is_admin']) && (int) $filters['is_admin'] === 1) {
                // Admin — if a specific executive is chosen, restrict to them
                if (!empty($filters['view_assigned'])) {
                    $this->db->where('s.staffid', (int) $filters['view_assigned']);
                }
            // otherwise admin sees ALL staff — no restriction
            } else {
                // Non-admin: restrict to their own staffid
                $this->db->where('s.staffid', (int) $filters['current_user_id']);
            }

            $rows = $this->db->get()->result_array();

            $leaderboard = [];
            foreach ($rows as $r) {
                $leaderboard[] = [
                    'executive' => $r['executive'],
                    'conversion' => $r['total_leads'] > 0
                        ? round(($r['converted'] / $r['total_leads']) * 100, 2)
                        : 0,
                    'revenue' => (float) $r['revenue'],
                ];
            }

            $allowed = ['executive', 'conversion', 'revenue'];
            $sort_by = in_array($sort_by, $allowed) ? $sort_by : 'revenue';
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

            usort($leaderboard, function ($a, $b) use ($sort_by, $sort_order) {
                if ($a[$sort_by] == $b[$sort_by]) {
                    return 0;
                }
                $lt = $a[$sort_by] < $b[$sort_by] ? -1 : 1;

                return $sort_order === 'ASC' ? $lt : -$lt;
            });

            $cached = ['all' => $leaderboard, 'total' => count($leaderboard)];
            $this->_cache_set($key, $cached, 60);
        }

        return [
            'data' => array_slice($cached['all'], $offset, $limit),
            'total' => $cached['total'],
        ];
    }

    // ================================================================
    //  AI QUERIES (cached 60s — change slowly)
    // ================================================================

    public function best_day_of_week($filters = [])
    {
        $key = $this->_cache_key('ai_bestday', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $this->db->reset_query();
        $this->db->select("
            DAYOFWEEK(CONVERT_TZ(c.created_at, '+00:00', '+05:30')) AS day_num,
            COUNT(c.id) AS total_calls,
            ROUND(COALESCE(SUM(c.is_success) / NULLIF(COUNT(c.id), 0) * 100, 0), 2) AS rate
        ");
        $this->db->from('tblcall_logs c');
        $this->db->join('tblleads l', 'l.id = c.lead_id', 'left');
        $this->_apply_filters($filters);
        $this->db->group_by('day_num');
        $this->db->having('total_calls >=', 10);
        $this->db->order_by('rate', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get()->row_array();

        if (!empty($row)) {
            $row['day_name'] = $days[$row['day_num'] - 1] ?? 'Unknown';
        }

        $this->_cache_set($key, $row ?: null, 60);

        return $row ?: null;
    }

    public function generate_insights($filters = [])
    {
        $key = $this->_cache_key('ai_insights_tips', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $best = $this->best_campaign($filters);
        $weekly = $this->weekly_summary($filters);
        $peaks = $this->peak_calling_hours($filters);
        $agents = $this->agent_performance($filters);
        $bestDay = $this->best_day_of_week($filters);

        $tips = [];

        if (empty($weekly['total_calls']) || (int) $weekly['total_calls'] === 0) {
            return [[
                'icon' => '📭',
                'text' => 'No call data found for the selected date range. Make calls to start seeing AI insights.',
            ]];
        }

        if ((float) ($weekly['success_rate'] ?? 0) === 0.0 && (float) ($best['success_rate'] ?? 0) === 0.0) {
            return [[
                'icon' => '⚠️',
                'text' => "Found <b>{$weekly['total_calls']}</b> calls but none are marked as successful yet. Check that your webhook is setting <b>is_success = 1</b> correctly.",
            ]];
        }

        if (!empty($best['campaign_name'])) {
            $diff = round(($best['success_rate'] ?? 0) - ($weekly['success_rate'] ?? 0), 1);
            $dir = $diff >= 0 ? 'outperforming' : 'underperforming';
            $sign = $diff >= 0 ? '+' : '';

            if ($best['success_rate'] > 0 || $weekly['success_rate'] > 0) {
                $tips[] = [
                    'icon' => '🏆',
                    'text' => "<b>{$best['campaign_name']}</b> is {$dir} the weekly baseline by <b>{$sign}{$diff}%</b> ({$best['success_rate']}% vs {$weekly['success_rate']}% avg).",
                ];
            } else {
                $tips[] = [
                    'icon' => '🏆',
                    'text' => "Top campaign: <b>{$best['campaign_name']}</b> with {$best['total_calls']} total calls. No success data recorded yet.",
                ];
            }
        }

        $valid_peaks = array_values(array_filter($peaks, fn ($h) => (int) $h['total_calls'] >= 5));
        $peak_list = array_values(array_filter($valid_peaks, fn ($h) => (float) $h['rate'] >= 65));
        $slow_list = array_values(array_filter($valid_peaks, fn ($h) => (float) $h['rate'] < 30));

        if (!empty($peak_list)) {
            $labels = implode(', ', array_map(fn ($h) => $h['hour'].':00', $peak_list));
            $tips[] = ['icon' => '⏰', 'text' => "Calls at <b>{$labels}</b> convert over 65%. Schedule your best agents in these windows."];
        }

        if (!empty($slow_list)) {
            $slow_labels = implode(', ', array_map(fn ($h) => $h['hour'].':00', $slow_list));
            $tips[] = ['icon' => '🚫', 'text' => "Avoid calling at <b>{$slow_labels}</b> — success rate drops below 30% in these hours."];
        }

        if (!empty($agents) && count($agents) > 1) {
            $rates = array_column($agents, 'success_rate');
            $avg_rate = round(array_sum($rates) / count($rates), 1);

            usort($agents, fn ($a, $b) => $b['success_rate'] <=> $a['success_rate']);

            $top = $agents[0];
            $top_name = trim(($top['firstname'] ?? '').' '.($top['lastname'] ?? ''));

            if (!empty($top_name) && (float) $top['success_rate'] > 0) {
                $tips[] = ['icon' => '🌟', 'text' => "Top agent: <b>{$top_name}</b> at {$top['success_rate']}% success rate. Use them for team coaching sessions."];
            }

            $low_agents = array_filter($agents, function ($a) use ($avg_rate) {
                $name = trim(($a['firstname'] ?? '').' '.($a['lastname'] ?? ''));

                return ($avg_rate - (float) $a['success_rate']) > 15
                    && !empty($name)
                    && (int) $a['total_calls'] >= 5;
            });

            if (!empty($low_agents)) {
                $names = array_filter(array_map(function ($a) {
                    $name = trim(($a['firstname'] ?? '').' '.($a['lastname'] ?? ''));

                    return !empty($name) ? $name : null;
                }, $low_agents));

                if (!empty($names)) {
                    $tips[] = ['icon' => '⚠️', 'text' => '<b>'.implode(', ', $names)."</b> are 15%+ below team average ({$avg_rate}%). Review their call recordings this week."];
                }
            }
        }

        if (!empty($bestDay) && (float) $bestDay['rate'] > 0) {
            $tips[] = ['icon' => '📅', 'text' => "<b>{$bestDay['day_name']}</b> is your best day with {$bestDay['rate']}% success rate. Front-load your hardest campaigns on this day."];
        }

        if (($weekly['total_calls'] ?? 0) < 50) {
            $tips[] = ['icon' => '📉', 'text' => "Only {$weekly['total_calls']} calls this period. Sample size is too small for reliable conclusions — keep calling."];
        }

        if (empty($tips)) {
            $tips[] = ['icon' => '📊', 'text' => 'Not enough data yet to generate insights. Keep calling to unlock AI-powered tips.'];
        }

        $this->_cache_set($key, $tips, 60);

        return $tips;
    }

    public function best_campaign($filters = [])
    {
        $key = $this->_cache_key('ai_camp', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $this->db->reset_query();
        $this->db->select('l.source_campaign AS campaign_name, COUNT(c.id) AS total_calls,
            COALESCE(SUM(c.is_success), 0) AS success_calls,
            ROUND(COALESCE(SUM(c.is_success) / NULLIF(COUNT(c.id), 0) * 100, 0), 2) AS success_rate');
        $this->db->from('tblcall_logs c');
        $this->db->join('tblleads l', 'l.id = c.lead_id', 'left');
        $this->_apply_filters($filters);
        $this->db->where('l.source_campaign IS NOT NULL', null, false);
        $this->db->group_by('l.source_campaign');
        $this->db->order_by('success_rate', 'DESC');
        $this->db->limit(1);
        $result = $this->db->get()->row_array();

        $this->_cache_set($key, $result, 60);

        return $result;
    }

    public function peak_calling_hours($filters = [])
    {
        $key = $this->_cache_key('ai_peak', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $this->db->reset_query();
        $this->db->select("
            HOUR(CONVERT_TZ(c.created_at, '+00:00', '+05:30')) AS hour,
            COUNT(c.id) AS total_calls,
            COALESCE(SUM(c.is_success), 0) AS success_calls,
            ROUND(COALESCE(SUM(c.is_success) / NULLIF(COUNT(c.id), 0) * 100, 0), 2) AS rate
        ");
        $this->db->from('tblcall_logs c');
        $this->db->join('tblleads l', 'l.id = c.lead_id', 'left');
        $this->_apply_filters($filters);
        $this->db->where("HOUR(CONVERT_TZ(c.created_at, '+00:00', '+05:30')) BETWEEN 10 AND 19", null, false);
        $this->db->group_by('hour');
        $this->db->order_by('hour', 'ASC');
        $db_result = $this->db->get()->result_array();

        $by_hour = [];
        foreach ($db_result as $row) {
            $by_hour[(int) $row['hour']] = $row;
        }

        $result = [];
        for ($h = 10; $h <= 19; ++$h) {
            $result[] = $by_hour[$h] ?? [
                'hour' => $h,
                'total_calls' => 0,
                'success_calls' => 0,
                'rate' => 0,
            ];
        }

        $this->_cache_set($key, $result, 60);

        return $result;
    }

    public function agent_performance($filters = [])
    {
        $key = $this->_cache_key('ai_agent', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $this->db->reset_query();
        $this->db->select('s.firstname, s.lastname, COUNT(c.id) AS total_calls,
            ROUND(COALESCE(SUM(c.is_success) / NULLIF(COUNT(c.id), 0) * 100, 0), 2) AS success_rate');
        $this->db->from('tblcall_logs c');
        $this->db->join('tblstaff s', 's.staffid = c.staff_id', 'left');
        $this->db->join('tblleads l', 'l.id = c.lead_id', 'left');
        $this->db->where('s.active', 1);
        $this->db->where("TRIM(CONCAT(s.firstname, ' ', s.lastname)) !=", '');
        $this->db->where('s.firstname IS NOT NULL', null, false);
        $this->_apply_filters($filters);
        $this->db->group_by('c.staff_id');
        $this->db->having('total_calls >=', 5);
        $this->db->order_by('success_rate', 'DESC');
        $result = $this->db->get()->result_array();

        $this->_cache_set($key, $result, 60);

        return $result;
    }

    public function weekly_summary($filters = [])
    {
        $key = $this->_cache_key('ai_weekly', $filters);
        $c = $this->_cache_get($key);
        if ($c !== null) {
            return $c;
        }

        $this->db->reset_query();
        $this->db->select('COUNT(c.id) AS total_calls, COALESCE(SUM(c.is_success), 0) AS success_calls,
            ROUND(COALESCE(SUM(c.is_success) / NULLIF(COUNT(c.id), 0) * 100, 0), 2) AS success_rate');
        $this->db->from('tblcall_logs c');
        $this->db->join('tblleads l', 'l.id = c.lead_id', 'left');
        if (empty($filters['date_range'])) {
            $this->db->where('c.created_at >=', date('Y-m-d', strtotime('-7 days')));
        }
        $this->_apply_filters($filters);
        $result = $this->db->get()->row_array();

        $this->_cache_set($key, $result, 60);

        return $result;
    }

    // ================================================================
    //  EXPORT REPORT (no cache — always fresh)
    // ================================================================
    public function get_dashboard_report($filters = [])
    {
        $this->db->reset_query();
        $this->db->select('DATE(l.dateadded) AS date, s.firstname AS executive,
            l.campaign_name AS campaign, ls.name AS status, IFNULL(l.total_payment, 0) AS amount,
            COUNT(DISTINCT l.id)                                        AS total_leads,
            SUM(l.status IN (1,12,28,29,25,27,26,23))                  AS total_dialed_calls,
            SUM(l.status IN (9,28,29,12))                               AS total_connected_calls,
            SUM(CASE WHEN cl.duration > 0 THEN 1 ELSE 0 END)           AS call_more_than_3_min,
            SUM(l.status IN (23,26,25,24,27,22))                        AS total_not_connected_calls');
        $this->db->from('tblleads l');
        $this->db->join('tblstaff s', 's.staffid = l.assigned', 'left');
        $this->db->join('tblleads_status ls', 'ls.id = l.status', 'left');
        $this->db->join('tblcall_logs cl', 'cl.lead_id = l.id', 'left');
        $this->_apply_filters($filters);
        $this->_apply_user_scope($filters);
        $this->db->group_by('l.id');
        $this->db->order_by('l.dateadded', 'DESC');

        return $this->db->get()->result_array();
    }

    // ================================================================
    //  CHECK IN / OUT / BREAK
    // ================================================================
    public function auto_check_in($staff_id)
    {
        if (empty($staff_id) || !is_numeric($staff_id)) {
            return;
        }
        $today = date('Y-m-d');

        // Close any forgotten session from a previous day
        $this->db->where('staff_id', $staff_id)
                 ->where('check_out_time IS NULL', null, false)
                 ->where('DATE(check_in_time) <', $today)
                 ->update('tbltaskstimers', [
                     'check_out_time' => date('Y-m-d H:i:s'),
                     'end_time' => time(),
                     'status' => 'CHECKED_OUT',
                 ]);

        $exists = $this->db
            ->where('staff_id', $staff_id)
            ->where('check_out_time IS NULL', null, false)
            ->where('DATE(check_in_time)', $today)
            ->get('tbltaskstimers')->row();

        if ($exists) {
            return; // already checked in today
        }

        $this->db->insert('tbltaskstimers', [
            'task_id' => 188,
            'staff_id' => $staff_id,
            'check_in_time' => date('Y-m-d H:i:s'),
            'status' => 'CHECKED_IN',
            'start_time' => time(),
            'note' => 'Auto-login',
            'break_time_seconds' => 0,
            'idle_time_seconds' => 0,
            'total_work_seconds' => 0,
        ]);
    }

    public function auto_check_out($staff_id)
    {
        $row = $this->db
            ->where('staff_id', $staff_id)
            ->where('check_out_time IS NULL', null, false)
            ->get('tbltaskstimers')->row();

        if (!$row) {
            return;
        }

        $total = time() - strtotime($row->check_in_time);
        $break = (int) $row->break_time_seconds;

        $this->db->where('id', $row->id)->update('tbltaskstimers', [
            'check_out_time' => date('Y-m-d H:i:s'),
            'status' => 'CHECKED_OUT',
            'total_work_seconds' => $total,
            'idle_time_seconds' => max(0, $total - $break),
            'end_time' => time(),
            'note' => 'Auto-stopped on logout',
        ]);
    }

    public function start_break($staff_id)
    {
        $this->db
            ->where('staff_id', $staff_id)
            ->where('check_out_time IS NULL', null, false)
            ->update('tbltaskstimers', [
                'is_on_break' => 1,
                'note' => 'BREAK:'.date('Y-m-d H:i:s'),
            ]);
    }

    public function end_break($staff_id)
    {
        $row = $this->db
            ->where('staff_id', $staff_id)
            ->where('check_out_time IS NULL', null, false)
            ->get('tbltaskstimers')->row();

        if (!$row || $row->is_on_break != 1 || strpos($row->note, 'BREAK:') === false) {
            return;
        }

        $duration = max(0, time() - strtotime(str_replace('BREAK:', '', $row->note)));

        $this->db->where('id', $row->id)->update('tbltaskstimers', [
            'is_on_break' => 0,
            'break_time_seconds' => $row->break_time_seconds + $duration,
            'note' => '',
        ]);
    }

    // ================================================================
    //  WEEKLY REPORT HELPERS
    // ================================================================
    public function get_weekly_attendance($from, $to)
    {
        return $this->db
            ->select('staff_id, SUM(total_work_seconds) work_seconds, SUM(call_time_seconds) call_seconds, SUM(break_time_seconds) break_seconds, SUM(idle_time_seconds) idle_seconds')
            ->from('tbltaskstimers')
            ->where('check_in_time >=', $from)
            ->where('check_in_time <=', $to)
            ->group_by('staff_id')
            ->get()->result_array();
    }

    public function get_weekly_calls($from, $to)
    {
        return $this->db
            ->select('staff_id, COUNT(*) total_calls, SUM(is_success = 1) successful_calls, AVG(duration) avg_duration')
            ->from('tblcall_logs')
            ->where('created_at >=', $from)
            ->where('created_at <=', $to)
            ->group_by('staff_id')
            ->get()->result_array();
    }

    public function get_weekly_leads($from, $to)
    {
        return $this->db
            ->select('assigned AS staff_id, COUNT(*) total_leads, SUM(date_converted IS NOT NULL) converted_leads')
            ->from('tblleads')
            ->where('dateadded >=', $from)
            ->where('dateadded <=', $to)
            ->group_by('assigned')
            ->get()->result_array();
    }


 public function get_today_leads($filters)
{
    $this->db->where('DATE(dateadded)', date('Y-m-d'));
    if (!empty($filters['view_assigned'])) {
        $this->db->where('assigned', $filters['view_assigned']);
    }
    return (int) $this->db->count_all_results('tblleads');
}

public function get_today_calls($filters)
{
    $this->db->where('DATE(created_at)', date('Y-m-d'));
    if (!empty($filters['view_assigned'])) {
        $this->db->where('staff_id', $filters['view_assigned']);
    }
    return (int) $this->db->count_all_results('tblcall_logs');
}

}