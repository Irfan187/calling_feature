const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

async function startAudioCapture() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        return stream;
    } catch (err) {
        console.error('Error capturing audio:', err);
    }
}
ws.onerror = (error) => {
    console.log(error);
};

ws.onopen = (event) => {
    console.log('WebSocket is open now.');

    startAudioCapture().then(stream => {
        const audioContext = new AudioContext();
        const source = audioContext.createMediaStreamSource(stream);
        const processor = audioContext.createScriptProcessor(4096, 1, 1);

        source.connect(processor);
        processor.connect(audioContext.destination);

        processor.onaudioprocess = function(e) {
            const audioData = e.inputBuffer.getChannelData(0);
            const streamId = 'd5604baa-0d11-4486-9903-90cd211c8c51'; // Replace with actual stream ID
            const message = {
                stream_id: streamId,
                payload: audioData
            };
            ws.send(JSON.stringify(message));
        };
    });
};

ws.onclose = (event) => {
  console.log('disconnected',event);
};

ws.onmessage = (event) => {
    if (message.event === 'error') {
        console.error('Stream Error:', message.payload.detail);
    } else {
        console.log('Received message:', message);
        // Handle incoming messages
    }
};


export { ws }