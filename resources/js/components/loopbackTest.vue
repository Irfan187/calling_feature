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

audioEncoder.onmessage = async (event) => {
    console.log(event);
    const { command, data } = event.data;

    if (command === 'processed') {
        await playAudio(data);
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
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/wav' });

    mediaRecorder.addEventListener('dataavailable', event => {
        audioChunks.push(event.data);
    });

    mediaRecorder.addEventListener('stop', async () => {
        if (audioChunks.length > 0) {
            const audioData = new Blob(audioChunks, { type: 'audio/wav' });
            audioChunks = [];
            audioEncoder.postMessage({ command: 'process', data: audioData });
        }
    });

    mediaRecorder.start();

    setInterval(() => {
        if (mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            mediaRecorder.start();
        }
    }, 1000);
}

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