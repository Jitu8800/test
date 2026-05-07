<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . 'libraries/import/App_import.php');

class Import_leads extends App_import
{
    private $uniqueValidationFields = [];

    protected $notImportableFields = [];

    protected $requiredFields = ['name'];

    protected $sources;

    protected $statuses;

    public function __construct()
    {
        $this->notImportableFields = hooks()->apply_filters('not_importable_leads_fields', ['id', 'assigned', 'dateadded', 'last_status_change', 'addedfrom', 'leadorder', 'date_converted', 'lost', 'junk', 'is_imported_from_email_integration', 'email_integration_uid', 'is_public', 'dateassigned', 'client_id', 'lastcontact', 'last_lead_status', 'from_form_id', 'default_language', 'hash']);

        $uniqueValidationFields = json_decode(get_option('lead_unique_validation'));

        if (count($uniqueValidationFields) > 0) {
            $this->uniqueValidationFields = $uniqueValidationFields;
            $message                      = '';

            foreach ($uniqueValidationFields as $key => $field) {
                if ($key === 0) {
                    $message .= 'Based on your leads <b class="text-danger">unique validation</b> configured <a href="' . admin_url('settings?group=leads#unique_validation_wrapper') . '" target="_blank">options</a>, the lead <b>won\'t</b> be imported if:<br />';
                }

                $message .= '<br />&nbsp;&nbsp;&nbsp; - Lead <b>' . $field . '</b> already exists OR';
            }

            if ($message != '') {
                $message = substr($message, 0, -3);
            }

            $message .= '<br /><br />If you still want to import all leads, uncheck all unique validation field';

            $this->addImportGuidelinesInfo($message);
        }

        parent::__construct();

        $this->sources  = $this->ci->db->get('tblleads_sources')->result_array();
        $this->statuses = $this->ci->db->get('tblleads_status')->result_array();
    }
    
     public function perform()
{
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    $this->initialize();

    // Original header map (DB field values)
    $headerMap = [
        'name'                  => 'name',
        'country'               => 'country',
        'city'                  => 'city',
        'state'                 => 'state',
        'address'               => 'address',
        'is active'             => 'is_active',
        'status'                => 'status',
        'sub status'            => 'sub_status',
        'customer status'       => 'customer_status',
        'customer sub status'   => 'customer_sub_status',
        'source'                => 'source',
        'campaign'              => 'source_campaign',
        'email'                 => 'email',
        'website'               => 'website',
        'phonenumber'           => 'phonenumber',
        'payin amount'          => 'payin_amount',
        'payout amount'         => 'payout_amount',
        'job'                   => 'job',
        'utm source'            => 'utm_source',
        'utm medium'            => 'utm_medium',
        'utm campagin'          => 'utm_campagin',
    ];

    // Normalize header map keys (lowercase + underscores)
    $normalizedHeaderMap = [];
    foreach ($headerMap as $k => $v) {
        $key = strtolower(trim(str_replace(' ', '_', $k)));
        $normalizedHeaderMap[$key] = $v;
    }

    // Normalize CSV headers
    $csvHeaders = array_map(function ($h) {
        return strtolower(trim(str_replace(' ', '_', $h)));
    }, $this->getImportableDatabaseFields());

    // Map CSV headers to DB fields
    $databaseFields = [];
    foreach ($csvHeaders as $h) {
        if (isset($normalizedHeaderMap[$h])) {
            $databaseFields[] = $normalizedHeaderMap[$h];
        }
    }

    $totalDatabaseFields = count($databaseFields);

    $responsibleArray = $this->ci->input->post('responsible');
    $assignUserCount  = !empty($responsibleArray) ? count($responsibleArray) : 0;

    foreach ($this->getRows() as $rowNumber => $row) {
        $insert = [];

        for ($i = 0; $i < $totalDatabaseFields; $i++) {
            $csvValue = array_key_exists($i, $row) ? $row[$i] : null;
            $value = $this->checkNullValueAddedByUser($csvValue);
            $dbField = $databaseFields[$i];

            // Transform values
            if ($dbField === 'name' && empty($value)) {
                $value = '/';
            } elseif ($dbField === 'country') {
                $value = $this->countryValue($value);
            } elseif ($dbField === 'source') {
                $value = $this->sourceValue($value);
            } elseif ($dbField === 'status') {
                $value = $this->statusValue($value);
            } elseif ($dbField === 'is_active') {
                $value = in_array(strtolower($value), ['active', 'inactive', 'break']) ? strtolower($value) : 'active';
            } elseif (in_array($dbField, ['payin_amount', 'payout_amount'])) {
                $value = (is_numeric($value)) ? (float)$value : 0;
            } elseif (in_array($dbField, ['status', 'sub_status', 'customer_status', 'customer_sub_status', 'source'])) {
                $value = (is_numeric($value)) ? (int)$value : null;
            }

            $insert[$dbField] = $value;
        }

        $insert = $this->trimInsertValues($insert);

        // Debug: check insert data
        // echo "<pre>Row {$rowNumber} Insert Data: ";
        // print_r($insert);
        // echo "</pre>";

        if (empty($insert)) {
            continue;
        }

        if ($this->isDuplicateLead($insert)) {
            continue;
        }

        $this->incrementImported();
        $id = null;

        if (!$this->isSimulation()) {
            $insert['dateadded']   = date('Y-m-d H:i:s');
            $insert['addedfrom']   = get_staff_user_id();
            $insert['record_type'] = $insert['record_type'] ?? 'lead';
            $insert['is_active']   = $insert['is_active'] ?? 'active';
            $insert['status']      = $insert['status'] ?? 1;
            $insert['sub_status']  = $insert['sub_status'] ?? 4;
            $insert['brokerage_per_cr'] = $insert['brokerage_per_cr'] ?? 0;

            if ($assignUserCount > 0) {
                $insert['assigned'] = $responsibleArray[$rowNumber % $assignUserCount];
            }

            if (!$this->ci->db->insert('tblleads', $insert)) {
                echo '<pre>DB ERROR: ' . print_r($this->ci->db->error(), true) . '</pre>';
                echo '<pre>INSERT DATA: ' . print_r($insert, true) . '</pre>';
                exit;
            }

            $id = $this->ci->db->insert_id();
        } else {
            $this->simulationData[$rowNumber] = $this->formatValuesForSimulation($insert);
        }

        $this->handleCustomFieldsInsert($id, $row, $i, $rowNumber, 'tblleads');

        if ($this->isSimulation() && $rowNumber >= $this->maxSimulationRows) {
            break;
        }
    }
}




    private function getHiddenFields()
    {
        return ['title', 'company', 'description', 'zip', 'tags'];
    }


    protected function findSource($id)
    {
        foreach ($this->sources as $source) {
            if ($source['name'] == $id || $source['id'] == $id) {
                return $source;
            }
        }
    }

    protected function findStatus($id)
    {
        foreach ($this->statuses as $status) {
            if ($status['name'] == $id || $status['id'] == $id) {
                return $status;
            }
        }
    }

    protected function statusValue($value)
    {
        return $this->findStatus($value)['id'] ?? $this->ci->input->post('status');
    }

    protected function sourceValue($value)
    {
        return $this->findSource($value)['id'] ?? $this->ci->input->post('source');
    }

    protected function tags_formatSampleData()
    {
        return 'tag1,tag2';
    }

    public function formatFieldNameForHeading($field)
    {
        // if (strtolower($field) == 'title') {
        //     return 'Position';
        // }
        $hide = ['title', 'company', 'description', 'tags'];

        if (in_array(strtolower($field), $hide)) {
            return; // skip
        }

        return parent::formatFieldNameForHeading($field);
    }

    protected function email_formatSampleData()
    {
        return uniqid() . '@example.com';
    }

    protected function failureRedirectURL()
    {
        return admin_url('leads/import');
    }

    private function isDuplicateLead($data)
    {
        foreach ($this->uniqueValidationFields as $field) {
            if ((isset($data[$field]) && $data[$field] != '') && total_rows('leads', [$field => $data[$field]]) > 0) {
                return true;
            }
        }

        return false;
    }

    private function formatValuesForSimulation($values)
    {
        foreach ($values as $column => $val) {
            if ($column == 'country' && !empty($val) && is_numeric($val)) {
                $country = $this->getCountry(null, $val);
                if ($country) {
                    $values[$column] = $country->short_name;
                }
            } elseif ($column == 'source') {
                $values[$column] = $this->findSource($val)['name'] ?? 'N/A';
            } elseif ($column == 'status') {
                $values[$column] = $this->findStatus($val)['name'] ?? 'N/A';
            }
        }

        return $values;
    }

    private function getCountry($search = null, $id = null)
    {
        if ($search) {
            $this->ci->db->where('iso2', $search)
            ->or_where('short_name', $search)
            ->or_where('long_name', $search);
        } else {
            $this->ci->db->where('country_id', $id);
        }

        return  $this->ci->db->get('countries')->row();
    }

    private function countryValue($value)
    {
        if ($value != '') {
            if (!is_numeric($value)) {
                $country = $this->getCountry($value);
                $value   = $country ? $country->country_id : 0;
            }
        } else {
            $value = 0;
        }

        return $value;
    }
}
