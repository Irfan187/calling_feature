import { WebSocketServer } from 'ws';

const PORT = process.env.PORT || 5000;
const wss = new WebSocketServer({ port: PORT });

wss.on('connection', function connection(ws) {
  ws.on('message', function message(data) {
    console.log('received: %s', data);
  });

  ws.send('something');
});