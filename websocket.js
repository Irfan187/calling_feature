import { createServer } from "https";
import { readFileSync } from "fs";
import { server as WebSocketServer } from "websocket";

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

// Function to check connection origin and blacklist as required
function originIsAllowed(origin) {
    return true;
}

// Clients array
let clients = [];

// WS request handler
wsServer.on("request", function (request) {
    /* Close connection if origin in blaclist */
    if (!originIsAllowed(request.origin)) {
        request.reject();
        console.log(
            new Date() +
            " Connection from origin " +
            request.origin +
            " rejected."
        );
        return;
    }

    /* Accept otherwise */
    const connection = request.accept(null, request.origin);
    clients.push(connection);
    console.log(new Date() + " Connection accepted.");

    /* Message handler */
    connection.on("message", function (data) {
        /* Forward all messages to client */
        clients.forEach((client) => {
            if (client !== connection && client.connected) {
                client.send(JSON.stringify(data.utf8Data));
            }
        });

        // Record our audio
        navigator.mediaDevices
                .getUserMedia({ audio: true })
                .then(app);
        const parsedData = JSON.parse(data.utf8Data);

            const app = function (stream) {
                let mediaRecorder = new MediaRecorder(stream);
                let chunks = [];

                if(parsedData.event == 'start'){
                    mediaRecorder.start();
                    mediaRecorder.ondataavailable = function (e) {
                        chunks.push(e.data);
                    }
    
                    
                }

                if(parsedData.event == 'stop'){
                    mediaRecorder.stop();

                    mediaRecorder.onstop = function (e) {
                        let blob = new Blob(chunks, {
                            'type': 'audio/ogg; codecs=opus',
                        });
    
                        chunks = [];
    
                        var audio = URL.createObjectURL(blob);
                        audio.play();
                    };
                }
            };

            

            
    });

    /* Close connection handler */
    connection.on("close", function (reasonCode, description) {
        console.log(
            new Date() + " Peer " + connection.remoteAddress + " disconnected."
        );
        clients = clients.filter((client) => client !== connection);
    });
});
