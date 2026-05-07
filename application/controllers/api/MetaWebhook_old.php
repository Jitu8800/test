<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MetaWebhook extends CI_Controller
{
    private $log_file;
    private $verify_token;
    private $system_token;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();

        $this->verify_token = META_VERIFY_TOKEN;
        $this->system_token = META_SYSTEM_USER_TOKEN;

        $this->log_file = APPPATH.'logs/meta_webhook.log';

        // Create logs dir if missing
        if (!is_dir(APPPATH.'logs')) {
            mkdir(APPPATH.'logs', 0775, true);
        }

        // Create log file if missing — prevents permission crash on first write
        if (!file_exists($this->log_file)) {
            @touch($this->log_file);
            @chmod($this->log_file, 0664);
        }
    }

    /* ----------------------------------------------------
     * WEBHOOK ENTRY POINT
     * FIX: Always returns 200 immediately, then processes.
     * This prevents Meta from suspending the webhook on errors.
     * --------------------------------------------------*/
    public function receive()
    {
        // Webhook verification (GET)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (
                isset($_GET['hub_mode'], $_GET['hub_verify_token'])
                && $_GET['hub_mode'] === 'subscribe'
                && $_GET['hub_verify_token'] === $this->verify_token
            ) {
                echo $_GET['hub_challenge'];
                exit;
            }
            http_response_code(403);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        $this->log('RAW_WEBHOOK', $payload);

        $leadgen_id = $payload['entry'][0]['changes'][0]['value']['leadgen_id'] ?? null;

        // ✅ CRITICAL: Always respond 200 to Meta FIRST, before any processing.
        // If we return 500, Meta retries then suspends the webhook entirely.
        $this->respond(['status' => 'received', 'lead_id' => $leadgen_id]);

        // Flush output so Meta gets the 200 response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Now process safely — Meta already got its 200
        if (!$leadgen_id) {
            $this->log('INVALID_PAYLOAD', $payload);

            return;
        }

        // Forward to UAT (fire and forget — don't wait for response)

        $lead = $this->fetchLead($leadgen_id);

        if (isset($lead['error'])) {
            $this->log('LEAD_FETCH_FAILED', $lead);

            return;
        }

        $this->saveLead($lead);
    }

    /* ----------------------------------------------------
     * FETCH LEAD FROM META API
     * --------------------------------------------------*/
    private function fetchLead($leadgen_id)
    {
        $url = "https://graph.facebook.com/v21.0/{$leadgen_id}"
            .'?fields=field_data,ad_id,adset_id,campaign_id,form_id,created_time'
            .'&access_token='.urlencode($this->system_token);

        return $this->curlGetJson($url);
    }

    /* ----------------------------------------------------
     * SAVE LEAD
     * --------------------------------------------------*/
    private function saveLead($lead)
    {
        // Duplicate protection
        $exists = $this->db->get_where('tblleads', [
            'meta_lead_id' => $lead['id'],
        ])->row();

        if ($exists) {
            $this->log('DUPLICATE_LEAD', $lead['id']);

            return false;
        }

        // Normalize field_data into flat key => value map
        $fields = [];
        foreach ($lead['field_data'] ?? [] as $f) {
            $fields[strtolower(trim($f['name']))] = $f['values'][0] ?? '';
        }

        // Fetch campaign, ad, and form names in parallel (faster than 3 sequential calls)
        $names = $this->fetchNamesBatch([
            'campaign' => $lead['campaign_id'] ?? null,
            'ad' => $lead['ad_id'] ?? null,
            'form' => $lead['form_id'] ?? null,
        ]);

        $campaign_id = $lead['campaign_id'] ?? '';
        $campaign_name = $names['campaign'] ?? '';
        $ad_name = $names['ad'] ?? '';
        $form_name = $names['form'] ?? '';

        // FIX: upsertSource is wrapped in try/catch.
        // If tblleads_sources.campaign_id column doesn't exist yet, falls back to source=3.
        $source_id = 3; // safe default (same as old working code)
        try {
            if (!empty($campaign_id) && !empty($campaign_name)) {
                $source_id = $this->upsertSource($campaign_id, $campaign_name);
            }
        } catch (Exception $e) {
            $this->log('SOURCE_UPSERT_FAILED', $e->getMessage());
            // Continue with default source=3 — don't crash the lead save
        }

        $insert = [
            'meta_lead_id' => $lead['id'],

            // REQUIRED FIELDS
            'name' => $fields['full_name'] ?? 'Unknown',
            'email' => $fields['email'] ?? '',
            'phonenumber' => $fields['phone_number'] ?? '',
            'company' => $fields['business_/_brand_name_'] ?? '',
            'city' => $fields['city'] ?? '',

            // CUSTOM QUESTIONS
            'title' => $fields['what_type_of_real_estate_business_do_you_run?'] ?? '',
            'q1' => $fields['_do_you_work_in_real_estate?'] ?? '',

            // META INFO
            'campaign_id' => $campaign_id,
            'campaign_name' => $campaign_name,
            'ad_id' => $lead['ad_id'] ?? '',
            'ad_name' => $ad_name,
            'form_id' => $lead['form_id'] ?? '',
            'form_name' => $form_name,

            // SOURCE
            'source' => $source_id,

            // RAW
            'raw_payload' => json_encode($lead),

            // SYSTEM
            'status' => 4,
            'addedfrom' => 1,
            'dateadded' => date('Y-m-d H:i:s'),
            'description' => 'Imported from Meta Lead Ads',
            'platform_text' => 'facebook',
        ];

        // Optional columns — only add if they exist in your tblleads schema.
        // Comment out the lines below if these columns don't exist in your DB.
        // $insert['source_campaign'] = $campaign_name;
        // $insert['assigned']        = 0;

        $this->log('INSERT_PAYLOAD', $insert);

        $ok = $this->db->insert('tblleads', $insert);

        if (!$ok) {
            $this->log('DB_ERROR', $this->db->error());

            return false;
        }

        $this->log('LEAD_SAVED', ['meta_lead_id' => $lead['id'], 'name' => $insert['name']]);

        return true;
    }

    /* ----------------------------------------------------
     * UPSERT SOURCE
     * Check by campaign_id — insert if new, update name if changed.
     * Returns the auto-increment id from tblleads_sources.
     * NOTE: tblleads_sources must have a campaign_id VARCHAR column.
     * If it doesn't, run: ALTER TABLE tblleads_sources ADD campaign_id VARCHAR(64) DEFAULT NULL;
     * --------------------------------------------------*/
    private function upsertSource($campaign_id, $campaign_name)
    {
        if (empty($campaign_id) || empty($campaign_name)) {
            return 3; // default fallback
        }

        $existing = $this->db
            ->where('campaign_id', $campaign_id)
            ->get('tblleads_sources')
            ->row();

        if ($existing) {
            if ($existing->name !== $campaign_name) {
                $this->db
                    ->where('id', $existing->id)
                    ->update('tblleads_sources', ['name' => $campaign_name]);

                $this->log('SOURCE_NAME_UPDATED', [
                    'id' => $existing->id,
                    'old_name' => $existing->name,
                    'new_name' => $campaign_name,
                ]);
            } else {
                $this->log('SOURCE_EXISTS', ['id' => $existing->id, 'name' => $campaign_name]);
            }

            return $existing->id;
        }

        // New campaign — insert
        $this->db->insert('tblleads_sources', [
            'name' => $campaign_name,
            'campaign_id' => $campaign_id,
        ]);

        $new_id = $this->db->insert_id();

        $this->log('SOURCE_CREATED', [
            'id' => $new_id,
            'name' => $campaign_name,
            'campaign_id' => $campaign_id,
        ]);

        return $new_id;
    }

    /* ----------------------------------------------------
     * FETCH NAMES IN PARALLEL (curl_multi)
     * Much faster than 3 sequential fetchName() calls.
     * --------------------------------------------------*/
    private function fetchNamesBatch($ids)
    {
        $results = [];
        $mh = curl_multi_init();
        $handles = [];

        foreach ($ids as $key => $id) {
            if (!$id) {
                $results[$key] = '';
                continue;
            }

            $url = "https://graph.facebook.com/v21.0/{$id}"
                 .'?fields=name&access_token='.urlencode($this->system_token);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $handles[$key] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($handles as $key => $ch) {
            $res = json_decode(curl_multi_getcontent($ch), true);
            $results[$key] = $res['name'] ?? '';
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $results;
    }

    /* ----------------------------------------------------
     * RE-SYNC MISSED LEADS
     * Call once to pull leads from the last N hours that
     * Meta never delivered (e.g. after webhook was suspended).
     * URL: /api/MetaWebhook/resync_missed
     * --------------------------------------------------*/
    public function resync_missed()
    {
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        // Get all known form IDs from DB
        $forms = $this->db
            ->select('DISTINCT(form_id)')
            ->where('form_id !=', '')
            ->where('form_id IS NOT NULL', null, false)
            ->get('tblleads')
            ->result_array();

        if (empty($forms)) {
            echo json_encode(['error' => 'No form_ids found in tblleads']);

            return;
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        // Pull leads from last 4 hours to be safe
        $since = strtotime('-4 hours');

        foreach ($forms as $row) {
            $form_id = $row['form_id'];

            $url = "https://graph.facebook.com/v21.0/{$form_id}/leads"
                 .'?fields=id,created_time,field_data,ad_id,adset_id,campaign_id,form_id'
                 .'&filtering=[{"field":"time_created","operator":"GREATER_THAN","value":'.$since.'}]'
                 .'&access_token='.urlencode($this->system_token)
                 .'&limit=100';

            while ($url) {
                $response = $this->curlGetJson($url);

                if (isset($response['error'])) {
                    $this->log('RESYNC_ERROR', ['form_id' => $form_id, 'error' => $response['error']]);
                    $errors[] = $form_id;
                    break;
                }

                foreach ($response['data'] ?? [] as $lead) {
                    $exists = $this->db->get_where('tblleads', [
                        'meta_lead_id' => $lead['id'],
                    ])->row();

                    if ($exists) {
                        ++$skipped;
                        continue;
                    }

                    $saved = $this->saveLead($lead);
                    $saved ? $created++ : $errors[] = $lead['id'];
                }

                $url = $response['paging']['next'] ?? null;
            }
        }

        $result = [
            'success' => true,
            'forms_checked' => count($forms),
            'created' => $created,
            'skipped' => $skipped,
            'failed' => count($errors),
            'failed_ids' => $errors,
        ];

        $this->log('RESYNC_DONE', $result);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }

    /* ----------------------------------------------------
     * FIX CAMPAIGN NAMES (one-time migration)
     * Updates campaign_name + source on all existing leads.
     * URL: /api/MetaWebhook/fix_campaign_names
     * --------------------------------------------------*/
    public function fix_campaign_names()
    {
        if (!is_admin()) {
            echo json_encode(['error' => 'No permission']);

            return;
        }

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $this->log('FIX_START', date('Y-m-d H:i:s'));

        $leads = $this->db
            ->select('id, campaign_id, campaign_name')
            ->where('campaign_id !=', '')
            ->where('campaign_id IS NOT NULL', null, false)
            ->get('tblleads')
            ->result_array();

        if (empty($leads)) {
            echo json_encode(['success' => true, 'message' => 'No leads found']);

            return;
        }

        $unique_ids = array_values(array_unique(array_filter(
            array_column($leads, 'campaign_id')
        )));

        $this->log('UNIQUE_IDS', $unique_ids);

        // Fetch all campaign names from Meta in batches of 10
        $campaign_map = [];
        foreach (array_chunk($unique_ids, 10) as $batch) {
            $ids_keyed = array_combine($batch, $batch);
            $names = $this->fetchNamesBatch($ids_keyed);
            foreach ($names as $cid => $cname) {
                $campaign_map[$cid] = $cname;
            }
            sleep(1); // rate-limit safety
        }

        $this->log('CAMPAIGN_MAP', $campaign_map);

        // Upsert sources for all campaigns
        $source_id_map = [];
        foreach ($campaign_map as $cid => $cname) {
            if (empty($cname)) {
                continue;
            }
            try {
                $source_id_map[$cid] = $this->upsertSource($cid, $cname);
            } catch (Exception $e) {
                $this->log('SOURCE_UPSERT_ERROR', ['cid' => $cid, 'error' => $e->getMessage()]);
            }
        }

        $updated = 0;
        $skipped = 0;

        foreach (array_chunk($leads, 500) as $batch) {
            foreach ($batch as $lead) {
                $cid = $lead['campaign_id'];
                $cname = $campaign_map[$cid] ?? null;

                if (empty($cname)) {
                    ++$skipped;
                    continue;
                }

                $update = ['campaign_name' => $cname];

                if (!empty($source_id_map[$cid])) {
                    $update['source'] = $source_id_map[$cid];
                }

                $this->db->where('id', $lead['id'])->update('tblleads', $update);
                ++$updated;
            }
        }

        $result = [
            'success' => true,
            'total_leads' => count($leads),
            'unique_campaigns' => count($unique_ids),
            'updated' => $updated,
            'skipped' => $skipped,
            'campaign_map' => $campaign_map,
        ];

        $this->log('FIX_DONE', $result);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }

    /* ----------------------------------------------------
     * TEST CAMPAIGN FETCH
     * URL: /api/MetaWebhook/test_campaign_fetch/CAMPAIGN_ID
     * --------------------------------------------------*/
    public function test_campaign_fetch($campaign_id = null)
    {
        if (!is_admin()) {
            echo 'No permission';

            return;
        }

        $campaign_id = $campaign_id ?: '120240939145410070';

        $url = "https://graph.facebook.com/v21.0/{$campaign_id}"
             .'?fields=name&access_token='.urlencode($this->system_token);

        $result = $this->curlGetJson($url);
        echo '<pre>'.print_r($result, true).'</pre>';
    }

    /* ----------------------------------------------------
     * CURL GET JSON
     * --------------------------------------------------*/
    private function curlGetJson($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp = curl_exec($ch);

        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);

            return ['error' => $err];
        }

        curl_close($ch);

        return json_decode($resp, true);
    }

    /* ----------------------------------------------------
     * LOGGING — auto-rotates at 5MB
     * --------------------------------------------------*/
    private function log($title, $data)
    {
        if (file_exists($this->log_file) && filesize($this->log_file) > 5 * 1024 * 1024) {
            rename($this->log_file, $this->log_file.'.'.date('Ymd').'.bak');
        }

        $entry = "== {$title} | ".date('Y-m-d H:i:s')." ==\n";
        $entry .= print_r($data, true)."\n\n";
        @file_put_contents($this->log_file, $entry, FILE_APPEND);
    }

    /* ----------------------------------------------------
     * RESPONSE
     * --------------------------------------------------*/
    private function respond($data, $status = 200)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
