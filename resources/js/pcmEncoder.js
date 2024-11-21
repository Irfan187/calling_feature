class PCMProcessor extends AudioWorkletProcessor {
    constructor() {
        super();
        this.buffer = [];
        this.targetSamples = 160; // 20ms of audio at 8kHz
        this.lastPacketTime = 0; // Last time a packet was sent
        this.timerInitialized = false; // Ensure timer is only set up once
    }

    process(inputs, outputs, parameters) {
        const input = inputs[0];
        if (!input || !input[0]) {
            return true;
        }

        // Accumulate downsampled audio into the buffer
        const channelData = input[0];
        const downsampledBuffer = this.downsampleBuffer(
            channelData,
            sampleRate,
            8000
        );
        this.buffer.push(...downsampledBuffer);

        // Start the timer if not already initialized
        if (!this.timerInitialized) {
            this.startPacketTimer();
            this.timerInitialized = true;
        }

        return true;
    }

    startPacketTimer() {
        const intervalMs = 20; // 20ms for 8kHz packets

        const sendPacket = () => {
            if (this.buffer.length >= this.targetSamples) {
                // Prepare a packet from the buffer
                const packet = this.buffer.splice(0, this.targetSamples);
                const pcmuPacket = this.convertToPCMU(packet);
                const rtpPacket = this.rtpPacketize(pcmuPacket);

                // Send the packet
                this.port.postMessage({ rtpPacket });
            }

            // Re-schedule the next packet
            setTimeout(sendPacket, intervalMs);
        };

        // Start the first packet timer
        setTimeout(sendPacket, intervalMs);
    }

    downsampleBuffer(buffer, inputSampleRate, outputSampleRate) {
        if (outputSampleRate === inputSampleRate) {
            return buffer;
        }

        const sampleRatio = inputSampleRate / outputSampleRate;
        const newLength = Math.floor(buffer.length / sampleRatio);
        const downsampledBuffer = new Float32Array(newLength);

        const filterLength = Math.ceil(
            (inputSampleRate / outputSampleRate) * 10
        );
        const cutoffFreq = outputSampleRate / 2;
        const filter = this.designFIRFilter(
            filterLength,
            cutoffFreq,
            inputSampleRate
        );

        for (let i = 0; i < newLength; i++) {
            const start = Math.floor(i * sampleRatio);
            let sum = 0;
            for (let j = 0; j < filter.length; j++) {
                if (start + j < buffer.length) {
                    sum += buffer[start + j] * filter[j];
                }
            }
            downsampledBuffer[i] = Math.max(-1, Math.min(1, sum)); // Clamp to [-1, 1]
        }

        return downsampledBuffer;
    }

    designFIRFilter(length, cutoff, sampleRate) {
        const filter = new Float32Array(length);
        const middle = Math.floor(length / 2);
        for (let i = 0; i < length; i++) {
            if (i === middle) {
                filter[i] = (2 * cutoff) / sampleRate;
            } else {
                const numerator = Math.sin(
                    (2 * Math.PI * cutoff * (i - middle)) / sampleRate
                );
                const denominator = Math.PI * (i - middle);
                filter[i] = numerator / denominator;
            }
            filter[i] *=
                0.54 - 0.46 * Math.cos((2 * Math.PI * i) / (length - 1));
        }
        return filter;
    }

    convertToPCMU(buffer) {
        const PCM_MAX = 32767; // Maximum PCM value
        const PCM_MIN = -32768; // Minimum PCM value

        const muLawEncode = (sample) => {
            const MU = 255;
            const clamped = Math.max(Math.min(sample, PCM_MAX), PCM_MIN);
            const magnitude =
                Math.log(1 + MU * Math.abs(clamped / PCM_MAX)) /
                Math.log(1 + MU);
            const sign = clamped < 0 ? 0 : 0x80;
            return ~(sign | (magnitude * 0x7f));
        };

        return new Uint8Array(
            buffer.map((sample) => muLawEncode(sample * PCM_MAX))
        );
    }

    rtpPacketize(pcmuData) {
        const packetSize = 160; // Matches the target sample size
        return new Uint8Array(pcmuData.slice(0, packetSize));
    }
}

registerProcessor("pcmEncoder", PCMProcessor);
