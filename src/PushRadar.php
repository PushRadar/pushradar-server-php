<?php

namespace PushRadar;

class PushRadar
{
    public static $version = '3.1.1';
    private $apiEndpoint = 'https://api.pushradar.com/v3';
    private $secretKey = null;

    public function __construct(string $secretKey)
    {
        $this->doCompatibilityCheck();

        if (trim($secretKey) === '' || !preg_match('/^sk_/', $secretKey)) {
            throw new PushRadarException("Please provide your PushRadar secret key. You can find it on the API page of your dashboard.");
        }

        $this->secretKey = $secretKey;
    }

    private function doCompatibilityCheck()
    {
        if (!extension_loaded('curl')) {
            throw new PushRadarException("PushRadar's PHP server library requires the curl module to be installed.");
        }

        if (!extension_loaded('json')) {
            throw new PushRadarException("PushRadar's PHP server library requires the json module to be installed.");
        }
    }

    private function validateDataSize($channelName, $data)
    {
        if ((strlen(serialize($data)) + strlen(serialize($channelName))) > (10 * 1024)) {
            throw new PushRadarException('Data size is greater than 10KiB. PushRadar only allows you to broadcast data up to 10KiB in one go.');
        }
    }

    private function validateClientDataSize($data)
    {
        if (strlen(serialize($data)) > 1024) {
            throw new PushRadarException('Client data size is greater than 1KiB. PushRadar only accepts client data < 1KiB in size.');
        }
    }

    private function validateChannelName($channelName)
    {
        if (!preg_match('/\A[-a-zA-Z0-9_=@,.;]+\z/', $channelName)) {
            throw new PushRadarException('Invalid channel name: ' . $channelName . '. Channel names cannot contain spaces, and must consist of only upper and lowercase ' .
                'letters, numbers, underscores, equals characters, @ characters, commas, periods, semicolons, and hyphens (A-Za-z0-9_=@,.;-).');
        }
    }

    public function broadcast(string $channelName, $data)
    {
        if (trim($channelName) === '') {
            throw new PushRadarException('Channel name empty. Please provide a channel name.');
        }

        $this->validateChannelName($channelName);
        $this->validateDataSize($channelName, $data);

        $response = $this->doCURL('POST', $this->apiEndpoint . "/broadcasts", [
            "channel" => $channelName,
            "data" => json_encode($data)
        ]);

        if ($response['status'] === 200) {
            return true;
        } else {
            throw new PushRadarException('An error occurred while calling the API. Server returned: ' . $response['body']);
        }
    }

    public function auth(string $channelName, string $socketID)
    {
        if (trim($channelName) === '') {
            throw new PushRadarException('Channel name empty. Please provide a channel name.');
        }

        if (!(substr($channelName, 0, strlen('private-')) === 'private-' || substr($channelName, 0, strlen('presence-')) === 'presence-')) {
            throw new PushRadarException('Channel authentication can only be used with private and presence channels.');
        }

        if (trim($socketID) === '') {
            throw new PushRadarException('Socket ID empty. Please pass through a socket ID.');
        }

        $response = $this->doCURL('GET', $this->apiEndpoint . "/channels/auth?channel=" . urlencode($channelName) . "&socketID=" . urlencode($socketID), []);
        if ($response['status'] === 200) {
            return json_decode($response['body'])->token;
        }

        throw new PushRadarException('There was a problem receiving a channel authentication token. Server returned: ' . $response['body']);
    }

    public function registerClientData(string $socketID, $clientData)
    {
        if (trim($socketID) === '') {
            throw new PushRadarException('Socket ID empty. Please pass through a socket ID.');
        }

        $this->validateClientDataSize($clientData);

        $response = $this->doCURL('POST', $this->apiEndpoint . "/client-data", [
            "socketID" => $socketID,
            "clientData" => json_encode($clientData)
        ]);

        if ($response['status'] === 200) {
            return true;
        } else {
            throw new PushRadarException('An error occurred while calling the API. Server returned: ' . $response['body']);
        }
    }

    private function doCURL($method, $url, $data)
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new PushRadarException('Could not initialise cURL.');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-PushRadar-Library: pushradar-server-php ' . self::$version
        ]);

        curl_setopt($ch, CURLOPT_USERPWD, $this->secretKey . ":");
        curl_setopt($ch, CURLOPT_URL, $url);

        if (strtolower($method) === 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POST, 0);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = [];
        $response['body'] = curl_exec($ch);
        $response['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $response;
    }
}