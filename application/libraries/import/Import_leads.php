<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'libraries/import/App_import.php';

class Import_leads extends App_import
{
    protected $batchSize = 1000;

    protected $uniqueValidationFields = ['email', 'phonenumber'];

    protected $sources = [];
    protected $statuses = [];

    /* FORCE SAME DB STRUCTURE FOR EVERY ROW */
    protected $dbFields = [
        'name',
        'email',
        'phonenumber',
        'city',
        'state',
        'country',
        'status',
        'source',
        'source_campaign',
        'assigned',
        'dateadded',
        'addedfrom',
        'is_active',
        'record_type',
    ];

    public function __construct()
    {
        parent::__construct();

        // Force MySQL errors as exceptions
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->sources = $this->ci->db->get('tblleads_sources')->result_array();
        $this->statuses = $this->ci->db->get('tblleads_status')->result_array();
    }

    /* =====================================================
       MAIN IMPORT
    ===================================================== */
    public function perform()
    {
        $this->initialize();

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $filePath = $this->tmpFileStoragePath;

        if (!file_exists($filePath)) {
            show_error('CSV file not found.');
        }

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            show_error('Unable to open CSV file.');
        }

        // Header
        $headers = fgetcsv($handle);

        if (!$headers) {
            show_error('Invalid CSV header.');
        }

        $map = $this->mapHeaders($headers);

        if (empty($map)) {
            show_error('No valid columns found in CSV.');
        }

        $batch = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            ++$rowNum;

            $data = $this->prepareRow($row, $map);

            if (!$data) {
                continue;
            }

            if ($this->isDuplicate($data)) {
                continue;
            }

            $batch[] = $data;

            if (count($batch) >= $this->batchSize) {
                $this->safeInsertBatch($batch, $rowNum);

                $batch = [];

                gc_collect_cycles();
            }
        }

        if (!empty($batch)) {
            $this->safeInsertBatch($batch, $rowNum);
        }

        fclose($handle);
    }

    /* =====================================================
       HEADER MAP
    ===================================================== */
    private function mapHeaders(array $headers)
    {
        $mapping = [
            'name' => 'name',
            'email' => 'email',
            'phonenumber' => 'phonenumber',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'status' => 'status',
            'source' => 'source',
            'source_campaign' => 'source_campaign',
            'assigned' => 'assigned',
            'dateadded' => 'dateadded',
        ];

        $map = [];

        foreach ($headers as $i => $header) {
            // Remove BOM
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

            $key = strtolower(trim($header));
            $key = str_replace(' ', '_', $key);
            $key = preg_replace('/[^a-z0-9_]/', '', $key);

            if (isset($mapping[$key])) {
                $map[$i] = $mapping[$key];
            }
        }

        return $map;
    }

    /* =====================================================
       PREPARE ROW (NORMALIZED)
    ===================================================== */
    private function prepareRow(array $row, array $map)
    {
        // Force fixed column structure
        $insert = [
            'name' => null,
            'email' => null,
            'phonenumber' => null,
            'city' => null,
            'state' => null,
            'country' => 0,
            'status' => null,
            'source' => null,
            'source_campaign' => null,
            'assigned' => 0,
            'dateadded' => null,
        ];

        foreach ($map as $index => $field) {
            if (!array_key_exists($field, $insert)) {
                continue;
            }

            $value = isset($row[$index]) ? trim($row[$index]) : null;

            if ($value === '') {
                $value = null;
            }

            // Fix unicode
            if (is_string($value)) {
                $value = $this->cleanUnicode($value);
            }

            switch ($field) {
                case 'country':
                    $value = $this->countryValue($value);
                    break;

                case 'status':
                    $value = $this->statusValue($value);
                    break;

                case 'source':
                    $value = $this->sourceValue($value);
                    break;

                case 'assigned':
                    $value = (int) $value;
                    break;

                case 'dateadded':
                    $value = $value
                        ? date('Y-m-d H:i:s', strtotime($value))
                        : null;
                    break;
            }

            $insert[$field] = $value;
        }

        /* ===== Required Fields ===== */

        if (empty($insert['name'])) {
            $insert['name'] = '/';
        }

        /* ===== System Defaults ===== */

        $insert['addedfrom'] = get_staff_user_id();
        $insert['dateadded'] = $insert['dateadded'] ?? date('Y-m-d H:i:s');
        $insert['status'] = $insert['status'] ?? 1;
        $insert['source'] = $insert['source'] ?? 1;
        $insert['is_active'] = 'active';
        $insert['record_type'] = 'lead';

        // Remove any numeric keys (CRITICAL)
        foreach ($insert as $k => $v) {
            if (is_int($k)) {
                unset($insert[$k]);
            }
        }

        return $insert;
    }

    /* =====================================================
       SAFE BATCH INSERT
    ===================================================== */
    private function safeInsertBatch(array $batch, $rowNum)
    {
        if ($this->isSimulation()) {
            return;
        }

        try {
            // RESET QUERY BUILDER (CRITICAL)
            $this->ci->db->reset_query();

            $this->ci->db->insert_batch('tblleads', $batch);

            $this->incrementImported(count($batch));
        } catch (Exception $e) {
            echo "<h2>❌ Batch Failed Near Row: {$rowNum}</h2>";

            foreach ($batch as $row) {
                try {
                    // Fresh builder
                    $this->ci->db->reset_query();

                    $this->ci->db->insert('tblleads', $row);
                } catch (Exception $ex) {
                    echo '<h3>🚨 BAD ROW FOUND</h3><pre>';
                    print_r($row);
                    echo '</pre>';

                    echo '<strong>MySQL Error:</strong><br>';
                    echo $ex->getMessage();

                    exit;
                }
            }

            exit;
        }
    }

    /* =====================================================
       DUPLICATE CHECK
    ===================================================== */
    private function isDuplicate(array $data)
    {
        foreach ($this->uniqueValidationFields as $field) {
            if (empty($data[$field])) {
                continue;
            }

            // NEW QUERY INSTANCE (IMPORTANT)
            $db = $this->ci->load->database('default', true);

            $db->where($field, $data[$field]);

            if ($db->count_all_results('tblleads') > 0) {
                return true;
            }
        }

        return false;
    }

    /* =====================================================
       HELPERS
    ===================================================== */

    private function statusValue($value)
    {
        foreach ($this->statuses as $s) {
            if ($s['id'] == $value || $s['name'] == $value) {
                return $s['id'];
            }
        }

        return null;
    }

    private function sourceValue($value)
    {
        foreach ($this->sources as $s) {
            if ($s['id'] == $value || $s['name'] == $value) {
                return $s['id'];
            }
        }

        return null;
    }

    private function countryValue($value)
    {
        if (!$value) {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $this->ci->db->where('short_name', $value);

        $row = $this->ci->db->get('countries')->row();

        return $row ? $row->country_id : 0;
    }

    /* =====================================================
       UNICODE FIX
    ===================================================== */
    private function cleanUnicode($text)
    {
        return iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }

    /* =====================================================
       REQUIRED
    ===================================================== */
    protected function failureRedirectURL()
    {
        return admin_url('leads/import');
    }
}
