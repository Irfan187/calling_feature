class PCMProcessor extends AudioWorkletProcessor {
    constructor() {
        super();
    }

    process(inputs, outputs, parameters) {
        const input = inputs[0];
        if (!input || !input[0]) {
            return true; // No input; skip processing
        }

        const channelData = input[0]; // Get audio data from the first channel

        // Send raw audio data to the main thread
        this.port.postMessage({
            rawAudio: Array.from(channelData), // Convert Float32Array to plain array
        });

        return true; // Keep the processor running
    }
}

registerProcessor("pcmEncoder", PCMProcessor);
