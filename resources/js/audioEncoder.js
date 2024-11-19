let pcmBuffer = new Uint8Array();

self.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === "process") {
        try {
            const newBuffer = new Uint8Array(data);
            const combinedBuffer = new Uint8Array(
                pcmBuffer.length + newBuffer.length
            );
            combinedBuffer.set(pcmBuffer);
            combinedBuffer.set(newBuffer, pcmBuffer.length);

            // Ensure the buffer length is a multiple of 2
            const processableLength =
                combinedBuffer.length - (combinedBuffer.length % 2);
            const processableData = combinedBuffer.subarray(
                0,
                processableLength
            );

            // Save residual data
            pcmBuffer = combinedBuffer.subarray(processableLength);

            // Process aligned PCM data
            const pcmData = new Int16Array(processableData.buffer);
            const resampledPCM = resample(pcmData, 48000, 8000);
            const pcMuData = encodeSamplesToPCMU(resampledPCM);
            const packets = sliceIntoPackets(pcMuData, 160); // 160 samples per packet (20 ms at 8 kHz)

            self.postMessage({ command: "processed", data: packets });
        } catch (error) {
            console.error("Error processing audio data:", error);
        }
    }
};

// Resample the input PCM data to 8 kHz if necessary
const resample = (input, fromRate, toRate) => {
    const ratio = fromRate / toRate;
    const outputLength = Math.floor(input.length / ratio);

    console.log("Resampling from", fromRate, "to", toRate);
    console.log("Input length:", input.length);

    const output = new Int16Array(outputLength);
    for (let i = 0; i < outputLength; i++) {
        const nearestSample = Math.round(i * ratio);
        output[i] = input[nearestSample] || 0;
    }

    console.log("Output length:", output.length);

    return output;
};

// Convert Int16 PCM samples to PCMU (G.711 μ-law)
const encodeSamplesToPCMU = (samples) => {
    const pcMuData = new Uint8Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        pcMuData[i] = linearToMuLaw(samples[i]);
    }
    return pcMuData;
};

// Linear PCM to μ-law conversion
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

// Slice encoded PCMU data into packets of the specified size
const sliceIntoPackets = (data, packetSize) => {
    const packets = [];
    for (let i = 0; i < data.length; i += packetSize) {
        const packet = data.subarray(i, i + packetSize);
        packets.push(packet);

        if (packet.length !== packetSize) {
            console.warn("Packet with incorrect size:", packet.length);
        }
    }

    console.log("Total packets:", packets.length);
    return packets;
};
