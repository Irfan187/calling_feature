class PCMProcessor extends AudioWorkletProcessor {
    static get parameterDescriptors() {
        return [];
    }

    process(inputs, outputs, parameters) {
        const input = inputs[0];
        const sampleRate = 48000;

        const channelData = input[0];
        const downsampledBuffer = this.downsampleBuffer(
            channelData,
            sampleRate,
            8000
        );

        if (downsampledBuffer.length === 0) {
            return true; // Skip processing if downsampled buffer is empty
        }

        const pcmuData = this.convertToPCMU(downsampledBuffer);
        const rtpPacket = this.rtpPacketize(pcmuData);

        if (this.port) {
            this.port.postMessage({ rtpPacket });
        } else {
            console.warn("Port is not connected");
        }
        return true;
    }

    downsampleBuffer(buffer, inputSampleRate, outputSampleRate) {
        if (outputSampleRate === inputSampleRate) {
            return buffer;
        }

        const sampleRatio = inputSampleRate / outputSampleRate;
        const newLength = Math.floor(buffer.length / sampleRatio);
        const downsampledBuffer = new Float32Array(newLength);

        const filterLength = 21;
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
            downsampledBuffer[i] = sum;
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
        const packetSize = 800;
        return new Uint8Array(pcmuData.slice(0, packetSize));
    }
}

registerProcessor("pcmEncoder", PCMProcessor);
