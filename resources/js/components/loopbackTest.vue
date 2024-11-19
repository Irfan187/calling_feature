<script setup>
import { ref } from "vue";

const isRecording = ref(false);
const outputBase64RTP = ref("");
let audioContext = null;
let mediaStream = null;
let processor = null;
let sourceNode = null;

const startRecording = async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Your browser does not support audio recording.");
        return;
    }

    try {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });

        const sampleRate = 8000; // Target sample rate for PCMU
        sourceNode = audioContext.createMediaStreamSource(mediaStream);

        // ScriptProcessorNode is deprecated but still widely supported.
        processor = audioContext.createScriptProcessor(4096, 1, 1);

        processor.onaudioprocess = (event) => {
            const inputBuffer = event.inputBuffer.getChannelData(0); // Mono channel
            const downsampledBuffer = downsampleBuffer(inputBuffer, audioContext.sampleRate, sampleRate);
            const pcmuPacket = convertToPCMU(downsampledBuffer);
            const rtpPacketBase64 = encodeToBase64(rtpPacketize(pcmuPacket));

            // Append the Base64 RTP packet
            outputBase64RTP.value += rtpPacketBase64 + "\n";
        };

        sourceNode.connect(processor);
        processor.connect(audioContext.destination); // For a loopback effect (optional)
        isRecording.value = true;
    } catch (error) {
        console.error("Error accessing microphone:", error);
    }
};

const stopRecording = () => {
    if (isRecording.value) {
        sourceNode.disconnect();
        processor.disconnect();
        mediaStream.getTracks().forEach((track) => track.stop());
        audioContext.close();

        isRecording.value = false;
    }
};

const downsampleBuffer = (buffer, inputSampleRate, outputSampleRate) => {
    if (outputSampleRate === inputSampleRate) {
        return buffer;
    }

    const sampleRatio = inputSampleRate / outputSampleRate;
    const newLength = Math.round(buffer.length / sampleRatio);
    const downsampledBuffer = new Float32Array(newLength);

    for (let i = 0; i < newLength; i++) {
        const index = Math.round(i * sampleRatio);
        downsampledBuffer[i] = buffer[index];
    }

    return downsampledBuffer;
};

const convertToPCMU = (buffer) => {
    return buffer.map((sample) => {
        const mulawSample = 127.5 * Math.log(1 + 255 * Math.abs(sample)) / Math.log(1 + 255);
        return sample < 0 ? -mulawSample : mulawSample;
    });
};

const rtpPacketize = (pcmuData) => {
    const packetSize = 800; // 100ms of audio at 8 kHz
    const packet = pcmuData.slice(0, packetSize);
    return new Uint8Array(packet);
};

const encodeToBase64 = (data) => {
    return btoa(String.fromCharCode(...data));
};
</script>

<template>
    <div>
        <button @click="startRecording" :disabled="isRecording">Start</button>
        <button @click="stopRecording" :disabled="!isRecording">Stop</button>
        <div v-if="outputBase64RTP">
            <h3>Streaming Base64 RTP Packets:</h3>
            <textarea readonly rows="10" cols="50">{{ outputBase64RTP }}</textarea>
        </div>
    </div>
</template>
