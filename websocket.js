// import WebSocket from 'ws';

// const ws = new WebSocket('ws://callingfeature.scrumad.com:5000');

// ws.on('open', function open() {
//   console.log('connected');
//   ws.send('Welcome to the WebSocket server!');
//   console.log('Welcome to the WebSocket server!');
// });

// ws.on('close', function close() {
//   console.log('disconnected');
// });

// ws.on('message', function message(data) {
//   console.log('received: %s', data);
// });
// ws.on('error', function error(err) {
//   console.error('WebSocket error:', err);
// });
// ws.on("listening", () => {
//   console.log("Server running at port 5000 is listening");
// });
import https from "https";
import fs from "fs";
import { WebSocketServer } from "ws";

const privateKey = fs.readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.key", "utf8");
const certificate = fs.readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.crt", "utf8");
const credentials = { key: privateKey, cert: certificate };

const httpsServer = https.createServer(credentials);
const wss = new WebSocketServer({ server: httpsServer, clientTracking: true, });

wss.on("connection", (ws) => {
    ws.on("message", (message) => {
        console.log('Received message:', message);
    });

    ws.on("open", (message) => {
        console.log('WebSocket open',message);
    });

    ws.on("close", (code, reason) => {
        console.log(`WebSocket closed: code=${code}, reason=${reason}`);
    });

    ws.send("Welcome to the WebSocket server!");
});

httpsServer.listen(5000, () => {
    console.log("Server running at port 5000");
});

wss.on("listening", () => {
    console.log("Server running at port 5000 is listening");
});