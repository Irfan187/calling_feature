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
import * as lame from '@breezystack/lamejs';
import { MediaRecorder } from 'extendable-media-recorder';

const to = ref('+17274257260');
const from = ref('+16265401233');
const callStatus = ref('No Active Call');

let mediaRecorder;
let audioChunks = [];
let ws = null;
let audioContext = null;
let recordingInterval;

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

    ws.onmessage = (event) => {
        try {
            const eventData = JSON.parse(event.data);
            if (eventData.event === "start") {
                handleStartEvent(eventData.start);
            } else if (eventData.event === "media") {
                handleMediaEvent(eventData.media);
            } else if (eventData.event === "stop") {
                handleStopEvent(eventData.stop);
            }
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    };
};

const handleStartEvent = async (startData) => {
    console.log('Call started with call_control_id:', startData.call_control_id);
    callStatus.value = 'Call Started';
    startRecording();
};

const handleMediaEvent = async (mediaData) => {
    const payload = mediaData.payload;
    try {
        await playAudio(payload);
    } catch (error) {
        console.error(error);
    }
};

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

const handleStopEvent = (stopData) => {
    console.log('Call stopped with call_control_id:', stopData.call_control_id);
    callStatus.value = 'Call Stopped';
    stopRecording();
    ws.close();
};

const startRecording = async () => {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/wav' });

    mediaRecorder.addEventListener('dataavailable', event => {
        audioChunks.push(event.data);
    });

    mediaRecorder.addEventListener('stop', () => {
        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
        audioChunks = [];
        processAndSendAudio(audioBlob);
    });

    mediaRecorder.start();

    recordingInterval = setInterval(() => {
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

const processAndSendAudio = async (blob) => {
    console.log('blob', blob);
    const arrayBuffer = await blob.arrayBuffer();
    const mp3Data = await encodeToMP3(arrayBuffer);
    console.log('mp3Data', mp3Data);
    if (ws && ws.readyState === WebSocket.OPEN) {
        let payload = {
            "event": "media",
            "media": {
                "payload": mp3Data
            }
        };
        ws.send(JSON.stringify(payload));
    }
}

const encodeToMP3 = (buffer) => {
    return new Promise((resolve, reject) => {
        const wav = lame.WavHeader.readHeader(new DataView(buffer));
        console.log('wav headers', wav);
        const samples = new Int16Array(buffer, wav.dataOffset, wav.dataLen / 2);
        const mp3Encoder = new lame.Mp3Encoder(wav.channels, wav.sampleRate, 128);
        const mp3Data = [];
        let mp3Buffer;

        mp3Buffer = mp3Encoder.encodeBuffer(samples);
        if (mp3Buffer.length > 0) {
            mp3Data.push(mp3Buffer);
        }

        mp3Buffer = mp3Encoder.flush();
        if (mp3Buffer.length > 0) {
            mp3Data.push(mp3Buffer);
        }

        const blob = new Blob(mp3Data, { type: 'audio/mp3' });
        const reader = new FileReader();
        reader.onload = () => {
            const base64data = reader.result.split(',')[1];
            resolve(base64data);
        };
        reader.readAsDataURL(blob);
    });
}

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>