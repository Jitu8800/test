<?php

header('Content-Type: text/html; charset=utf-8');
defined('BASEPATH') or exit('No direct script access allowed');

use GuzzleHttp\Client;

class LeadController extends CI_Controller
{
    private $verify_token;
    private $system_token;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('leads_model');
        $this->load->model('dialer_model');
        $this->load->library('App_object_cache');
        require_once APPPATH.'vendor/autoload.php';

        $this->verify_token = META_VERIFY_TOKEN;
        $this->system_token = META_SYSTEM_USER_TOKEN;
    }

    /* List all leads */

    public function table()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }
        $this->app->get_table_data('leads');
    }

    public function kanban()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }

        $data['statuses'] = $this->leads_model->get_status();
        $data['base_currency'] = get_base_currency();
        $data['summary'] = get_leads_summary();

        echo $this->load->view('admin/leads/kan-ban', $data, true);
    }

    /* Add or update lead */

    public function receiveLeadFromPabbly_______________()
    {
        $rawData = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? trim(str_replace('Bearer', '', $headers['Authorization'])) : '';

        if (!empty($rawData) && $token === '7d68e2c38dfb38785e7a') {
            // Validate required fields
            if (empty($rawData['name']) || empty($rawData['email']) || empty($rawData['phone'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Required fields missing: name, email, phone',
                    'code' => 422,
                ]);

                return;
            }

            // Validate email format
            if (!filter_var($rawData['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email format',
                    'code' => 422,
                ]);

                return;
            }

            // Check for duplicate email
            $this->db->where('email', $rawData['email']);
            $emailExists = $this->db->get(db_prefix().'leads')->row();
            if ($emailExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already exists',
                    'code' => 409,
                ]);

                return;
            }

            // Check for duplicate phone
            $this->db->where('phonenumber', $rawData['phone']);
            $phoneExists = $this->db->get(db_prefix().'leads')->row();
            if ($phoneExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number already exists',
                    'code' => 409,
                ]);

                return;
            }

            // Prepare lead data
            $dataArr = [
                'status' => 4,
                'source' => 3,
                'assigned' => 0, // default, will update later
                'sub_status' => null,
                'name' => trim($rawData['name']),
                'title' => 'Lead from Pabbly',
                'email' => trim($rawData['email']),
                'phonenumber' => trim($rawData['phone']),
                'company' => isset($rawData['company']) ? trim($rawData['company']) : null,
                'description' => isset($rawData['description']) ? trim($rawData['description']) : null,
                'dateadded' => date('Y-m-d H:i:s'),
                'address' => isset($rawData['address']) ? trim($rawData['address']) : null,
                'city' => isset($rawData['city']) ? trim($rawData['city']) : null,
                'state' => isset($rawData['state']) ? trim($rawData['state']) : null,
                'country' => isset($rawData['country']) ? (int) $rawData['country'] : 0,
                'zip' => isset($rawData['zip']) ? trim($rawData['zip']) : null,
                'q1' => isset($rawData['q1']) ? trim($rawData['q1']) : null,
                'q2' => isset($rawData['q2']) ? trim($rawData['q2']) : null,
                'q3' => isset($rawData['q3']) ? trim($rawData['q3']) : null,
                'branchid' => isset($rawData['branchid']) ? trim($rawData['branchid']) : null,
                'default_language' => 'English',
            ];

            $leadId = $this->leads_model->addApiLead($dataArr);

            if ($leadId) {
                $assignedStaffId = null;

                if (!empty($dataArr['branchid'])) {
                    $branchId = $dataArr['branchid'];

                    // Step 1: Get all active staff (those with end_time IS NULL and not admin)

                    $this->db->distinct();
                    $this->db->select('s.staffid');
                    $this->db->from(db_prefix().'staff s');
                    $this->db->join(db_prefix().'taskstimers t', 't.staff_id = s.staffid AND t.end_time IS NULL', 'inner');
                    $this->db->where('s.branch_id', $branchId);
                    $this->db->where('s.staffid !=', 1); // exclude admin
                    $activeStaff = $this->db->get()->result_array();

                    if (!empty($activeStaff)) {
                        $activeStaffIds = array_column($activeStaff, 'staffid');

                        // Step 2: Count current leads for each active staff in this branch
                        $this->db->select('assigned, COUNT(id) AS lead_count');
                        $this->db->from(db_prefix().'leads');
                        $this->db->where_in('assigned', $activeStaffIds);
                        $this->db->where('branchid', $branchId);
                        $this->db->group_by('assigned');
                        $leadCounts = $this->db->get()->result_array();

                        // Step 3: Build map (default = 0 if no leads)
                        $leadCountMap = [];
                        foreach ($activeStaffIds as $sid) {
                            $leadCountMap[$sid] = 0;
                        }
                        foreach ($leadCounts as $row) {
                            $leadCountMap[$row['assigned']] = (int) $row['lead_count'];
                        }

                        // Step 4: Find the minimum lead count
                        $minLeadCount = min($leadCountMap);

                        // Step 5: Filter staff who have that minimum count
                        $eligibleStaff = array_keys($leadCountMap, $minLeadCount);

                        // Step 6: Randomly pick one from eligible
                        if (!empty($eligibleStaff)) {
                            $assignedStaffId = $eligibleStaff[array_rand($eligibleStaff)];
                        }
                    }
                }

                // Step 7: If no active staff, lead remains unassigned
                if (!$assignedStaffId) {
                    $assignedStaffId = 0;
                }

                // Step 8: Update lead record
                $this->db->where('id', $leadId);
                $this->db->update(db_prefix().'leads', ['assigned' => $assignedStaffId]);

                // Step 9: Log + notify only if assigned
                if ($assignedStaffId > 0) {
                    $this->db->select('firstname, lastname');
                    $this->db->where('staffid', $assignedStaffId);
                    $staff = $this->db->get(db_prefix().'staff')->row();
                    $assignedName = $staff ? trim($staff->firstname.' '.$staff->lastname) : 'Unknown Staff';

                    $additional_data = serialize([
                        $assignedName,
                        '<a href="'.admin_url('profile/'.$assignedStaffId).'" target="_blank">'.$assignedName.'</a>',
                    ]);

                    $this->db->insert(db_prefix().'lead_activity_log', [
                        'date' => date('Y-m-d H:i:s'),
                        'description' => 'not_lead_activity_assigned_to',
                        'leadid' => $leadId,
                        'staffid' => $assignedStaffId,
                        'additional_data' => $additional_data,
                        'full_name' => $assignedName,
                        'custom_activity' => 0,
                    ]);

                    $notificationData = [
                        'date' => date('Y-m-d H:i:s'),
                        'description' => 'not_assigned_lead_to_you',
                        'fromuserid' => 0,
                        'fromclientid' => 0,
                        'from_fullname' => '[API]',
                        'touserid' => $assignedStaffId,
                        'fromcompany' => 1,
                        'link' => 'leads/index/'.$leadId,
                        'additional_data' => serialize([$assignedName]),
                    ];

                    $this->db->insert(db_prefix().'notifications', $notificationData);
                }

                // Step 10: Response
                echo json_encode([
                    'success' => true,
                    'message' => $assignedStaffId > 0
                        ? 'Lead created and assigned to active staff successfully in CRM'
                        : 'Lead created but left unassigned (no active staff found in branch)',
                    'lead_id' => $leadId,
                    'assigned_staff' => $assignedStaffId,
                    'code' => 201,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create lead in CRM',
                    'code' => 500,
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request or token',
                'code' => 400,
            ]);
        }
    }

    public function receiveLeadFromPabbly()
    {
        $rawData = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? trim(str_replace('Bearer', '', $headers['Authorization'])) : '';

        // Validate token
        if (empty($rawData) || $token !== '7d68e2c38dfb38785e7a') {
            echo json_encode(['success' => false, 'message' => 'Invalid request or token', 'code' => 400]);

            return;
        }

        // Validate required fields
        if (empty($rawData['name']) || empty($rawData['email']) || empty($rawData['phone'])) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing: name, email, phone', 'code' => 422]);

            return;
        }

        // Validate email format
        if (!filter_var($rawData['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format', 'code' => 422]);

            return;
        }

        // Check duplicate email
        $this->db->where('email', $rawData['email']);
        if ($this->db->get(db_prefix().'leads')->row()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists', 'code' => 409]);

            return;
        }

        // Check duplicate phone
        $this->db->where('phonenumber', $rawData['phone']);
        if ($this->db->get(db_prefix().'leads')->row()) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists', 'code' => 409]);

            return;
        }

        // Prepare lead data
        $dataArr = [
            'status' => 4,
            'source' => 3,
            'assigned' => 0,
            'sub_status' => null,
            'name' => trim($rawData['name']),
            'title' => 'Lead from Pabbly',
            'email' => trim($rawData['email']),
            'phonenumber' => trim($rawData['phone']),
            'company' => isset($rawData['company']) ? trim($rawData['company']) : null,
            'description' => isset($rawData['description']) ? trim($rawData['description']) : null,
            'dateadded' => date('Y-m-d H:i:s'),
            'address' => isset($rawData['address']) ? trim($rawData['address']) : null,
            'city' => isset($rawData['city']) ? trim($rawData['city']) : null,
            'state' => isset($rawData['state']) ? trim($rawData['state']) : null,
            'country' => isset($rawData['country']) ? (int) $rawData['country'] : 0,
            'zip' => isset($rawData['zip']) ? trim($rawData['zip']) : null,
            'q1' => isset($rawData['q1']) ? trim($rawData['q1']) : null,
            'q2' => isset($rawData['q2']) ? trim($rawData['q2']) : null,
            'q3' => isset($rawData['q3']) ? trim($rawData['q3']) : null,
            'branchid' => isset($rawData['branchid']) ? trim($rawData['branchid']) : null,
            'language' => isset($rawData['language']) ? trim($rawData['language']) : null,
            'default_language' => 'English',
        ];

        // Insert lead
        $leadId = $this->leads_model->addApiLead($dataArr);

        if (!$leadId) {
            echo json_encode(['success' => false, 'message' => 'Failed to create lead in CRM', 'code' => 500]);

            return;
        }

        $assignedStaffId = 0;
        $branchId = $dataArr['branchid'];
        $leadLang = $dataArr['language'];

        // Assign only if branch exists
        if (!empty($branchId)) {
            // Step 1: Get all active (timer-running) staff in branch
            $this->db->distinct();
            $this->db->select('s.staffid, s.language');
            $this->db->from(db_prefix().'staff s');
            $this->db->join(db_prefix().'taskstimers t', 't.staff_id = s.staffid AND t.end_time IS NULL', 'inner');
            $this->db->where('s.branch_id', $branchId);
            $this->db->where('s.staffid !=', 1); // exclude admin
            $this->db->where('s.active', 1);
            $activeStaff = $this->db->get()->result_array();

            if (!empty($activeStaff)) {
                $filteredStaff = [];

                // Step 2: If language provided → filter by language
                if (!empty($leadLang)) {
                    foreach ($activeStaff as $stf) {
                        if (strtolower(trim($stf['language'])) === strtolower(trim($leadLang))) {
                            $filteredStaff[] = $stf['staffid'];
                        }
                    }

                    // No match on language → leave unassigned
                    if (empty($filteredStaff)) {
                        log_message('info', "No staff with language '{$leadLang}' found in branch {$branchId}");
                    }
                } else {
                    // No language → all active staff eligible
                    $filteredStaff = array_column($activeStaff, 'staffid');
                }

                if (!empty($filteredStaff)) {
                    // Step 3: Count leads per eligible staff
                    $this->db->select('assigned, COUNT(id) AS lead_count');
                    $this->db->from(db_prefix().'leads');
                    $this->db->where_in('assigned', $filteredStaff);
                    $this->db->where('branchid', $branchId);
                    $this->db->group_by('assigned');
                    $leadCounts = $this->db->get()->result_array();

                    // Step 4: Build lead count map
                    $leadCountMap = [];
                    foreach ($filteredStaff as $sid) {
                        $leadCountMap[$sid] = 0;
                    }
                    foreach ($leadCounts as $row) {
                        $leadCountMap[$row['assigned']] = (int) $row['lead_count'];
                    }

                    // Step 5: Assign to staff with fewest leads
                    $minLeadCount = min($leadCountMap);
                    $eligible = array_keys($leadCountMap, $minLeadCount);
                    $assignedStaffId = $eligible[array_rand($eligible)];
                }
            } else {
                log_message('info', "No active staff found in branch {$branchId}, leaving lead unassigned.");
            }
        }

        // Step 6: Update lead assignment
        $this->db->where('id', $leadId);
        $this->db->update(db_prefix().'leads', ['assigned' => $assignedStaffId]);

        // Step 7: Log + notify if assigned
        if ($assignedStaffId > 0) {
            $this->db->select('firstname, lastname');
            $this->db->where('staffid', $assignedStaffId);
            $staff = $this->db->get(db_prefix().'staff')->row();
            $assignedName = $staff ? trim($staff->firstname.' '.$staff->lastname) : 'Unknown Staff';

            $additional_data = serialize([
                $assignedName,
                '<a href="'.admin_url('profile/'.$assignedStaffId).'" target="_blank">'.$assignedName.'</a>',
            ]);

            // Activity log
            $this->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'not_lead_activity_assigned_to',
                'leadid' => $leadId,
                'staffid' => $assignedStaffId,
                'additional_data' => $additional_data,
                'full_name' => $assignedName,
                'custom_activity' => 0,
            ]);

            // Notification
            $this->db->insert(db_prefix().'notifications', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'not_assigned_lead_to_you',
                'fromuserid' => 0,
                'fromclientid' => 0,
                'from_fullname' => '[API]',
                'touserid' => $assignedStaffId,
                'fromcompany' => 1,
                'link' => 'leads/index/'.$leadId,
                'additional_data' => serialize([$assignedName]),
            ]);
        } else {
            // Log reason for unassigned lead
            $reason = empty($branchId)
                ? 'No branch assigned'
                : (!empty($leadLang)
                    ? "No matching staff found for language '{$leadLang}' in branch {$branchId}"
                    : "No active staff found in branch {$branchId}");

            $this->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'Lead left unassigned: '.$reason,
                'leadid' => $leadId,
                'staffid' => 0,
                'full_name' => '[System]',
                'custom_activity' => 1,
            ]);
        }

        // Step 8: Final response
        echo json_encode([
            'success' => true,
            'message' => $assignedStaffId > 0
                ? 'Lead created and assigned successfully'
                : 'Lead created but left unassigned (no matching staff found)',
            'lead_id' => $leadId,
            'assigned_staff' => $assignedStaffId,
            'code' => 201,
        ]);
    }

    // --------------------------------------------------------
    //   META WEBHOOK ENTRY
    // --------------------------------------------------------
    public function receive()
    {
        // ✅ STEP 1: Verification
        if ($this->input->method() === 'get') {
            $mode = $this->input->get('hub_mode');
            $challenge = $this->input->get('hub_challenge');
            $verify = $this->input->get('hub_verify_token');

            log_message('debug', 'META VERIFY REQUEST: '.json_encode($_GET));

            if ($mode === 'subscribe' && $verify === $this->verify_token) {
                echo $challenge;

                return;
            }

            log_message('error', 'META VERIFY FAILED');
            show_error('Verification failed', 403);

            return;
        }

        // ✅ STEP 2: Lead Payload
        $raw = file_get_contents('php://input');
        log_message('debug', 'META RAW PAYLOAD: '.$raw);

        if (empty($raw)) {
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'Empty payload']));

            return;
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'META INVALID JSON');
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'Invalid JSON']));

            return;
        }

        $leadgen_id = $data['entry'][0]['changes'][0]['value']['leadgen_id'] ?? null;

        if (!$leadgen_id) {
            log_message('error', 'META INVALID PAYLOAD STRUCTURE');
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'Invalid payload']));

            return;
        }

        log_message('debug', 'META LEADGEN ID: '.$leadgen_id);

        $this->fetchLead($leadgen_id);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'ok', 'leadgen_id' => $leadgen_id]));
    }

    // --------------------------------------------------------
    //   FETCH LEAD FROM GRAPH API
    // --------------------------------------------------------
    private function fetchLead($leadgen_id)
    {
        $url = "https://graph.facebook.com/v21.0/{$leadgen_id}"
            .'?fields=field_data,created_time,ad_id,form_id,campaign_id'
            ."&access_token={$this->system_token}";

        $resp = $this->curl($url);
        $lead = json_decode($resp, true);

        if (isset($lead['id'])) {
            $this->saveLead($lead);
        } else {
            log_message('error', 'META LEAD FETCH FAILED: '.$resp);
        }
    }

    // --------------------------------------------------------
    //   SAVE LEAD
    // --------------------------------------------------------
    private function saveLead($lead)
    {
        // prevent duplicate
        $exists = $this->db->get_where('tblleads', [
            'meta_lead_id' => $lead['id'],
        ])->row();

        if ($exists) {
            log_message('debug', 'META DUPLICATE LEAD: '.$lead['id']);

            return;
        }

        // normalize fields
        $fields = [];
        foreach ($lead['field_data'] ?? [] as $f) {
            $fields[strtolower(trim($f['name']))] = $f['values'][0] ?? null;
        }

        // fetch names
        $campaign_name = $this->fetchName($lead['campaign_id'] ?? null);
        $ad_name = $this->fetchName($lead['ad_id'] ?? null);
        $form_name = $this->fetchName($lead['form_id'] ?? null);

        $dataArr = [
            'meta_lead_id' => $lead['id'],

            // main fields
            'name' => $fields['full_name'] ?? 'Unknown',
            'email' => $fields['email'] ?? null,
            'phonenumber' => $fields['phone_number'] ?? null,
            'company' => $fields['business_/_brand_name_'] ?? null,
            'city' => $fields['city'] ?? null,

            // custom questions
            'title' => $fields['what_type_of_real_estate_business_do_you_run?'] ?? null,
            'q1' => $fields['_do_you_work_in_real_estate?'] ?? null,

            // meta info
            'campaign_id' => $lead['campaign_id'] ?? null,
            'campaign_name' => $campaign_name,
            'ad_id' => $lead['ad_id'] ?? null,
            'ad_name' => $ad_name,
            'form_id' => $lead['form_id'] ?? null,
            'form_name' => $form_name,

            // system
            'status' => 4,
            'source' => 3,
            'addedfrom' => 1,
            'dateadded' => date('Y-m-d H:i:s'),
            'description' => 'Imported from Meta Lead Ads',
            'platform_text' => 'facebook',

            // raw
            'raw_payload' => json_encode($lead),
        ];

        $this->leads_model->addApiLead($dataArr);
        log_message('debug', 'META LEAD SAVED: '.$lead['id']);
    }

    // --------------------------------------------------------
    //   FETCH NAME BY ID
    // --------------------------------------------------------
    private function fetchName($id)
    {
        if (!$id) {
            return null;
        }

        $url = "https://graph.facebook.com/v21.0/{$id}?fields=name&access_token={$this->system_token}";
        $resp = $this->curl($url);
        $res = json_decode($resp, true);

        return $res['name'] ?? null;
    }

    // --------------------------------------------------------
    //   CURL HELPER
    // --------------------------------------------------------
    private function curl($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);

        if (curl_errno($ch)) {
            log_message('error', 'CURL ERROR: '.curl_error($ch));
        }
        curl_close($ch);

        return $resp;
    }

    public function receiveLeadFromPabbly_old()
    {
        $rawData = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? trim(str_replace('Bearer', '', $headers['Authorization'])) : '';

        if (!empty($rawData) && $token === '7d68e2c38dfb38785e7a') {
            if (empty($rawData['name']) || empty($rawData['email']) || empty($rawData['phone'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Required fields missing: name, email, phone',
                    'code' => 422,
                ]);

                return;
            }

            if (!filter_var($rawData['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email format',
                    'code' => 422,
                ]);

                return;
            }

            $this->db->where('email', $rawData['email']);
            $emailExists = $this->db->get(db_prefix().'leads')->row();

            if ($emailExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already exists',
                    'code' => 409,
                ]);

                return;
            }

            $this->db->where('phonenumber', $rawData['phone']);
            $phoneExists = $this->db->get(db_prefix().'leads')->row();

            if ($phoneExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number already exists',
                    'code' => 409,
                ]);

                return;
            }

            $dataArr = [
                'status' => 4, // default status
                'source' => 3, // example source
                'assigned' => 0, // default assigned user
                'sub_status' => null,
                'name' => trim($rawData['name']),
                'title' => 'Lead from Pabbly',
                'email' => trim($rawData['email']),
                'phonenumber' => trim($rawData['phone']),
                'company' => isset($rawData['company']) ? trim($rawData['company']) : null,
                'description' => isset($rawData['description']) ? trim($rawData['description']) : null,
                'dateadded' => date('Y-m-d H:i:s'),
                'address' => isset($rawData['address']) ? trim($rawData['address']) : null,
                'city' => isset($rawData['city']) ? trim($rawData['city']) : null,
                'state' => isset($rawData['state']) ? trim($rawData['state']) : null,
                'country' => isset($rawData['country']) ? (int) $rawData['country'] : 0,
                'zip' => isset($rawData['zip']) ? trim($rawData['zip']) : null,
                'default_language' => 'English',
            ];

            $leadId = $this->leads_model->addApiLead($dataArr);

            if ($leadId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Lead created successfully in CRM',
                    'lead_id' => $leadId,
                    'code' => 201,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create lead in CRM',
                    'code' => 500,
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request or token',
                'code' => 400,
            ]);
        }
    }

    public function leadGenerate()
    {
        $rawData = json_decode(file_get_contents('php://input'), true);

        if (!empty($rawData) && isset($rawData['token']) && $rawData['token'] == '7d68e2c38dfb38785e7a') {
            if (empty($rawData['name']) || empty($rawData['email']) || empty($rawData['phone'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Required fields missing: name, email, phone',
                    'code' => 422,
                ]);

                return;
            }

            if (!filter_var($rawData['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email format',
                    'code' => 422,
                ]);

                return;
            }

            $this->db->where('email', $rawData['email']);
            $emailExists = $this->db->get(db_prefix().'leads')->row();

            if ($emailExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already exists',
                    'code' => 409,
                ]);

                return;
            }

            $this->db->where('phonenumber', $rawData['phone']);
            $phoneExists = $this->db->get(db_prefix().'leads')->row();

            if ($phoneExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number already exists',
                    'code' => 409,
                ]);

                return;
            }

            $dataArr['status'] = 2;
            $dataArr['source'] = 5;
            $dataArr['assigned'] = 1;
            $dataArr['sub_status'] = null;
            $dataArr['name'] = trim($rawData['name']);
            $dataArr['title'] = 'Lead from Bastionex Site';
            $dataArr['email'] = trim($rawData['email']);
            $dataArr['phonenumber'] = trim($rawData['phone']);
            $dataArr['company'] = isset($rawData['company']) ? trim($rawData['company']) : null;
            $dataArr['description'] = isset($rawData['description']) ? trim($rawData['description']) : null;
            $dataArr['dateadded'] = date('Y-m-d H:i:s');
            $dataArr['address'] = isset($rawData['address']) ? trim($rawData['address']) : null;
            $dataArr['city'] = isset($rawData['city']) ? trim($rawData['city']) : null;
            $dataArr['state'] = isset($rawData['state']) ? trim($rawData['state']) : null;
            $dataArr['country'] = isset($rawData['country']) ? (int) $rawData['country'] : 0;
            $dataArr['zip'] = isset($rawData['zip']) ? trim($rawData['zip']) : null;
            $dataArr['default_language'] = 'English';

            $status = $this->leads_model->addApiLead($dataArr);

            if ($status) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Lead created successfully',
                    'lead_id' => $status,
                    'code' => 201,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create lead',
                    'code' => 500,
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Request',
                'code' => 400,
            ]);
        }
    }

    /* Total Leads Summary */

    public function totalLeadsSummary()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, token');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        $start_date = isset($data['start_date']) ? $data['start_date'] : null;
        $end_date = isset($data['end_date']) ? $data['end_date'] : null;
        $validToken = base64_encode('7d68e2c38dfb38785e7a');
        $requestToken = $this->input->get_request_header('token', true);
        if ($requestToken === $validToken) {
            $sql = "SELECT  
                COUNT(tblleads.id) AS total_leads, 
                SUM(CASE WHEN tblleads.status = 4 THEN 1 ELSE 0 END) AS new_leads, 
                SUM(CASE WHEN tblleads.status = 11 THEN 1 ELSE 0 END) AS whatsap_msg_Leads, 
                SUM(CASE WHEN tblleads.status = 5 THEN 1 ELSE 0 END) AS answered_leads, 
                SUM(CASE WHEN tblleads.status = 6 THEN 1 ELSE 0 END) AS call_leads, 
                SUM(CASE WHEN tblleads.status = 10 THEN 1 ELSE 0 END) AS close_leads, 
                SUM(CASE WHEN tblleads.status = 13 THEN 1 ELSE 0 END) AS cash_on_delivery_leads, 
                SUM(CASE WHEN tblleads.status = 1 THEN 1 ELSE 0 END) AS customer_leads, 
                SUM(CASE WHEN tblleads.status = 7 THEN 1 ELSE 0 END) AS failed_leads, 
                SUM(CASE WHEN tblleads.status = 12 THEN 1 ELSE 0 END) AS follow_up_leads, 
                SUM(CASE WHEN tblleads.status = 8 THEN 1 ELSE 0 END) AS intro_call_leads, 
                SUM(CASE WHEN tblleads.status = 9 THEN 1 ELSE 0 END) AS prospect_leads, 
                SUM(CASE WHEN tblleads.status = 3 THEN 1 ELSE 0 END) AS rejected_leads, 
                SUM(CASE WHEN tblleads.card_ordered = 'Yes' THEN 1 ELSE 0 END) AS card_ordered, 
                SUM(CASE WHEN tblleads.card_delivered = 'Yes' THEN 1 ELSE 0 END) AS card_delivered, 
                SUM(CASE WHEN tblleads.source = 2 THEN tblleads.total_payment ELSE 0 END) AS online_revenue, 
                SUM(CASE WHEN tblleads.source = 1 THEN tblleads.total_payment ELSE 0 END) AS offline_revenue, 
                SUM(CASE WHEN tblleads.status = 1 THEN tblleads.total_payment ELSE 0 END) AS total_revenue 
            FROM tblleads";

            if (isset($data['email']) && !empty($data['email'])) {
                $emails = explode(',', $data['email']);
                $emails = array_map(function ($email) {
                    return "'".$this->db->escape_str(trim($email))."'";
                }, $emails);
                $sql .= ' JOIN tblstaff ON tblleads.assigned = tblstaff.staffid';
                if (count($emails) === 1) {
                    $sql .= ' WHERE tblstaff.email = '.$emails[0];
                } else {
                    $sql .= ' WHERE tblstaff.email IN ('.implode(',', $emails).')';
                }
            } else {
                $sql .= ' WHERE tblleads.junk = 0';
            }

            if (!empty($start_date) && !empty($end_date)) {
                $sql .= " AND tblleads.dateadded BETWEEN '$start_date' AND '$end_date'";
            }

            $query = $this->db->query($sql);
            $result = $query->row_array();

            if (empty($result['total_leads']) || $result['total_leads'] < 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No data found!',
                    'code' => 0,
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Data found successfully',
                    'data' => $result,
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Request',
                'code' => 0,
            ]);
        }
    }

    public function sinkBusinessBayLead()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, token');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        $validToken = base64_encode('7d68e2c38dfb38785e7a');
        $requestToken = $this->input->get_request_header('token', true);

        if ($requestToken === $validToken) {
            $sql = 'SELECT * FROM tblleads WHERE id ='.$data['id'];
            $query = $this->db->query($sql);
            $result = $query->row_array();

            if (empty($result)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No data found!',
                    'code' => 0,
                ]);

                return;
            } else {
                $arr = ['status' => 4];
                $this->db->where('id', $result['id']);
                $this->db->update('tblleads', $arr);
                if ($this->db->affected_rows() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Update successful!',
                    ]);

                    return;
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Update failed or no changes were made.',
                    ]);

                    return;
                }
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Request',
                'code' => 0,
            ]);
        }
    }

    // ##########3

    public function leadgGenerateByMeta()
    {
        if ($this->input->post() && $this->input->post('token') == '7d68e2c38dfb38785e7a') {
            $requiredFields = [
                'name' => $this->input->post('name'),
                'utm_source' => $this->input->post('utm_source'),
                'utm_medium' => $this->input->post('utm_medium'),
                'utm_campagin' => $this->input->post('utm_campagin'),
                'email' => $this->input->post('email'),
                'phone' => $this->input->post('phone'),
            ];

            foreach ($requiredFields as $field => $value) {
                if (empty($value)) {
                    echo json_encode([
                        'status' => false,
                        'message' => "Error: The field '$field' is required.",
                        'code' => 0,
                    ]);

                    return;
                }
            }

            $email = $this->input->post('email');
            $existingLead = $this->db->get_where('tblleads', ['email' => $email])->row();

            if ($existingLead) {
                echo json_encode([
                    'status' => false,
                    'message' => "Error: Duplicate email. The email '$email' is already in use.",
                    'code' => 0,
                ]);

                return;
            }

            $dataSource = $this->db->get_where('tblleads_sources', ['name' => $this->input->post('utm_source')])->row();
            $matched_value = $dataSource ? $dataSource->value : '';

            $dataArr = [
                'status' => 2,
                'source' => 3,
                'assigned' => 1,
                'name' => $requiredFields['name'],
                'utm_source' => $requiredFields['utm_source'],
                'utm_medium' => $requiredFields['utm_medium'],
                'utm_campagin' => $requiredFields['utm_campagin'],
                'title' => 'Lead from bastionex Site',
                'email' => $requiredFields['email'],
                'phonenumber' => $requiredFields['phone'],
                // 'lead_value' => $matched_value,
                'company' => $this->input->post('company'),
                'address' => null,
                'city' => null,
                'state' => null,
                'country' => 0,
                'zip' => null,
                'default_language' => 'english',
                'description' => $this->input->post('message'),
            ];

            $status = $this->leads_model->leadByMeta($dataArr);

            echo json_encode([
                'status' => $status ? true : false,
                'message' => $status ? 'Success' : 'Failed',
                'code' => $status ? 1 : 0,
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Request',
                'code' => 0,
            ]);
        }
    }

    public function importVmycard()
    {
        if ($this->input->post() && $this->input->post('token') == '7d68e2c38dfb38785e7a') {
            // if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '')
            // {
            //     //echo "yes";die;
            //     $this->import->setSimulation($this->input->post('simulate'))
            //                 ->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])
            //                 ->setFilename($_FILES['file_csv']['name'])
            //                 ->perform();
            //     $data['total_rows_post'] = $this->import->totalRows();

            // echo $data['total_rows_post'];die;
            if (!$this->import->isSimulation()) {
                set_alert('success', _l('import_total_imported', $this->import->totalImported()));
            }
            $dataArr['name'] = $this->input->post('name');
            $dataArr['title'] = null;
            $dataArr['company'] = $this->input->post('company');
            $dataArr['country'] = $this->input->post('country');
            $dataArr['zip'] = $this->input->post('zip');
            $dataArr['city'] = $this->input->post('city');
            $dataArr['state'] = $this->input->post('state');
            $dataArr['address'] = $this->input->post('address');
            $dataArr['email'] = $this->input->post('email');
            $dataArr['phonenumber'] = $this->input->post('phonenumber');
            $dataArr['website'] = $this->input->post('website');
            $dataArr['description'] = $this->input->post('description');
            $dataArr['leadorder'] = 1;
            $dataArr['assigned'] = 1;
            $dataArr['from_form_id'] = 0;
            $dataArr['status'] = 3;
            $dataArr['source'] = 4;
            $dataArr['lastcontact'] = null;
            $dataArr['dateassigned'] = null;
            $dataArr['last_status_change'] = null;
            $dataArr['addedfrom'] = 1;
            $dataArr['date_converted'] = null;
            $dataArr['lost'] = 0;
            $dataArr['junk'] = 0;
            $dataArr['last_lead_status'] = 0;
            $dataArr['is_imported_from_email_integration'] = null;
            $dataArr['email_integration_uid'] = null;
            $dataArr['is_public'] = 0;
            $dataArr['default_language'] = null;
            $dataArr['client_id'] = 0;
            // $dataArr['lead_value']                          = $this->input->post('lead_value');

            // }
            $status = $this->leads_model->addApiLead($dataArr);
            if ($status) {
                echo json_encode([
                    'status' => true,
                    'message' => 'Success',
                    'code' => 1,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Falied',
                    'code' => 0,
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Request',
                'code' => 0,
            ]);
        }
    }

    // lead auto-assignment logic

    public function autoAssignLeads_old()
    {
        $this->db->select('id');
        $this->db->from(db_prefix().'leads');
        $this->db->where('assigned', 0);
        $unassignedLeads = $this->db->get()->result_array();

        if (empty($unassignedLeads)) {
            echo json_encode([
                'success' => true,
                'message' => 'No unassigned leads found',
                'assigned_count' => 0,
            ]);

            return;
        }

        // STEP 1: Get all LOGGED-IN staff
        $this->db->select('s.staffid, s.firstname, s.lastname');
        $this->db->from(db_prefix().'staff AS s');
        $this->db->join('tbltaskstimers AS t', 't.staff_id = s.staffid', 'inner');

        $this->db->where('s.active', 1);
        $this->db->where('s.admin !=', 1);
        $this->db->where('s.role =', 3);
        $this->db->where('s.auto_assign_leads =', 1);
        $this->db->where('t.status', 'CHECKED_IN');
        $this->db->where('t.check_in_time IS NOT NULL', null, false);
        $this->db->where('t.check_out_time IS NULL', null, false);

        $this->db->group_by('s.staffid');
        $loggedInStaff = $this->db->get()->result_array();

        if (empty($loggedInStaff)) {
            echo json_encode([
                'success' => false,
                'message' => 'No logged-in staff available',
                'assigned_count' => 0,
            ]);

            return;
        }

        // Prepare staff IDs
        $staffIds = array_column($loggedInStaff, 'staffid');

        // STEP 2: Count existing leads per staff
        $this->db->select('assigned, COUNT(id) AS lead_count');
        $this->db->from(db_prefix().'leads');
        $this->db->where_in('assigned', $staffIds);
        $this->db->where('status', 4);
        $this->db->group_by('assigned');
        $leadCounts = $this->db->get()->result_array();

        $leadCountMap = array_fill_keys($staffIds, 0);

        foreach ($leadCounts as $row) {
            $leadCountMap[$row['assigned']] = (int) $row['lead_count'];
        }

        $MAX_LIMIT = 20;  // HARD LIMIT
        $assignedCount = 0;

        // STEP 3: Assign each unassigned lead WITH MAX LIMIT + ROUND ROBIN
        foreach ($unassignedLeads as $lead) {
            // Filter staff who have NOT reached max 20
            $eligibleStaff = array_filter($leadCountMap, function ($count) use ($MAX_LIMIT) {
                return $count < $MAX_LIMIT;
            });

            // NO staff available, stop assigning
            if (empty($eligibleStaff)) {
                break;
            }

            // Round robin: take staff with minimum lead load
            $minLeadCount = min($eligibleStaff);

            // All staff having minimum count
            $minStaff = array_keys($eligibleStaff, $minLeadCount);

            // Random pick among the minimum load users
            $assignedStaffId = $minStaff[array_rand($minStaff)];

            // UPDATE lead table
            $this->db->where('id', $lead['id']);
            $this->db->update(db_prefix().'leads', [
                'assigned' => $assignedStaffId,
            ]);

            // Increase count
            ++$leadCountMap[$assignedStaffId];

            // Get staff name
            $staffData = array_values(array_filter(
                $loggedInStaff,
                fn ($s) => $s['staffid'] == $assignedStaffId
            ))[0] ?? null;

            $assignedName = $staffData
                ? trim($staffData['firstname'].' '.$staffData['lastname'])
                : 'Unknown';

            // Activity Log
            $this->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'Auto-assigned lead',
                'leadid' => $lead['id'],
                'staffid' => $assignedStaffId,
                'full_name' => $assignedName,
                'custom_activity' => 0,
            ]);

            ++$assignedCount;
        }

        echo json_encode([
            'success' => true,
            'message' => "Auto-assigned {$assignedCount} lead(s) successfully",
            'assigned_count' => $assignedCount,
        ]);
    }

    public function autoAssignLeads()
    {
        // ── Fetch unassigned leads ────────────────────────────────
        $this->db->select('id, source');
        $this->db->from(db_prefix().'leads');
        $this->db->where('assigned', 0);
        $unassignedLeads = $this->db->get()->result_array();

        if (empty($unassignedLeads)) {
            echo json_encode([
                'success' => true,
                'message' => 'No unassigned leads found',
                'assigned_count' => 0,
            ]);

            return;
        }

        // ── STEP 1: Get all LOGGED-IN eligible staff ─────────────
        $this->db->select('s.staffid, s.firstname, s.lastname');
        $this->db->from(db_prefix().'staff AS s');
        $this->db->join('tbltaskstimers AS t', 't.staff_id = s.staffid', 'inner');
        $this->db->where('s.active', 1);
        $this->db->where('s.admin !=', 1);
        $this->db->where_in('s.role', [3, 14]);
        $this->db->where('s.auto_assign_leads =', '1');
        $this->db->where('t.status', 'CHECKED_IN');
        $this->db->where('t.check_in_time IS NOT NULL', null, false);
        $this->db->where('t.check_out_time IS NULL', null, false);

        // ✅ Only count as logged in if checked in within last 8 hours
        $this->db->where('t.check_in_time >=', date('Y-m-d H:i:s', strtotime('-8 hours')));

        $this->db->group_by('s.staffid');
        $loggedInStaff = $this->db->get()->result_array();

        if (empty($loggedInStaff)) {
            echo json_encode([
                'success' => false,
                'message' => 'No logged-in staff available',
                'assigned_count' => 0,
            ]);

            return;
        }

        // FIX 1: Cast all staffIds to int so array key lookups are type-safe
        $staffIds = array_map('intval', array_column($loggedInStaff, 'staffid'));

        $staffNameMap = [];
        foreach ($loggedInStaff as $s) {
            $staffNameMap[(int) $s['staffid']] = trim($s['firstname'].' '.$s['lastname']);
        }

        // ── STEP 2: Count existing leads per staff ───────────────
        $this->db->select('assigned, COUNT(id) AS lead_count');
        $this->db->from(db_prefix().'leads');
        $this->db->where_in('assigned', $staffIds);
        $this->db->where('status', 4);
        $this->db->group_by('assigned');
        $leadCounts = $this->db->get()->result_array();

        $leadCountMap = array_fill_keys($staffIds, 0);
        foreach ($leadCounts as $row) {
            $leadCountMap[(int) $row['assigned']] = (int) $row['lead_count'];
        }

        // ── STEP 3: Load campaign rules ──────────────────────────
        $campaignRules = [];  // source_id (int) => [staff_id (int), ...]
        $staffCampaigns = []; // staff_id (int)  => [source_id (int), ...]

        $ruleRows = $this->db
            ->select('source_id, staff_id')
            ->where('is_active', 1)
            ->get('tblcampaign_staff_assignments')
            ->result_array();

        // FIX 2: Cast both keys and values to int — DB returns strings,
        // causing empty($staffCampaigns[$sid]) to always be true when
        // $sid is an int but array key is a string.
        foreach ($ruleRows as $r) {
            $sid = (int) $r['staff_id'];
            $src = (int) $r['source_id'];
            $campaignRules[$src][] = $sid;
            $staffCampaigns[$sid][] = $src;
        }

        // ── STEP 4: Assign each lead ─────────────────────────────
        $MAX_LIMIT = 50;
        $assignedCount = 0;
        $skippedCount = 0;

        foreach ($unassignedLeads as $lead) {
            $source_id = (int) ($lead['source'] ?? 0);

            // ✅ Only assign if lead's source has a campaign rule
            if (!$source_id || empty($campaignRules[$source_id])) {
                // Lead has no campaign rule → skip entirely
                ++$skippedCount;
                continue;
            }

            // ✅ Only assign to staff mapped to this campaign AND logged in
            $ruleStaffIds = $campaignRules[$source_id];
            $eligibleIds = array_values(array_intersect($ruleStaffIds, $staffIds));

            // ✅ Campaign staff not logged in → skip
            if (empty($eligibleIds)) {
                ++$skippedCount;
                continue;
            }

            // ✅ Filter by MAX_LIMIT
            $eligibleStaff = [];
            foreach ($eligibleIds as $sid) {
                if (isset($leadCountMap[$sid]) && $leadCountMap[$sid] < $MAX_LIMIT) {
                    $eligibleStaff[$sid] = $leadCountMap[$sid];
                }
            }

            if (empty($eligibleStaff)) {
                ++$skippedCount;
                continue;
            }

            // ✅ Round Robin — pick staff with minimum load
            $minLeadCount = min($eligibleStaff);
            $minStaff = array_keys($eligibleStaff, $minLeadCount);
            $assignedStaffId = $minStaff[array_rand($minStaff)];

            $this->db->where('id', $lead['id']);
            $this->db->update(db_prefix().'leads', ['assigned' => $assignedStaffId]);

            ++$leadCountMap[$assignedStaffId];

            $this->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => 'Auto-assigned lead (campaign rule)',
                'leadid' => $lead['id'],
                'staffid' => $assignedStaffId,
                'full_name' => $staffNameMap[$assignedStaffId] ?? 'Unknown',
                'custom_activity' => 0,
            ]);

            ++$assignedCount;
        }

        echo json_encode([
            'success' => true,
            'message' => "Auto-assigned {$assignedCount} lead(s) successfully",
            'assigned_count' => $assignedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function autoAssignLeads_30_03_26()
    {
        // ── Fetch unassigned leads ────────────────────────────────
        $this->db->select('id, source');
        $this->db->from(db_prefix().'leads');
        $this->db->where('assigned', 0);
        $unassignedLeads = $this->db->get()->result_array();

        if (empty($unassignedLeads)) {
            echo json_encode([
                'success' => true,
                'message' => 'No unassigned leads found',
                'assigned_count' => 0,
            ]);

            return;
        }

        // ── STEP 1: Get all LOGGED-IN eligible staff ─────────────
        $this->db->select('s.staffid, s.firstname, s.lastname');
        $this->db->from(db_prefix().'staff AS s');
        $this->db->join('tbltaskstimers AS t', 't.staff_id = s.staffid', 'inner');
        $this->db->where('s.active', 1);
        $this->db->where('s.admin !=', 1);
        $this->db->where_in('s.role', [3, 14]);
        $this->db->where('s.auto_assign_leads =', '1');
        $this->db->where('t.status', 'CHECKED_IN');
        $this->db->where('t.check_in_time IS NOT NULL', null, false);
        $this->db->where('t.check_out_time IS NULL', null, false);
        $this->db->group_by('s.staffid');
        $loggedInStaff = $this->db->get()->result_array();

        if (empty($loggedInStaff)) {
            echo json_encode([
                'success' => false,
                'message' => 'No logged-in staff available',
                'assigned_count' => 0,
            ]);

            return;
        }

        $staffIds = array_column($loggedInStaff, 'staffid');

        $staffNameMap = [];
        foreach ($loggedInStaff as $s) {
            $staffNameMap[$s['staffid']] = trim($s['firstname'].' '.$s['lastname']);
        }

        // ── STEP 2: Count existing leads per staff ───────────────
        $this->db->select('assigned, COUNT(id) AS lead_count');
        $this->db->from(db_prefix().'leads');
        $this->db->where_in('assigned', $staffIds);
        $this->db->where('status', 4);
        $this->db->group_by('assigned');
        $leadCounts = $this->db->get()->result_array();

        $leadCountMap = array_fill_keys($staffIds, 0);
        foreach ($leadCounts as $row) {
            $leadCountMap[$row['assigned']] = (int) $row['lead_count'];
        }

        // ── STEP 3: Load campaign rules ──────────────────────────
        // source_id => [staff_id, staff_id, ...]
        $campaignRules = [];

        // staff_id => [source_id, source_id, ...]
        $staffCampaigns = [];

        $ruleRows = $this->db
            ->select('source_id, staff_id')
            ->where('is_active', 1)
            ->get('tblcampaign_staff_assignments')
            ->result_array();

        foreach ($ruleRows as $r) {
            $campaignRules[$r['source_id']][] = (int) $r['staff_id'];
            $staffCampaigns[$r['staff_id']][] = (int) $r['source_id'];
        }

        // ── STEP 4: Assign each lead ─────────────────────────────
        $MAX_LIMIT = 20;
        $assignedCount = 0;
        $skippedCount = 0;

        foreach ($unassignedLeads as $lead) {
            $source_id = (int) ($lead['source'] ?? 0);

            // ── Determine eligible staff for this lead ────────────
            if ($source_id && !empty($campaignRules[$source_id])) {
                $ruleStaffIds = $campaignRules[$source_id];
                $eligibleIds = array_intersect($ruleStaffIds, $staffIds);
            } else {
                $eligibleIds = array_filter(
                    $staffIds,
                    fn ($sid) => empty($staffCampaigns[$sid])
                );
            }

            $eligibleStaff = array_filter(
                array_intersect_key($leadCountMap, array_flip(array_values($eligibleIds))),
                fn ($count) => $count < $MAX_LIMIT
            );

            if (empty($eligibleStaff)) {
                ++$skippedCount;
                continue;
            }

            // ── Round Robin: minimum load वाले को assign ──────────
            $minLeadCount = min($eligibleStaff);
            $minStaff = array_keys($eligibleStaff, $minLeadCount);
            $assignedStaffId = $minStaff[array_rand($minStaff)];

            // ── Update lead ───────────────────────────────────────
            $this->db->where('id', $lead['id']);
            $this->db->update(db_prefix().'leads', [
                'assigned' => $assignedStaffId,
            ]);

            ++$leadCountMap[$assignedStaffId];

            // ── Activity Log ──────────────────────────────────────
            $isRuleApplied = $source_id && !empty($campaignRules[$source_id]);

            $this->db->insert(db_prefix().'lead_activity_log', [
                'date' => date('Y-m-d H:i:s'),
                'description' => $isRuleApplied
                                        ? 'Auto-assigned lead (campaign rule)'
                                        : 'Auto-assigned lead',
                'leadid' => $lead['id'],
                'staffid' => $assignedStaffId,
                'full_name' => $staffNameMap[$assignedStaffId] ?? 'Unknown',
                'custom_activity' => 0,
            ]);

            ++$assignedCount;
        }

        echo json_encode([
            'success' => true,
            'message' => "Auto-assigned {$assignedCount} lead(s) successfully",
            'assigned_count' => $assignedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function start_dialer()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        if (!$staff_id) {
            echo json_encode(['status' => 'error', 'msg' => 'No staff session']);

            return;
        }
        $this->dialer_model->set_running($staff_id);
        echo json_encode(['status' => 'running']);
    }

    public function pause_dialer()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        if (!$staff_id) {
            echo json_encode(['status' => 'error', 'msg' => 'No staff session']);

            return;
        }
        $this->dialer_model->pause($staff_id);
        echo json_encode(['status' => 'paused']);
    }

    public function resume_dialer()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        if (!$staff_id) {
            echo json_encode(['status' => 'error', 'msg' => 'No staff session']);

            return;
        }
        $this->dialer_model->stop($staff_id);
        echo json_encode(['status' => 'resumed']);
    }

    public function stop_dialer()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        if (!$staff_id) {
            echo json_encode(['status' => 'error', 'msg' => 'No staff session']);

            return;
        }
        $this->dialer_model->stop($staff_id);
        echo json_encode(['status' => 'stopped']);
    }

    public function get_dialer_status()
    {
        $staff_id = $this->session->userdata('staff_user_id');
        if (!$staff_id) {
            echo json_encode(['state' => 'idle']);

            return;
        }

        $row = $this->dialer_model->get_status($staff_id);

        if (!$row) {
            echo json_encode(['state' => 'idle']);

            return;
        }

        if ($row->is_running == 1 && $row->is_paused == 0) {
            $state = 'running';
        } elseif ($row->is_paused == 1) {
            $state = 'paused';
        } else {
            $state = 'idle';
        }

        echo json_encode(['state' => $state]);
    }

    public function make_call()
    {
        $staff_id = $this->input->post('staff_id') ?: $this->session->userdata('staff_user_id');

        if (!$staff_id) {
            echo json_encode(['status' => 'danger', 'message' => 'Missing staff id']);

            return;
        }

        // Get next lead (for auto call)
        $lead = $this->leads_model->get_next_lead($staff_id);
        if (!$lead) {
            $this->dialer_model->stop($staff_id);
            echo json_encode(['status' => 'done', 'message' => 'No pending leads']);

            return;
        }

        $phone = trim($lead->phonenumber ?? '');
        if (!$phone) {
            $this->db->insert('tblcall_logs', [
                'lead_id' => $lead->id,
                'staff_id' => $staff_id,
                'status' => 'invalid_number',
                'created_at' => date('Y-m-d H:i:s'),
                'is_success' => 0,
            ]);

            echo json_encode(['status' => 'skipped', 'message' => 'Invalid phone number']);

            return;
        }

        // Fetch staff credentials
        $staff = $this->db->get_where('tblstaff', ['staffid' => $staff_id])->row();

        if (!$staff) {
            echo json_encode([
                'status' => 'danger',
                'message' => 'Invalid staff account',
            ]);

            return;
        }

        $agentNumber = trim($staff->dialer_agent_id ?? '');
        $callerId = trim($staff->dialer_phone ?? '');

        if (!$agentNumber || !$callerId) {
            echo json_encode([
                'status' => 'danger',
                'message' => 'Dialer is not configured for this account.',
            ]);

            return;
        }

        $client = new Client();

        try {
            // API CALL
            $res = $client->post(DIALER_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer '.DIALER_API_TOKEN,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'async' => 1,
                    'agent_number' => $agentNumber,
                    'caller_id' => $callerId,
                    'destination_number' => $phone,
                    'reference_lead_id' => $lead->id,
                    'callback_url' => base_url('api/Webhook/fetchCallDetails'),
                ],
            ]);

            $resp = json_decode($res->getBody(), true);
            $ref = $resp['uuid'] ?? $resp['ref_id'] ?? null;

            // Track active call
            $this->db->insert('tblactive_calls', [
                'staff_id' => $staff_id,
                'lead_id' => $lead->id,
                'call_ref' => $ref,
                'started_at' => date('Y-m-d H:i:s'),
            ]);

            echo json_encode([
                'status' => 'success',
                'lead_id' => $lead->id,
                'number' => $phone,
                'ref_id' => $ref,
                'call_state' => 'dialing',
            ]);
        } catch (Exception $e) {
            $apiMsg = 'Call failed.';

            if ($e instanceof GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $body = json_decode($e->getResponse()->getBody()->getContents(), true);
                if (is_array($body)) {
                    $apiMsg = reset($body)[0] ?? $apiMsg;
                }
            }

            // Log failure
            $this->db->insert('tblcall_logs', [
                'lead_id' => $lead->id,
                'staff_id' => $staff_id,
                'status' => 'call_failed',
                'created_at' => date('Y-m-d H:i:s'),
                'is_success' => 0,
            ]);

            echo json_encode([
                'status' => 'danger',
                'message' => $apiMsg,
            ]);
        }
    }
}
