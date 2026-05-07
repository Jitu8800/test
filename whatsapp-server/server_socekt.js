const { Client, LocalAuth, MessageMedia } = require("whatsapp-web.js");
const qrcode    = require("qrcode");
const WebSocket = require("ws");
const http      = require("http");
const fs        = require("fs");
const path      = require("path");
const mysql     = require("mysql2");
const { parsePhoneNumberFromString } = require("libphonenumber-js");
require("dotenv").config();

/* ═══════════════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════════════ */
const PORT       = process.env.PORT       || 5001;
const WS_PORT    = process.env.WS_PORT    || 8080;
const UPLOAD_DIR = process.env.UPLOAD_DIR || "./uploads";

/* ═══════════════════════════════════════════════════════════════
   MYSQL POOL
════════════════════════════════════════════════════════════════ */
const db = mysql.createPool({
  host                 : process.env.DB_HOST,
  user                 : process.env.DB_USER,
  password             : process.env.DB_PASSWORD,
  database             : process.env.DB_NAME,
  port                 : process.env.DB_PORT || 3306,
  waitForConnections   : true,
  connectionLimit      : 10,
  enableKeepAlive      : true,
  keepAliveInitialDelay: 30000,
});

setInterval(() => {
  db.query("SELECT 1", (err) => {
    if (err) console.warn("⚠️  DB keepalive failed:", err.message);
  });
}, 25000);

/* ═══════════════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════════════ */
function getISTDateTime() {
  const d = new Date(Date.now() + 5.5 * 60 * 60 * 1000);
  return d.toISOString().replace("T", " ").split(".")[0];
}

function delay(ms) {
  return new Promise((res) => setTimeout(res, ms));
}

async function getRealPhone(msg) {
  try {
    const chat = await msg.getChat();
    if (chat?.name) {
      const n = chat.name.replace(/\D/g, "");
      if (n.length >= 10) return n;
    }
    if (msg?._data?.notifyName) {
      const n = msg._data.notifyName.replace(/\D/g, "");
      if (n.length >= 10) return n;
    }
  } catch {}
  return msg.from.split("@")[0];
}

/* ═══════════════════════════════════════════════════════════════
   STATE
════════════════════════════════════════════════════════════════ */
const sockets        = {};
let waClient         = null;
let waStatus         = "disconnected";
let creatingClient   = false;
let reconnectAttempt = 0;
let waReady          = false;
let lastQrDataUrl    = null;
let initTimeout      = null;
let qrExpireTimeout  = null;

function nextDelay() {
  const ms = Math.min(5000 * Math.pow(2, reconnectAttempt), 60000);
  reconnectAttempt++;
  return ms;
}

/* ═══════════════════════════════════════════════════════════════
   HTTP
════════════════════════════════════════════════════════════════ */
http.createServer((req, res) => {
  res.writeHead(200, { "Content-Type": "application/json" });
  res.end(JSON.stringify({
    status : "ok",
    wa     : waStatus,
    ready  : waReady,
    agents : Object.keys(sockets).length,
    queue  : queueLength,
  }));
}).listen(PORT, () => console.log(`🌐 HTTP on :${PORT}`));

/* ═══════════════════════════════════════════════════════════════
   BROADCAST
════════════════════════════════════════════════════════════════ */
function broadcast(agentIds, payload) {
  if (!agentIds && agentIds !== 0) return;
  if (!Array.isArray(agentIds)) agentIds = [String(agentIds)];
  else agentIds = agentIds.map(String);

  const msg = JSON.stringify(payload);

  agentIds.forEach((id) => {
    const set = sockets[id];
    if (!set || set.size === 0) return;
    for (const ws of set) {
      if (ws.readyState === WebSocket.OPEN) {
        try { ws.send(msg); } catch { ws.terminate(); set.delete(ws); }
      } else {
        ws.terminate();
        set.delete(ws);
      }
    }
    if (set.size === 0) delete sockets[id];
  });
}

function broadcastAll(payload) {
  broadcast(Object.keys(sockets), payload);
}

/* ═══════════════════════════════════════════════════════════════
   DESTROY CRASHED CLIENT
════════════════════════════════════════════════════════════════ */
async function destroyClient(reason) {
  console.warn(`🔴 Destroying client — reason: ${reason}`);

  clearTimeout(initTimeout);
  clearTimeout(qrExpireTimeout);
  initTimeout     = null;
  qrExpireTimeout = null;
  lastQrDataUrl   = null;

  waReady        = false;
  waStatus       = "disconnected";
  creatingClient = false;

  broadcastAll({ type: "status", data: "disconnected" });

  if (waClient) {
    const old = waClient;
    waClient = null;
    try { await old.destroy(); } catch {}
  }

  scheduleReconnect();
}

/* ═══════════════════════════════════════════════════════════════
   WHATSAPP CLIENT
════════════════════════════════════════════════════════════════ */
function createWhatsAppClient() {
  if (creatingClient || waClient) return;
  creatingClient = true;
  waReady        = false;

  console.log(`🔄 Creating WhatsApp client (attempt ${reconnectAttempt + 1})...`);
 
  initTimeout = setTimeout(() => {
    if (!waReady) {
      console.warn("⏱️  Client init timed out — forcing reconnect");
      creatingClient = false;
      destroyClient("init timeout");
    }
  }, 90_000);

  let client;
  try {
    client = new Client({
      authStrategy: new LocalAuth({
        clientId: "crm-main-number",
        dataPath : "./whatsapp-session",
      }),
      puppeteer: {
        headless: true,
        executablePath: '/usr/bin/chromium-browser',
        // executablePath: "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
        args: [
          "--no-sandbox",
          "--disable-setuid-sandbox",
          "--disable-dev-shm-usage",
          "--disable-accelerated-2d-canvas",
          "--no-first-run",
          "--disable-gpu",
          "--no-zygote",
        ],
        timeout: 60000,
      },
    });
  } catch (err) {
    clearTimeout(initTimeout);
    creatingClient = false;
    console.error("❌ new Client() threw:", err.message);
    scheduleReconnect();
    return;
  }


  waClient = client;
  waStatus = "connecting";

  // ── QR ──────────────────────────────────────────────────────
  client.on("qr", async (qr) => {
    console.log("📱 QR code generated — waiting for scan");
    waReady  = false;
    waStatus = "qr";

    clearTimeout(qrExpireTimeout);
    qrExpireTimeout = setTimeout(() => {
      if (waStatus === "qr") {
        console.warn("⏱️  QR code expired");
        lastQrDataUrl = null;
        broadcastAll({ type: "status", data: "qr_expired" });
      }
    }, 20_000);

    try {
      const img = await qrcode.toDataURL(qr);

      lastQrDataUrl = img;

      broadcastAll({ type: "qr",    data: img   });
      broadcastAll({ type: "status", data: "qr" });
    } catch (err) {
      console.error("QR toDataURL error:", err.message);
    }
  });

  client.on("loading_screen", (percent, message) => {
    console.log(`⏳ Loading: ${percent}% — ${message}`);
    broadcastAll({ type: "status", data: "loading" });
  });

  client.on("authenticated", () => {
    waStatus = "authenticated";
    // FIX: clear QR expiry — no longer needed once authenticated
    clearTimeout(qrExpireTimeout);
    lastQrDataUrl = null;
    broadcastAll({ type: "status", data: waStatus });
    console.log("🔐 WhatsApp authenticated");
  });

  client.on("auth_failure", (msg) => {
    console.error("❌ Auth failure:", msg);
    clearTimeout(initTimeout);
    try {
      const dir = "./whatsapp-session";
      if (fs.existsSync(dir)) {
        fs.rmSync(dir, { recursive: true, force: true });
        console.log("🗑️  Session wiped");
      }
    } catch {}
    destroyClient("auth_failure");
  });

  client.on("ready", () => {
    // FIX: clear the init-deadlock guard — we made it to ready successfully
    clearTimeout(initTimeout);
    initTimeout = null;

    waStatus         = "connected";
    waReady          = true;
    creatingClient   = false;
    reconnectAttempt = 0;

    broadcastAll({ type: "status", data: waStatus });
    console.log("✅ WhatsApp connected and READY");

    attachPageListeners();
  });

  client.on("disconnected", (reason) => {
    console.warn("⚠️  WhatsApp disconnected:", reason);
    destroyClient(`disconnected: ${reason}`);
  });

  client.on("message", handleIncomingMessage);

  client.initialize().catch((err) => {
    console.error("❌ initialize() failed:", err.message);
    clearTimeout(initTimeout);
    waClient       = null;
    creatingClient = false;
    waReady        = false;
    scheduleReconnect();
  });
}


function attachPageListeners() {
  if (!waClient?.pupPage) {
    console.warn("⚠️  attachPageListeners: pupPage not available");
    return;
  }

  waClient.pupPage.on("error", (err) => {
    console.error("💥 Puppeteer page error:", err.message);
    destroyClient("puppeteer page error");
  });

  
  waClient.pupPage.on("close", () => {
    if (waReady) {
      console.warn("⚠️  Puppeteer page closed unexpectedly");
      destroyClient("puppeteer page closed");
    }
  });

  console.log("🔗 Puppeteer page listeners attached");
}

function scheduleReconnect() {
  const ms = nextDelay();
  console.log(`⏳ Reconnecting in ${ms / 1000}s...`);
  setTimeout(createWhatsAppClient, ms);
}

/* ═══════════════════════════════════════════════════════════════
   INCOMING MESSAGE
════════════════════════════════════════════════════════════════ */
async function handleIncomingMessage(msg) {
  if (msg.from === "status@broadcast") return;

  try {
    const rawPhone = await getRealPhone(msg);
    if (!rawPhone) return;

    const fullPhone     = rawPhone.replace(/\D/g, "");
    const nationalPhone = fullPhone.slice(-10);

    console.log(`📩 Incoming from: ${fullPhone}`);

    let lead_id  = null;
    let agent_id = null;

    const [lead] = await db.promise().query(
      `SELECT id, assigned
       FROM tblleads
       WHERE RIGHT(REPLACE(phonenumber, ' ', ''), 10) = ?
       ORDER BY id DESC LIMIT 1`,
      [nationalPhone]
    );

    if (lead.length) {
      lead_id  = lead[0].id;
      agent_id = lead[0].assigned;
      console.log(`✅ Matched lead_id=${lead_id} agent_id=${agent_id}`);
    } else {
      console.log(`⚠️  No lead found for phone: ${fullPhone}`);
    }

    let messageType = "text";
    let fileId      = null;
    let fileUrl     = null;

    if (msg.hasMedia) {
      try {
        const media   = await msg.downloadMedia();
        const mimeExt = (media.mimetype.split("/")[1] || "bin").split(";")[0];
        const filename = `${Date.now()}.${mimeExt}`;

        fs.mkdirSync(UPLOAD_DIR, { recursive: true });
        fs.writeFileSync(
          path.join(UPLOAD_DIR, filename),
          Buffer.from(media.data, "base64")
        );

        const [fileResult] = await db.promise().query(
          "INSERT INTO tblwhatsapp_files SET ?",
          {
            stored_name: filename,
            mime_type  : media.mimetype,
            file_size  : media.data.length,
            created_at : getISTDateTime(),
          }
        );

        fileId      = fileResult.insertId;
        fileUrl     = `${process.env.BASE_URL}/${filename}`;
        messageType = media.mimetype.split("/")[0];

        await db.promise().query(
          "DELETE FROM tblwhatsapp_chats WHERE (message IS NULL OR message = '') AND message_type = 'file'"
        );

        console.log(`📎 Media saved: ${filename}`);
      } catch (mediaErr) {
        console.error("❌ Media download failed:", mediaErr.message);
      }
    }

    await db.promise().query(
      `INSERT INTO tblwhatsapp_chats
       (channel_id, agent_id, lead_id, phone, message, message_type, file_id, direction, status, created_at)
       VALUES (1, ?, ?, ?, ?, ?, ?, 'received', 'delivered', ?)`,
      [
        agent_id  || null,
        lead_id   || null,
        fullPhone,
        msg.body  || null,
        messageType,
        fileId    || null,
        getISTDateTime(),
      ]
    );

    console.log(`💾 Chat saved — lead_id=${lead_id} phone=${fullPhone} type=${messageType}`);

    if (lead.length && agent_id) {
      const agentStr = String(agent_id);

      const [[unread]] = await db.promise().query(
        `SELECT COUNT(*) AS unread_count
         FROM tblwhatsapp_chats
         WHERE lead_id = ? AND agent_id = ? AND direction = 'received' AND is_read = 0`,
        [lead_id, agent_id]
      );

      const [[totalUnread]] = await db.promise().query(
        `SELECT COUNT(*) AS total
         FROM tblwhatsapp_chats uc
         INNER JOIN tblleads l ON l.id = uc.lead_id
         WHERE uc.agent_id = ? AND l.assigned = ? AND uc.direction = 'received' AND uc.is_read = 0`,
        [agent_id, agent_id]
      );

      broadcast(agentStr, {
        type             : "incoming",
        lead_id,
        phone            : fullPhone,
        message          : msg.body || null,
        fileUrl          : fileUrl  || null,
        message_type     : messageType,
        direction        : "received",
        unread_count     : unread.unread_count,
        dashboard_unread : totalUnread.total,
        last_message     : msg.body || "📎 Media",
        last_message_time: getISTDateTime(),
      });

      console.log(`📡 Broadcast to agent ${agentStr}: unread=${unread.unread_count}`);
    }

  } catch (err) {
    console.error("❌ handleIncomingMessage error:", err.message, err.stack);
  }
}

/* ═══════════════════════════════════════════════════════════════
   SEND QUEUE
════════════════════════════════════════════════════════════════ */
let sendQueue   = Promise.resolve();
let queueLength = 0;
const QUEUE_MAX = 100;

function withTimeout(promise, ms) {
  return Promise.race([
    promise,
    new Promise((_, rej) => setTimeout(() => rej(new Error("Send timeout")), ms)),
  ]);
}

function enqueueSend(fn) {
  if (queueLength >= QUEUE_MAX) {
    console.warn("⚠️  Send queue full — dropping message");
    return;
  }
  queueLength++;
  sendQueue = sendQueue
    .then(() => withTimeout(fn(), 30000))
    .catch((err) => console.error("Send queue error:", err.message))
    .finally(() => queueLength--);
}

/* ═══════════════════════════════════════════════════════════════
   WEBSOCKET SERVER
════════════════════════════════════════════════════════════════ */
const wss = new WebSocket.Server({ port: WS_PORT });
console.log("🔌 WebSocket running on port", WS_PORT);

wss.on("connection", (ws) => {
  let agentId = null;

  ws.isAlive = true;
  ws.on("pong", () => { ws.isAlive = true; });

  ws.on("message", async (raw) => {
    let data;
    try { data = JSON.parse(raw); } catch { return; }

    /* ── INIT ─────────────────────────────────────────────────── */
    if (data.action === "init") {
      agentId = String(data.agent_id);
      sockets[agentId] = sockets[agentId] || new Set();
      sockets[agentId].add(ws);

      console.log(`👤 Agent ${agentId} connected (${sockets[agentId].size} socket(s))`);

      // Tell agent current WhatsApp status
      ws.send(JSON.stringify({ type: "status", data: waStatus }));

      // FIX: re-send cached QR to newly joined agent so they don't miss it
      if (waStatus === "qr" && lastQrDataUrl) {
        ws.send(JSON.stringify({ type: "qr", data: lastQrDataUrl }));
      }
      return;
    }

    /* ── MARK READ ────────────────────────────────────────────── */
    if (data.action === "mark_read") {
      const leadId = data.lead_id;
      if (!leadId) return;
      try {
        await db.promise().query(
          `UPDATE tblwhatsapp_chats
           SET is_read = 1
           WHERE lead_id = ? AND direction = 'received' AND is_read = 0`,
          [leadId]
        );
        if (agentId) {
          broadcast(agentId, { type: "update_unread", lead_id: leadId, unread_count: 0 });
        }
      } catch (err) {
        console.error("mark_read error:", err.message);
      }
      return;
    }

    /* ── SEND MESSAGE ─────────────────────────────────────────── */
    if (data.action === "send") {
      if (!agentId) {
        console.warn("⚠️  Send blocked: agent not initialized");
        return;
      }

      if (!waClient || !waReady || waStatus !== "connected") {
        ws.send(JSON.stringify({
          type   : "error",
          message: waStatus === "qr"
            ? "WhatsApp is not connected. Please scan the QR code first."
            : `WhatsApp is ${waStatus}. Please wait for connection.`,
        }));
        console.warn(`⛔ Send blocked — waReady=${waReady} waStatus=${waStatus}`);
        return;
      }

      const phone = String(data.to || "").replace(/\D/g, "");
      if (!phone) {
        ws.send(JSON.stringify({ type: "error", message: "Invalid phone number." }));
        return;
      }

      let chatId;
      try {
        chatId = await waClient.getNumberId(phone);
      } catch (err) {
        console.error("getNumberId error:", err.message);
        if (err.message && err.message.includes("detached Frame")) {
          destroyClient("detached frame on getNumberId");
          ws.send(JSON.stringify({
            type   : "error",
            message: "WhatsApp connection lost. Reconnecting automatically. Please try again in a moment.",
          }));
        } else {
          ws.send(JSON.stringify({ type: "error", message: "Failed to check number. Please try again." }));
        }
        return;
      }

      if (!chatId || !chatId._serialized) {
        ws.send(JSON.stringify({ type: "error", message: "Number not found on WhatsApp." }));
        return;
      }

      enqueueSend(async () => {
        try {
          const chat = await waClient.getChatById(chatId._serialized).catch(() => null);
          if (!chat) return;

          if (data.msg) {
            await delay(2000 + Math.random() * 3000);
            await chat.sendMessage(data.msg, { sendSeen: false });
            console.log(`✉️  Sent text to ${phone}`);
          }

          if (data.file_url) {
            const filePath = path.join(UPLOAD_DIR, path.basename(data.file_url));
            if (fs.existsSync(filePath)) {
              await delay(1000 + Math.random() * 2000);
              const media = MessageMedia.fromFilePath(filePath);
              await chat.sendMessage(media, { sendSeen: false });
              console.log(`📎 Sent media to ${phone}`);
            } else {
              console.warn("⚠️  File not found:", filePath);
            }
          }
        } catch (err) {
          if (err.message && err.message.includes("detached Frame")) {
            destroyClient("detached frame in send queue");
          } else {
            console.warn("WhatsApp send error (non-fatal):", err.message);
          }
        }
      });

      // Save to DB
      try {
        await db.promise().query(
          `INSERT INTO tblwhatsapp_chats
           (channel_id, agent_id, lead_id, phone, message, message_type, direction, status, is_read, created_at)
           VALUES (1, ?, ?, ?, ?, ?, 'sent', 'sent', 1, ?)`,
          [
            agentId,
            data.lead_id    || null,
            phone,
            data.msg        || null,
            data.message_type || (data.msg ? "text" : "file"),
            getISTDateTime(),
          ]
        );
        console.log(`💾 Sent message saved — agent=${agentId} phone=${phone}`);
      } catch (err) {
        console.error("DB insert sent msg failed:", err.message);
      }

      broadcast(agentId, {
        type     : "sent",
        lead_id  : data.lead_id,
        phone,
        msg      : data.msg || null,
        fileUrl  : data.file_url || null,
        direction: "sent",
      });
    }
  });

  ws.on("close", () => {
    if (agentId && sockets[agentId]) {
      sockets[agentId].delete(ws);
      if (sockets[agentId].size === 0) delete sockets[agentId];
      console.log(`👤 Agent ${agentId} disconnected`);
    }
  });

  ws.on("error", (err) => {
    console.warn(`WS error (agent ${agentId}):`, err.message);
    try { ws.terminate(); } catch {}
  });
});

/* ═══════════════════════════════════════════════════════════════
   HEARTBEAT
════════════════════════════════════════════════════════════════ */
setInterval(() => {
  let dead = 0;
  wss.clients.forEach((ws) => {
    if (!ws.isAlive) { ws.terminate(); dead++; return; }
    ws.isAlive = false;
    ws.ping();
  });
  if (dead > 0) console.log(`🧹 Heartbeat removed ${dead} dead connection(s)`);
}, 30000);

/* ═══════════════════════════════════════════════════════════════
   GRACEFUL SHUTDOWN
════════════════════════════════════════════════════════════════ */
async function shutdown(signal) {
  console.log(`\n📴 ${signal} — shutting down...`);
  clearTimeout(initTimeout);
  clearTimeout(qrExpireTimeout);
  for (const set of Object.values(sockets)) {
    for (const ws of set) { try { ws.close(); } catch {} }
  }
  if (waClient) { try { await waClient.destroy(); } catch {} }
  db.end(() => { console.log("✅ DB closed"); process.exit(0); });
  setTimeout(() => process.exit(1), 10000);
}

process.on("SIGTERM", () => shutdown("SIGTERM"));
process.on("SIGINT",  () => shutdown("SIGINT"));

/* ═══════════════════════════════════════════════════════════════
   GLOBAL ERROR HANDLERS
════════════════════════════════════════════════════════════════ */
process.on("uncaughtException", (err) => {
  console.error("💥 uncaughtException:", err.message);
  if (err.message && err.message.includes("detached Frame")) {
    destroyClient("uncaughtException detached frame");
  }
});

process.on("unhandledRejection", (reason) => {
  const msg = reason?.message || String(reason);
  console.error("💥 unhandledRejection:", msg);
  if (msg.includes("detached Frame")) {
    destroyClient("unhandledRejection detached frame");
  }
});

/* ═══════════════════════════════════════════════════════════════
   START
════════════════════════════════════════════════════════════════ */
createWhatsAppClient();

console.log("🚀 WhatsApp CRM server started");
