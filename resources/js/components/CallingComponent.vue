<template>
    <div class="container mt-5">
        <h1>Make a Call</h1>
        <div class="mb-3">
            <label for="to" class="form-label">To</label>
            <input type="text" class="form-control" v-model="to" required>
        </div>
        <div class="mb-3">
            <label for="from" class="form-label">From</label>
            <input type="text" class="form-control" v-model="from" required>
        </div>

        <button type="button" class="btn btn-primary" @click="makeCall">Make Call</button>
        <br><br>
        <button type="button" class="btn btn-primary" @click="startCallRecording">start Call Recording</button>
        <br><br>
        <button type="button" class="btn btn-primary" @click="endCallRecording">end Call Recording</button>
        <br><br>
        <button type="button" class="btn btn-primary" @click="answerCall">Answer a call</button>
        <div class="">
            <h2 class="text-center">Conference Call</h2>
            <button type="button" class="btn btn-primary" @click="createConference">Create Conference</button><br><br>
            <button v-show="conference_created" @click="joinConference">Join Conference</button>
        </div>

        <div class="my-3">
            <div>{{ callStatus }}</div>
        </div>
    </div>

</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { MediaRecorder, register } from 'extendable-media-recorder';
import { connect } from 'extendable-media-recorder-wav-encoder';

const to = ref('+17274257260');
const from = ref('+16265401233');
const callStatus = ref('No Active Call');
const call_control_id = ref('');
let mediaRecorder;
let audioChunks = [];
let ws = null;
let audioContext = null;
let recordingInterval;
let audioEncoder = new Worker(new URL('../audioEncoder.js', import.meta.url), { type: 'module' });

const conference_created = ref(false);
const conference_id = ref();

let sequenceNumber = 0;
let timestamp = 0;

const createRTPPacket = (payload) => {
    const HEADER_SIZE = 12;
    const rtpPacket = new Uint8Array(HEADER_SIZE + payload.length);

    const version = 2;
    const padding = 0;
    const extension = 0;
    const csrcCount = 0;
    const marker = 0;
    const payloadType = 0;
    const ssrc = 12345;

    rtpPacket[0] = (version << 6) | (padding << 5) | (extension << 4) | csrcCount;

    rtpPacket[1] = (marker << 7) | payloadType;

    rtpPacket[2] = (sequenceNumber >> 8) & 0xff;
    rtpPacket[3] = sequenceNumber & 0xff;
    sequenceNumber = (sequenceNumber + 1) % 65536;

    rtpPacket[4] = (timestamp >> 24) & 0xff;
    rtpPacket[5] = (timestamp >> 16) & 0xff;
    rtpPacket[6] = (timestamp >> 8) & 0xff;
    rtpPacket[7] = timestamp & 0xff;
    timestamp += 160;

    rtpPacket[8] = (ssrc >> 24) & 0xff;
    rtpPacket[9] = (ssrc >> 16) & 0xff;
    rtpPacket[10] = (ssrc >> 8) & 0xff;
    rtpPacket[11] = ssrc & 0xff;

    rtpPacket.set(payload, HEADER_SIZE);

    return rtpPacket;
};

const encodeRTPToBase64 = (rtpPacket) => {
    return btoa(String.fromCharCode(...rtpPacket));
};

audioEncoder.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === 'processed') {
        if (ws && ws.readyState === WebSocket.OPEN) {
            const rtpPacket = createRTPPacket(data);
            const base64Payload = encodeRTPToBase64(rtpPacket);
            let payload = {
                "event": "media",
                "media": {
                    "payload": base64Payload
                }
            };
            ws.send(JSON.stringify(payload));
        }
    }
};

const makeCall = async () => {
    const data = {
        to: to.value,
        from: from.value,
    };
    try {
        await axios.post('/api/make-call', data);
        initializeWebSocketAndAudio();
    } catch (error) {
        console.error('Error making call:', error);
    }
};

const startCallRecording = async () => {
    console.log(call_control_id.value);
    const data = {
        to: to.value,
        from: from.value,
        call_control_id: call_control_id.value
    };
    try {
        await axios.post('/api/start-call-recording', data);
    } catch (error) {
        console.error('Error making call:', error);
    }
};

const endCallRecording = async () => {
    const data = {
        to: to.value,
        from: from.value,
        call_control_id: call_control_id.value
    };
    try {
        await axios.post('/api/end-call-recording', data);
    } catch (error) {
        console.error('Error making call:', error);
    }
};

const answerCall = async () => {
    const data = {
        to: to.value,
        from: from.value,
        call_control_id: call_control_id.value
    };
    try {
        await axios.post('/api/answer-call', data);
    } catch (error) {
        console.error('Error making call:', error);
    }
};


const createConference = async () => {
    const data = {
        call_control_id: call_control_id.value
    };
    try {
        await axios.post('/api/conference-create', data)
            .then(async (response) => {
                
                conference_id.value = response.data;
                conference_created.value = true;
            })
            .catch(async (error) => {
                
            });;

    } catch (error) {
        console.error('Error making call:', error);
    }
}

const joinConference = async () => {
    const data = {
        call_control_id: call_control_id.value,
        conference_id: conference_id.value
    };
    try {
        await axios.post('/api/join/conference', data)
            .then(async (response) => {
                console.log(response);
            })
            .catch(async (error) => {
                
            });

    } catch (error) {
        console.error('Error making call:', error);
    }
}


const initializeWebSocketAndAudio = () => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioContext.state === 'suspended') {
        audioContext.resume();
    }

    ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    ws.onopen = (event) => {
        console.log('WebSocket is open now.', event);
    };

    ws.onclose = (event) => {
        console.log('WebSocket disconnected', event);
    };

    ws.onmessage = (event) => {
        try {
            const eventData = JSON.parse(event.data);
            if (eventData.event === "start") {
                handleStartEvent(eventData.start);
            } else if (eventData.event === "media") {
                handleMediaEvent(eventData.media);
            } else if (eventData.event === "stop") {
                handleStopEvent(eventData.stop);
            }
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    };
};

const handleStartEvent = async (startData) => {
    console.log('Call started with call_control_id:', startData.call_control_id);
    call_control_id.value = startData.call_control_id;
    callStatus.value = 'Call Started';
    startRecording();
};

const handleMediaEvent = async (mediaData) => {
    const payload = mediaData.payload;
    try {
        await playAudio(payload);
    } catch (error) {
        console.error(error);
    }
};

const playAudio = async (base64Data) => {
    return new Promise((resolve, reject) => {
        try {
            // Step 1: Decode base64 data to binary data
            const binaryString = atob(base64Data);
            const len = binaryString.length;
            const arrayBuffer = new ArrayBuffer(len);
            const uint8Array = new Uint8Array(arrayBuffer);

            for (let i = 0; i < len; i++) {
                uint8Array[i] = binaryString.charCodeAt(i);
            }

            // Step 2: Decode the audio data
            audioContext.decodeAudioData(arrayBuffer, (audioBuffer) => {
                // Step 3: Play the audio
                const source = audioContext.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(audioContext.destination);
                source.start(0);

                // Step 4: Resolve the promise when the audio finishes playing
                source.onended = () => {
                    resolve();
                };
            }, (error) => {
                reject('Error decoding audio data: ' + error);
            });
        } catch (error) {
            reject('Error processing audio data: ' + error);
        }
    });
};

const handleStopEvent = (stopData) => {
    console.log('Call stopped with call_control_id:', stopData.call_control_id);
    callStatus.value = 'Call Stopped';
    stopRecording();
    ws.close();
};

const startRecording = async () => {
    await register(await connect());

    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

    mediaRecorder.addEventListener('dataavailable', (event) => {
        if (event.data.size > 0) {
            const reader = new FileReader();

            reader.onload = () => {
                const audioChunk = reader.result;
                audioEncoder.postMessage({ command: 'process', data: audioChunk });
            };

            reader.onerror = (error) => {
                console.error("Error reading Blob as ArrayBuffer:", error);
            };

            reader.readAsArrayBuffer(event.data);
        } else {
            console.warn("Empty audio chunk received.");
        }
    });

    mediaRecorder.start(100);
};

const stopRecording = () => {
    mediaRecorder.stop();
    clearInterval(recordingInterval);
}

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>