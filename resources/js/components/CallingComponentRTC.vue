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
const call_control_id = ref('');
let ws = null;

let recordingAudioContext = null;
let playbackAudioContext = null;

let mediaStream = null;
let sourceNode = null;
let pcmEncoder = null;


const designFIRFilter = (length, cutoff, sampleRate) => {
    const filter = new Float32Array(length);
    const middle = Math.floor(length / 2);
    for (let i = 0; i < length; i++) {
        if (i === middle) {
            filter[i] = 2 * cutoff / sampleRate;
        } else {
            const numerator = Math.sin(2 * Math.PI * cutoff * (i - middle) / sampleRate);
            const denominator = Math.PI * (i - middle);
            filter[i] = numerator / denominator;
        }
        filter[i] *= 0.54 - 0.46 * Math.cos(2 * Math.PI * i / (length - 1));
    }
    return filter;
};

const downsampleBuffer = (buffer, inputSampleRate, outputSampleRate) => {
    if (outputSampleRate === inputSampleRate) {
        return buffer;
    }

    const sampleRatio = inputSampleRate / outputSampleRate;
    const newLength = Math.floor(buffer.length / sampleRatio);
    const downsampledBuffer = new Float32Array(newLength);

    const filterLength = 21;
    const cutoffFreq = outputSampleRate / 2;
    const filter = designFIRFilter(filterLength, cutoffFreq, inputSampleRate);

    for (let i = 0; i < newLength; i++) {
        const start = Math.floor(i * sampleRatio);
        let sum = 0;
        for (let j = 0; j < filter.length; j++) {
            if (start + j < buffer.length) {
                sum += buffer[start + j] * filter[j];
            }
        }
        downsampledBuffer[i] = sum;
    }

    return downsampledBuffer;
};


const convertToPCMU = (buffer) => {
    const PCM_MAX = 32767; // Maximum PCM value
    const PCM_MIN = -32768; // Minimum PCM value

    const muLawEncode = (sample) => {
        const MU = 255;
        const clamped = Math.max(Math.min(sample, PCM_MAX), PCM_MIN);
        const magnitude = Math.log(1 + MU * Math.abs(clamped / PCM_MAX)) / Math.log(1 + MU);
        const sign = clamped < 0 ? 0 : 0x80;
        return ~(sign | (magnitude * 0x7F));
    };

    return new Uint8Array(buffer.map((sample) => muLawEncode(sample * PCM_MAX)));
};

const startRecording = async () => {
    const pcmuBuffer = []; // Buffer for raw PCMU data
    const rawAudioBuffer = []; // Accumulate raw audio samples from the worklet
    const blockSize = 4096; // Block size
    const targetSamples = 800; // Packet size in samples at 8kHz

    try {
        if (!recordingAudioContext) {
            recordingAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        } else if (recordingAudioContext.state === 'suspended') {
            recordingAudioContext.resume();
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });

        await recordingAudioContext.audioWorklet.addModule(new URL('../pcmEncoder.js', import.meta.url));
        sourceNode = recordingAudioContext.createMediaStreamSource(mediaStream);
        pcmEncoder = new AudioWorkletNode(recordingAudioContext, 'pcmEncoder');

        // Handle incoming data from the worklet
        pcmEncoder.port.onmessage = (event) => {
            const { rawAudio } = event.data;

            // Accumulate raw audio data for buffering (equivalent to ScriptProcessor)
            rawAudioBuffer.push(...rawAudio);

            // Process the buffer when it reaches the target block size
            if (rawAudioBuffer.length >= blockSize) {
                const block = rawAudioBuffer.splice(0, blockSize); // Extract a 4096-sample block

                // Downsample the block to 8kHz
                const downsampledBuffer = downsampleBuffer(block, recordingAudioContext.sampleRate, 8000);

                // Convert to PCMU and accumulate in the pcmuBuffer
                const pcmuData = convertToPCMU(downsampledBuffer);
                pcmuBuffer.push(...pcmuData);

                // Send 800-sample packets
                while (pcmuBuffer.length >= targetSamples) {
                    const rtpPayload = pcmuBuffer.splice(0, targetSamples);
                    const rtpPacket = new Uint8Array(rtpPayload);

                    // Send packet over WebSocket
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        const base64Packet = btoa(String.fromCharCode(...rtpPacket));
                        ws.send(JSON.stringify({
                            "event": "media",
                            "media": {
                                "payload": base64Packet
                            }
                        }));
                    }
                }
            }
        };

        sourceNode.connect(pcmEncoder);
    } catch (error) {
        console.error("Error accessing microphone or initializing recording:", error);
    }
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
    if (!playbackAudioContext) {
        playbackAudioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (playbackAudioContext.state === 'suspended') {
        playbackAudioContext.resume();
    }

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
            playbackAudioContext.decodeAudioData(arrayBuffer, (audioBuffer) => {
                // Step 3: Play the audio
                const source = playbackAudioContext.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(playbackAudioContext.destination);
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
    pcmEncoder.disconnect();
    mediaStream.getTracks().forEach((track) => track.stop());
    playbackAudioContext.close();
    recordingAudioContext.close();
};

</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>