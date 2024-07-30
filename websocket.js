// import https from "https";
// import fs from "fs";
// import { WebSocketServer } from "ws";

// const privateKey = fs.readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.key", "utf8");
// const certificate = fs.readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.crt", "utf8");
// const credentials = { key: privateKey, cert: certificate };

// const httpsServer = https.createServer(credentials);
// const wss = new WebSocketServer({ server: httpsServer, clientTracking: true, });

// wss.on("connection", (ws) => {
//     ws.on("message", (message) => {
//         console.log('Received message:', message);
//     });

//     ws.on("open", (message) => {
//         console.log('WebSocket open',message);
//     });

//     ws.on("close", (code, reason) => {
//         console.log(`WebSocket closed: code=${code}, reason=${reason}`);
//     });

//     ws.send("Welcome to the WebSocket server!");
// });

// httpsServer.listen(3001, () => {
//     console.log("Server running at port 3001");
// });

// wss.on("listening", () => {
//     console.log("Server running at port 3001 is listening");
// });
import { createServer } from 'https';
import { readFileSync } from 'fs';
import { server as WebSocketServer } from 'websocket';

var httpsOptions = {
  key: readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.key"),
  cert: readFileSync("/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.crt")
};


var httpsServer = createServer(httpsOptions, function (request, response) {
  console.log((new Date()) + " Received request for " + request.url);
  // response.writeHead(404);
  response.end();
});

httpsServer.listen(3001, function () {
  console.log((new Date()) + " Server is listening on port 6502");
});


console.log("***CREATING WEBSOCKET SERVER");
var wsServer = new WebSocketServer({
  httpServer: httpsServer,
  autoAcceptConnections: false
});
console.log("***CREATED");

wsServer.on('request', function (request) {
  console.log(request);
  console.log("Handling request from " + request.origin);

  var connection = request.accept("json", request.origin);
  connection.on('message', function (message) {
    console.log("***MESSAGE", message);
  });
});