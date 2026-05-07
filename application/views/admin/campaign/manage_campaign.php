<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.ca-wrap {
    padding: 20px;
}

.ca-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.ca-toolbar h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
}

.ca-search {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 13px;
    width: 240px;
    outline: none;
}

.ca-search:focus {
    border-color: #6741d9;
}

.ca-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
}

.ca-table thead tr {
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
    color: #fff;
}

.ca-table thead th {
    padding: 13px 16px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
    white-space: nowrap;
}

.ca-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}

.ca-table tbody tr:hover {
    background: #f8fbff;
}

.ca-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    font-size: 13px;
}

.badge-stat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-total {
    background: #e8f4fd;
    color: #6741d9;
}

.badge-today {
    background: #e8f8f0;
    color: #16a34a;
}

.badge-staff {
    background: #fef3e2;
    color: #d97706;
}

.staff-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 480px;
}

.staff-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px 4px 6px;
    border-radius: 20px;
    border: 1.5px solid #dde3ec;
    background: #f7f9fc;
    cursor: pointer;
    transition: all .15s;
    user-select: none;
    font-size: 12px;
    color: #374151;
}

.staff-chip:hover {
    border-color: #6741d9;
    background: #e8f4fd;
    color: #6741d9;
}

.staff-chip.selected {
    border-color: #6741d9;
    background:linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
    color: #fff;
}

.staff-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    object-fit: cover;
}

.staff-avatar-placeholder {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.staff-chip.selected .staff-avatar-placeholder {
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
}

.save-row-btn {
    padding: 5px 14px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
    color: #fff;
    cursor: pointer;
    transition: background .15s;
    white-space: nowrap;
}

.save-row-btn:hover {
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
}

.save-row-btn.saved {
    background: #16a34a;
}

.campaign-name {
    font-weight: 600;
    color: #1a1a2e;
    font-size: 13px;
}

.campaign-id {
    font-size: 10px;
    color: #9ca3af;
    margin-top: 2px;
}

.ca-bulk-bar {
    display: none;
    background: #1e293b;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    margin-bottom: 16px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.ca-bulk-bar.show {
    display: flex;
}

.no-rule-badge {
    font-size: 11px;
    color: #9ca3af;
    font-style: italic;
}

.switch {
    position: relative;
    display: inline-block;
    width: 55px;
    height: 20px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 20px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.switch input:checked+.slider {
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
}

.switch input:checked+.slider:before {
    transform: translateX(36px);
}

#btnSaveAll{
    background: linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="ca-wrap">

            <!-- Toolbar -->
            <div class="ca-toolbar">
                <h3><i class="fa fa-bullhorn" style="color:#6741d9 ;margin-right:8px;"></i> Campaign Assignments</h3>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="text" id="caSearch" class="ca-search" placeholder="Search campaign...">
                    <button class="btn btn-success btn-sm" id="btnSaveAll">
                        <i class="fa fa-save"></i> Save All
                    </button>
                </div>
            </div>

            <!-- No staff warning -->
            <?php if (!empty($no_staff_message)) { ?>
            <div class="alert alert-warning" style="margin-bottom:16px;">
                <i class="fa fa-exclamation-triangle"></i>
                <?php echo $no_staff_message; ?>
                Staff must be <strong>checked in</strong> and have <strong>auto-assign enabled</strong> to appear here.
            </div>
            <?php } ?>

            <!-- Bulk save bar -->
            <div class="ca-bulk-bar" id="bulkBar">
                <span id="bulkCount">0 campaigns modified</span>
                <button class="btn btn-sm" style="background:linear-gradient(135deg, #3b5bdb 0%, #6741d9 100%);color:#fff;border:none;" id="btnBulkSave">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>

            <!-- Table -->
            <div style="overflow-x:auto;">
                <table class="ca-table" id="caTable">
                    <thead>
                        <tr>
                            <th width="22"><input type="checkbox" id="selectAll" style="cursor:pointer;"></th>
                            <th>Campaign</th>
                            <th>Stats</th>
                            <th>Assign Staff</th>
                            <th width="90">Action</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($campaigns as $c) { ?>
                        <tr data-source-id="<?php echo $c['id']; ?>">

                            <td><input type="checkbox" class="row-check" data-source-id="<?php echo $c['id']; ?>"></td>

                            <!-- Campaign name + ID -->
                            <td>
                                <div class="campaign-name"><?php echo htmlspecialchars($c['name']); ?></div>
                                <?php if (!empty($c['campaign_id'])) { ?>
                                <div class="campaign-id">ID: <?php echo $c['campaign_id']; ?></div>
                                <?php } ?>
                            </td>

                            <!-- Stats -->
                            <td>
                                <div style="display:flex;flex-direction:column;gap:4px;">
                                    <span class="badge-stat badge-total">
                                        <i class="fa fa-users" style="font-size:10px;"></i>
                                        <?php echo $c['total_leads']; ?> total leads
                                    </span>
                                    <span class="badge-stat badge-today">
                                        <i class="fa fa-calendar" style="font-size:10px;"></i>
                                        <?php echo $c['leads_today']; ?> today
                                    </span>
                                    <span class="badge-stat badge-staff" id="badge-staff-<?php echo $c['id']; ?>">
                                        <i class="fa fa-user" style="font-size:10px;"></i>
                                        <?php echo $c['assigned_count']; ?> staff assigned
                                    </span>
                                </div>
                            </td>

                            <!-- Staff chips -->
                            <td>
                                <div class="staff-grid" data-source-id="<?php echo $c['id']; ?>">

                                    <?php if (!empty($staff)) { ?>
                                    <?php foreach ($staff as $s) {
                                        $selected = in_array($s['staffid'], $c['assigned_staff']) ? 'selected' : '';
                                        $initials = strtoupper(substr($s['firstname'], 0, 1).substr($s['lastname'], 0, 1));
                                        ?>
                                    <div class="staff-chip <?php echo $selected; ?>"
                                        data-staff-id="<?php echo $s['staffid']; ?>"
                                        data-source-id="<?php echo $c['id']; ?>" onclick="toggleChip(this)">

                                        <?php if (!empty($s['profile_image'])) { ?>
                                        <img src="<?php echo base_url('uploads/staff_profile_images/'.$s['staffid'].'/small_'.$s['profile_image']); ?>"
                                            class="staff-avatar" alt="">
                                        <?php } else { ?>
                                        <span class="staff-avatar-placeholder"><?php echo $initials; ?></span>
                                        <?php } ?>

                                        <?php echo htmlspecialchars($s['firstname'].' '.$s['lastname']); ?>
                                    </div>
                                    <?php } ?>
                                    <?php } else { ?>
                                    <span class="no-rule-badge">No checked-in staff available</span>
                                    <?php } ?>

                                </div>
                            </td>

                            <!-- Save button -->
                            <td>
                                <div style="display:flex; flex-direction:column; gap:6px;">

                                    <!-- Auto Assign Toggle (independent) -->
                                    <label class="switch">
                                        <input type="checkbox"
                                            onchange="toggleAutoAssign(this, <?php echo $c['id']; ?>)"
                                            <?php echo !empty($c['is_on']) ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>

                                    <!-- KEEP THIS SAME -->
                                    <button class="save-row-btn" data-source-id="<?php echo $c['id']; ?>"
                                        onclick="saveRow(this, <?php echo $c['id']; ?>)">
                                        Save
                                    </button>

                                </div>
                            </td>

                        </tr>
                        <?php } ?>

                        <?php if (empty($campaigns)) { ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;color:#9ca3af;">
                                No campaigns found. Leads with campaign data will appear here automatically.
                            </td>
                        </tr>
                        <?php } ?>

                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
var admin_url_base = '<?php echo admin_url(); ?>';
var modifiedRows = {};

function toggleChip(el) {
    el.classList.toggle('selected');
    var source_id = el.closest('.staff-grid').dataset.sourceId;
    modifiedRows[source_id] = true;
    updateBulkBar();
}

function saveRow(btn, source_id) {
    var chips = document.querySelectorAll('.staff-grid[data-source-id="' + source_id + '"] .staff-chip.selected');
    var staff_ids = Array.from(chips).map(c => c.dataset.staffId);

    btn.textContent = 'Saving...';
    btn.disabled = true;

    $.post(admin_url_base + 'campaign/save', {
        source_id: source_id,
        staff_ids: staff_ids
    }, function(res) {
        if (res.success) {
            btn.textContent = 'Saved';
            btn.classList.add('saved');
            delete modifiedRows[source_id];
            updateBulkBar();

            // Update staff count badge
            var badge = document.getElementById('badge-staff-' + source_id);
            if (badge) {
                badge.innerHTML = '<i class="fa fa-user" style="font-size:10px;"></i> ' + staff_ids.length +
                    ' staff assigned';
            }

                alert_float('success', res.message);

            setTimeout(function() {
                btn.textContent = 'Save';
                btn.classList.remove('saved');
                btn.disabled = false;
            }, 2000);
        } else {
            btn.textContent = 'Error';
            btn.disabled = false;
            alert_float('danger', 'Failed to save');
        }
    }, 'json');
}

document.getElementById('btnSaveAll').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    var assignments = {};
    document.querySelectorAll('.staff-grid').forEach(function(grid) {
        var source_id = grid.dataset.sourceId;
        var selected = grid.querySelectorAll('.staff-chip.selected');
        assignments[source_id] = Array.from(selected).map(c => c.dataset.staffId);
    });

    $.post(admin_url_base + 'campaign/bulk_save', {
        assignments: assignments
    }, function(res) {
        if (res.success) {
            btn.innerHTML = '<i class="fa fa-check"></i> All Saved';
            modifiedRows = {};
            updateBulkBar();
                alert_float('success', res.message);
            setTimeout(function() {
                btn.innerHTML = '<i class="fa fa-save"></i> Save All';
                btn.disabled = false;
            }, 2500);
        } else {
            btn.innerHTML = '<i class="fa fa-save"></i> Save All';
            btn.disabled = false;
            alert_float('danger', 'Some assignments failed');
        }
    }, 'json');
});

document.getElementById('btnBulkSave').addEventListener('click', function() {
    document.getElementById('btnSaveAll').click();
});

function updateBulkBar() {
    var count = Object.keys(modifiedRows).length;
    var bar = document.getElementById('bulkBar');
    if (count > 0) {
        bar.classList.add('show');
        document.getElementById('bulkCount').textContent = count + ' campaign' + (count > 1 ? 's' : '') +
            ' modified — unsaved';
    } else {
        bar.classList.remove('show');
    }
}

document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(function(cb) {
        cb.checked = this.checked;
    }.bind(this));
});

document.getElementById('caSearch').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#caTable tbody tr').forEach(function(tr) {
        var name = tr.querySelector('.campaign-name');
        if (!name) return;
        tr.style.display = name.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>




<script>
function toggleAutoAssign(el, source_id) {
    var status = el.checked ? 1 : 0;

    $.post(admin_url_base + 'campaign/campaign_is_on', {
        source_id: source_id,
        status: status
    }, function(res) {

        if (res.success) {

            var type = status ? 'success' : 'danger';

            alert_float(type, res.message);

        } else {
            alert_float('danger', 'Failed to update');
            el.checked = !el.checked;
        }

    }, 'json').fail(function() {
        alert_float('danger', 'Server error');
        el.checked = !el.checked;
    });
}
</script>

<?php init_tail(); ?>