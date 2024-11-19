<script setup>
import { ref } from "vue";
import FFmpeg from "@ffmpeg/ffmpeg";

const ffmpeg = FFmpeg.createFFmpeg({ log: true });
const isRecording = ref(false);
const outputBase64RTP = ref("");

let mediaRecorder = null;

const startRecording = async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Your browser does not support audio recording.");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream, { mimeType: "audio/webm;codecs=opus" });

        mediaRecorder.ondataavailable = async (event) => {
            const base64RTP = await processAudioChunkWithFFmpeg(event.data);
            if (base64RTP) {
                outputBase64RTP.value += base64RTP + "\n";
            }
        };

        mediaRecorder.start(100); // Record in 100ms chunks
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

const processAudioChunkWithFFmpeg = async (audioBlob) => {
    try {
        const arrayBuffer = await audioBlob.arrayBuffer();
        const inputFileName = "input.webm";
        const outputFileName = "output.raw";

        await ffmpeg.load();
        ffmpeg.FS("writeFile", inputFileName, new Uint8Array(arrayBuffer));

        // Convert to raw PCM at 8 kHz
        await ffmpeg.run("-i", inputFileName, "-ar", "8000", "-ac", "1", "-f", "s16le", outputFileName);

        const output = ffmpeg.FS("readFile", outputFileName);
        const pcmData = new Float32Array(output.buffer);

        // Convert PCM to PCMU
        const pcmuData = pcmData.map((sample) => {
            const mulawSample = 127.5 * Math.log(1 + 255 * Math.abs(sample)) / Math.log(1 + 255);
            return sample < 0 ? -mulawSample : mulawSample;
        });

        // Create RTP packets and encode to Base64
        const packet = pcmuData.slice(0, 800);
        const rtpPacket = new Uint8Array(packet);
        return btoa(String.fromCharCode(...rtpPacket));
    } catch (error) {
        console.error("Error processing audio chunk with FFmpeg:", error);
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
