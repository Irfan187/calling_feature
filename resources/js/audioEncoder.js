let pcmBuffer = new Uint8Array();
let residualPCMU = new Uint8Array();

self.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === "process") {
        try {
            // Accumulate input PCM data
            const processablePCM = accumulatePCMData(new Uint8Array(data));
            if (processablePCM.length === 0) return;

            // Convert to Int16 PCM
            const pcmData = new Int16Array(processablePCM.buffer);

            // Resample to 8 kHz
            const resampledPCM = resample(pcmData, 48000, 8000);

            // Encode to PCMU
            const pcMuData = encodeSamplesToPCMU(resampledPCM);

            // Slice into 800-byte packets
            const packets = sliceIntoPackets(pcMuData, 800);

            // Post the packets back to the main thread
            self.postMessage({ command: "processed", data: packets });
        } catch (error) {
            console.error("Error processing audio data:", error);
        }
    }
};

const accumulatePCMData = (newData) => {
    const chunkSize = 4800; // 100 ms at 48 kHz
    const combinedBuffer = new Uint8Array(pcmBuffer.length + newData.length);
    combinedBuffer.set(pcmBuffer);
    combinedBuffer.set(newData, pcmBuffer.length);

    const processableLength =
        Math.floor(combinedBuffer.length / chunkSize) * chunkSize;
    const processableData = combinedBuffer.subarray(0, processableLength);
    pcmBuffer = combinedBuffer.subarray(processableLength); // Save residual data

    return processableData;
};

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
    return ~(sign | (exponent << SEG_SHIFT) | mantissa) & 0xff;
};

const sliceIntoPackets = (data, packetSize) => {
    const combinedData = new Uint8Array(residualPCMU.length + data.length);
    combinedData.set(residualPCMU);
    combinedData.set(data, residualPCMU.length);

    const totalPackets = Math.floor(combinedData.length / packetSize);
    const processableLength = totalPackets * packetSize;

    const packets = [];
    for (let i = 0; i < processableLength; i += packetSize) {
        packets.push(combinedData.subarray(i, i + packetSize));
    }

    residualPCMU = combinedData.subarray(processableLength);
    return packets;
};
