import * as lame from "@breezystack/lamejs";

self.onmessage = async (event) => {
    const { command, data } = event.data;

    if (command === "process") {
        const arrayBuffer = await data.arrayBuffer();
        const mp3Data = await encodeToMP3(arrayBuffer);
        self.postMessage({ command: "processed", data: mp3Data });
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
