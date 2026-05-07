<style>
/* ensure dropdown not clipped */
#leadStatusModal,
#leadStatusModal .modal-content {
    overflow: visible !important;
}

/* ensure select2 container has higher z-index if still issues */
.select2-container {
    z-index: 999999 !important;
}
</style>
<?php defined('BASEPATH') or exit('No direct script access allowed');
$lead_already_client_tooltip = '';
$lead_is_client              = $lead['is_lead_client'] !== '0';
if ($lead_is_client) {
    $lead_already_client_tooltip = ' data-toggle="tooltip" title="' . _l('lead_have_client_profile') . '"';
}
if ($lead['status'] == $status['id']) { ?>
<li data-lead-id="<?php echo $lead['id']; ?>" <?php echo $lead_already_client_tooltip; ?> class="lead-kan-ban<?php if ($lead['assigned'] == get_staff_user_id()) {
    echo ' current-user-lead';
} ?><?php if ($lead_is_client && get_option('lead_lock_after_convert_to_customer') == 1 && !is_admin()) {
    echo ' not-sortable';
} ?>">
    <div class="panel-body lead-body">
        <div class="row">
            <div class="col-md-12 lead-name">
                <?php if ($lead['assigned'] != 0) { ?>
                <a href="<?php echo admin_url('profile/' . $lead['assigned']); ?>" data-placement="right"
                    data-toggle="tooltip" title="<?php echo get_staff_full_name($lead['assigned']); ?>"
                    class="pull-left mtop8 mright5">
                    <?php echo staff_profile_image($lead['assigned'], [
                  'staff-profile-image-xs',
                  ]); ?></a>
                <?php  } ?>
                <a href="<?php echo admin_url('leads/index/' . $lead['id']); ?>"
                    onclick="init_lead(<?php echo $lead['id']; ?>);return false;" class="pull-left">
                    <span
                        class="inline-block mtop10 mbot10">#<?php echo $lead['id'] . ' - ' . $lead['lead_name']; ?></span>
                </a>
            </div>
            <div class="col-md-12">
                <div class="tw-flex">
                    <div class="tw-grow">
                        <p class="tw-text-sm tw-mb-0">
                            <?php echo _l('leads_canban_source', $lead['source_name']); ?>
                        </p>

                    </div>
                    <div class="tw-shrink-0 text-right">
                        <?php if (is_date($lead['lastcontact']) && $lead['lastcontact'] != '0000-00-00 00:00:00') { ?>
                        <small class="text-dark tw-text-sm"><?php echo _l('leads_dt_last_contact'); ?> <span
                                class="bold">
                                <span class="text-has-action" data-toggle="tooltip"
                                    data-title="<?php echo _dt($lead['lastcontact']); ?>">
                                    <?php echo time_ago($lead['lastcontact']); ?>
                                </span>
                            </span>
                        </small><br />
                        <?php } ?>
                        <small class="text-dark"><?php echo _l('lead_created'); ?>: <span class="bold">
                                <span class="text-has-action" data-toggle="tooltip"
                                    data-title="<?php echo _dt($lead['dateadded']); ?>">
                                    <?php echo time_ago($lead['dateadded']); ?>
                                </span>
                            </span>
                        </small><br />
                        <?php hooks()->do_action('before_leads_kanban_card_icons', $lead); ?>
                        <span class="mright5 mtop5 inline-block text-muted" data-toggle="tooltip" data-placement="left"
                            data-title="<?php echo _l('leads_canban_notes', $lead['total_notes']); ?>">
                            <i class="fa-regular fa-note-sticky"></i> <?php echo $lead['total_notes']; ?>
                        </span>
                        <span class="mtop5 inline-block text-muted" data-placement="left" data-toggle="tooltip"
                            data-title="<?php echo _l('lead_kan_ban_attachments', $lead['total_files']); ?>">
                            <i class="fa fa-paperclip"></i>
                            <?php echo $lead['total_files']; ?>
                        </span>
                        <?php hooks()->do_action('after_leads_kanban_card_icons', $lead); ?>
                    </div>
                </div>
            </div>

            <?php if ($lead['tags']) { ?>
            <div class="col-md-12">
                <div class="kanban-tags tw-text-sm tw-inline-flex">
                    <?php // echo render_tags($lead['tags']); ?>
                </div>
            </div>
            <?php } ?>


            <?php 
              $substatuses = get_substatus($lead['status']); 
              if (!empty($substatuses)) { ?>
            <div class="col-md-12 text-right mtop10"
                style="display:flex;justify-content: space-between;align-items:center;gap: 10px;">
                <div style="text-align:left">
                    <?php
              $CI =& get_instance();
              $CI->load->model('misc_model');
              $notes = $CI->misc_model->get_notes($lead['id'], 'lead');

              if (!empty($notes)) {
                  foreach ($notes as $note) {
                      echo '<p>';
                      echo '<strong>Name:</strong> ' . $note['firstname'] . ' ' . $note['lastname'] . '<br>';
                      echo '<strong>Note:</strong> ' . htmlspecialchars($note['description']);
                      echo '</p><hr>';
                  }
              } else {
                  echo 'No notes found.';
              }
          ?>


                </div>
                <button type="button" class="btn btn-default btn-sm change-status-btn"
                    data-lead-id="<?php echo $lead['id']; ?>" data-status-id="<?php echo $lead['status']; ?>"
                    data-substatuses='<?php echo html_escape(json_encode($substatuses)); ?>'>
                    <i class="fa fa-refresh"></i> Change Status
                </button>
            </div>
            <?php } ?>







            <a href="#" class="pull-right text-muted kan-ban-expand-top"
                onclick="slideToggle('#kan-ban-expand-<?php echo $lead['id']; ?>'); return false;">
                <i class="fa fa-expand" aria-hidden="true"></i>
            </a>
            <div class="clearfix no-margin"></div>
            <div id="kan-ban-expand-<?php echo $lead['id']; ?>" class="padding-10" style="display:none;">
                <div class="clearfix"></div>
                <hr class="hr-10" />
                <p class="text-muted lead-field-heading"><?php echo _l('lead_title'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['title'] != '' ? $lead['title'] : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_add_edit_email'); ?></p>
                <p class="bold tw-text-sm">
                    <?php echo($lead['email'] != '' ? '<a href="mailto:' . $lead['email'] . '">' . $lead['email'] . '</a>' : '-') ?>
                </p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_website'); ?></p>
                <p class="bold tw-text-sm">
                    <?php echo($lead['website'] != '' ? '<a href="' . maybe_add_http($lead['website']) . '" target="_blank">' . $lead['website'] . '</a>' : '-') ?>
                </p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_add_edit_phonenumber'); ?></p>
                <p class="bold tw-text-sm">
                    <?php echo($lead['phonenumber'] != '' ? '<a href="tel:' . $lead['phonenumber'] . '">' . $lead['phonenumber'] . '</a>' : '-') ?>
                </p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_company'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['company'] != '' ? $lead['company'] : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_address'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['address'] != '' ? $lead['address'] : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_city'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['city'] != '' ? $lead['city'] : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_state'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['state'] != '' ? $lead['state'] : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_country'); ?></p>
                <p class="bold tw-text-sm">
                    <?php echo($lead['country'] != 0 ? get_country($lead['country'])->short_name : '-') ?></p>
                <p class="text-muted lead-field-heading"><?php echo _l('lead_zip'); ?></p>
                <p class="bold tw-text-sm"><?php echo($lead['zip'] != '' ? $lead['zip'] : '-') ?></p>
            </div>
        </div>
    </div>
</li>
<?php } ?>

<div class="modal fade" id="leadStatusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Lead Substatus</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <form id="leadStatusForm" method="post">
                <!-- CSRF hidden field -->
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>"
                    value="<?php echo $this->security->get_csrf_hash(); ?>">

                <div class="modal-body">
                    <input type="hidden" name="leadid" id="lead_id">

                    <div class="form-group" id="substatus-container" style="display:none;">
                        <label for="substatus_id">Select Substatus</label>
                        <select name="status" id="substatus_id" class="form-control">
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
$(document).ready(function() {
    $(document).on('click', '.change-status-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var leadId = $btn.data('lead-id');
        var statusId = $btn.data('status-id');
        var raw = $btn.attr('data-substatuses') || '[]';
        var substatuses;
        try {
            substatuses = JSON.parse(raw);
        } catch (err) {
            substatuses = $btn.data('substatuses') || [];
        }

        $('#lead_id').val(leadId);
        $('#status_id').val(statusId);

        var $dropdown = $('#substatus_id');

        if ($.fn.select2 && $dropdown.hasClass('select2-hidden-accessible')) {
            try {
                $dropdown.select2('destroy');
            } catch (e) {
                /* ignore */ }
        }
        if ($dropdown.hasClass('selectpicker') && $.fn.selectpicker) {
            try {
                $dropdown.selectpicker('destroy');
            } catch (e) {
                /* ignore */ }
            $dropdown.removeClass('selectpicker'); // clean up class if needed
        }

        $dropdown.empty().append('<option value="">-- Select --</option>');
        if (Array.isArray(substatuses) && substatuses.length > 0) {
            substatuses.forEach(function(sub) {
                var text = sub.sub_name !== undefined ? sub.sub_name : (sub.name || '');
                $dropdown.append(
                    '<option value="sub-' + sub.id + '">' + sub.sub_name + '</option>'
                );
            });
            $('#substatus-container').show();
        } else {
            $('#substatus-container').hide();
        }
        $('#leadStatusModal').modal('show');
        $('#leadStatusModal').one('shown.bs.modal', function() {
            if ($.fn.select2) {
                $dropdown.select2({
                    dropdownParent: $('#leadStatusModal'),
                    width: '100%'
                });
            } else if ($.fn.selectpicker) {
                $dropdown.addClass('selectpicker');
                $dropdown.selectpicker();
            } else {}
        });
    });

    $('#leadStatusForm').off('submit').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');

        if ($button.prop('disabled')) return;
        $button.prop('disabled', true).data('original-text', $button.text()).text('Saving...');

        $.post("<?php echo admin_url('leads/update_lead_status'); ?>", $form.serialize())
            .done(function(responseText) {
                $button.prop('disabled', false).text($button.data('original-text'));
                alert_float('success', "Status Updated Successfully!" || 'Updated');
                // var response = {};
                // try {
                //     response = JSON.parse(responseText);
                // } catch (err) {
                //     console.error('Invalid JSON response:', responseText);
                //     alert_float('danger', 'Something went wrong (invalid server response111).');
                //     return;
                // }

                // if (response.success) {
                //     $('#leadStatusModal').modal('hide');
                //     alert_float('success', response.message || 'Updated');

                //     if (response.lead_id && response.substatus_name) {
                //         var $card = $('#lead-card-' + response.lead_id);
                //         if ($card.length) {
                //             if ($card.find('.substatus-text').length) {
                //                 $card.find('.substatus-text').text(response.substatus_name);
                //             } else {
                //                 $card.append('<span class="substatus-text">' + response.substatus_name + '</span>');
                //             }
                //         }
                //     }

                // } else {
                //     alert_float('danger', response.message || 'Unable to update');
                // }
            })
            .fail(function(xhr, status, error) {
                $button.prop('disabled', false).text($button.data('original-text'));
                console.error('AJAX error:', status, error, xhr.responseText);
                alert_float('danger', 'Something went wrong.');
            });
    });



});
</script>