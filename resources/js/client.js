// import WebSocket from 'ws';

const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

ws.onerror((ws,error) => {
    console.log(error);
});

ws.onopen((ws, event) => {
  console.log('connected');
  ws.send('dsadsfdsfdfs');
});

ws.onclose((ws, event) => {
  console.log('disconnected');
});

ws.onmessage((ws, event) => {
  console.log(event);

  ws.send('dtrrtr');
});


export { ws }