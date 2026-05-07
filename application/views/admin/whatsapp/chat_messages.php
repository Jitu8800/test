<!DOCTYPE html>
<html>

<head>
    <title>WhatsApp Multi Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
    body {
        background: #e5ddd5;
        margin: 0;
        font-family: Helvetica, Arial, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* ========== MAIN LAYOUT ========== */
    .chat-container {
        width: 100%;
        height: 90vh;
        background: #fff;
        display: flex;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .3);
    }

    .contacts {
        width: 35%;
        background: #f0f0f0;
        overflow-y: auto;
        border-right: 1px solid #ddd;
    }

    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    /* ========== LEAD ROW ========== */
    .lead {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        cursor: pointer;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        position: relative;
    }

    .lead.active {
        background: #ddd;
    }

    .lead-text {
        display: flex;
        flex-direction: column;
    }


    /* Right side container */
    .lead-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 10px;
        /* space between elements */
    }

    /* Icon + Badge wrapper */
    .label-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        /* fixed size */
    }

    /* Badge */
    .unread-count {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #25d366;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        box-sizing: border-box;
        pointer-events: none;
    }

    /* Icon */
    .label-btn {
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        opacity: 0.8;
        margin-right: 40px
    }

    .label-btn:hover {
        opacity: 1;
    }


    /* ========== LABEL DROPDOWN ========== */
    .label-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 26px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        width: 180px;
        z-index: 999;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Label row */
    .label-row {
        padding: 8px 10px;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .label-row:hover {
        background: #f5f5f5;
    }

    /* Delete X in dropdown */
    .label-delete {
        color: #ef4444;
        font-size: 12px;
        cursor: pointer;
    }

    /* Add label */
    .label-add {
        padding: 8px 10px;
        font-size: 13px;
        cursor: pointer;
        color: #25d366;
        font-weight: 600;
    }

    .label-dropdown hr {
        margin: 4px 0;
        border: none;
        border-top: 1px solid #eee;
    }

    /* ========== LABELS UNDER LEAD NAME ========== */
    .labels {
        margin-top: 4px;
    }

    /* Label pill */
    .label {
        font-size: 8px;
        padding: 2px;
        border-radius: 4px;
        color: #fff;
        display: inline-block;
        position: relative;
    }

    /* X icon on label */
    .label-x {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 14px;
        height: 14px;
        background: #000;
        color: #fff;
        border-radius: 50%;
        font-size: 10px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    /* Show X on hover */
    .label:hover .label-x {
        display: flex;
    }

    /* ========== CHAT HEADER ========== */
    #chat-header {
        padding: 10px;
        background: #075e54;
        color: #fff;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ========== MESSAGES ========== */
    #messages {
        flex: 1;
        padding: 12px;
        background: #e5ddd5;
        overflow-y: auto;
    }

    .msg {
        max-width: 75%;
        padding: 8px 10px;
        margin-bottom: 10px;
        border-radius: 8px;
        font-size: 14px;
        clear: both;
    }

    .sent {
        gap: 14px;
        display: flex;
        background: #dcf8c6;
        float: right;
        border-top-right-radius: 0;
        align-items: flex-end;
    }

    .received {
        gap: 14px;
        display: flex;
        background: #fff;
        float: left;
        border-top-left-radius: 0;
        align-items: flex-end;
    }

    .time {
        font-size: 11px;
        color: #838383;
        text-align: right;
        /* margin-top: 4px; */
        white-space: nowrap;

    }

    .ticks {
        margin-left: 4px;
        font-size: 11px;
        color: #999;
    }

    .ticks.seen {
        color: #4fc3f7;
    }

    /* ========== FOOTER ========== */
    .footer {
        display: flex;
        padding: 10px;
        background: #f0f0f0;
        gap: 6px;
        align-items: center;
    }

    .footer textarea {
        flex: 1;
        resize: none;
        border-radius: 20px;
        padding: 6px 12px;
        border: 1px solid #ccc;
    }

    .footer button {
        background: #25d366;
        border: none;
        color: #fff;
        border-radius: 50%;
        width: 42px;
        height: 42px;
        font-size: 18px;
    }

    .attach-btn {
        font-size: 22px;
        cursor: pointer;
        padding: 4px 8px;
    }

    /* ========== MISC ========== */
    #typing {
        font-size: 12px;
        color: #555;
        padding: 4px 12px;
        display: none;
    }

    #qr {
        display: flex;
        justify-content: center;
        align-items: center;
        flex: 1;
    }

    #qr img {
        width: 240px;
    }

    /* === PERFEX STYLE FLOAT ALERT === */
    .float-alert {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
        min-width: 280px;
        max-width: 360px;
        padding: 12px 15px;
        border-radius: 4px;
        background: #098209;
        color: #ffffff;
        border: none;
    }

    /* stack multiple alerts */
    .float-alert+.float-alert {
        margin-top: 10px;
    }

    /* icon */
    .float-alert .fa-bell {
        margin-right: 8px;
    }

    /* title text */
    .float-alert .alert-title {
        font-size: 13px;
        font-weight: 100;
    }

    /* close button fix */
    .float-alert .close {
        position: absolute;
        right: 8px;
        top: 6px;
    }


    .wa-label-bar {
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
        background: #fff;
    }

    .wa-label-list {
        display: flex;
        gap: 2px;
        flex-wrap: wrap;
    }

    .wa-label {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 5px 10px;
        background: #f0f2f5;
        border-radius: 16px;
        font-size: 10px;
        cursor: pointer;
        user-select: none;
        transition: all .2s ease;
    }

    .wa-label:hover {
        background: #e4e6e9;
    }

    .wa-label.active {
        background: #25d366;
        color: #fff;
    }

    .wa-label-name {
        white-space: nowrap;
    }

    .wa-label-count {
        background: rgba(0, 0, 0, .15);
        padding: 0 6px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .wa-label.active .wa-label-count {
        background: rgba(255, 255, 255, .3);
    }

    .lead-text {
        font-size: 15px !important;

    }

    .chat-file {
        height: 300px;
        width: auto;
    }

    .qr-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        width: 100%;
        background: #e5ddd5;
    }

    .qr-card {
        margin: auto;
        background: #fff;
        width: 300px;
        padding: 20px 25px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .qr-card h2 {
        font-size: 18px;
        margin-bottom: 6px;
        color: #111;
    }

    .qr-subtitle {
        font-size: 13px;
        color: #666;
        margin-bottom: 20px;
    }

    .qr-box {
        position: relative;
        width: 250px;
        height: 250px;
        border: 2px solid #25D366;
        border-radius: 10px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    #qr_img {
        width: 220px;
        height: 220px;
        object-fit: contain;
        display: none;
    }

    .qr-loader {
        position: absolute;
        width: 45px;
        height: 45px;
        border: 4px solid #ddd;
        border-top: 4px solid #25D366;
        border-radius: 50%;
        animation: qrSpin 1s linear infinite;
    }

    @keyframes qrSpin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .qr-steps {
        text-align: left;
        margin-top: 20px;
        padding-left: 18px;
        font-size: 13px;
        color: #444;
        line-height: 1.6;
    }
    </style>
</head>

<body>

    <div class="chat-container">

        <!-- CONTACTS -->
        <div class="contacts">
            <ul class="nav" id="side-menu" style="background:#1e293b; padding:0;">
                <li id="logo" style="height:63px; display:flex; justify-content:center; align-items:center;">
                    <img src="<?php echo site_url('uploads/company/'.get_option('company_logo')); ?>"
                        style="height:50px; width:auto;">
                </li>
            </ul>

            <input type="text" id="leadSearch" placeholder="Search name or phone"
                style="width:100%;padding:8px;margin-bottom:10px;">

            <div class="wa-label-bar">
                <div id="label-filter-list" class="wa-label-list"></div>
            </div>

            <div id="lead-list">

                <?php if (!empty($leads) && is_array($leads)) { ?>
                <?php foreach ($leads as $lead) { ?>

                <div class="lead" data-phone="<?php echo strtolower($lead->phonenumber ?? ''); ?>"
                    data-name="<?php echo strtolower($lead->name ?? ''); ?>" data-lead-id="<?php echo $lead->id; ?>"
                    data-label-ids="<?php echo implode(',', $lead->label_ids ?? []); ?>"
                    style="position:relative;display:flex;gap:10px;">


                    <!-- Avatar -->
                    <svg class="avatar" viewBox="0 0 24 24" width="36" height="36">
                        <circle cx="12" cy="12" r="12" fill="#e0e0e0" />
                        <circle cx="12" cy="9" r="4" fill="#9e9e9e" />
                        <path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#9e9e9e" />
                    </svg>

                    <!-- Lead Info (ONLY name + phone) -->
                    <div class="lead-text">
                        <div class="lead-name"><?php echo htmlspecialchars($lead->name ?? ''); ?></div>
                        <div class="lead-phone">
                            <small><?php echo htmlspecialchars($lead->phonenumber ?? ''); ?></small>
                        </div>
                    </div>

                    <!-- 🏷️ Labels (OUTSIDE lead-text) -->
                    <?php if (!empty($lead->labels)) { ?>
                    <div class="labels" id="lead-labels-<?php echo $lead->id; ?>">
                        <?php foreach ($lead->labels as $i => $label) { ?>
                        <span class="label" id="lead-label-<?php echo $lead->id; ?>-<?php echo $lead->label_ids[$i]; ?>"
                            data-label-id="<?php echo $lead->label_ids[$i]; ?>"
                            style="background:<?php echo $lead->label_colors[$i]; ?>"
                            onclick="event.stopPropagation();">

                            <?php echo htmlspecialchars($label ?? ''); ?>

                            <span class="label-x"
                                onclick="event.stopPropagation(); deleteLeadLabel(<?php echo $lead->id; ?>, <?php echo $lead->label_ids[$i]; ?>)">
                                ✖
                            </span>
                        </span>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Actions -->
                    <div class="lead-actions">

                        <div class="label-wrap">

                            <!-- Unread Badge (Always Exists) -->
                            <span class="unread-count" id="unread-<?php echo $lead->id; ?>"
                                style="<?php echo ((int) $lead->unread_count > 0) ? '' : 'display:none;'; ?>">
                                <?php echo (int) $lead->unread_count; ?>
                            </span>

                            <!-- Label Button -->
                            <span class="label-btn" onclick="toggleLabelMenu(<?php echo $lead->id; ?>)">
                                🏷️
                            </span>

                        </div>

                        <!-- Dropdown -->
                        <div class="label-dropdown" id="label-menu-<?php echo $lead->id; ?>"></div>

                    </div>


                </div>


                <?php } ?>
                <?php } else { ?>
                <div style="padding:15px;color:#aaa;text-align:center;">
                    No leads assigned
                </div>
                <?php } ?>

            </div>


        </div>

        <!-- CHAT -->
        <div class="chat-main">
            <div id="chat-header">
                <a href="<?php echo base_url('admin/leads'); ?>" class="back-btn"
                    style="color:#fff;text-decoration:none;font-size:24px;">←</a>
                <span id="active-lead">Select a lead</span>
            </div>

            <div id="qr" class="qr-wrapper">
                <div class="qr-card">
                    <h2>Log in with WhatsApp</h2>

                    <p class="qr-subtitle">
                        Scan this QR with WhatsApp
                    </p>

                    <div class="qr-box">
                        <div id="qr_loader" class="qr-loader"></div>
                        <img id="qr_img" />
                    </div>
                </div>
            </div>

            <div id="messages"></div>
            <div id="typing">typing…</div>

            <div class="footer" style="display:none;">
                <label for="fileInput" class="attach-btn">📎</label>
                <input type="file" id="fileInput" hidden>
                <textarea id="msg" rows="1" placeholder="Type a message"></textarea>
                <button id="send">➤</button>
            </div>
        </div>

    </div>

    <input type="hidden" id="csrf_token" value="<?php echo $this->security->get_csrf_hash(); ?>">
    <input type="hidden" id="AGENT_ID" value="<?php echo $this->session->userdata('staff_user_id'); ?>">

    <!-- MEDIA PREVIEW MODAL -->
    <div class="modal fade" id="mediaModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Send Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body text-center" id="mediaPreview">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" id="confirmSendMedia" class="btn btn-success">
                        Send
                    </button>
                </div>

            </div>
        </div>
    </div>


    <script>
    function alert_float(type, message, timeout) {
        var aId = $(".float-alert").length + 1;
        aId = "alert_float_" + aId;

        var el = $("<div>", {
            id: aId,
            class: "float-alert animated fadeInRight alert alert-" + type
        });

        el.append('<button type="button" class="close" data-dismiss="alert">&times;</button>');
        el.append('<span class="fa-regular fa-bell"></span>');
        el.append('<span class="alert-title">' + message + '</span>');

        $("body").append(el);

        timeout = timeout || 3500;
        setTimeout(function() {
            $("#" + aId).fadeOut(300, function() {
                $(this).remove();
            });
        }, timeout);
    }
    </script>


    <script>
    /* ================= UNIQUE CLIENT ID (TAB SAFE) ================= */
    let CLIENT_ID = sessionStorage.getItem("WA_CLIENT_ID");
    if (!CLIENT_ID) {
        CLIENT_ID = "c_" + Date.now() + "_" + Math.random().toString(36).slice(2);
        sessionStorage.setItem("WA_CLIENT_ID", CLIENT_ID);
    }

     /* ================= SOCKET ================= */
    const ws = new WebSocket("<?php echo WS_BASE_URL; ?>");

    let connected = false;
    let activePhone = null;
    let activeLeadId = null;
    let typingTimeout = null;
    let fileToUpload = null;
    /* ================= INIT ================= */
    ws.onopen = () => {
        ws.send(JSON.stringify({
            action: "init",
            agent_id: $("#AGENT_ID").val(),
            channel_id: 1,
            client_id: CLIENT_ID
        }));
    };

    /* ================= RECEIVE MESSAGES ================= */
    ws.onmessage = e => {
        const d = JSON.parse(e.data);

        if (d.type === "qr") {
            $("#qr").css("display", "flex");

            const qrImg = document.getElementById("qr_img");
            const qrLoader = document.getElementById("qr_loader");

            qrLoader.style.display = "block";
            qrImg.style.display = "none";

            qrImg.onload = function() {
                qrLoader.style.display = "none";
                qrImg.style.display = "block";
            };

            qrImg.onerror = function() {
                console.log("QR image failed:", d.data);
                qrLoader.style.display = "none";
            };

            qrImg.src = "";

            setTimeout(() => {
                qrImg.src = d.data.startsWith("data:image") ?
                    d.data :
                    "data:image/png;base64," + d.data;
            }, 50);

            return;
        }

        if (d.type === "status") {
            connected = (d.data === "connected");
            $("#qr").toggle(!connected);
            $(".footer").toggle(connected);
            return;
        }

        if ((d.type === "incoming" || d.type === "sent") &&
            (d.lead_id == activeLeadId || (!d.lead_id && d.phone == activePhone))) {

            let content = d.fileUrl || d.file_url || d.message || d.msg || "";
            let msgType = d.message_type || (d.message || d.msg ? "text" : "file");

            appendMessage(content, d.type === "incoming" ? "received" : "sent", msgType);
        }

        const badge = document.getElementById("unread-" + d.lead_id);
        if (badge) {
            if (d.unread_count > 0) {
                badge.innerText = d.unread_count;
                badge.style.display = "inline-block";
            } else {
                badge.style.display = "none";
            }
        }

        const lastMsg = document.getElementById("last-msg-" + d.lead_id);
        if (lastMsg) lastMsg.innerText = d.last_message || "📎 Media";

        const leadDiv = document.querySelector(`#lead-list .lead[data-lead-id='${d.lead_id}']`);
        if (leadDiv) {
            const leadList = document.getElementById("lead-list");
            leadList.prepend(leadDiv);
        }

        if (d.type === "typing" && activePhone && d.phone === activePhone) {
            $("#typing").show();
        }

        if (d.type === "stop_typing") {
            $("#typing").hide();
        }
    };


    $(function() {
        loadLabelFilters();
        const phone = localStorage.getItem('whatsapp_active_phone');
        if (!phone) return;

        let attempts = 0;
        const maxAttempts = 25;

        const timer = setInterval(() => {
            const $lead = $('.lead[data-phone="' + phone + '"]');

            if ($lead.length) {
                console.log('Auto-select lead:', phone);

                $lead[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                $lead.trigger('click');

                localStorage.removeItem('whatsapp_active_phone');
                clearInterval(timer);
            }

            attempts++;
            if (attempts >= maxAttempts) {
                console.warn('Lead not found:', phone);
                localStorage.removeItem('whatsapp_active_phone');
                clearInterval(timer);
            }
        }, 300);
    });

    /* ================= LOAD HISTORY ================= */
    function loadHistory(lead_id) {
        $("#messages").html("Loading...");
        $.post(
            "<?php echo base_url('admin/whatsapp/load_history'); ?>", {
                lead_id: lead_id,
                <?php echo $this->security->get_csrf_token_name(); ?>: $("#csrf_token").val()
            },
            res => {
                $("#messages").html(res.html);
                $("#csrf_token").val(res.csrfHash);
                $("#messages").scrollTop($("#messages")[0].scrollHeight);
            },
            "json"
        );
    }

    /* ================= SEND TEXT ================= */
    function sendTextMessage() {
        const text = $("#msg").val().trim();
        if (!text || !connected || !activePhone) return;

        ws.send(JSON.stringify({
            action: "send",
            agent_id: $("#AGENT_ID").val(),
            channel_id: 1,
            client_id: CLIENT_ID,
            to: activePhone,
            lead_id: activeLeadId,
            msg: text,
            message_type: "text"
        }));

        $("#msg").val("");
    }

    /* ================= SEND FILE ================= */
    function sendFileMessage() {
        if (!fileToUpload || !activePhone) return;

        const fd = new FormData();
        fd.append("file", fileToUpload);
        fd.append("lead_id", activeLeadId);
        fd.append("phone", activePhone);
        fd.append("<?php echo $this->security->get_csrf_token_name(); ?>", $("#csrf_token").val());

        $.ajax({
            url: "<?php echo base_url('admin/whatsapp/send_message'); ?>",
            type: "POST",
            data: fd,
            processData: false,
            contentType: false,
            dataType: "json",
            success: res => {
                $("#csrf_token").val(res.csrfHash);

                ws.send(JSON.stringify({
                    action: "send",
                    agent_id: $("#AGENT_ID").val(),
                    channel_id: 1,
                    client_id: CLIENT_ID,
                    to: activePhone,
                    lead_id: activeLeadId,
                    file_url: res.file_url,
                    message_type: res.file_type
                }));

                bootstrap.Modal.getInstance(document.getElementById("mediaModal")).hide();
                resetFilePreview();
            }
        });
    }

    /* ================= UI EVENTS ================= */
    $("#send").on("click", () => fileToUpload ? sendFileMessage() : sendTextMessage());
    $("#msg").on("keydown", e => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            fileToUpload ? sendFileMessage() : sendTextMessage();
        }
    });
    $("#msg").on("input", () => {
        if (!activePhone) return;
        ws.send(JSON.stringify({
            action: "typing",
            agent_id: $("#AGENT_ID").val(),
            channel_id: 1,
            client_id: CLIENT_ID,
            to: activePhone
        }));
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            ws.send(JSON.stringify({
                action: "stop_typing",
                agent_id: $("#AGENT_ID").val(),
                channel_id: 1,
                client_id: CLIENT_ID,
                to: activePhone
            }));
        }, 1200);
    });

    /* ================= FILE PICK ================= */
    $("#fileInput").on("change", function() {
        fileToUpload = this.files[0];
        if (!fileToUpload) return;

        const url = URL.createObjectURL(fileToUpload);
        let html = "";

        if (fileToUpload.type.startsWith("image")) html = `<img src="${url}" class="img-fluid rounded">`;
        else if (fileToUpload.type.startsWith("video")) html =
            `<video src="${url}" controls class="w-100 rounded"></video>`;
        else if (fileToUpload.type.startsWith("audio")) html = `<audio src="${url}" controls></audio>`;
        else html = `<p><strong>${fileToUpload.name}</strong></p>`;

        $("#mediaPreview").html(html);
        new bootstrap.Modal("#mediaModal").show();
    });

    $("#confirmSendMedia").on("click", sendFileMessage);

    /* ================= HELPERS ================= */
    function resetFilePreview() {
        fileToUpload = null;
        $("#fileInput").val("");
    }


    function appendMessage(content, dir, msgType = "text") {
        let html = "";
        if (msgType === "image") {
            html = `<img src="${content}" class="chat-file">`;
        } else if (msgType === "file") {
            html = `<img src="${content}" class="chat-file">`;
        } else if (msgType === "video") {
            html = `<video src="${content}" controls class="chat-file"></video>`;
        } else if (msgType === "audio") {
            html = `<audio src="${content}" controls></audio>`;
        } else if (msgType === "file") {
            html = `<a href="${content}" target="_blank">📎 Download file</a>`;
        } else {
            html = content;
        }

        $("#messages").append(`<div class="msg ${dir}">${html}</div>`);
        $("#messages").scrollTop($("#messages")[0].scrollHeight);
    }


    function nl2br(str) {
        if (!str) return "";
        return str.replace(/\n/g, "<br>");
    }
    </script>


    <script>
    document.getElementById('leadSearch').addEventListener('input', function() {

        const q = this.value.toLowerCase().trim();

        document.querySelectorAll('.lead').forEach(lead => {

            const name = (lead.dataset.name || '').toLowerCase();
            const phone = (lead.dataset.phone || '').toLowerCase();
            const labels = (lead.dataset.labelNames || '').toLowerCase();

            lead.style.display =
                name.includes(q) ||
                phone.includes(q) ||
                labels.includes(q) ?
                'flex' :
                'none';
        });
    });
    </script>


    <script>
    /* ================= CONTACT CLICK ================= */
    $(document).on("click", ".lead", function() {

        $(".lead").removeClass("active");
        $(this).addClass("active");
        $(this).find(".unread-count").hide();

        activePhone = $(this).data("phone");
        activeLeadId = $(this).data("lead-id");

        // ✅ FIX: ONLY name + phone (NO LABELS)
        const name = $(this).find(".lead-name").text().trim();
        const phone = $(this).find(".lead-phone").text().trim();

        $("#active-lead").text(name + " " + phone);

        $(".footer").show();

        loadHistory(activeLeadId);

        if (typeof ws !== "undefined" && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                action: "mark_read",
                lead_id: activeLeadId
            }));
        }
    });


    /* ================================
       1️⃣ TOGGLE LABEL DROPDOWN
    ================================ */
    function toggleLabelMenu(leadId) {

        const menu = $("#label-menu-" + leadId);
        $(".label-dropdown").hide();

        if (menu.is(":visible")) {
            menu.hide();
            return;
        }

        $.post(
            "<?php echo base_url('admin/whatsapp/lead_labels'); ?>", {
                lead_id: leadId,
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);

                let html = "";

                res.labels.forEach(l => {
                    html += `
                <div class="label-row"
                     onclick="assignLabel(${leadId}, ${l.id})">
                    <span class="check">${l.assigned ? '✅' : '⬜'}</span>
                    <span class="name" style="color:${l.color}">${l.name}</span>
                    <span class="label-delete"
                          onclick="event.stopPropagation(); deleteLabel(${l.id})">✖</span>
                </div>`;
                });

                html += `
                <hr>
                <div class="label-row add"
                     onclick="addNewLabel(${leadId})">
                    ➕ Add Label
                </div>`;

                menu.html(html).show();
            },
            "json"
        );
    }


    /* ================================
       2️⃣ ASSIGN / UNASSIGN LABEL
    ================================ */
    function assignLabel(leadId, labelId) {

        $.post(
            "<?php echo base_url('admin/whatsapp/toggle_label'); ?>", {
                lead_id: leadId,
                label_id: labelId,
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);
                if (!res.status) return;

                let html = "";

                // ✅ IMPORTANT: backend must return ONLY assigned labels
                res.labels.forEach(l => {
                    html += `
                <span class="label"
                      id="lead-label-${leadId}-${l.id}"
                      data-label-id="${l.id}"
                      style="background:${l.color}">
                    ${l.name}
                    <span class="label-x"
                          onclick="deleteLeadLabel(${leadId}, ${l.id})">✖</span>
                </span>`;
                });

                $("#lead-labels-" + leadId)
                    .html(html)
                    .show();

                $("#label-menu-" + leadId).hide();
                loadLabelFilters(); // 🔥 REQUIRED

                alert_float('success', 'Label updated');
            },
            "json"
        );
    }


    /* ================================
       3️⃣ ADD NEW LABEL
    ================================ */
    function addNewLabel(leadId) {

        const name = prompt("Label name");
        if (!name) return;

        $.post(
            "<?php echo base_url('admin/whatsapp/add_label'); ?>", {
                name: name,
                color: "#25d366",
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);

                if (res.status) {
                    alert_float('success', res.message);
                    toggleLabelMenu(leadId);
                }
            },
            "json"
        );
    }


    /* ================================
       4️⃣ DELETE LABEL (GLOBAL)
    ================================ */
    function deleteLabel(labelId) {

        if (!confirm("Delete this label permanently?")) return;

        $.post(
            "<?php echo base_url('admin/whatsapp/delete_label'); ?>", {
                label_id: labelId,
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);

                if (res.status) {
                    alert_float('success', res.message);
                    $(".label-dropdown").hide();
                    $(`[data-label-id="${labelId}"]`).remove();
                    loadLabelFilters();
                }
            },
            "json"
        );
    }


    /* ================================
       5️⃣ DELETE LABEL FROM LEAD
    ================================ */
    function deleteLeadLabel(leadId, labelId) {

        if (!confirm("Remove this label from lead?")) return;

        $.post(
            "<?php echo base_url('admin/whatsapp/deleteLeadLabel'); ?>", {
                lead_id: leadId,
                label_id: labelId,
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);

                if (res.status) {
                    $("#lead-label-" + leadId + "-" + labelId)
                        .fadeOut(150, function() {
                            $(this).remove();
                        });
                    loadLabelFilters();

                    alert_float('success', res.message);
                }
            },
            "json"
        );
    }




    function loadLabelFilters() {
        $.post(
            "<?php echo base_url('admin/whatsapp/get_labels_with_count'); ?>", {
                "<?php echo $this->security->get_csrf_token_name(); ?>": $("#csrf_token").val()
            },
            function(res) {

                $("#csrf_token").val(res.csrfHash);

                let html = "";

                res.labels.forEach(l => {

                    if (l.total == 0) return; // ✅ only assigned labels

                    html += `
                    <div class="wa-label"
                         data-label-id="${l.id}"
                         onclick="filterLeadsByLabel(${l.id}, this)">
                         
                        <span class="wa-label-name">${l.name}</span>
                        <span class="wa-label-count">${l.total}</span>
                    </div>
                `;
                });

                $("#label-filter-list").html(html);
            },
            "json"
        );
    }



    function filterLeadsByLabel(labelId, el) {

        const $el = $(el);

        // 🔁 toggle off = reset
        if ($el.hasClass("active")) {
            $(".wa-label").removeClass("active");
            $(".lead").show();
            return;
        }

        $(".wa-label").removeClass("active");
        $el.addClass("active");

        let anyVisible = false;

        $(".lead").each(function() {

            const labelIdsStr = $(this).attr("data-label-ids");

            if (!labelIdsStr) {
                $(this).hide();
                return;
            }

            const labelIds = labelIdsStr.split(",");

            if (labelIds.includes(labelId.toString())) {
                $(this).show();
                anyVisible = true;
            } else {
                $(this).hide();
            }
        });

        if (!anyVisible) {
            $(".lead").show();
            $(".wa-label").removeClass("active");
        }
    }
    </script>


</body>

</html>