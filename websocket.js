import WebSocket from 'ws';

const ws = new WebSocket('ws://callingfeature.scrumad.com:5000');

ws.on('open', function open() {
  console.log('connected');
  ws.send('Welcome to the WebSocket server!');
  console.log('Welcome to the WebSocket server!');
});

ws.on('close', function close() {
  console.log('disconnected');
});

ws.on('message', function message(data) {
  console.log('received: %s', data);
});
ws.on('error', function error(err) {
  console.error('WebSocket error:', err);
});
ws.on("listening", () => {
  console.log("Server running at port 5000 is listening");
});