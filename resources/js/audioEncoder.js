import * as lame from "@breezystack/lamejs";

self.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === "process") {
        //const mp3Data = await encodeToMP3(arrayBuffer);
        const pcmuData = await encodeToPCMU(data);
        self.postMessage({ command: "processed", data: pcmuData });
    }
};

const encodeToMP3 = (buffer) => {
    return new Promise((resolve, reject) => {
        const wav = lame.WavHeader.readHeader(new DataView(buffer));
        const samples = new Int16Array(buffer, wav.dataOffset, wav.dataLen / 2);
        const mp3Encoder = new lame.Mp3Encoder(
            wav.channels,
            wav.sampleRate,
            128
        );
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

        const blob = new Blob(mp3Data, { type: "audio/mp3" });
        const reader = new FileReader();
        reader.onload = () => {
            const base64data = reader.result.split(",")[1];
            resolve(base64data);
        };
        reader.readAsDataURL(blob);
    });
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

const encodeToPCMU = async (buffer) => {
    // Decode the raw audio to PCM
    const pcmData = new Int16Array(buffer); // Assuming Int16 PCM input

    // Resample to 8 kHz if necessary
    const resampledPCM =
        pcmData.length > 0 && pcmData.sampleRate !== 8000
            ? resample(pcmData, pcmData.sampleRate, 8000)
            : pcmData;

    // Encode PCM data to PCMU
    const pcMuData = encodeSamplesToPCMU(resampledPCM);

    // Slice PCMU data into 20 ms packets (160 samples at 8 kHz)
    const packetSize = 8000 * 0.02; // 160 samples per packet
    return sliceIntoPackets(pcMuData, packetSize);
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
    const packets = [];
    for (let i = 0; i < data.length; i += packetSize) {
        packets.push(data.subarray(i, i + packetSize));
    }
    return packets;
};
