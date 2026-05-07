<?php

defined('BASEPATH') or exit('No direct script access allowed');




$this->load->database();

$where  = "";
$user = $this->session;
$userRoles = get_staff($user->staff_user_id);


if($userRoles->role == '' && $userRoles->admin == 1){
    $where = "tblstaff.admin = 0 AND tblstaff.role IS NOT NULL";
}

if($userRoles->role == 2 && $userRoles->admin ==0 ){
    $where = "tblstaff.admin = 0 AND tblstaff.role = 3 AND tblstaff.staffid !=". $user->staff_user_id;
}

if($userRoles->role == 3 && $userRoles->admin ==0 ){
    $where = "tblstaff.admin = 0 AND tblstaff.staffid =". $userRoles->staffid;
}
$where = "tblstaff.active = 1 AND tblleads.status IS NOT NULL";

$query = $this->db->query("
    SELECT 
        tblstaff.staffid, 
        tblstaff.firstname, 
        tblstaff.lastname, 
        tblstaff.email, 
        COUNT(tblleads.id) AS totalleads, 
        SUM(CASE WHEN tblleads.status = 1 THEN 1 ELSE 0 END) AS converted_leads,
        SUM(CASE WHEN tblleads.status = 2 THEN 1 ELSE 0 END) AS pipeline_leads,
        SUM(CASE WHEN tblleads.status = 3 THEN 1 ELSE 0 END) AS rejected_leads
    FROM 
        tblstaff 
    LEFT JOIN 
        tblleads 
    ON 
        tblleads.assigned = tblstaff.staffid 
    WHERE 
        $where 
    GROUP BY 
        tblstaff.staffid;
");
$leads = $query->result();


?>
<div class="table-responsive">
<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">Name</th>
      <th scope="col">Email</th>
      <th scope="col">Total Leads</th>
      <th scope="col">Converted Leads</th>
      <th scope="col">Pipeline Leads</th>
      <th scope="col">Rejected Leads</th>
      <th scope="col">No. of Cards</th>
    </tr>
  </thead>
  <tbody>
    <?php if( count($leads) >0 ) { 
        $i = 1;
        foreach($leads as $row){
        ?>
    <tr>
      <th scope="row"><?= $i++; ?></th>
      <td><?= ucfirst($row->firstname) . ' ' . ucfirst($row->lastname); ?></td>
        <td><?= $row->email; ?></td>
        <td><?= $row->totalleads; ?></td>
        <td><?= $row->converted_leads; ?></td>
        <td><?= $row->pipeline_leads; ?></td>
        <td><?= $row->rejected_leads; ?></td>
    </tr>
    <?php } } ?>
  </tbody>
</table>
</div>