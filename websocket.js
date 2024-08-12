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

let userClient;
let telnyxClient;
let clients = {};

// WS request handler
wsServer.on("request", function (request) {
    const connection = request.accept(null, request.origin);

    if (request.origin?.includes("scrumad.com")) {
        userClient = connection;
    } else {
        telnyxClient = connection;
    }

    const audioBuffer = new AudioBuffer(async (combinedChunks) => {
        pcmuToMp3Base64(combinedChunks, (mp3Base64, error) => {
            if (error) {
                console.error("Failed to convert audio:", error);
                return;
            }
            userClient?.send(
                JSON.stringify({
                    event: "media",
                    media: {
                        payload: mp3Base64,
                    },
                })
            );
        });
    }, 160);

    /* Message handler */
    connection.on("message", function (data) {
        // let length = Object.keys(clients).length;
        // let call_id = 'call_id_from_data';
        // if(length == 0){
        //     clients["call_id"] = {
        //         "user_client": "3456hhhh",
        //         "telnyx_client": "345t5555555"
        //     };
        //     connection = clients['call_id']['telnyx_client'];
        //     telnyxClient = connection;
        // }else{
        //     if(call_id in clients){
        //         if (request.origin?.includes("scrumad.com")) {
        //             userClient = clients['call_id']['user_client'];
        //         } else {
        //             telnyxClient = clients['call_id']['telnyx_client'];
        //         }
        //     }else{
        //         clients["call_id"] = call_id;
        //         if (request.origin?.includes("scrumad.com")) {
        //             clients['call_id']['user_client'] = connection;
        //             userClient = clients['call_id']['user_client'];
        //         } else {
        //             clients['call_id']['telnyx_client'] = connection;
        //             telnyxClient = clients['call_id']['telnyx_client'];
        //         }
        //     }
        // }
        console.log(data.utf8Data);
        if (connection == userClient) {
            telnyxClient.send(data.utf8Data);
        } else if (connection == telnyxClient) {
            /* Forward all messages to client */
            let eventData = JSON.parse(data.utf8Data);
            if (eventData.event == "media") {
                const chunk = Buffer.from(eventData.media.payload, "base64");
                const sequenceNumber = eventData.sequence_number;
                audioBuffer.add(chunk, sequenceNumber);
            } else if (eventData.event == "stop") {
                audioBuffer.flush();
                userClient?.send(data.utf8Data);
            } else if (eventData.event == "error") {
            } else {
                userClient?.send(data.utf8Data);
            }
        }

    });

    /* Close connection handler */
    connection.on("close", function (reasonCode, description) { });
});
