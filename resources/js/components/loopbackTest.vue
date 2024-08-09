<template>
    <div class="container mt-5">
        <h1>Audio recorder loopback test</h1>

        <button type="button" class="btn btn-primary" @click="handleStartEvent">Start</button>
        <button type="button" class="btn btn-primary" @click="handleStopEvent">Stop</button>
    </div>

</template>

<script setup>
import * as lame from '@breezystack/lamejs';
import { MediaRecorder, register } from 'extendable-media-recorder';
import { connect } from 'extendable-media-recorder-wav-encoder';

let mediaRecorder;
let audioChunks = [];
let ws = null;
let audioContext = null;
let recordingInterval;

const handleStartEvent = async (startData) => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } else if (audioContext.state === 'suspended') {
        audioContext.resume();
    }
    startRecording();
};

const handleStopEvent = (stopData) => {
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
        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
        audioChunks = [];
        await processAndPlayAudio(audioBlob);
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

const processAndPlayAudio = async (blob) => {
    const arrayBuffer = await blob.arrayBuffer();
    const mp3Data = await encodeToMP3(arrayBuffer);
    await playAudio(mp3Data);
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

const encodeToMP3 = (buffer) => {
    return new Promise((resolve, reject) => {
        const wav = lame.WavHeader.readHeader(new DataView(buffer));
        const samples = new Int16Array(buffer, wav.dataOffset, wav.dataLen / 2);
        const mp3Encoder = new lame.Mp3Encoder(wav.channels, wav.sampleRate, 128);
        const mp3Data = [];
        let mp3Buffer;

        if (wav.channels == 2) {
            const leftChannel = [];
            const rightChannel = [];
            for (let i = 0; i < samples.length; i += 2) {
                leftChannel.push(samples[i]);
                rightChannel.push(samples[i + 1]);
            }

            mp3Buffer = mp3Encoder.encodeBuffer(leftChannel, rightChannel);
        } else {
            mp3Buffer = mp3Encoder.encodeBuffer(samples);
        }

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