<template>
    <div class="container mt-5">
        <h1>Make a Call</h1>
        <div class="mb-3">
            <label for="to" class="form-label">To</label>
            <input type="text" class="form-control" v-model="to" required>
        </div>
        <div class="mb-3">
            <label for="from" class="form-label">From</label>
            <input type="text" class="form-control" v-model="from" required>
        </div>

        <button type="button" class="btn btn-primary" @click="makeCall">Make Call</button>

        <div class="my-3">
            <div>{{ callStatus }}</div>
        </div>
    </div>

</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const to = ref('+17274257260');
const from = ref('+16265401233');
const callStatus = ref('No Active Call');

let ws = null;
const sampleRate = 8000;

const audioContext = new (window.AudioContext || window.webkitAudioContext)();

let recorder;
let audioChunks = [];
let recordingInterval;
let isRecording = false;
let base64Array = [];

const makeCall = async () => {
    const data = {
        to: to.value,
        from: from.value,
    };
    try {
        await axios.post('/api/make-call', data);
        initializeWebSocketAndAudio();
    } catch (error) {
        console.error('Error making call:', error);
    }
};

const initializeWebSocketAndAudio = () => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioContext.state === 'suspended') {
        audioContext.resume();
    }

    ws = new WebSocket('wss://callingfeature.scrumad.com:3001');

    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    ws.onopen = (event) => {
        console.log('WebSocket is open now.', event);
    };

    ws.onclose = (event) => {
        console.log('WebSocket disconnected', event);
    };

    ws.onmessage = async (event) => {
        try {
            const eventData = JSON.parse(event.data);
            // Record own voice and send it to websocket

            if (eventData.event === "start") {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const input = audioContext.createMediaStreamSource(stream);
                recorder = new MediaRecorder(stream);

                recorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                };

                recordingInterval = setInterval(() => {
                    if (isRecording) {
                        stopRecording();
                    } else {
                        recorder.start();
                        isRecording = true;
                    }
                }, 100);

            } else if (eventData.event === "stop") {
                clearInterval(recordingInterval);
                if (recorder && recorder.state !== 'inactive') {
                    recorder.stop();
                }
            }
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    };

    function stopRecording() {
        recorder.stop();
        isRecording = false;

        recorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
            const arrayBuffer = await audioBlob.arrayBuffer();
            const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            const base64data = "";
            reader.onloadend = () => {
                base64data = reader.result.split(',')[1];
                base64Array.push(base64data);
            };
            playAudio(audioBuffer,base64data);
            audioChunks = []; // Clear the chunks after processing
        };
    }

    function playAudio(audioBuffer,base64data) {
        const source = audioContext.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(audioContext.destination);
        source.start(0);
        console.log('base', base64data);
    }

};


</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>