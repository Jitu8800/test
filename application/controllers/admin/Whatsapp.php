<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp extends CI_Controller {

    public function __construct(){
        parent::__construct();
        $this->load->model('Whatsapp_model');
        $this->load->helper('url');
        $this->load->library('upload');
        $this->channel_id = $this->session->userdata('staff_user_id'); // current agent
    }

    /**
     * List all leads for current staff
     */// application/controllers/admin/Whatsapp.php

public function index()
{
    $agent_id = $this->session->userdata('staff_user_id');

    if (!$agent_id) {
        show_error('Unauthorized', 401);
    }

    $data['leads'] = $this->Whatsapp_model->get_agent_leads($agent_id);

    $this->load->view('admin/whatsapp/chat_messages', $data);
}


    /**
     * Open chat page for a lead
     */
    public function chat($lead_id){
        $lead = $this->db->where('id', $lead_id)->get('tblleads')->row();
        if(!$lead) show_404();

        $data['lead'] = $lead;
        $data['phone'] = $lead->phonenumber;
        $data['channel_id'] = $this->channel_id;

        // load chat history filtered by agent and lead
        $data['chats'] = $this->Whatsapp_model->get_history($lead_id, null, $this->channel_id);

        $this->load->view('admin/whatsapp/chat_messages', $data);
    }

    /**
     * Load chat history (AJAX)
     */
    
    public function load_history()
{ 
     $phone = preg_replace('/\D/', '', $this->input->post('lead_id'));
    $channel_id = (int)$this->session->userdata('staff_user_id');

    $chats = $this->Whatsapp_model->get_history($phone, $channel_id);

    $html = $this->load->view(
        'admin/whatsapp/chat_messages_partial',
        ['chats' => $chats],
        TRUE
    );

    echo json_encode([
        'html' => $html,
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}

    /**
     * Send file/message
     */
    public function send_message(){
        $lead_id = $this->input->post('lead_id', TRUE);
        $phone   = preg_replace('/\D/','', $this->input->post('phone', TRUE));
        $agent_id = $this->channel_id;

        if(!$phone){
            echo json_encode(['status'=>false,'msg'=>'Phone missing','csrfHash'=>$this->security->get_csrf_hash()]);
            return;
        }

        // Check if file exists
        $file_id = null;
        $file_url = null;
        $file_type = null;

        if(!empty($_FILES['file']['name'])){
            $config['upload_path']   = './uploads/whatsapp/';
            $config['allowed_types'] = 'jpg|jpeg|png|gif|mp4|mp3|pdf|doc|docx';
            $config['encrypt_name']  = true;
            $this->upload->initialize($config);

            if(!$this->upload->do_upload('file')){
                echo json_encode([
                    'status'=>false,
                    'error'=>$this->upload->display_errors('', ''),
                    'csrfHash'=>$this->security->get_csrf_hash()
                ]);
                return;
            }

            $f = $this->upload->data();
            $file_id = $this->Whatsapp_model->save_file([
                'stored_name'=>$f['file_name'],
                'mime_type'=>$f['file_type'],
                'file_size'=>$f['file_size'],
                'created_at'=>date('Y-m-d H:i:s')
            ]);

            $file_url = base_url('uploads/whatsapp/'.$f['file_name']);
            $file_type = $f['file_type'];
        }

        // Save message to DB
        $this->Whatsapp_model->save_message([
            'channel_id'   => $this->channel_id,
            'agent_id'     => $agent_id,
            'lead_id'      => $lead_id,
            'phone'        => $phone,
            'message'      => $this->input->post('msg', TRUE),
            'message_type' => $file_id ? $file_type : 'text',
            'direction'    => 'sent',
            'file_id'      => $file_id,
            'status'       => 'sent'
        ]);

        echo json_encode([
            'status'=>true,
            'file_id'=>$file_id,
            'file_url'=>$file_url,
            'file_type'=>$file_type,
            'csrfHash'=>$this->security->get_csrf_hash()
        ]);
    }

    /**
     * Get inbox contacts (last message per lead)
     */
    public function get_contacts(){
        $agent_id = $this->channel_id;
        $contacts = $this->Whatsapp_model->get_last_messages($agent_id);

        foreach($contacts as $c){
            $unread = $this->Whatsapp_model->get_unread_count($agent_id, $c->lead_id);
            $badge = $unread>0 ? "<span class='unread'>{$unread}</span>":'';
            echo "<div class='lead' data-phone='{$c->phone}' data-lead='{$c->lead_id}'>{$c->phone} {$badge}</div>";
        }
    }

    /**
     * Reset unread messages for a lead
     */
    public function reset_unread(){
        $lead_id = $this->input->post('lead_id', TRUE);
        if(!$lead_id) return;

        $this->db->where(['lead_id'=>$lead_id,'agent_id'=>$this->channel_id])
                 ->update('tblwhatsapp_chats', ['status'=>'read','is_read'=>1,'seen_at'=>date('Y-m-d H:i:s')]);
    }

    /**
     * Mark single message seen
     */
    public function mark_seen(){
        $chat_id = $this->input->post('chat_id');
        if($chat_id) $this->Whatsapp_model->mark_seen($chat_id);
    }



    // ================================
// 1️⃣ DROPDOWN LABELS (ALL + STATUS)
// ================================
public function lead_labels()
{
    $lead_id = (int) $this->input->post('lead_id');

    $labels = $this->Whatsapp_model->get_all_labels_with_status($lead_id);

    echo json_encode([
        'labels' => $labels,
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}




// ================================
// 2️⃣ LEAD LABELS ONLY
// ================================
public function get_lead_labels()
{
    $lead_id = (int) $this->input->post('lead_id');

    if (!$lead_id) {
        echo json_encode([
            'labels' => [],
            'csrfHash' => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $labels = $this->db
        ->select('lb.id, lb.name, lb.color')
        ->from('tbllead_labels ll')
        ->join('tbllabels lb', 'lb.id = ll.label_id')
        ->where('ll.lead_id', $lead_id)
        ->get()
        ->result();

    echo json_encode([
        'labels'   => $labels,
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}



// ================================
// 3️⃣ TOGGLE LABEL
// ================================
public function toggle_label()
{
    $lead_id  = (int) $this->input->post('lead_id');
    $label_id = (int) $this->input->post('label_id');

    if (!$lead_id || !$label_id) {
        echo json_encode([
            'status' => false,
            'csrfHash' => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $exists = $this->db->where([
        'lead_id'  => $lead_id,
        'label_id' => $label_id
    ])->get('tbllead_labels')->row();

    if ($exists) {
        $this->db->delete('tbllead_labels', [
            'lead_id'  => $lead_id,
            'label_id' => $label_id
        ]);
    } else {
        $this->db->insert('tbllead_labels', [
            'lead_id'  => $lead_id,
            'label_id' => $label_id
        ]);
    }

    // 🔥 FETCH ASSIGNED LABELS ONLY
    $labels = $this->db
        ->select('l.id, l.name, l.color')
        ->from('tbllabels l')
        ->join('tbllead_labels ll', 'll.label_id = l.id')
        ->where('ll.lead_id', $lead_id)
        ->get()
        ->result();

    echo json_encode([
        'status' => true,
        'labels' => $labels,
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}





// ================================
// 4️⃣ ADD LABEL
// ================================
public function add_label()
{
    $name  = trim($this->input->post('name'));
    $color = $this->input->post('color');

    if (!$name) {
        echo json_encode([
            'status' => false,
            'message' => 'Label name required',
            'csrfHash' => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $this->db->insert('tbllabels', [
        'name'  => $name,
        'color' => $color ?: '#4caf50'
    ]);

    echo json_encode([
        'status' => true,
        'message' => 'Label added successfully',
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}



// ================================
// 5️⃣ DELETE LABEL (GLOBAL)
// ================================
public function delete_label()
{
    $label_id = (int) $this->input->post('label_id');

    if (!$label_id) {
        echo json_encode([
            'status' => false,
            'message' => 'Invalid label id',
            'csrfHash' => $this->security->get_csrf_hash()
        ]);
        return;
    }

    // remove from leads
    $this->db->delete('tbllead_labels', ['label_id' => $label_id]);

    // remove label
    $this->db->delete('tbllabels', ['id' => $label_id]);

    echo json_encode([
        'status' => true,
        'message' => 'Label deleted successfully',
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}



// ================================
// 6️⃣ DELETE LABEL FROM LEAD
// ================================
public function deleteLeadLabel()
{
    $lead_id  = (int) $this->input->post('lead_id');
    $label_id = (int) $this->input->post('label_id');

    if (!$lead_id || !$label_id) {
        echo json_encode([
            'status' => false,
            'message' => 'Invalid data',
            'csrfHash' => $this->security->get_csrf_hash()
        ]);
        return;
    }

    $this->db->delete('tbllead_labels', [
        'lead_id'  => $lead_id,
        'label_id' => $label_id
    ]);

    echo json_encode([
        'status' => true,
        'message' => 'Label removed from lead',
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}


public function get_labels_with_count()
{
    $labels = $this->db->select("
            l.id,
            l.name,
            l.color,
            COUNT(ll.lead_id) AS total
        ")
        ->from('tbllabels l')
        ->join('tbllead_labels ll', 'll.label_id = l.id', 'inner')
        ->group_by('l.id')
        ->having('total >', 0)
        ->order_by('l.name', 'ASC')
        ->get()
        ->result_array();

    echo json_encode([
        'status'   => true,
        'labels'   => $labels,
        'csrfHash' => $this->security->get_csrf_hash()
    ]);
}







}