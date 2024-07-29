import { WebSocketServer } from 'ws';

import http from 'http';

const server = http.createServer();
console.log(server);
const wss = new WebSocketServer({ server });

wss.on('connection', function connection(ws) {
  ws.on('message', function message(data) {
    console.log('received: %s', data);
  });

  ws.send('something');
});
server.listen(5000, () => {
  console.log("Server running at port 5000");
});

wss.on("listening", () => {
  console.log("Server running at port 5000 is listening");
});
