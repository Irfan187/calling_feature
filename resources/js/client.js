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
    let eventData = JSON.parse(event.data);
    // Record our audio
    navigator.mediaDevices
    .getUserMedia({ audio: true })
    .then(app);

    const app = function (stream) {
        let mediaRecorder = new MediaRecorder(stream);
        let chunks = [];

        if(eventData.event == 'start'){
            mediaRecorder.start();
            mediaRecorder.ondataavailable = function (e) {
                chunks.push(e.data);
            }
        }

        if(eventData.event == 'stop'){
            mediaRecorder.stop();

            mediaRecorder.onstop = function (e) {
                let blob = new Blob(chunks, {
                    'type': 'audio/ogg; codecs=opus',
                });

                chunks = [];

                var audio = URL.createObjectURL(blob);
                audio.play();
            };
        }
    };
};

export { ws };
