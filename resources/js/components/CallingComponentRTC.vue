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
import { MediaRecorder, register } from 'extendable-media-recorder';
import { connect } from 'extendable-media-recorder-wav-encoder';

const to = ref('+17274257260');
const from = ref('+16265401233');
const callStatus = ref('No Active Call');
const call_control_id = ref('');
let ws = null;
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

        const sampleRate = 8000;
        sourceNode = audioContext.createMediaStreamSource(mediaStream);

        processor = audioContext.createScriptProcessor(4096, 1, 1);

        processor.onaudioprocess = (event) => {
            const inputBuffer = event.inputBuffer.getChannelData(0);
            const downsampledBuffer = downsampleBuffer(inputBuffer, audioContext.sampleRate, sampleRate);
            const pcmuPacket = convertToPCMU(downsampledBuffer);
            const rtpPacketBase64 = encodeToBase64(rtpPacketize(pcmuPacket));

            let payload = {
                "event": "media",
                "media": {
                    "payload": rtpPacketBase64
                }
            };
            ws.send(JSON.stringify(payload));
        };

        sourceNode.connect(processor);
    } catch (error) {
        console.error("Error accessing microphone:", error);
    }
};

const downsampleBuffer = (buffer, inputSampleRate, outputSampleRate) => {
    if (outputSampleRate === inputSampleRate) {
        return buffer;
    }
    const ratio = inputSampleRate / outputSampleRate;
    const newLength = Math.ceil(buffer.length / ratio);
    const downsampledBuffer = new Float32Array(newLength);

    let offset = 0;
    for (let i = 0; i < newLength; i++) {
        const pos = i * ratio;
        const left = Math.floor(pos);
        const right = Math.min(left + 1, buffer.length - 1);
        const alpha = pos - left;
        downsampledBuffer[i] = buffer[left] + alpha * (buffer[right] - buffer[left]);
    }
    return downsampledBuffer;
};

const convertToPCMU = (buffer) => {
    const MULAW_MAX = 0x7F;
    const MULAW_BIAS = 132;

    return buffer.map((sample) => {
        let sign = sample < 0 ? -1 : 1;
        sample = Math.min(Math.abs(sample), 1) * 32767; // Convert to 16-bit PCM
        sample = Math.log(1 + MULAW_BIAS * sample / 32767) / Math.log(1 + MULAW_BIAS);
        sample = Math.round(sign * sample * MULAW_MAX);
        return (sample + MULAW_MAX) & 0xFF; // Unsigned 8-bit value
    });
};


const rtpPacketize = (pcmuData) => {
    const packetSize = 160;
    const packets = [];
    for (let i = 0; i < pcmuData.length; i += packetSize) {
        packets.push(pcmuData.slice(i, i + packetSize));
    }
    return packets;
};

const encodeToBase64 = (data) => {
    return btoa(String.fromCharCode(...data));
};

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
    call_control_id.value = startData.call_control_id;
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
    ws.close();
    sourceNode.disconnect();
    processor.disconnect();
    mediaStream.getTracks().forEach((track) => track.stop());
    audioContext.close();
};

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>