const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

// ws.onerror((error) => {
//     console.log(error);
// });

ws.onopen = (event) => {
  console.log('connected');
  ws.send('dsadsfdsfdfs');
};

ws.onclose = (event) => {
  console.log('disconnected');
};

ws.onmessage = (event) => {
  console.log(event);

  ws.send('dtrrtr');
};


export { ws }