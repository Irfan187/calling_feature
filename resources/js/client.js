import WebSocket from 'ws';

const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

ws.on('error', console.error);

ws.on('open', function open() {
  console.log('connected');
  ws.send('dsadsfdsfdfs');
});

ws.on('close', function close() {
  console.log('disconnected');
});

ws.on('message', function message(data) {
  console.log(data);

  ws.send('dtrrtr');
});


export { ws }