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
function processPayload(payloadBase64, streamId, sequenceNumber) {
  const decodedBuffer = Buffer.from(payloadBase64, 'base64');

  // Save the decoded audio data to a file
  const fileName = `audio_${streamId}_${sequenceNumber}.mp3`; // or .wav, depending on your data
  fs.writeFile(fileName, decodedBuffer, (err) => {
      if (err) {
          console.error('Error saving audio file:', err);
      } else {
          console.log('Audio file saved as:', fileName);
      }
  });
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
        processPayload(parsedData.media.payload, parsedData.stream_id, parsedData.sequence_number);
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