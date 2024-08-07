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

let ws = null;
let audioContext = null;
const sampleRate = 8000;

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
        const eventData = JSON.parse(event.data);

        if (eventData.event === "start") {
            handleStartEvent(eventData.start);
        } else if (eventData.event === "media") {
            handleMediaEvent(eventData.media);
        } else if (eventData.event === "stop") {
            handleStopEvent(eventData.stop);
        }
    };
};

const handleStartEvent = (startData) => {
    console.log('Call started with call_control_id:', startData.call_control_id);
    callStatus.value = 'Call Started';
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
    const audioBuffer = audioContext.createBuffer(1, pcmData.length, sampleRate);
    audioBuffer.getChannelData(0).set(pcmData);
    const source = audioContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(audioContext.destination);
    source.start(0);
};

const handleStopEvent = (stopData) => {
    console.log('Call stopped with call_control_id:', stopData.call_control_id);
    callStatus.value = 'Call Stopped';
};
</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>