import WebSocket from 'ws';

const ws = new WebSocket('ws://callingfeature.scrumad.com:5000');

ws.on('open', function open() {
  console.log('connected');
  ws.send(Date.now());
});

ws.on('close', function close() {
  console.log('disconnected');
});

ws.on('message', function message(data) {
  console.log('received: %s', data);
});