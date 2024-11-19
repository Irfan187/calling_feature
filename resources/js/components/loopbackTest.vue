<template>
    <div class="container mt-5">
        <h1>Audio recorder loopback test</h1>

        <button type="button" class="btn btn-primary me-2" @click="handleStartEvent" v-if="!isRecording">Start</button>
        <button type="button" class="btn btn-primary" @click="handleStopEvent" v-else>Stop</button>
    </div>

</template>

<script setup>
import { ref } from "vue";

let mediaRecorder;
const isRecording = ref(false);

// Single global AudioContext
const audioContext = new AudioContext({ sampleRate: 8000 });

const handleStartEvent = () => {
    startRecording();
    isRecording.value = true;
};

const handleStopEvent = () => {
    isRecording.value = false;
    mediaRecorder.stop();
};

const startRecording = async () => {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    mediaRecorder = new MediaRecorder(stream, { mimeType: "audio/webm" });

    mediaRecorder.ondataavailable = async (event) => {
        if (event.data.size > 0) {
            const arrayBuffer = await event.data.arrayBuffer();
            processAudio(arrayBuffer);
        }
    };

    mediaRecorder.start(20); // Emit chunks every 20 ms
};

const processAudio = async (arrayBuffer) => {
    // Align buffer to Int16Array requirements
    if (arrayBuffer.byteLength % 2 !== 0) {
        arrayBuffer = arrayBuffer.slice(0, arrayBuffer.byteLength - 1);
    }

    const pcmData = new Int16Array(arrayBuffer);

    try {
        // Resample to 8 kHz
        const resampledPCM = await resamplePCM(pcmData, 48000, 8000);

        // Encode to PCMA
        const pcmaData = encodeToPCMA(resampledPCM);

        // Create RTP packet
        const rtpPacket = createRTPPacket(pcmaData);

        // Play RTP for testing
        testRTPPlayback(rtpPacket);
    } catch (error) {
        console.error("Error processing audio:", error);
    }
};

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

const encodeToPCMA = (pcmData) => {
    const pcmaData = new Uint8Array(pcmData.length);
    for (let i = 0; i < pcmData.length; i++) {
        pcmaData[i] = linearToALaw(pcmData[i]);
    }
    return pcmaData;
};

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

const testRTPPlayback = (rtpPacket) => {
    const pcmaPayload = rtpPacket.subarray(12); // Extract payload
    const pcmData = decodePCMA(pcmaPayload);
    playPCMData(pcmData);
};

</script>