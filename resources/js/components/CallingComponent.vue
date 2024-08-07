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

function convertPCMUToPCM(pcmuArray, sampleRate) {
    const pcmArray = new Float32Array(pcmuArray.length);
    for (let i = 0; i < pcmuArray.length; i++) {
        pcmArray[i] = ulawDecode(pcmuArray[i]) / 32768.0;
    }
    return pcmArray;
}

function ulawDecode(value) {
    const EXP_BIAS = 0x84;
    value = ~value;
    const sign = (value & 0x80) >> 7;
    const exponent = (value & 0x70) >> 4;
    let mantissa = value & 0x0F;
    mantissa |= 0x10;
    mantissa <<= 3;
    mantissa += 0x04;
    mantissa <<= exponent;

    return (sign === 0 ? mantissa : -mantissa) * 8;
}

const playAudio = (pcmuData) => {
    return new Promise((resolve) => {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const pcmData = decodeBase64ToPCMU(pcmuData);
        const pcmBuffer = convertPCMUToPCM(pcmData, audioContext.sampleRate);

        const audioBuffer = audioContext.createBuffer(1, pcmBuffer.length, audioContext.sampleRate);
        audioBuffer.copyToChannel(pcmBuffer, 0);

        const source = audioContext.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(audioContext.destination);
        source.start();

        source.onended = resolve;
    });
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