<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Daily_admin_summary_model extends App_Model
{
    public function send()
    {
        // Run only at 10 AM
        if (date('H') !== '10') {
            return;
        }

        // Prevent duplicate sending
        if (get_option('last_daily_admin_summary') === date('Y-m-d')) {
            return;
        }

        $admin = $this->get_admin();
        if (!$admin) {
            return;
        }

        $merge_fields = $this->get_summary_data();

        $this->load->model('emails_model');
        $this->emails_model->send_email_template(
            'daily-admin-summary',
            'staff',
            $admin->staffid,
            $merge_fields
        );

        update_option('last_daily_admin_summary', date('Y-m-d'));
    }

    // ---------------------------------------------------
    // 👇 PUT YOUR FUNCTION HERE (INSIDE CLASS)
    // ---------------------------------------------------
    private function get_summary_data()
    {
        $today = date('Y-m-d');

        // Total Calls
        $total_calls = $this->db
            ->where('DATE(lastcontact)', $today)
            ->where_in('status', [3, 4])
            ->from(db_prefix() . 'leads')
            ->count_all_results();

        // Payments
        $payments = sum_from_table(
            db_prefix() . 'leads',
            'total_payment',
            ['DATE(payment_date)' => $today]
        );

        // Closed Leads
        $closed_leads = total_rows(
            db_prefix() . 'leads',
            [
                'status' => 8,
                'DATE(last_status_change)' => $today
            ]
        );

        // New Leads
        $new_leads = total_rows(
            db_prefix() . 'leads',
            [
                'status' => 1,
                'DATE(dateadded)' => $today
            ]
        );

        // Refund Amount
        $refund_amount = sum_from_table(
            db_prefix() . 'approval_requests',
            'amount',
            [
                'entity_type' => 'refund',
                'status' => 'approved',
                'DATE(created_at)' => $today
            ]
        );

        return [
            '{report_date}'    => date('d M Y'),
            '{total_calls}'    => $total_calls,
            '{total_payments}' => app_format_money((float) $payments, get_base_currency()),
            '{closed_leads}'   => $closed_leads,
            '{new_leads}'      => $new_leads,
            '{refund_amount}'  => app_format_money((float) $refund_amount, get_base_currency()),
        ];
    }

    // ---------------------------------------------------
    // Helper
    // ---------------------------------------------------
    private function get_admin()
    {
        return $this->db
            ->where('admin', 1)
            ->order_by('staffid', 'ASC')
            ->get(db_prefix() . 'staff')
            ->row();
    }
}
