<template>
    <div>
        <button @click="startRecording" :disabled="isRecording">Start</button>
        <button @click="stopRecording" :disabled="!isRecording">Stop</button>
        <div v-if="outputBase64RTP">
            <h3>Base64 RTP Packet:</h3>
            <textarea readonly rows="10" cols="50">{{ outputBase64RTP }}</textarea>
        </div>
    </div>
</template>


<script setup>
import { ref } from "vue";

const audioContext = new (window.AudioContext || window.webkitAudioContext)();
let mediaRecorder = null;
let audioChunks = [];
const isRecording = ref(false);
const outputBase64RTP = ref("");

const startRecording = async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Your browser does not support audio recording.");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const source = audioContext.createMediaStreamSource(stream);

        // Setup a loop-back test
        const destination = audioContext.destination;
        source.connect(destination);

        // Initialize MediaRecorder
        mediaRecorder = new MediaRecorder(stream);
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };

        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: "audio/webm" });
            audioChunks = [];

            const pcmuBase64 = await convertToPCMUBase64(audioBlob);
            outputBase64RTP.value = pcmuBase64;
        };

        mediaRecorder.start();
        isRecording.value = true;
    } catch (error) {
        console.error("Error accessing microphone:", error);
    }
};

const stopRecording = () => {
    if (mediaRecorder && mediaRecorder.state !== "inactive") {
        mediaRecorder.stop();
        isRecording.value = false;
    }
};

const convertToPCMUBase64 = async (audioBlob) => {
    const arrayBuffer = await audioBlob.arrayBuffer();
    const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);

    // Resample to 8 kHz and convert to PCM
    const offlineContext = new OfflineAudioContext(
        1, // Mono channel
        Math.ceil(audioBuffer.duration * 8000),
        8000 // 8 kHz sample rate
    );

    const source = offlineContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(offlineContext.destination);
    source.start(0);

    const resampledBuffer = await offlineContext.startRendering();
    const pcmData = resampledBuffer.getChannelData(0);

    // Convert to PCMU
    const pcmuData = pcmData.map((sample) => {
        const mulawSample = 127.5 * Math.log(1 + 255 * Math.abs(sample)) / Math.log(1 + 255);
        return sample < 0 ? -mulawSample : mulawSample;
    });

    // Create RTP packet (100ms = 800 samples for 8 kHz)
    const rtpPackets = [];
    for (let i = 0; i < pcmuData.length; i += 800) {
        const packet = pcmuData.slice(i, i + 800);
        rtpPackets.push(new Uint8Array(packet));
    }

    // Convert RTP packets to Base64
    return rtpPackets.map((packet) => btoa(String.fromCharCode(...packet))).join("\n");
};
</script>
