import { WebSocketServer } from 'ws';

const PORT = process.env.PORT || 5000;
const wss = new WebSocketServer('ws://callingfeature.scrumad.com:5000');

wss.on('connection', function connection(ws) {
  ws.on('message', function message(data) {
    console.log('received: %s', data);
  });

  ws.send('something');
});