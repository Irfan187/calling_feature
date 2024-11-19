self.onmessage = async (event) => {
    let { command, data } = event.data;

    if (command === "process") {
        try {
            if (data.byteLength % 2 !== 0) {
                console.warn(
                    "ArrayBuffer byte length is not a multiple of 2. Trimming extra byte."
                );
                data = data.slice(0, data.byteLength - (data.byteLength % 2));
            }
            const pcmuPackets = await encodeToPCMU(data);
            self.postMessage({ command: "processed", data: pcmuPackets });
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

// Encode the input audio data to PCMU (G.711 μ-law) format
const encodeToPCMU = async (buffer) => {
    // Convert ArrayBuffer to Int16Array for PCM processing
    const pcmData = new Int16Array(buffer);
    console.log("Input PCM data length:", pcmData.length);
    
    // Assuming a sample rate (MediaRecorder does not provide sample rate directly)
    const assumedSampleRate = 48000; // Default assumed MediaRecorder sample rate
    const targetSampleRate = 8000;

    // Resample to 8 kHz if necessary
    const resampledPCM =
        assumedSampleRate !== targetSampleRate
            ? resample(pcmData, assumedSampleRate, targetSampleRate)
            : pcmData;

    // Encode PCM data to PCMU
    const pcMuData = encodeSamplesToPCMU(resampledPCM);

    // Slice PCMU data into 20 ms packets (160 samples per packet at 8 kHz)
    const packetSize = 160; // 20 ms at 8 kHz
    return sliceIntoPackets(pcMuData, packetSize);
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
