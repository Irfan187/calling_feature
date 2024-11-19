let pcmBuffer = new Uint8Array();
let residualPCM = new Uint8Array();

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

            const chunkSize = 4800; // 100 ms at 48 kHz
            const processableLength =
                Math.floor(combinedBuffer.length / chunkSize) * chunkSize;
            const processableData = combinedBuffer.subarray(
                0,
                processableLength
            );
            pcmBuffer = combinedBuffer.subarray(processableLength);

            // Convert to Int16 PCM
            const pcmData = new Int16Array(processableData.buffer);

            console.log(
                "Input PCM Data (first 10 samples):",
                pcmData.slice(0, 10)
            );
            console.log("Input PCM Data Length:", pcmData.length);

            // Resample to 8 kHz
            const resampledPCM = resample(pcmData, 48000, 8000);

            console.log(
                "Resampled PCM Data (first 10 samples):",
                resampledPCM.slice(0, 10)
            );
            console.log("Resampled PCM Data Length:", resampledPCM.length);

            // Encode to PCMU
            const pcMuData = encodeSamplesToPCMU(resampledPCM);
            
            console.log(
                "Encoded PCMU Data (first 10 samples):",
                pcMuData.slice(0, 10)
            );
            console.log("Encoded PCMU Data Length:", pcMuData.length);

            // Slice into 100 ms packets
            const packets = sliceIntoPackets(pcMuData, 800); // 800 samples at 8 kHz

            self.postMessage({ command: "processed", data: packets });
        } catch (error) {
            console.error("Error processing audio data:", error);
        }
    }
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
    const muLawSample = ~(sign | (exponent << SEG_SHIFT) | mantissa);

    return muLawSample & 0xff;
};

const sliceIntoPackets = (data, packetSize) => {
    const combinedData = new Uint8Array(residualPCM.length + data.length);
    combinedData.set(residualPCM);
    combinedData.set(data, residualPCM.length);

    const totalPackets = Math.floor(combinedData.length / packetSize);
    const processableLength = totalPackets * packetSize;

    const packets = [];
    for (let i = 0; i < processableLength; i += packetSize) {
        packets.push(combinedData.subarray(i, i + packetSize));
    }

    residualPCM = combinedData.subarray(processableLength);

    console.log("Total Packets:", packets.length);
    console.log("Residual Data Length:", residualPCM.length);

    return packets;
};
