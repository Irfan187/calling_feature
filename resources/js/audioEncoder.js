import * as lame from "@breezystack/lamejs";

self.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === "process") {
        const arrayBuffer = await data.arrayBuffer();
        //const mp3Data = await encodeToMP3(arrayBuffer);
        const pcmuData = await encodeToPCMU(arrayBuffer);
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
    const output = new Float32Array(Math.floor(input.length / ratio));

    for (let i = 0; i < output.length; i++) {
        output[i] = input[Math.round(i * ratio)];
    }

    return Int16Array.from(
        output.map((val) => Math.max(-32768, Math.min(32767, val)))
    );
};

const encodeToPCMU = async (buffer) => {
    const wav = lame.WavHeader.readHeader(new DataView(buffer));
    const samples = new Int16Array(buffer, wav.dataOffset, wav.dataLen / 2);

    const resampledSamples =
        wav.sampleRate !== 8000
            ? resample(samples, wav.sampleRate, 8000)
            : samples;

    const pcMuData = new Uint8Array(resampledSamples.length);
    for (let i = 0; i < resampledSamples.length; i++) {
        pcMuData[i] = linearToMuLaw(resampledSamples[i]);
    }

    return pcMuData;
};

const linearToMuLaw = (sample) => {
    const SIGN_BIT = 0x80;
    const QUANT_MASK = 0x0f;
    const SEG_SHIFT = 4;
    const SEG_MASK = 0x70;

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
