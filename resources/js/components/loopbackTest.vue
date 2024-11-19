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
let isRecording = ref(false);

const handleStartEvent = () => {
    startRecording();
    isRecording.value = true;
};

const handleStopEvent = () => {
    isRecording.value = false;
    stopRecording();
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

const stopRecording = () => {
    mediaRecorder.stop();
}

const resamplePCM = async (pcmData, inputSampleRate, targetSampleRate) => {
    const audioContext = new OfflineAudioContext(1, pcmData.length, inputSampleRate);
    const audioBuffer = audioContext.createBuffer(1, pcmData.length, inputSampleRate);

    audioBuffer.getChannelData(0).set(pcmData);

    const source = audioContext.createBufferSource();
    source.buffer = audioBuffer;

    const resampledContext = new OfflineAudioContext(1, pcmData.length * targetSampleRate / inputSampleRate, targetSampleRate);
    source.connect(resampledContext.destination);
    source.start(0);

    const resampledBuffer = await resampledContext.startRendering();
    return resampledBuffer.getChannelData(0); // Float32Array of resampled PCM data
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

const encodeToPCMA = (pcmData) => {
    const pcmaData = new Uint8Array(pcmData.length);
    for (let i = 0; i < pcmData.length; i++) {
        pcmaData[i] = linearToALaw(pcmData[i]);
    }
    return pcmaData;
};

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

let loggingDone = false;

const processAudio = async (arrayBuffer) => {
    const pcmData = new Int16Array(arrayBuffer);

    if (!loggingDone) console.log('pcmData', pcmData);

    // Resample PCM data to 8 kHz
    const resampledPCM = await resamplePCM(pcmData, 48000, 8000);
    if (!loggingDone) console.log('resampledPCM', resampledPCM);

    // Encode to PCMA
    const pcmaData = encodeToPCMA(resampledPCM);
    if (!loggingDone) console.log('pcmaData', pcmaData);

    // Create RTP packets
    const rtpPacket = createRTPPacket(pcmaData);
    if (!loggingDone) console.log('rtpPacket', rtpPacket);

    testRTPPlayback(rtpPacket);

    if (!loggingDone) loggingDone = true;
};

const extractPCMAFromRTP = (rtpPacket) => {
    const HEADER_SIZE = 12; // RTP header size
    return rtpPacket.subarray(HEADER_SIZE); // Extract payload after the header
};

const decodePCMA = (pcmaData) => {
    const pcmData = new Int16Array(pcmaData.length);
    for (let i = 0; i < pcmaData.length; i++) {
        pcmData[i] = alawToLinear(pcmaData[i]);
    }
    return pcmData;
};

const playPCMData = async (pcmData, sampleRate = 8000) => {
    const audioContext = new AudioContext({ sampleRate });

    // Create an AudioBuffer
    const audioBuffer = audioContext.createBuffer(1, pcmData.length, sampleRate);
    const bufferData = audioBuffer.getChannelData(0);

    // Normalize PCM data to Float32 range (-1 to 1)
    for (let i = 0; i < pcmData.length; i++) {
        bufferData[i] = pcmData[i] / 32768; // Normalize to -1 to 1
    }

    // Create a BufferSource and connect it
    const source = audioContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(audioContext.destination);

    // Play the audio
    source.start(0);
};

const testRTPPlayback = (rtpPacket) => {
    // Extract PCMA payload
    const pcmaPayload = extractPCMAFromRTP(rtpPacket);

    // Decode PCMA to PCM
    const pcmData = decodePCMA(pcmaPayload);

    // Play PCM data
    playPCMData(pcmData, 8000);
};
</script>