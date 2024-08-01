import { createServer } from 'https';
import { readFileSync } from 'fs';
import { server as WebSocketServer } from 'websocket';
const Opus = require('node-opus');
const decoder = new Opus.Decoder(48000, 1); // 48kHz sampling rate, mono channel
const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

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
    const data = JSON.parse(message);
        const { stream_id, payload } = data;

        // Process the audio data based on the stream ID
        if (stream_id) {
            console.log(`Received audio data for stream ID ${stream_id}`);
            // Example: Broadcast the audio data to other clients with the same stream ID
            ws.clients.forEach(client => {
                if (client != ws && client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify({ stream_id, payload }));
                }
            });
        } else {
            console.error('Stream ID missing in the message'); 
        }
  });

  connection.on('close', function (reasonCode, description) {
    console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
  });

});