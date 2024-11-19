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
    const outputLength = Math.floor(input.length / ratio);

    const output = new Int16Array(outputLength);
    for (let i = 0; i < outputLength; i++) {
        const nearestSample = Math.round(i * ratio);
        output[i] = input[nearestSample] || 0;
    }

    return output;
};

const encodeToPCMU = async (buffer) => {
    return new Promise((resolve, reject) => {
        const wav = lame.WavHeader.readHeader(new DataView(buffer));

        if (wav.bitsPerSample !== 16) {
            throw new Error("Only 16-bit PCM audio is supported.");
        }

        const samples = new Int16Array(buffer, wav.dataOffset, wav.dataLen / 2);

        const resampledSamples =
            wav.sampleRate !== 8000
                ? resample(samples, wav.sampleRate, 8000)
                : samples;

        const pcMuData = encodeSamplesToPCMU(resampledSamples);

        const packetSize = 8000 * 0.02;
        const packets = sliceIntoPackets(pcMuData, packetSize);

        resolve(packets);
    });
};

const encodeSamplesToPCMU = (samples) => {
    const pcMuData = new Uint8Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        pcMuData[i] = linearToMuLaw(samples[i]);
    }
    return pcMuData;
};

const sliceIntoPackets = (data, packetSize) => {
    const packets = [];
    for (let i = 0; i < data.length; i += packetSize) {
        packets.push(data.subarray(i, i + packetSize));
    }
    return packets;
};
