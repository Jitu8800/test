<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<!-- CSS -->
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
    rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />

<style>
body {
    font-family: "Inter", sans-serif;
    background: #f8f8f8;
    margin: 0;
    padding: 20px;
}

.dashboard {
    max-width: 1200px;
    margin: auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
}

h2 {
    margin: 0;
    padding: 10px 0;
    font-size: 20px;
    color: #727272;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-create {
    background: transparent;
    color: #727272;
    font-weight: 600;
    padding: 8px 15px;
    border: 2px solid #6d6d6d;
    border-radius: 20px;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 15px;
    border-radius: 20px;
    overflow: hidden;
}

table thead {
    border: solid;
    border-radius: 20px;
    outline: 2px solid #fafbfd;
}

th,
td {
    padding: 10px;
    text-align: center;
    font-size: 12px;
    font-weight: 500;
}

th {
    background: #fafbfd;
    color: #000;
    font-family: "Inter", sans-serif;
}

tr:nth-child(even) td {
    background: #f2f2f2;
}

select,
input[type="number"] {
    padding: 5px;
    border-radius: 5px;
    border: 1px solid #ccc;
    outline: none;
    font-size: 13px;
    text-transform: capitalize;
}

input[type="number"] {
    border: 2px solid black;
    text-align: center;
    width: 50%;
}

select {
    border-radius: 20px;
    padding: 6px;
    width: 100%;
    background-color: #737373;
    color: black;
    font-size: 12px;
    font-weight: 600;
}

select option {
    text-align: center;
    color: black;
    font-size: 12px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 28px;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.4s;
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
    font-size: 10px;
    font-weight: bold;
    color: white;
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
    z-index: 2;
}

.slider .switch-text-off {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 1;
    transition: opacity 0.3s;
    color: #666;
}

.slider .switch-text-on {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    transition: opacity 0.3s;
    color: white;
}

input:checked+.slider {
    background-color: #28a745;
}

input:checked+.slider:before {
    transform: translateX(32px);
}

input:checked+.slider .switch-text-on {
    opacity: 1;
}

input:checked+.slider .switch-text-off {
    opacity: 0;
}

.section-title {
    background: #000;
    color: #fff;
    padding: 14px 12px;
    border-radius: 10px;
    margin-top: 30px;
}
</style>


<div id="wrapper">
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Leads Assign Dashboard</title>
    </head>
    <div class="dashboard">
        <div class="header">
            <h2>Leads Assign Dashboard</h2>
            <button class="btn-create">Create +</button>
        </div>

        <!-- Table -->

        <body>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>CSV/GSHEET Connection Name</th>
                        <th>Campaign Name</th>
                        <th>Total Leads</th>
                        <th>Assigned Leads</th>
                        <th>Remaining Leads</th>
                        <th>Select Branch</th>
                        <th>Select Agents</th>
                        <th>No of Leads</th>
                        <th>Assign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
if (count($campaigns) > 0) {
    foreach ($campaigns as $campaign) { ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($campaign['source_name']); ?></td>
                        <td>
                            <?php $campaignList = explode(',', $campaign['campaigns']); ?>
                            <select name="campaign" disabled>
                                <option value="ALL">ALL</option>
                                <?php foreach ($campaignList as $c) { ?>
                                <option value="<?php echo htmlspecialchars(trim($c)); ?>"><?php echo htmlspecialchars(trim($c)); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><?php echo $campaign['total_leads']; ?></td>
                        <td><?php echo $campaign['assigned_leads']; ?></td>
                        <td><?php echo $campaign['remaining_leads']; ?></td>

                        <!-- Branch Selection (only ALL selected by default) -->
                        <td>
                            <select name="branch_ids[]" class="form-control branchSelectMultiple branch_id"
                                multiple="multiple" style="width:150px;">
                                <option value="ALL" selected>ALL</option>
                                <?php foreach ($branches as $b) { ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php } ?>
                            </select>
                        </td>

                        <!-- Users Selection (only ALL selected by default) -->
                        <td>
                            <select name="responsible[]" class="form-control responsible" multiple="multiple"
                                style="width:150px;">
                                <option value="ALL" selected>ALL</option>
                                <?php foreach ($members as $member) { ?>
                                <option value="<?php echo $member['staffid']; ?>">
                                    <?php echo $member['firstname'].' '.$member['lastname']; ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>"
                            value="<?php echo $this->security->get_csrf_hash(); ?>" class="csrf_token">

                        <!-- Number of Leads -->
                        <td>
                            <input type="number" name="leads_count" value="50" min="1" style="width:60px;" />
                        </td>

                        <!-- Assign Button -->
                        <td>
                            <form class="assign_leads_form" action="<?php echo admin_url('leads/assign_leads'); ?>"
                                method="POST">
                            <input type="hidden" name="source_id"
                                value="<?php echo htmlspecialchars($campaign['source']); ?>">
                            <input type="hidden" name="campaigns"
                                value="<?php echo htmlspecialchars($campaign['campaigns']); ?>">                            
                                <button type="submit" class="btn btn-primary btn-assign">Assign</button>
                            </form>
                        </td>
                    </tr>
                    <?php }
    } ?>
                </tbody>

            </table>
            <!-- Section 2 -->
            <h3 class="section-title">Re Assign Leads</h3>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>CSV/GSHEET <br />Connection Name</th>
                        <th>Campaign Name</th>
                        <th>Total Leads</th>
                        <th>Assigned Leads</th>
                        <th>Remaining Leads</th>
                        <th>Select Branch</th>
                        <th>Select Agents</th>
                        <th>No of Leads</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select>
                                <option>ALL</option>
                            </select>
                        </td>
                        <td>
                            <select>
                                <option>ALL</option>
                            </select>
                        </td>
                        <td>JK Surat VS</td>
                        <td>500</td>
                        <td>200</td>
                        <td>300</td>
                        <td>
                            <select class="customselect">
                                <option>ALL</option>
                            </select>
                        </td>
                        <td>
                            <select class="customselect">
                                <option>ALL</option>
                            </select>
                        </td>
                        <td><input type="number" value="100" /></td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" checked />
                                <span class="slider">
                                    <span class="switch-text-on">ON</span>
                                    <span class="switch-text-off">OFF</span>
                                </span>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </body>
    </div>
</div>

<?php init_tail(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.branchSelectMultiple, .responsible').select2({
        placeholder: "Select",
        allowClear: true
    });

    // Update users by branch selection
    $(document).on('change', '.branch_id', function() {
        var branchIds = $(this).val();
        var $row = $(this).closest('tr');
        var $responsible = $row.find('.responsible');

        if (!branchIds || branchIds.length === 0) {
            $responsible.empty().append('<option disabled>No users</option>').trigger('change');
            return;
        }

        $.ajax({
            url: "<?php echo admin_url('leads/get_users_by_branch'); ?>",
            type: "POST",
            data: {
                branch_id: branchIds
            },
            dataType: "json",
            success: function(response) {
                $responsible.empty();
                if (response.length > 0) {
                    $.each(response, function(i, user) {
                        $responsible.append('<option value="' + user.staffid +
                            '">' + user.firstname + ' ' + user.lastname +
                            '</option>');
                    });
                } else {
                    $responsible.append('<option disabled>No users found</option>');
                }
                $responsible.trigger('change');
            }
        });
    });

   
    $(document).on('submit', '.assign_leads_form', function(e) {
    e.preventDefault();
    var $form = $(this);
    var $tr = $form.closest('tr');

    var formData = new FormData();

    formData.append('source_id', $form.find('input[name="source_id"]').val());
    formData.append('campaigns', $form.find('input[name="campaigns"]').val());
    formData.append('leads_count', $tr.find('input[name="leads_count"]').val());
    formData.append($('.csrf_token').attr('name'), $('.csrf_token').val());

    // multiple selects
    var branchIds = $tr.find('.branch_id').val() || [];
    branchIds.forEach(function(id) {
        formData.append('branch_ids[]', id);
    });

    var responsible = $tr.find('.responsible').val() || [];
    responsible.forEach(function(id) {
        formData.append('responsible[]', id);
    });

    $.ajax({
        url: $form.attr('action'),
        type: 'POST',
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        success: function(resp) {
            try {
                var response = JSON.parse(resp);
                if (response.success) {
                    alert_float('success', response.message || 'Leads assigned successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert_float('danger', response.message || 'Something went wrong.');
                }
            } catch (e) {
                console.error('Invalid JSON response:', resp);
                alert_float('danger', 'Unexpected server response.');
            }
        },
        error: function(xhr) {
            console.error('AJAX error:', xhr.responseText);
            try {
                var error = JSON.parse(xhr.responseText);
                alert_float('danger', error.message || 'Error occurred.');
            } catch (e) {
                alert_float('danger', 'Something went wrong!');
            }
        }
    });
});


});
</script>