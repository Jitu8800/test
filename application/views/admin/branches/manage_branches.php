<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-2 sm:tw-mb-4">
                    <a href="#" onclick="new_branch(); return false;" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('New Branch'); ?>
                    </a>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php if (count($branches) > 0) { ?>
                        <table class="table dt-table" data-order-col="1" data-order-type="asc">
                            <thead>
                                <th><?php echo _l('id'); ?></th>
                                <th><?php echo _l('Branch Name'); ?></th>
                                <th><?php echo _l('options'); ?></th>
                            </thead>
                            <tbody>
                                <?php foreach ($branches as $branch) { ?>
                                <tr>
                                    <td><?php echo $branch['id']; ?></td>
                                    <td><a href="#"
                                            onclick="edit_branch(this,<?php echo $branch['id']; ?>); return false"
                                            data-name="<?php echo $branch['name']; ?>"><?php echo $branch['name']; ?></a><br />
                                        <span class="text-muted">
                                            <?php echo _l('leads_table_total', total_rows(db_prefix() . 'branches', ['id' => $branch['id']])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="tw-flex tw-items-center tw-space-x-3">
                                            <a href="#"
                                                onclick="edit_branch(this,<?php echo $branch['id']; ?>); return false"
                                                data-name="<?php echo $branch['name']; ?>" data-value="<?php echo $branch['id']; ?>"
                                                class="tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700">
                                                <i class="fa-regular fa-pen-to-square fa-lg"></i>
                                            </a>
                                            <a href="<?php echo admin_url('branch/delete_source/' . $branch['id']); ?>"
                                                class="tw-mt-px tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700 _delete">
                                                <i class="fa-regular fa-trash-can fa-lg"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php } else { ?>
                        <p class="no-margin"><?php echo _l('No branch found'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="branch" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?php echo form_open(admin_url('branch/source')); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="edit-title"><?php echo _l('Edit Branch'); ?></span>
                    <span class="add-title"><?php echo _l('lead_new_branch'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div id="additional"></div>
                        <?php echo render_input('name', 'leads_branch_add_edit_name'); ?>
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
<?php init_tail(); ?>
<script>
$(function() {
    appValidateForm($('form'), {
        name: 'required'
    }, manage_leads_branchs);
    $('#branch').on('hidden.bs.modal', function(event) {
        $('#additional').html('');
        $('#branch input[name="name"]').val('');
        $('.add-title').removeClass('hide');
        $('.edit-title').removeClass('hide');
    });
});

function manage_leads_branchs(form) {
    var data = $(form).serialize();
    var url = form.action;
    $.post(url, data).done(function(response) {
        window.location.reload();
    });
    return false;
}

function new_branch() {
    $('#branch').modal('show');
    $('.edit-title').addClass('hide');
}

function edit_branch(invoker, id) {
    var name = $(invoker).data('name');
    var value = $(invoker).data('value');
    $('#additional').append(hidden_input('id', id));
    $('#branch input[name="name"]').val(name);
    $('#branch input[name="value"]').val(value);
    $('#branch').modal('show');
    $('.add-title').addClass('hide');
}
</script>
</body>

</html>