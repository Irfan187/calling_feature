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

const extractPCMU = (rtpPacket) => {
    const HEADER_SIZE = 12;
    return rtpPacket.slice(HEADER_SIZE); // Remove the RTP header
};

const muLawToLinear = (muLaw) => {
    const SIGN_BIT = 0x80;
    const QUANT_MASK = 0x0f;
    const SEG_SHIFT = 4;

    muLaw = ~muLaw;
    const sign = (muLaw & SIGN_BIT) ? -1 : 1;
    const exponent = (muLaw & 0x70) >> SEG_SHIFT;
    const mantissa = muLaw & QUANT_MASK;

    let sample = ((1 << (exponent + 3)) + (mantissa << (exponent + 3))) - 33;
    return sign * sample;
};

const decodePCMUToPCM = (pcmuData) => {
    const pcmData = new Int16Array(pcmuData.length);
    for (let i = 0; i < pcmuData.length; i++) {
        pcmData[i] = muLawToLinear(pcmuData[i]);
    }
    return pcmData;
};

const pcmToFloat32 = (pcmData) => {
    const floatData = new Float32Array(pcmData.length);
    for (let i = 0; i < pcmData.length; i++) {
        floatData[i] = pcmData[i] / 32768; // Normalize to [-1.0, 1.0]
    }
    return floatData;
};
const playAudio = async (base64Data) => {
    return new Promise((resolve, reject) => {
        try {
            // Step 1: Decode base64 data to binary
            const binaryString = atob(base64Data);
            const len = binaryString.length;
            const arrayBuffer = new ArrayBuffer(len);
            const uint8Array = new Uint8Array(arrayBuffer);

            for (let i = 0; i < len; i++) {
                uint8Array[i] = binaryString.charCodeAt(i);
            }

            // Step 2: Extract PCMU payload
            const pcmuPayload = extractPCMU(uint8Array);

            // Step 3: Decode PCMU to PCM
            const pcmData = decodePCMUToPCM(pcmuPayload);

            // Step 4: Convert PCM to Float32
            const floatData = pcmToFloat32(pcmData);

            // Step 5: Create an AudioBuffer and play it
            const audioBuffer = audioContext.createBuffer(1, floatData.length, 8000); // Mono, 8 kHz
            audioBuffer.copyToChannel(floatData, 0, 0);

            const source = audioContext.createBufferSource();
            source.buffer = audioBuffer;
            source.connect(audioContext.destination);
            source.start(0);

            // Resolve when the audio finishes
            source.onended = () => {
                resolve();
            };
        } catch (error) {
            reject("Error processing audio data: " + error);
        }
    });
};


</script>