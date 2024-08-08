import { createServer } from "https";
import { readFileSync } from "fs";
import { server as WebSocketServer } from "websocket";
import ffmpegInstaller from "@ffmpeg-installer/ffmpeg";
import ffmpeg from "fluent-ffmpeg";

import { AudioBuffer } from "./audioBuffer.js";
import { pcmuToMp3Base64 } from "./audioConversion.js";

ffmpeg.setFfmpegPath(ffmpegInstaller.path);

// Server SSL config
let httpsOptions = {
    key: readFileSync(
        "/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.key"
    ),
    cert: readFileSync(
        "/etc/nginx/ssl/callingfeature.scrumad.com/2279529/server.crt"
    ),
};

// Server http request handler (ignore all)
let httpsServer = createServer(httpsOptions, function (request, response) {
    response.end();
});

// Start http server (listening on port 3001)
httpsServer.listen(3001, function () {
    console.log(new Date() + " Server is listening on port 3001");
});

// WS server config
let wsServer = new WebSocketServer({
    httpServer: httpsServer,
    autoAcceptConnections: false,
});

// WS request handler
wsServer.on("request", function (request) {
    const connection = request.accept(null, request.origin);

    const audioBuffer = new AudioBuffer(async (combinedChunks) => {
        pcmuToMp3Base64(combinedChunks, (mp3Base64, error) => {
            if (error) {
                console.error("Failed to convert audio:", error);
                return;
            }
            connection.send(
                JSON.stringify({
                    event: "media",
                    media: {
                        payload: mp3Base64,
                    },
                })
            );
        });
    });

    /* Message handler */
    connection.on("message", function (data) {
        /* Forward all messages to client */
        let eventData = JSON.parse(data.utf8Data);
        console.log(eventData);
        if (eventData.event == "media") {
            const chunk = Buffer.from(eventData.payload, "base64");
            const sequenceNumber = data.sequence_number;
            audioBuffer.add(chunk, sequenceNumber);
        } else if (eventData.event == "stop") {
            audioBuffer.flush();
            connection.send(data.utf8Data);
        } else {
            connection.send(data.utf8Data);
        }
    });

    /* Close connection handler */
    connection.on("close", function (reasonCode, description) {});
});
