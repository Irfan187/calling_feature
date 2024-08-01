import { createServer } from 'https';
import { readFileSync } from 'fs';
import { server as WebSocketServer } from 'websocket';
const Opus = require('node-opus');
const decoder = new Opus.Decoder(48000, 1); // 48kHz sampling rate, mono channel


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
    const decodedData = decoder.decode(message);
        // Process the decoded audio data or relay it to other clients
        wsServer.clients.forEach(function each(client) {
            console.log('Logging here');
            if (client.readyState === WebSocket.OPEN) {
                console.log('Logging here 333333333333333333333333333');
                client.send(decodedData);

            }
        });
  });

  connection.on('close', function (reasonCode, description) {
    console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
  });

});