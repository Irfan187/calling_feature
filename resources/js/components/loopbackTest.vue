<template>
    <div class="container mt-5">
        <h1>Audio Recorder with RTP Playback</h1>
        <button v-if="!isRecording" @click="startRecording" class="btn btn-primary">Start</button>
        <button v-if="isRecording" @click="stopRecording" class="btn btn-danger">Stop</button>
    </div>
</template>

<script setup>
import { ref } from 'vue';

let mediaRecorder;
let isRecording = ref(false);

// Global variables for RTP sequence and timestamp
let sequenceNumber = 0;
let timestamp = 0;

// Single AudioContext
const audioContext = new AudioContext({ sampleRate: 8000 });

const startRecording = async () => {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream, { mimeType: "audio/webm" });

    mediaRecorder.ondataavailable = async (event) => {
        if (event.data.size > 0) {
            const arrayBuffer = await event.data.arrayBuffer();
            processAudio(arrayBuffer);
        }
    };

    mediaRecorder.start(20); // Record audio in 20ms chunks
    isRecording.value = true;
};

const stopRecording = () => {
    if (mediaRecorder) {
        mediaRecorder.stop();
    }
    isRecording.value = false;
};

// Resample PCM data to 8 kHz
const resamplePCM = async (pcmData, inputSampleRate, targetSampleRate) => {
    const audioBuffer = audioContext.createBuffer(1, pcmData.length, inputSampleRate);
    audioBuffer.getChannelData(0).set(pcmData);

    const offlineContext = new OfflineAudioContext(1, Math.ceil((pcmData.length * targetSampleRate) / inputSampleRate), targetSampleRate);
    const bufferSource = offlineContext.createBufferSource();
    bufferSource.buffer = audioBuffer;

    bufferSource.connect(offlineContext.destination);
    bufferSource.start(0);

    const resampledBuffer = await offlineContext.startRendering();
    return resampledBuffer.getChannelData(0); // Float32Array
};

// Encode PCM data to PCMA (G.711 A-law)
const encodeToPCMA = (pcmData) => {
    const pcmaData = new Uint8Array(pcmData.length);
    for (let i = 0; i < pcmData.length; i++) {
        pcmaData[i] = linearToALaw(pcmData[i]);
    }
    return pcmaData;
};

// Convert linear PCM to A-law
const linearToALaw = (sample) => {
    const ALAW_MAX = 0x7FFF;
    const QUANT_MASK = 0x0F;
    const SEG_SHIFT = 4;
    const SEG_MASK = 0x70;

    const absSample = Math.min(ALAW_MAX, Math.abs(sample));
    let sign = (sample >> 8) & 0x80;
    let compressedByte;

    if (absSample < 256) {
        compressedByte = (absSample >> 4) & QUANT_MASK;
    } else {
        let segment = 0;
        for (let value = absSample; value >= 256; segment++) {
            value >>= 1;
        }
        compressedByte = ((segment << SEG_SHIFT) | ((absSample >> (segment + 3)) & QUANT_MASK)) & 0xFF;
    }

    return ~(sign | compressedByte) & 0xFF;
};

// Create RTP packet
const createRTPPacket = (payload) => {
    const HEADER_SIZE = 12;
    const rtpPacket = new Uint8Array(HEADER_SIZE + payload.length);

    const version = 2;
    const padding = 0;
    const extension = 0;
    const csrcCount = 0;
    const marker = 0;
    const payloadType = 8; // PCMA payload type
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
    timestamp += payload.length;

    rtpPacket[8] = (ssrc >> 24) & 0xff;
    rtpPacket[9] = (ssrc >> 16) & 0xff;
    rtpPacket[10] = (ssrc >> 8) & 0xff;
    rtpPacket[11] = ssrc & 0xff;

    rtpPacket.set(payload, HEADER_SIZE);

    return rtpPacket;
};

// Extract PCMA payload from RTP
const extractPCMAFromRTP = (rtpPacket) => {
    const HEADER_SIZE = 12; // RTP header size
    return rtpPacket.subarray(HEADER_SIZE); // Extract payload
};

// Decode PCMA to PCM
const decodePCMA = (pcmaData) => {
    const pcmData = new Int16Array(pcmaData.length);
    for (let i = 0; i < pcmaData.length; i++) {
        pcmData[i] = alawToLinear(pcmaData[i]);
    }
    return pcmData;
};

// Convert A-law to linear PCM
const alawToLinear = (aLawByte) => {
    const SIGN_BIT = 0x80;
    const QUANT_MASK = 0x0F;
    const SEG_SHIFT = 4;
    const SEG_MASK = 0x70;
    const exp_lut = [0, 132, 396, 924, 1980, 4092, 8316, 16764];

    let aLaw = ~aLawByte;
    let sign = (aLaw & SIGN_BIT) !== 0 ? -1 : 1;
    let exponent = (aLaw & SEG_MASK) >> SEG_SHIFT;
    let mantissa = aLaw & QUANT_MASK;
    let sample = exp_lut[exponent] + (mantissa << (exponent > 0 ? exponent + 2 : 4));

    return sign * sample;
};

// Play PCM data
const playPCMData = async (pcmData, sampleRate = 8000) => {
    const audioBuffer = audioContext.createBuffer(1, pcmData.length, sampleRate);
    audioBuffer.getChannelData(0).set(pcmData.map(sample => sample / 32768)); // Normalize PCM to -1 to 1

    const source = audioContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(audioContext.destination);
    source.start(0);
};

// Test RTP playback
const testRTPPlayback = (rtpPacket) => {
    const pcmaPayload = extractPCMAFromRTP(rtpPacket);
    const pcmData = decodePCMA(pcmaPayload);
    playPCMData(pcmData);
};

// Process audio data
const processAudio = async (arrayBuffer) => {
    if (arrayBuffer.byteLength % 2 !== 0) {
        arrayBuffer = arrayBuffer.slice(0, arrayBuffer.byteLength - 1);
    }

    const pcmData = new Int16Array(arrayBuffer);
    const resampledPCM = await resamplePCM(pcmData, 48000, 8000);
    const pcmaData = encodeToPCMA(resampledPCM);
    const rtpPacket = createRTPPacket(pcmaData);

    testRTPPlayback(rtpPacket); // Test playback
};
</script>
