// =========================
// LOAD ENV VARIABLES
// =========================
require('dotenv').config();

// =========================
// IMPORT DEPENDENCIES
// =========================
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const bodyParser = require("body-parser");
const cors = require("cors");

// =========================
// APP & SERVER SETUP
// =========================
const app = express();
const server = http.createServer(app);

// =========================
// SOCKET.IO SETUP
// =========================
const io = new Server(server, {
    cors: { origin: "*", methods: ["GET","POST"] }
});

// =========================
// MIDDLEWARES
// =========================
app.use(cors());
app.use(bodyParser.json());

// =========================
// CONFIG
// =========================
const PORT = process.env.PORT || 3000;
const NOTIFY_SECRET = process.env.NOTIFY_SECRET || "mysecret123";

// =========================
// SOCKET.IO CONNECTION
// =========================
io.on("connection", (socket) => {
    console.log(`[socket] New connection: ${socket.id}`);

    // Register agent to a room based on staff_id
    socket.on("register_agent", (data) => {
        if (data.staff_id) {
            const room = `agent_${data.staff_id}`;
            socket.join(room);
            console.log(`[socket] Agent registered: staff_id=${data.staff_id} in room ${room}`);
            socket.emit("registered", { staff_id: data.staff_id });
        }
    });

    socket.on("disconnect", () => {
        console.log(`[socket] Disconnected: ${socket.id}`);
    });
});

// =========================
// WEBHOOK: PHP POSTS CALL DATA
// ========================= 

app.post("/notify", async (req, res) => {
    try {
        console.log("\n[notify] Headers:", req.headers);
        console.log("[notify] Body:", req.body);

        const secret = req.headers["x-notify-secret"];
        if (secret !== NOTIFY_SECRET) {
            console.log("[notify] ❌ Invalid secret");
            return res.status(403).json({ status: "forbidden" });
        }

        const payload = req.body;
        if (!payload || !payload.staff_id) {
            console.log("[notify] ❌ Invalid payload");
            return res.status(400).json({ status: "invalid" });
        }

        const staffId = String(payload.staff_id);
        const room = `agent_${staffId}`; // ✅ FIX HERE

        const sockets = await io.in(room).allSockets();
        console.log(`[notify] Connected sockets in ${room}:`, sockets.size);

        io.to(room).emit("call_ended", payload);

        console.log(
            `[notify] ✅ EMIT call_ended to room=${room}`,
            payload
        );

        res.json({ status: "ok" });

    } catch (err) {
        console.error("[notify] ❌ Error:", err);
        res.status(500).json({ status: "error" });
    }
});



// =========================
// START SERVER
// =========================
server.listen(PORT, () => {
    console.log(`Socket.IO server running on port ${PORT}`);
});
