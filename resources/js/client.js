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

    if (eventData.event == "start") {
        /* 
            {
                start: {
                    call_control_id:
                        "v3:p9MPzFLcZWaJM8wM5mayhPFbZbVzSjTIw27meKQYUMX_M_vHaSecag",
                    user_id: "66401123-f43a-4b66-8d68-506c0aa4770e",
                    client_state: null,
                    custom_parameters: {},
                    media_format: {
                        channels: 1,
                        encoding: "PCMU",
                        sample_rate: 8000,
                    },
                },
                stream_id: "8ee1719d-51fc-41db-87eb-b808a960a0fe",
                event: "start",
                sequence_number: "1",
            } 
        */
        handleStartEvent(eventData.start);
    } else if (eventData.event == "media") {
        /* 
            {
                stream_id: "8ee1719d-51fc-41db-87eb-b808a960a0fe",
                event: "media",
                media: {
                    timestamp: "3200",
                    chunk: "1",
                    payload:
                        "/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////w==",
                    track: "inbound",
                },
                sequence_number: "2",
            } 
        */
        handleMediaEvent(eventData.media);
    } else if (eventData.event == "stop") {
        /* 
            {
                stop: {
                    call_control_id:
                        "v3:p9MPzFLcZWaJM8wM5mayhPFbZbVzSjTIw27meKQYUMX_M_vHaSecag",
                    user_id: "66401123-f43a-4b66-8d68-506c0aa4770e",
                },
                stream_id: "8ee1719d-51fc-41db-87eb-b808a960a0fe",
                event: "stop",
                sequence_number: "131",
            }
         */
        handleStopEvent(eventData.stop);
    }
};

const audioContext = new (window.AudioContext || window.webkitAudioContext)();
const sampleRate = 8000;

const handleStartEvent = (startData) => {
    console.log(
        "Call started with call_control_id:",
        startData.call_control_id
    );
    document.getElementById("callStatus").innerText = "Call Started";
};

const handleMediaEvent = (mediaData) => {
    const payload = mediaData.payload;
    const pcmuData = base64ToByteArray(payload);
    const pcmData = pcmuToPcm(pcmuData);
    playAudio(pcmData);
};

const base64ToByteArray = (base64) => {
    const binaryString = atob(base64);
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    return bytes;
};

const pcmuToPcm = (pcmuData) => {
    const pcmData = new Float32Array(pcmuData.length);
    for (let i = 0; i < pcmuData.length; i++) {
        pcmData[i] = (pcmuData[i] - 128) / 128;
    }
    return pcmData;
};

const playAudio = (pcmData) => {
    const audioBuffer = audioContext.createBuffer(
        1,
        pcmData.length,
        sampleRate
    );
    audioBuffer.getChannelData(0).set(pcmData);
    const source = audioContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(audioContext.destination);
    source.start(0);
};

const handleStopEvent = (stopData) => {
    console.log("Call stopped with call_control_id:", stopData.call_control_id);
    document.getElementById("callStatus").innerText = "Call Stopped";
};

export { ws };
