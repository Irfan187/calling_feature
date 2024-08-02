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
function processPayload(payload) {
  const binaryString = window.atob(payload);
  const len = binaryString.length;
  const bytes = new Uint8Array(len);
  console.log('binaryString : ', binaryString);
  console.log('len : ', len);
  console.log('bytes : ', bytes);
  for (let i = 0; i < len; i++) {
    bytes[i] = binaryString.charCodeAt(i);
  }
  const blob = new Blob([bytes], { type: 'audio/mp3' });
  const url = URL.createObjectURL(blob);

  const audio = new Audio(url);
  audio.play();
}


wsServer.on('request', function (request) {
  if (!originIsAllowed(request.origin)) {
    request.reject();
    console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
    return;
  }

  const connection = request.accept(null, request.origin);
  console.log((new Date()) + ' Connection accepted.');

  connection.on('message', function (data) {
    try {
      console.log(data);
      console.log(JSON.parse(data.utf8Data))
      const parsedData = JSON.parse(data.utf8Data);
      var event = parsedData.event;
      if(event != 'start' && event != 'stop'){
        processPayload(parsedData.media.payload);
      }
      // 
    } catch (error) {
      console.log('Error in Message : ', error);
    }

  });

  connection.on('close', function (reasonCode, description) {
    console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
  });

});