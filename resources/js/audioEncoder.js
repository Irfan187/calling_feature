let pcmBuffer = new Uint8Array();

self.onmessage = async (event) => {
    let { command, data } = event.data;

    if (command === "process") {
        try {
            if (data.byteLength % 2 !== 0) {
                data = data.slice(0, data.byteLength - 1);
            }

            const newBuffer = new Uint8Array(data);

            // Accumulate incoming data
            const combinedBuffer = new Uint8Array(
                pcmBuffer.length + newBuffer.length
            );
            combinedBuffer.set(pcmBuffer);
            combinedBuffer.set(newBuffer, pcmBuffer.length);

            // Ensure enough data for 100 ms chunks at 48 kHz
            const chunkSize = 4800; // 100 ms at 48 kHz
            const processableLength =
                Math.floor(combinedBuffer.length / chunkSize) * chunkSize;
            const processableData = combinedBuffer.subarray(
                0,
                processableLength
            );
            pcmBuffer = combinedBuffer.subarray(processableLength); // Save residual data

            // Convert to Int16 PCM
            const pcmData = new Int16Array(processableData.buffer);

            // Resample to 8 kHz
            const resampledPCM = resample(pcmData, 48000, 8000);

            // Encode to PCMU
            const pcMuData = encodeSamplesToPCMU(resampledPCM);

            // Ensure 100 ms packet (800 samples at 8 kHz)
            const packetSize = 800; // 100 ms at 8 kHz
            const packets = sliceIntoPackets(pcMuData, packetSize);

            self.postMessage({ command: "processed", data: packets });
        } catch (error) {
            console.error("Error processing audio data:", error);
        }
    }
};

// Resample PCM data to 8 kHz
const resample = (input, fromRate, toRate) => {
    const ratio = fromRate / toRate;
    const outputLength = Math.floor(input.length / ratio);
    const output = new Int16Array(outputLength);

    for (let i = 0; i < outputLength; i++) {
        const nearestSample = Math.round(i * ratio);
        output[i] = input[nearestSample] || 0;
    }

    return output;
};

// Encode Int16 PCM to PCMU
const encodeSamplesToPCMU = (samples) => {
    const pcMuData = new Uint8Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        pcMuData[i] = linearToMuLaw(samples[i]);
    }
    return pcMuData;
};

const linearToMuLaw = (sample) => {
    const SIGN_BIT = 0x80;
    const QUANT_MASK = 0x0f;
    const SEG_SHIFT = 4;

    const MAX = 32635;
    sample = Math.max(-MAX, Math.min(MAX, sample));

    let sign = sample < 0 ? SIGN_BIT : 0;
    if (sign) sample = -sample;

    sample += 33;

    let exponent = 7;
    for (
        let expMask = 0x4000;
        (sample & expMask) === 0 && exponent > 0;
        expMask >>= 1
    ) {
        exponent--;
    }

    const mantissa = (sample >> (exponent + 3)) & QUANT_MASK;
    const muLawSample = ~(sign | (exponent << SEG_SHIFT) | mantissa);

    return muLawSample & 0xff;
};

// Slice PCMU into 100 ms packets
const sliceIntoPackets = (data, packetSize) => {
    const packets = [];
    for (let i = 0; i < data.length; i += packetSize) {
        packets.push(data.subarray(i, i + packetSize));
    }
    return packets;
};
