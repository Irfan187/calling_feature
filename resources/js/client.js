const ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

async function startAudioCapture() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        return stream;
    } catch (err) {
        if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            console.error('Error capturing audio: No microphone found.');
        } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            console.error('Error capturing audio: Permission denied.');
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            console.error('Error capturing audio: Microphone is already in use.');
        } else {
            console.error('Error capturing audio:', err);
        }
        throw err; // Rethrow the error after logging
    }
}

ws.onerror = (error) => {
    console.log(error,'hdgfdjfdhjdjgfjjfgfg');
};

ws.onopen = (event) => {
    console.log('WebSocket is open now.',event);

    // startAudioCapture().then(stream => {
    //     const audioContext = new AudioContext();
    //     const source = audioContext.createMediaStreamSource(stream);
    //     const processor = audioContext.createScriptProcessor(4096, 1, 1);

    //     source.connect(processor);
    //     processor.connect(audioContext.destination);

    //     processor.onaudioprocess = function(e) {
    //         const audioData = e.inputBuffer.getChannelData(0);
    //         const streamId = 'd5604baa-0d11-4486-9903-90cd211c8c51'; // Replace with actual stream ID
    //         const message = {
    //             stream_id: streamId,
    //             payload: audioData
    //         };
    //         ws.send(JSON.stringify(message));
    //     };
    // });
};

ws.onclose = (event) => {
  console.log('disconnected',event);
};

ws.onmessage = (event) => {
    console.log('hgjdgfggfhgkjfgjkhgkjfhkgjfhkjghfkh',event)
    const data = event.data;
    if (event.event === 'methodEmit') {
        console.log('Method emitted from server:', data);
        // Handle the method emission in your Vue.js application
    }else{
        console.log('other:');
    }
};

export { ws }