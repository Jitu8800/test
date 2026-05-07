<?php
defined('BASEPATH') OR exit('No direct script access allowed');


require_once FCPATH . 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExecutiveReports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
        $this->load->model('leads_model');
        $this->load->model('staff_model');
        $this->load->library('ReportCardGenerator');
    }

    public function generate_weekly_excel()
    {
        // CLI only
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.');
        }

        // Safety: allow execution only on Saturday
        if (date('N') != 6) { // 6 = Saturday
            log_message('error', 'Weekly executive report can only run on Saturday');
            exit;
        }

        // --- Weekly date range: Saturday to Friday ---
        $weekStart = strtotime('this saturday');
        $from = date('Y-m-d 00:00:00', $weekStart);
        $to   = date('Y-m-d 23:59:59', strtotime('+6 days', $weekStart));

        log_message('info', "Weekly report period: FROM $from TO $to");

        // --- Fetch data ---
        $attendance = $this->indexByStaff($this->dashboard_model->get_weekly_attendance($from, $to));
        $calls      = $this->indexByStaff($this->dashboard_model->get_weekly_calls($from, $to));
        $leads      = $this->indexByStaff($this->dashboard_model->get_weekly_leads($from, $to));

        $staffList = $this->db
            ->select('staffid, firstname, lastname')
            ->from('tblstaff')
            ->where('active', 1)
            ->get()
            ->result_array();

        // --- Create Excel ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Weekly Executive Report');

        // Header
        $sheet->fromArray([
            'Executive', 'Work Hours', 'Call Hours', 'Break Hours',
            'Idle Hours', 'Total Calls', 'Successful Calls',
            'Total Leads', 'Converted Leads', 'Score', 'Grade'
        ], NULL, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $row = 2;

        foreach ($staffList as $staff) {
            $sid = $staff['staffid'];

            $score = $this->reportcardgenerator->calculate_score(
                $attendance[$sid] ?? [],
                $calls[$sid] ?? [],
                $leads[$sid] ?? []
            );

            $sheet->fromArray([
                $staff['firstname'].' '.$staff['lastname'],
                round(($attendance[$sid]['work_seconds'] ?? 0) / 3600, 2),
                round(($attendance[$sid]['call_seconds'] ?? 0) / 3600, 2),
                round(($attendance[$sid]['break_seconds'] ?? 0) / 3600, 2),
                round(($attendance[$sid]['idle_seconds'] ?? 0) / 3600, 2),
                $calls[$sid]['total_calls'] ?? 0,
                $calls[$sid]['successful_calls'] ?? 0,
                $leads[$sid]['total_leads'] ?? 0,
                $leads[$sid]['converted_leads'] ?? 0,
                $score,
                $this->reportcardgenerator->grade($score)
            ], NULL, "A{$row}");

            $row++;
        }

        foreach (range('A','K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // --- Save file ---
        $dir = FCPATH . 'reports/executive/weekly/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = sprintf(
            'Executive_Report_%s_to_%s.xlsx',
            date('Y-m-d', strtotime($from)),
            date('Y-m-d', strtotime($to))
        );

        $fullPath = $dir . $filename;
        $relativePath = 'reports/executive/weekly/' . $filename;

        // Avoid duplicate generation
        if (!file_exists($fullPath)) {
            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);

            // Insert DB record only after file is created
            $this->db->insert('tblexecutive_weekly_reports', [
                'week_start' => date('Y-m-d', strtotime($from)),
                'week_end'   => date('Y-m-d', strtotime($to)),
                'file_name'  => $filename,
                'file_path'  => $relativePath
            ]);

            log_message('info', 'Weekly executive report generated: ' . $filename);
            echo "Excel generated: $fullPath\n";
        } else {
            log_message('info', 'Report already exists: ' . $filename);
            echo "Report already exists: $fullPath\n";
        }
}


    // Helper: convert query result into staff-indexed array
    private function indexByStaff($rows)
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['staff_id']] = $r;
        }
        return $out;
    }
}