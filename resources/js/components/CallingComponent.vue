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
const sampleRate = 8000;
const frameDuration = 0.16; // 160ms
const frameSize = Math.floor(sampleRate * frameDuration); // 8000 * 0.16 = 1280 samples per frame
const audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate });
let audioBufferQueue = [];
let isPlaying = false;

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

const handleStartEvent = (startData) => {
    console.log('Call started with call_control_id:', startData.call_control_id);
    callStatus.value = 'Call Started';
    sampleRate = startData.media_format.sample_rate; // Update sample rate from media format
};

const handleMediaEvent = async (mediaData) => {
    const payload = mediaData.payload;
    const pcmData = decodeBase64ToPCMU(payload);
    const pcmBuffer = convertPCMUToPCM(pcmData);

    audioBufferQueue.push(pcmBuffer);
    if (!isPlaying) {
        playQueuedAudio();
    }
};

const playQueuedAudio = () => {
    if (audioBufferQueue.length > 0) {
        isPlaying = true;
        const pcmBuffer = audioBufferQueue.shift();
        const audioBuffer = audioContext.createBuffer(1, pcmBuffer.length, sampleRate);
        audioBuffer.copyToChannel(pcmBuffer, 0);

        const source = audioContext.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(audioContext.destination);
        source.onended = playQueuedAudio;
        source.start(audioContext.currentTime + frameDuration);
    } else {
        isPlaying = false;
    }
};

function decodeBase64ToPCMU(base64) {
    const binaryString = atob(base64);
    const pcmuArray = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        pcmuArray[i] = binaryString.charCodeAt(i);
    }
    return pcmuArray;
}

function convertPCMUToPCM(pcmuArray) {
    const pcmArray = new Float32Array(pcmuArray.length);
    for (let i = 0; i < pcmuArray.length; i++) {
        pcmArray[i] = ulawDecode(pcmuArray[i]) / 32768.0;
    }
    return pcmArray;
}

function ulawDecode(ulawByte) {
    const BIAS = 0x84;
    ulawByte = ~ulawByte;
    const sign = ulawByte & 0x80;
    let exponent = (ulawByte & 0x70) >> 4;
    let mantissa = ulawByte & 0x0F;
    mantissa |= 0x10;
    mantissa <<= 1;
    mantissa += 1;
    mantissa <<= exponent + 2;
    mantissa -= BIAS;

    return (sign ? -mantissa : mantissa);
}

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