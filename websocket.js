import { WebSocketServer } from 'ws';

const HOST = 'ws://callingfeature.scrumad.com:5000';
const wss = new WebSocketServer({ port: 5000, host: 'ws://callingfeature.scrumad.com' });

wss.on('connection', function connection(ws) {
  ws.on('message', function message(data) {
    console.log('received: %s', data);
  });

  ws.send('something');
});