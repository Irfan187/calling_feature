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

        <div class="my-3">
            <div>{{ callStatus }}</div>
        </div>
    </div>

</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const to = ref('+17274257260');
const from = ref('+16265401233');
const callStatus = ref('No Active Call');
const call_control_id = ref('');
let ws = null;

let recordingAudioContext = null;
let playbackAudioContext = null;

let mediaStream = null;
let sourceNode = null;
let pcmEncoder = null;

const createRTPPacket = (payload, sequenceNumber, timestamp) => {
    const rtpHeader = new Uint8Array(12);
    rtpHeader[0] = 0x80;
    rtpHeader[1] = 0x00;
    rtpHeader[2] = (sequenceNumber >> 8) & 0xff;
    rtpHeader[3] = sequenceNumber & 0xff;
    rtpHeader[4] = (timestamp >> 24) & 0xff;
    rtpHeader[5] = (timestamp >> 16) & 0xff;
    rtpHeader[6] = (timestamp >> 8) & 0xff;
    rtpHeader[7] = timestamp & 0xff;
    const ssrc = 0x12345678;
    rtpHeader[8] = (ssrc >> 24) & 0xff;
    rtpHeader[9] = (ssrc >> 16) & 0xff;
    rtpHeader[10] = (ssrc >> 8) & 0xff;
    rtpHeader[11] = ssrc & 0xff;
    return new Uint8Array([...rtpHeader, ...payload]);
};

const startRecording = async () => {
    const pcmuBuffer = [];
    const targetSamples = 160;

    let sequenceNumber = 0;
    let timestamp = 0;

    try {
        if (!recordingAudioContext) {
            recordingAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        } else if (recordingAudioContext.state === 'suspended') {
            recordingAudioContext.resume();
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });

        await recordingAudioContext.audioWorklet.addModule(new URL('../pcmEncoder.js', import.meta.url));
        sourceNode = recordingAudioContext.createMediaStreamSource(mediaStream);
        pcmEncoder = new AudioWorkletNode(recordingAudioContext, 'pcmEncoder');

        pcmEncoder.port.onmessage = (event) => {
            const { pcmuPacket } = event.data;
            pcmuBuffer.push(...pcmuPacket);

            if (pcmuBuffer.length >= targetSamples) {
                const rtpPayload = pcmuBuffer.splice(0, targetSamples);
                const rtpPacket = createRTPPacket(rtpPayload, sequenceNumber, timestamp);

                sequenceNumber = (sequenceNumber + 1) & 0xffff;
                timestamp += targetSamples;

                if (ws && ws.readyState === WebSocket.OPEN) {
                    const base64Packet = btoa(String.fromCharCode(...rtpPacket));
                    ws.send(base64Packet);
                }
            }
        };

        sourceNode.connect(pcmEncoder);
    } catch (error) {
        console.error("Error accessing microphone or initializing recording:", error);
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

const initializeWebSocketAndAudio = () => {
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
    if (!playbackAudioContext) {
        playbackAudioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (playbackAudioContext.state === 'suspended') {
        playbackAudioContext.resume();
    }

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
            playbackAudioContext.decodeAudioData(arrayBuffer, (audioBuffer) => {
                // Step 3: Play the audio
                const source = playbackAudioContext.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(playbackAudioContext.destination);
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
    ws.close();
    sourceNode.disconnect();
    pcmEncoder.disconnect();
    mediaStream.getTracks().forEach((track) => track.stop());
    playbackAudioContext.close();
    recordingAudioContext.close();
};

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>