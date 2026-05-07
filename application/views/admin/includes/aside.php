<style>
::-webkit-scrollbar {
    display: none;
}

* {
    scrollbar-width: none;
}

.whatsapp-float {
    position: fixed;
    bottom: 20px;
    right: 24px;
    width: 30px;
    height: 30px;
    background-color: #25D366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
    z-index: 9999;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.whatsapp-float:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
}



.whatsapp-float svg {
    z-index: 1;
}

.unread-count {
    position: absolute;
    top: -10px;
    right: -8px;
    background: #ff3b30;
    color: #fff;
    font-size: 10px;
    min-width: 25px;
    height: 25px;
    line-height: 25px;
    border-radius: 50%;
    text-align: center;
    padding: 0 5px;
    box-shadow: 0 0 0 2px #25D366;
}
#setup-menu{
    background:linear-gradient(-135deg, #3b5bdb 0%, #6741d9 100%);
    
}
</style>

<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<aside id="menu" class="sidebar sidebar" style="overflow:scroll;height:100%;">
    <ul class="nav metis-menu" id="side-menu">
        <li class="tw-mt-[63px] sm:tw-mt-0 -tw-mx-2 tw-overflow-hidden ">
            <div id="logo" class="tw-py-2 tw-px-2 tw-h-[63px] tw-flex tw-items-center" style="border-bottom: 1px solid #fff;">
                <img src="<?php echo site_url('uploads/company/'.get_option('company_logo')); ?>"
                    class="tw-h-8 sm:tw-h-10 tw-mx-auto" style="width: auto; height: 100%;"
                    alt="<?php echo get_option('companyname'); ?>">
            </div>
        </li>
        <?php
         hooks()->do_action('before_render_aside_menu');
?>
        <?php
     //   print_r($sidebar_menu);die;
    //  'dashboard', 'customers1222222', 'sales', 'subscriptions', 'expenses', 'contracts', 'projects', 'tasks', 'support', 'leads', 'estimate_request', 'knowledge-base', 'utilities', 'reports'

     $user = $this->session->userdata();
$userRole = get_staff($user['staff_user_id']);
foreach ($sidebar_menu as $key => $item) {
    if (isset($userRole)) {
        if (in_array($item['slug'], ['contracts', 'knowledge-base', 'subscriptions', 'expenses', 'sales', 'projects', 'support', 'estimate_request', 'utilities'])) {
            continue;
        }
    }
    if ((isset($item['collapse']) && $item['collapse']) && count($item['children']) === 0) {
        continue;
    } ?>
        <li class="menu-item-<?php echo $item['slug']; ?>"
            <?php echo _attributes_to_string(isset($item['li_attributes']) ? $item['li_attributes'] : []); ?>>
            <a href="<?php echo count($item['children']) > 0 ? '#' : $item['href']; ?>" aria-expanded="false"
                <?php echo _attributes_to_string(isset($item['href_attributes']) ? $item['href_attributes'] : []); ?>>
                <i class="<?php echo $item['icon']; ?> menu-icon"></i>
                <span class="menu-text">
                    <?php echo _l($item['name'], '', false); ?>
                </span>
                <?php if (count($item['children']) > 0) { ?>
                <span class="fa arrow pleft5"></span>
                <?php } ?>
                <?php if (isset($item['badge'], $item['badge']['value']) && !empty($item['badge'])) {?>
                <span
                    class="badge pull-right
               <?php echo isset($item['badge']['type']) && $item['badge']['type'] != '' ? "bg-{$item['badge']['type']}" : 'bg-info'; ?>" <?php echo (isset($item['badge']['type']) && $item['badge']['type'] == '')
               || isset($item['badge']['color']) ? "style='background-color: {$item['badge']['color']}'" : ''; ?>>
                    <?php echo $item['badge']['value']; ?>
                </span>
                <?php } ?>
            </a>
            <?php if (count($item['children']) > 0) { ?>
            <ul class="nav nav-second-level collapse" aria-expanded="false">
                <?php foreach ($item['children'] as $submenu) {
                    ?>
                <li class="sub-menu-item-<?php echo $submenu['slug']; ?>"
                    <?php echo _attributes_to_string(isset($submenu['li_attributes']) ? $submenu['li_attributes'] : []); ?>>
                    <a href="<?php echo $submenu['href']; ?>"
                        <?php echo _attributes_to_string(isset($submenu['href_attributes']) ? $submenu['href_attributes'] : []); ?>>
                        <?php if (!empty($submenu['icon'])) { ?>
                        <i class="<?php echo $submenu['icon']; ?> menu-icon"></i>
                        <?php } ?>
                        <span class="sub-menu-text">
                            <?php echo _l($submenu['name'], '', false); ?>
                        </span>
                    </a>
                    <?php if (isset($submenu['badge'], $submenu['badge']['value']) && !empty($submenu['badge'])) {?>
                    <span
                        class="badge pull-right
               <?php echo isset($submenu['badge']['type']) && $submenu['badge']['type'] != '' ? "bg-{$submenu['badge']['type']}" : 'bg-info'; ?>" <?php echo (isset($submenu['badge']['type']) && $submenu['badge']['type'] == '')
                || isset($submenu['badge']['color']) ? "style='background-color: {$submenu['badge']['color']}'" : ''; ?>>
                        <?php echo $submenu['badge']['value']; ?>
                    </span>
                    <?php } ?>
                </li>
                <?php
                } ?>
            </ul>
            <?php } ?>
        </li>
        <?php hooks()->do_action('after_render_single_aside_menu', $item); ?>
        <?php
} ?>
        <?php if ($this->app->show_setup_menu() == true && (is_staff_member() || is_admin())) {
            $style = ($userRole->role === null) ? 'display:flex;' : 'display:none;';
            ?>
        <li<?php if (get_option('show_setup_menu_item_only_on_hover') == 1) {
            echo ' style="display:none;"';
        } ?> id="setup-menu-item" style="<?php echo $style; ?>">
            <a href="#" class="open-customizer"><i class="fa fa-cog menu-icon"></i>
                <span class="menu-text">
                    <?php echo _l('setting_bar_heading'); ?>
                    <?php
              if ($modulesNeedsUpgrade = $this->app_modules->number_of_modules_that_require_database_upgrade()) {
                  echo '<span class="badge menu-badge !tw-bg-warning-600">'.$modulesNeedsUpgrade.'</span>';
              }

            ?>
                </span>
            </a>
            <?php } ?>
            </li>
            <?php hooks()->do_action('after_render_aside_menu'); ?>
            <?php $this->load->view('admin/projects/pinned'); ?>
    </ul>
</aside>


<a href="<?php echo base_url('admin/whatsapp'); ?>" target="_blank" class="whatsapp-float" title="Chat on WhatsApp">

    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="20" height="20" fill="white">
        <path
            d="M19.11 17.07c-.27-.14-1.6-.79-1.85-.88-.25-.09-.43-.14-.61.14-.18.27-.7.88-.86 1.06-.16.18-.32.2-.59.07-.27-.14-1.14-.42-2.18-1.34-.81-.72-1.36-1.61-1.52-1.88-.16-.27-.02-.42.12-.56.13-.13.27-.32.41-.48.14-.16.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.14-.61-1.47-.84-2.01-.22-.53-.45-.46-.61-.47-.16-.01-.34-.01-.52-.01-.18 0-.48.07-.73.34-.25.27-.96.94-.96 2.29 0 1.35.98 2.65 1.11 2.83.14.18 1.93 2.95 4.69 4.14.66.29 1.17.46 1.57.59.66.21 1.26.18 1.73.11.53-.08 1.6-.65 1.83-1.28.23-.63.23-1.17.16-1.28-.07-.11-.25-.18-.52-.32z" />
        <path
            d="M16 3C9.38 3 4 8.38 4 15c0 2.65.87 5.1 2.34 7.07L4 29l7.13-2.28A11.9 11.9 0 0016 27c6.62 0 12-5.38 12-12S22.62 3 16 3zm0 22c-2.16 0-4.17-.6-5.88-1.63l-.42-.25-4.23 1.35 1.38-4.12-.27-.43A9.94 9.94 0 016 15c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10z" />
    </svg>

    <?php $CI = &get_instance();
$CI->load->model('Whatsapp_model');

$unread_count = $CI->Whatsapp_model->get_agent_unread_count($CI->session->userdata('staff_user_id'));
if ((int) @$unread_count > 0) { ?>
    <span class="unread-count" id="dashboard-unread-count">
        <?php echo (int) @$unread_count; ?>
    </span>
    <?php } ?>

</a>


<?php
$staff = get_staff(get_staff_user_id());
?>

<script>
const USER_DIALER_PHONE = "<?php echo $staff->dialer_phone ?? ''; ?>";
const USER_DIALER_AGENT = "<?php echo $staff->dialer_agent_id ?? ''; ?>";
</script>

<script>
const CLIENT_ID =
    sessionStorage.getItem("WA_CLIENT_ID") ||
    "d_" + Date.now() + "_" + Math.random().toString(36).slice(2);

sessionStorage.setItem("WA_CLIENT_ID", CLIENT_ID);

const ws = new WebSocket("<?php echo WS_BASE_URL; ?>");

ws.onopen = () => {
    ws.send(JSON.stringify({
        action: "init",
        agent_id: "<?php echo $CI->session->userdata('staff_user_id'); ?>",
        channel_id: 1,
        client_id: CLIENT_ID
    }));
};

ws.onmessage = e => {
    const d = JSON.parse(e.data);

    // 🔴 DASHBOARD TOTAL UNREAD
    if (d.dashboard_unread !== undefined) {
        const badge = document.getElementById("dashboard-unread-count");
        if (!badge) return;

        if (parseInt(d.dashboard_unread) > 0) {
            badge.innerText = d.dashboard_unread;
            badge.style.display = "inline-block";
        } else {
            badge.style.display = "none";
        }
    }
};
</script>