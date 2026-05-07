<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp_model extends CI_Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * Save a WhatsApp message
     * $data: [
     *   'channel_id', 'agent_id', 'lead_id', 'phone', 'country_code',
     *   'message', 'message_type', 'direction', 'file_id', 'status'
     * ]
     */
    public function save_message($data){
        $insert = [
            'channel_id'   => $data['channel_id'],
            'agent_id'     => isset($data['agent_id']) ? $data['agent_id'] : null,
            'lead_id'      => isset($data['lead_id']) ? $data['lead_id'] : null,
            'phone'        => $data['phone'],
            'message'      => isset($data['message']) ? $data['message'] : null,
            'message_type' => isset($data['message_type']) ? $data['message_type'] : 'text',
            'direction'    => $data['direction'], // sent/received
            'file_id'      => isset($data['file_id']) ? $data['file_id'] : null,
            'status'       => isset($data['status']) ? $data['status'] : 'queued',
            'is_read'      => isset($data['is_read']) ? $data['is_read'] : 0,
            'created_at'   => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s')
        ];
        $this->db->insert('tblwhatsapp_chats',$insert);
        return $this->db->insert_id();
    }

    /**
     * Get chat history for a specific lead or phone
     */
     public function get_history($phone,$channel_id){
        
        return $this->db->where('lead_id',$phone)
                        ->where('agent_id',$channel_id)
                        ->order_by('created_at','ASC')
                        ->get('tblwhatsapp_chats')
                        ->result();
    }

    /**
     * Save uploaded media/file
     */
    public function save_file($data){
        $insert = [
            'stored_name' => $data['stored_name'],
            'mime_type'   => $data['mime_type'],
            'file_size'   => $data['file_size'],
            'created_at'  => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s')
        ];
        $this->db->insert('tblwhatsapp_files', $insert);
        return $this->db->insert_id();
    }

    /**
     * Mark a message as seen/read
     */
    public function mark_seen($chat_id){
        $this->db->where('id',$chat_id)
                 ->update('tblwhatsapp_chats',[
                     'status'=>'seen',
                     'is_read'=>1,
                     'seen_at'=>date('Y-m-d H:i:s')
                 ]);
    }

    /**
     * Get last message per lead or phone for agent inbox
     */
    public function get_last_messages($agent_id){
        return $this->db->query("
            SELECT t.*
            FROM tblwhatsapp_chats t
            INNER JOIN (
                SELECT lead_id, MAX(id) as max_id
                FROM tblwhatsapp_chats
                WHERE agent_id=?
                GROUP BY lead_id
            ) x ON x.max_id=t.id
            ORDER BY t.created_at DESC
        ", [$agent_id])->result();
    }

    /**
     * Get file details
     */
    public function get_file($file_id){
        return $this->db->where('id',$file_id)
                        ->get('tblwhatsapp_files')
                        ->row();
    }

    /**
     * Get unread count per lead for agent dashboard
     */
    public function get_unread_count($agent_id, $lead_id){
        return $this->db->where('agent_id',$agent_id)
                        ->where('lead_id',$lead_id)
                        ->where('is_read',0)
                        ->count_all_results('tblwhatsapp_chats');
    }

    /**
     * Optional: get all leads assigned to agent
     */
    

    public function get_agent_leads($agent_id)
{
    $sql = "
        SELECT 
            l.id,
            l.name,
            l.phonenumber,

            /* 🔴 Unread count */
            COALESCE((
                SELECT COUNT(*)
                FROM tblwhatsapp_chats uc
                WHERE uc.lead_id = l.id
                  AND uc.agent_id = l.assigned
                  AND uc.direction = 'received'
                  AND uc.is_read = 0
            ), 0) AS unread_count,

            /* 🟢 Last message */
            (
                SELECT c.message
                FROM tblwhatsapp_chats c
                WHERE c.lead_id = l.id
                  AND c.agent_id = l.assigned
                ORDER BY c.created_at DESC
                LIMIT 1
            ) AS last_message,

            /* 🟢 Last message time */
            (
                SELECT c.created_at
                FROM tblwhatsapp_chats c
                WHERE c.lead_id = l.id
                  AND c.agent_id = l.assigned
                ORDER BY c.created_at DESC
                LIMIT 1
            ) AS last_message_time,

            /* 🏷️ Labels (IMPORTANT: keep ORDER SAME) */
            GROUP_CONCAT(lb.id ORDER BY lb.id)    AS label_ids,
            GROUP_CONCAT(lb.name ORDER BY lb.id)  AS labels,
            GROUP_CONCAT(lb.color ORDER BY lb.id) AS label_colors

        FROM tblleads l
        LEFT JOIN tbllead_labels ll ON ll.lead_id = l.id
        LEFT JOIN tbllabels lb ON lb.id = ll.label_id

        WHERE l.assigned = ?
        GROUP BY l.id
        ORDER BY last_message_time DESC
    ";

    $result = $this->db->query($sql, [$agent_id])->result();

    /* Prepare arrays for view */
    foreach ($result as &$row) {
        $row->label_ids    = $row->label_ids ? explode(',', $row->label_ids) : [];
        $row->labels       = $row->labels ? explode(',', $row->labels) : [];
        $row->label_colors = $row->label_colors ? explode(',', $row->label_colors) : [];
    }

    return $result;
}




public function get_agent_unread_count($agent_id)
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM tblwhatsapp_chats uc
        INNER JOIN tblleads l ON l.id = uc.lead_id
        WHERE uc.agent_id = ?
          AND l.assigned = ?
          AND uc.direction = 'received'
          AND uc.is_read = 0
    ";

    return (int) $this->db->query($sql, [$agent_id, $agent_id])
                          ->row()
                          ->total;
}



public function get_all_labels_with_status($lead_id)
{
    return $this->db->query("
        SELECT l.id, l.name, l.color,
               IF(ll.lead_id IS NULL, 0, 1) AS assigned
        FROM tbllabels l
        LEFT JOIN tbllead_labels ll 
            ON ll.label_id = l.id AND ll.lead_id = ?
        ORDER BY l.name ASC
    ", [$lead_id])->result();
}






}