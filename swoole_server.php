<?php

use Swoole\Http\Server;

$http = new Server("0.0.0.0", 9090);

$http->on("request", function ($request, $response) {
    $apacheHost = "127.0.0.1";
    $apachePort = 8888;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$apacheHost:$apachePort" . $request->server['request_uri']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->server['request_method']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    // Forward headers
    $headers = [];
    foreach ($request->header as $key => $value) {
        $headers[] = "$key: $value";
    }

    // Add Content-Length for POST requests
    if (isset($request->header['content-length'])) {
        $headers[] = "Content-Length: " . $request->header['content-length'];
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Forward POST data
    if ($request->server['request_method'] === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->rawContent());
    }

    $responseFromApache = curl_exec($ch);

    if ($responseFromApache === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        $response->status(500);
        $response->end("CURL Error: $curlError");
        return;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($responseFromApache, 0, $headerSize);
    $body = substr($responseFromApache, $headerSize);

    // Handle Gzip encoding
    if (strpos($headers, "Content-Encoding: gzip") !== false) {
        $body = gzdecode($body);
    }

    curl_close($ch);

    // Forward status code and body to Nginx
    $response->status($statusCode);
    $response->end($body);
});

$http->start();
