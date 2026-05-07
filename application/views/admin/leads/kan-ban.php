<?php defined('BASEPATH') or exit('No direct script access allowed');
$is_admin = is_admin();
$i        = 0;

$path = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));
$last_segment = end($segments);


foreach ($statuses as $status) {
    if ($last_segment === 'customers' && (int)$status['id'] !== 8) {
        continue;
    }

    $kanBan = new \app\services\leads\LeadsKanban($status['id']);
    $kanBan->search($this->input->get('search'))
           ->sortBy($this->input->get('sort_by'), $this->input->get('sort'));

    if ($this->input->get('refresh')) {
        $kanBan->refresh($this->input->get('refresh')[$status['id']] ?? null);
    }

    $leads = $kanBan->get();

    $CI =& get_instance();
    $totalLeadsCount = $CI->leads_model->get_total_leads($status['id']);
    // print_r(count($totalLeadsCount));

    if ($last_segment === 'customers') {
        $leads = array_filter($leads, function($lead) {
            return (int)$lead['status'] === 8;
        });
    }

    $total_leads = count($leads);
    $total_pages = $kanBan->totalPages();

    $settings = '';
    foreach (get_system_favourite_colors() as $color) {
        $color_selected_class = ($color == $status['color']) ? 'cpicker-big' : 'cpicker-small';
        $settings .= "<div class='kanban-cpicker cpicker " . $color_selected_class . "' data-color='" . $color . "' style='background:" . $color . ';border:1px solid ' . $color . "'></div>";
    }
    ?>
<ul class="kan-ban-col" data-col-status-id="<?php echo $status['id']; ?>" data-total-pages="<?php echo $total_pages; ?>"
    data-total="<?php echo $total_leads; ?>">
    <li class="kan-ban-col-wrapper">
        <div class="border-right panel_s">
            <?php
                $status_color = '';
                if (!empty($status['color'])) {
                    $status_color = 'style="background:' . $status['color'] . ';border:1px solid ' . $status['color'] . '"';
                } ?>
            <div class="panel-heading tw-bg-neutral-700 tw-text-white"
                <?php if ($status['isdefault'] == 1) { ?>data-toggle="tooltip"
                data-title="<?php echo _l('leads_converted_to_client') . ' - ' . _l('client'); ?>" <?php } ?>
                <?php echo $status_color; ?> data-status-id="<?php echo $status['id']; ?>">
                <div style="display:flex; align-items:center;">
                    <i class="fa fa-reorder pointer"></i>
                    <div style="display: flex; justify-content: space-between; width: 100%; align-items:center;">
                        <span class="heading pointer tw-ml-1" <?php if ($is_admin) { ?>
                            data-order="<?php echo $status['statusorder']; ?>"
                            data-color="<?php echo $status['color']; ?>" data-name="<?php echo $status['name']; ?>"
                            onclick="edit_status(this,<?php echo $status['id']; ?>); return false;" <?php } ?>>
                            <?php echo $status['name']; ?>
                        </span>
                        <p style="margin-left:auto; margin-right:0px;margin-top:10px;"><?php echo count($totalLeadsCount); //count($leads); ?></p>
                    </div>
                </div>

            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="status leads-status sortable" data-lead-status-id="<?php echo $status['id']; ?>">
                        <?php
                            foreach ($leads as $lead) {
                                $this->load->view('admin/leads/_kan_ban_card', [
                                    'lead' => $lead,
                                    'status' => $status,
                                    'base_currency' => $base_currency
                                ]);
                            } ?>
                        <?php if ($total_leads > 0) { ?>
                        <li class="text-center not-sortable kanban-load-more"
                            data-load-status="<?php echo $status['id']; ?>">
                            <a href="#" class="btn btn-default btn-block<?php if ($total_pages <= 1 || $kanBan->getPage() === $total_pages) {
                                    echo ' disabled';
                                } ?>" data-page="<?php echo $kanBan->getPage(); ?>"
                                onclick="kanban_load_more(<?php echo $status['id']; ?>, this, 'leads/leads_kanban_load_more', 315, 360); return false;">
                                <?php echo _l('load_more'); ?>
                            </a>
                        </li>
                        <?php } ?>
                        <li class="text-center not-sortable mtop30 kanban-empty<?php if ($total_leads > 0) {
                                echo ' hide';
                            } ?>">
                            <h4>
                                <i class="fa-solid fa-circle-notch" aria-hidden="true"></i><br /><br />
                                <?php echo _l('no_leads_found'); ?>
                            </h4>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </li>
</ul>
<?php
$i++;
} ?>