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
        mediaRecorder.ondataavailable = async (event) => {
            const pcmuBase64 = await processAudioChunk(event.data);
            if (pcmuBase64) {
                outputBase64RTP.value += pcmuBase64 + "\n";
            }
        };

        mediaRecorder.start(100); // Capture audio in chunks of 100ms
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

const processAudioChunk = async (audioBlob) => {
    try {
        const arrayBuffer = await audioBlob.arrayBuffer();
        const decodedAudio = await audioContext.decodeAudioData(arrayBuffer);

        // Resample to 8 kHz and convert to PCM
        const offlineContext = new OfflineAudioContext(
            1, // Mono channel
            Math.ceil(decodedAudio.duration * 8000),
            8000 // 8 kHz sample rate
        );

        const source = offlineContext.createBufferSource();
        source.buffer = decodedAudio;
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
        const packet = pcmuData.slice(0, 800);
        const rtpPacket = new Uint8Array(packet);

        // Convert RTP packet to Base64
        return btoa(String.fromCharCode(...rtpPacket));
    } catch (error) {
        console.error("Error processing audio chunk:", error);
        return null;
    }
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
