<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="status" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?php echo form_open(admin_url('leads/status'), ['id' => 'leads-status-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="edit-title"><?php echo _l('edit_status'); ?></span>
                    <span class="add-title"><?php echo _l('lead_new_status'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div id="additional"></div>
                        <?php echo render_input('name', 'leads_status_add_edit_name'); ?>
                        <div class="form-group no-mbot" id="inputTagsWrapper">
                            <label for="tags" class="control-label"><i class="fa fa-tag" aria-hidden="true"></i>
                                <?php echo _l('Sub Statuses'); ?></label>
                            <input type="text" class="tagsinput" id="sub_name" name="sub_name"
                                value="<?php echo(isset($lead) ? prep_tags_input() : ''); ?>" data-role="tagsinput">
                        </div>
                        <?php echo render_color_picker('color', _l('leads_status_color')); ?>
                        <?php echo render_input('statusorder', 'leads_status_add_edit_order', total_rows(db_prefix() . 'leads_status') + 1, 'number'); ?>
                        <div class="checkbox">
                  <input data-can-view="" type="checkbox" class="capability" id="bulk_pdf_exporter_view" name="customer" value="isCustomer">
                  <label for="bulk_pdf_exporter_view">Customer Status</label>
                                   </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <!-- /.modal-content -->
        <?php echo form_close(); ?>
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<script>
window.addEventListener('load', function() {
    appValidateForm($("body").find('#leads-status-form'), {
        name: 'required'
    }, manage_leads_statuses);
    $('#status').on("hidden.bs.modal", function(event) {
        $('#additional').html('');
        $('#status input[name="name"]').val('');
        $('#status input[name="sub_name"]').val('');
        $('#status input[name="color"]').val('');
        $('#status input[name="statusorder"]').val('');
        $('.add-title').removeClass('hide');
        $('.edit-title').removeClass('hide');
        $('#status input[name="statusorder"]').val($('table tbody tr').length + 1);
    });
});

// Create lead new status
function new_status() {
    $('#status').modal('show');
    $('.edit-title').addClass('hide');
}

// Edit status function which init the data to the modal
function edit_status(invoker, id) {
    console.log(invoker)
    $('#additional').append(hidden_input('id', id));
    $('#status input[name="name"]').val($(invoker).data('name'));

    $("#sub_name").tagit("removeAll");
    $("#sub_name").tagit("createTag", "Tag1");
    $("#sub_name").tagit("createTag", "Tag2");

    let subs = $(invoker).data('sub_name');
    let $subInput = $('#status input[name="sub_name"]');

    if ($subInput.data('ui-tagit')) {
        $subInput.tagit('removeAll'); // clear old tags
        if (subs) {
            subs.split(',').forEach(function(s) {
                $subInput.tagit('createTag', s.trim());
            });
        }
    } else {
        // fallback: set raw value (in case tagit not yet init)
        $subInput.val(subs || '');
    }
    $('#status .colorpicker-input').colorpicker('setValue', $(invoker).data('color'));
    $('#status input[name="statusorder"]').val($(invoker).data('order'));
    $('#status').modal('show');
    $('.add-title').addClass('hide');
}

// Form handler function for leads status
function manage_leads_statuses(form) {
    var data = $(form).serialize();
    var url = form.action;
    $.post(url, data).done(function(response) {
        window.location.reload();
    });
    return false;
}
</script>