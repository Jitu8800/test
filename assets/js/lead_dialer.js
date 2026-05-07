// =========================
// CONFIG
// =========================
const configDiv = document.getElementById("dialerConfig");
const CURRENT_STAFF_ID = configDiv
  ? parseInt(configDiv.dataset.staffId || 0)
  : 0;
const BASE_URL = configDiv ? configDiv.dataset.baseUrl || "" : "";
// const SOCKET_URL = "http://localhost:8080";
const SOCKET_URL = "https://salescrm-be.businessbay.io";

const COUNTDOWN_SECONDS = 120;

// =========================
// STATE MACHINE (SINGLE SOURCE OF TRUTH)
// =========================
const CallState = {
  running: false, // dialer running
  paused: false,
  active: false, // call exists
  connected: false, // call answered
  mode: null, // 'manual' | 'auto'
  lead_id: null,
  ref_id: null,
};

// =========================
// UI STATE
// =========================
let callTimerInterval = null;
let countdownTimerId = null;
let callSeconds = 0;
let lastMessage = "";

// =========================
// SAFE LOGGER
// =========================
function dbg() {
  console.log.apply(console, arguments);
}

// =========================
// UI HELPERS (safe for missing DOM)
// =========================
function updateStatuses(msg) {
  lastMessage = msg;
  const box = document.getElementById("callMessageBox");
  if (box) {
    box.innerHTML = msg;
  } else {
    // retry if DOM not ready
    setTimeout(() => updateStatuses(msg), 100);
  }
}

function openLeadModal(leadId) {
  try {
    $("#lead-modal").modal("hide");
  } catch (e) {}
  setTimeout(() => {
    if (typeof init_lead === "function") init_lead(leadId);
  }, 300);
}

function closeLeadModal() {
  try {
    $("#lead-modal").modal("hide");
  } catch (e) {}
}

// =========================
// TIMER
// =========================
function startCallTimer() {
  stopCallTimer();
  callSeconds = 0;

  callTimerInterval = setInterval(() => {
    callSeconds++;
    const m = String(Math.floor(callSeconds / 60)).padStart(2, "0");
    const s = String(callSeconds % 60).padStart(2, "0");
    const box = document.getElementById("callTimerBox");
    if (box) box.innerHTML = `<b>⏱️ ${m}:${s}</b>`;
  }, 1000);
}

function stopCallTimer() {
  if (callTimerInterval) {
    clearInterval(callTimerInterval);
    callTimerInterval = null;
  }
  const box = document.getElementById("callTimerBox");
  if (box) box.innerHTML = "<b>⏱️ 00:00</b>";
}

// =========================
// SOCKET
// =========================
const socket = io(SOCKET_URL, {
  transports: ["websocket", "polling"],
  reconnection: true,
});

socket.on("connect", () => {
  dbg("[socket] connected");
  socket.emit("register_agent", { staff_id: CURRENT_STAFF_ID });
});

// 🔔 CALL ANSWERED (REAL CONNECT)
socket.on("call_answered", (payload) => {
  if (!CallState.active || CallState.connected) return;
  if (String(payload.staff_id) !== String(CURRENT_STAFF_ID)) return;

  CallState.connected = true;
  dbg("[call] connected");
  updateStatuses("✅ Call connected");
  startCallTimer(); // ✅ ONLY HERE
});

// 🔴 CALL ENDED
socket.on("call_ended", (payload) => {
  if (!CallState.active) return;
  if (String(payload.staff_id) !== String(CURRENT_STAFF_ID)) return;

  dbg("[call] ended");

  stopCallTimer();
  closeLeadModal();

  CallState.active = false;
  CallState.connected = false;

  updateStatuses("Call ended");

  if (CallState.mode === "auto" && CallState.running && !CallState.paused) {
    startCountdown(); // ✅ 45s delay
  }

  CallState.mode = null;
});

// =========================
// COUNTDOWN (AUTO ONLY)
// =========================
function startCountdown() {
  let remaining = COUNTDOWN_SECONDS;
  updateStatuses(`Next call in ${remaining}s`);

  countdownTimerId = setInterval(() => {
    if (CallState.paused) return;

    remaining--;
    if (remaining > 0) {
      updateStatuses(`Next call in ${remaining}s`);
    } else {
      clearInterval(countdownTimerId);
      countdownTimerId = null;
      triggerNextCall();
    }
  }, 1000);
}

// =========================
// START CALL VISUALS (manual/auto)
// =========================
function startCallUI(number, leadId) {
  updateStatuses(`📞 Calling ${number}...`);
  stopCallTimer(); // reset
  startCallTimer(); // show 00:00 immediately

  if (leadId) openLeadModal(leadId);
}

// =========================
// AUTO CALL
// =========================
function triggerNextCall() {
  if (!CallState.running || CallState.paused) return;

  fetch(BASE_URL + "api/LeadController/make_call", { method: "POST" })
    .then((r) => r.json())
    .then((resp) => {
      if (resp.status !== "success") {
        if (resp.message) alert_float("danger", resp.message);
        if (resp.status === "done") CallState.running = false;
        return;
      }

      CallState.active = true;
      CallState.connected = false;
      CallState.mode = "auto";
      CallState.lead_id = resp.lead_id;
      CallState.ref_id = resp.ref_id;

      startCallUI(resp.number, resp.lead_id);
    })
    .catch(() => {
      alert_float("danger", "Server connection failed.");
    });
}

// =========================
// MANUAL CALL
// =========================

$(document).on("click", ".make-call", function (e) {
  e.preventDefault();

  if (!USER_DIALER_PHONE || !USER_DIALER_AGENT) {
    alert_float(
      "danger",
      "Dialer is not assigned to your account. Please contact admin.",
    );
    return;
  }

  const phone = $(this).data("phone");
  const leadId = $(this).data("lead-id");
  const assigned = $(this).data("assigned");

  if (!assigned || assigned == 0) {
    alert_float("danger", "This lead is not assigned.");
    return;
  }

  if (!phone) {
    alert_float("danger", "Phone number required.");
    return;
  }

  CallState.active = true;
  CallState.connected = false;
  CallState.mode = "manual";
  CallState.lead_id = leadId;

  $.post(
    BASE_URL + "admin/leads/make_call",
    {
      phone: phone,
      lead_id: leadId,
    },
    function (res) {
      let data = JSON.parse(res);

      if (data.status === "success") {
        startCallUI(phone, leadId);
      } else {
        alert_float("danger", data.message || "Call failed.");
      }
    },
  ).fail(function () {
    alert_float("danger", "Server error. Please try again.");
  });
});

// =========================
// BUTTON SHOW/HIDE HELPER
// =========================
function setDialerButtons(state) {
    document.getElementById('startDialer').style.display  = state === 'idle'    ? 'inline-block' : 'none';
    document.getElementById('pauseDialer').style.display  = state === 'running' ? 'inline-block' : 'none';
    document.getElementById('resumeDialer').style.display = state === 'paused'  ? 'inline-block' : 'none';
    localStorage.setItem('dialer_state_' + CURRENT_STAFF_ID, state);
}

// Restore on page load
(function() {
    var saved = localStorage.getItem('dialer_state_' + CURRENT_STAFF_ID) || 'idle';
    setDialerButtons(saved);
})();

// =========================
// START / PAUSE / RESUME
// =========================
document.getElementById("startDialer")?.addEventListener("click", () => {
    CallState.running = true;
    CallState.paused  = false;
    updateStatuses("Dialer started");
    setDialerButtons('running');   // ← ADD
    triggerNextCall();
});

document.getElementById("pauseDialer")?.addEventListener("click", () => {
    CallState.paused = true;
    updateStatuses("Dialer paused");
    setDialerButtons('paused');    // ← ADD
});

document.getElementById("resumeDialer")?.addEventListener("click", () => {
    CallState.paused  = false;
    CallState.running = false;
    updateStatuses("Dialer resumed");
    setDialerButtons('idle');      // ← ADD — resume goes back to Start
    // triggerNextCall();           // uncomment if resume should auto-call
});
