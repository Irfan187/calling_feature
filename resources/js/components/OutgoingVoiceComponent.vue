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
let audioContext = null;
const sampleRate = 8000;
var mediaRecorder = null;
const app = function (stream) {
    mediaRecorder = new MediaRecorder(stream);
};
navigator.mediaDevices
    .getUserMedia({ audio: true })
    .then(app);

let mediaStream;
let mediaStreamSource;
let gainNode;
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
                // mediaRecorder.start = function (event) {
                //     console.log({ 'event': event });
                // };
                // mediaRecorder.ondataavailable = function (e) {
                //     var object =
                //     {
                //         "event": "media",
                //         "media": {
                //             "payload": btoa(e.data)
                //         }
                //     };

                //     ws.send(JSON.stringify(object));
                // }
                mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });

                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                mediaStreamSource = audioContext.createMediaStreamSource(mediaStream);

                gainNode = audioContext.createGain();
                gainNode.gain.value = 1;  // Adjust the gain value if needed

                mediaStreamSource.connect(gainNode);
                gainNode.connect(audioContext.destination);
            } else if (eventData.event === "stop") {
                // mediaRecorder.stop();
            }
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    };
};


</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>