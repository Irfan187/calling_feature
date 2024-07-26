import { WebSocketServer } from 'ws';

import http from 'http';

const server = http.createServer();
const wss = new WebSocketServer({ server });

wss.on('connection', function connection(ws) {
  ws.on('message', function message(data) {
    console.log('received: %s', data);
  });

  ws.send('something');
});