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
  console.log((new Date()) + " Server is listening on port 3001");
});


console.log("***CREATING WEBSOCKET SERVER");
var wsServer = new WebSocketServer({
  httpServer: httpsServer,
  autoAcceptConnections: false
});
console.log("***CREATED");

function originIsAllowed(origin) {
  // put logic here to detect whether the specified origin is allowed.
  return true;
}
wsServer.on('request', function (request) {
  if (!originIsAllowed(request.origin)) {
    request.reject();
    console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
    return;
  }

  const connection = request.accept(null, request.origin);
  console.log((new Date()) + ' Connection accepted.');

  connection.on('message', function (event) {
    // if (message.type === 'utf8') {
    //   console.log('Received Message: ' + message.utf8Data);
    //   connection.sendUTF(message.utf8Data);
    // } else if (message.type === 'binary') {
    //   console.log('Received Binary Message of ' + message.binaryData.length + ' bytes');
    //   connection.sendBytes(message.binaryData);
    // }
    if (event.data instanceof ArrayBuffer) {
      console.log('Received binary data:', event.data);
      // Handle ArrayBuffer data
    } else {
      console.log('Received text data:', event.data);
    }
  });

  connection.on('close', function (reasonCode, description) {
    console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
  });

});