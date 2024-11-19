<template>
    <div class="container mt-5">
        <h1>Audio recorder loopback test</h1>

        <button type="button" class="btn btn-primary me-2" @click="handleStartEvent" v-if="!isRecording">Start</button>
        <button type="button" class="btn btn-primary" @click="handleStopEvent" v-else>Stop</button>
    </div>

</template>

<script setup>
import { MediaRecorder, register } from 'extendable-media-recorder';
import { connect } from 'extendable-media-recorder-wav-encoder';
import { ref } from 'vue';

let mediaRecorder;
let audioChunks = [];
let audioContext = null;
let recordingInterval;
let isRecording = ref(false);
let audioEncoder = new Worker(new URL('../audioEncoder.js', import.meta.url), { type: 'module' });

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
    console.log(data);
    const rtpPacket = createRTPPacket(data);
    const base64Payload = encodeRTPToBase64(rtpPacket);
    if (command === 'processed') {
        await playAudio(base64Payload);
    }
};

const handleStartEvent = () => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioContext.state === 'suspended') {
        audioContext.resume();
    }
    startRecording();
    isRecording.value = true;
};

const handleStopEvent = () => {
    isRecording.value = false;
    stopRecording();
};

const startRecording = async () => {
    await register(await connect());

    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

    mediaRecorder.addEventListener('dataavailable', async (event) => {
        if (event.data.size > 0) {
            console.log("Audio chunk size:", event.data.size);
            const audioChunk = await event.data.arrayBuffer();
            audioEncoder.postMessage({ command: 'process', data: audioChunk });
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



</script>