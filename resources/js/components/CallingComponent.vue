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
    await playAudio(payload);
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
    const pcmArray = new Int16Array(pcmuArray.length);
    for (let i = 0; i < pcmuArray.length; i++) {
        pcmArray[i] = ulawDecode(pcmuArray[i]);
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

function createWavBuffer(pcmBuffer) {
    const bufferLength = pcmBuffer.length;
    const wavBuffer = new ArrayBuffer(44 + bufferLength * 2);
    const view = new DataView(wavBuffer);

    // RIFF chunk descriptor
    writeString(view, 0, 'RIFF');
    view.setUint32(4, 36 + bufferLength * 2, true);
    writeString(view, 8, 'WAVE');

    // FMT sub-chunk
    writeString(view, 12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true); // Audio format (1 = PCM)
    view.setUint16(22, 1, true); // Number of channels
    view.setUint32(24, sampleRate, true); // Sample rate
    view.setUint32(28, sampleRate * 2, true); // Byte rate
    view.setUint16(32, 2, true); // Block align
    view.setUint16(34, 16, true); // Bits per sample

    // Data sub-chunk
    writeString(view, 36, 'data');
    view.setUint32(40, bufferLength * 2, true);

    // PCM data
    let offset = 44;
    for (let i = 0; i < bufferLength; i++, offset += 2) {
        view.setInt16(offset, pcmBuffer[i], true);
    }

    return wavBuffer;
}

function writeString(view, offset, string) {
    for (let i = 0; i < string.length; i++) {
        view.setUint8(offset + i, string.charCodeAt(i));
    }
}

const playAudio = (pcmuData) => {
    const payload = pcmuData;
    const pcmuData = decodeBase64ToPCMU(payload);
    const pcmData = convertPCMUToPCM(pcmuData);
    const wavBuffer = createWavBuffer(pcmData);
    const blob = new Blob([wavBuffer], { type: 'audio/wav' });
    const url = URL.createObjectURL(blob);

    const audio = new Audio(url);
    audio.play();
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