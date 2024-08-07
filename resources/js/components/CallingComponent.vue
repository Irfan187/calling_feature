<template>
    <div class="container mt-5">
        <h1>Make a Call</h1>
        <div class="mb-3">
            <label for="to" class="form-label">To</label>
            <input type="text" class="form-control" id="to" name="to" value="+17274257260" required>
        </div>
        <div class="mb-3">
            <label for="from" class="form-label">From</label>
            <input type="text" class="form-control" id="from" name="from" value="+16265401233" required>
        </div>

        <button type="button" class="btn btn-primary" @click="makeCall">Make Call</button>
    </div>
    <button id="buttonPlay" @click="playAll" :disabled="isPlayingAll || !tracks.length">Play All</button>
    <button id="buttonRecord" @click="startRecord" :disabled="isRecording">Record</button>
    <button id="buttonPause" @click="pauseOrStop" :disabled="!isRecording && !isPlayingAll">Pause/Stop</button>
    <div id="trackHolder">
        <template v-for="(track, index) in tracks" :key="index">
            <div class="track">
                <audio :src="track.src" ref="audioElements"></audio>
                <button class="track__button--play" @click="playTrack(index)" :disabled="track.isPlaying">Play</button>
                <button class="track__button--pause" @click="pauseTrack(index)"
                    :disabled="!track.isPlaying">Pause</button>
                <button class="track__button--remove" @click="removeTrack(index)">Remove</button>
            </div>
        </template>
    </div>
</template>

<script>
import axios from 'axios';
import { ref } from 'vue';
export default {
    data() {
        return {
            isRecording: false,
            isPlayingAll: false,
            mediaRecorder: null,
            chunks: [],
            tracks: [],
        };
    },
    mounted() {
        navigator.mediaDevices
            .getUserMedia({ audio: true })
            .then(this.initializeRecorder);
    },
    methods: {
        makeCall() {
            const data = {
                to: document.getElementById('to').value,
                from: document.getElementById('from').value,
            };
            axios.post('/api/make-call', data)
                .then(async (response) => {
                })
                .catch(async (error) => {
                });
        },
        initializeRecorder(stream) {
            this.mediaRecorder = new MediaRecorder(stream);
            this.mediaRecorder.ondataavailable = (e) => {
                this.chunks.push(e.data);
            };
            this.mediaRecorder.onstop = this.onStopRecording;
        },
        startRecord() {
            this.isRecording = true;
            this.mediaRecorder.start();
        },
        pauseOrStop() {
            if (this.isPlayingAll) {
                this.pauseAll();
            } else if (this.isRecording) {
                this.stopRecord();
            }
        },
        stopRecord() {
            this.isRecording = false;
            this.mediaRecorder.stop();
        },
        onStopRecording() {
            const blob = new Blob(this.chunks, { type: 'audio/ogg; codecs=opus' });
            this.chunks = [];
            this.addTrack(blob);
        },
        addTrack(blob) {
            const src = URL.createObjectURL(blob);
            this.tracks.push({ src, isPlaying: false });
        },
        playAll() {
            this.isPlayingAll = true;
            this.$refs.audioElements.forEach(audio => {
                audio.play();
            });
        },
        pauseAll() {
            this.isPlayingAll = false;
            this.$refs.audioElements.forEach(audio => {
                audio.pause();
            });
        },
        playTrack(index) {
            const track = this.tracks[index];
            this.$refs.audioElements[index].play();
            console.log(this.$refs.audioElements[index]);
            this.$set(track, 'isPlaying', true);
        },
        pauseTrack(index) {
            const track = this.tracks[index];
            this.$refs.audioElements[index].pause();
            this.$set(track, 'isPlaying', false);
        },
        removeTrack(index) {
            this.tracks.splice(index, 1);
        }
    }
};


const mediaRecorder = ref(null);
const stream = ref(null);
const chunks = ref([]);
const isRecording = ref(false);
const startRef = ref(Object);
const stopRef = ref(Object);
const recorderRef = ref();
const playerRef = ref();

class VoiceRecorder {
    constructor() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            console.log("getUserMedia supported")
        } else {
            console.log("getUserMedia is not supported on your browser!")
        }



        recorderRef.value = document.querySelector("#recorder")
        playerRef.value = document.querySelector("#player")
        startRef.value = document.querySelector("#start")
        stopRef.value = document.querySelector("#stop")

        startRef.onclick = this.startRecording.bind(this)
        stopRef.onclick = this.stopRecording.bind(this)

        this.constraints = {
            audio: true,
            video: false
        }

    }

    handleSuccess(stream_get) {
        stream.value = stream_get
        stream.value.oninactive = () => {
            console.log("Stream ended!")
        };
        recorderRef.value.srcObject = stream.value
        mediaRecorder.value = new MediaRecorder(stream.value)
        console.log(mediaRecorder.value)
        mediaRecorder.value.ondataavailable = this.onMediaRecorderDataAvailable.bind(this)
        mediaRecorder.value.onstop = this.onMediaRecorderStop.bind(this)
        recorderRef.value.play()
        mediaRecorder.value.start()
    }

    handleError(error) {
        console.log("navigator.getUserMedia error: ", error)
    }

    onMediaRecorderDataAvailable(e) { this.chunks.push(e.data) }

    onMediaRecorderStop(e) {
        const blob = new Blob(this.chunks, { 'type': 'audio/ogg; codecs=opus' })
        const audioURL = window.URL.createObjectURL(blob)
        playerRef.value.src = audioURL
        this.chunks = []
        stream.value.getAudioTracks().forEach(track => track.stop())
        stream.value = null
    }

    startRecording() {
        if (isRecording.value) return
        isRecording.value = true
        startRef.value.innerHTML = 'Recording...'
        playerRef.value.src = ''
        navigator.mediaDevices
            .getUserMedia(this.constraints)
            .then(this.handleSuccess.bind(this))
            .catch(this.handleError.bind(this))
    }

    stopRecording() {
        if (!isRecording.value) return
        isRecording.value = false
        startRef.value.innerHTML = 'Record'
        recorderRef.value.pause()
        mediaRecorder.value.stop()
    }

}

window.voiceRecorder = new VoiceRecorder()


</script>

<style scoped>
.example {
    text-align: center;
    margin-top: 50px;
}
</style>