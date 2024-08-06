const ws = new WebSocket("wss://callingfeature.scrumad.com:3001");

ws.onerror = (error) => {
    console.log(error);
};

ws.onopen = (event) => {
    console.log("WebSocket is open now.", event);
};

ws.onclose = (event) => {
    console.log("disconnected", event);
};

ws.onmessage = (event) => {
    const data = event.data;
    console.log(data);
};

export { ws };
