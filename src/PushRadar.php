<?php

namespace PushRadar;

class PushRadar
{
    public static $version = '3.0.0-alpha.2';
    private $apiEndpoint = 'https://api.pushradar.com/v3';
    private $secretKey = null;

    public function __construct($secretKey)
    {
        $this->doCompatibilityCheck();

        if (!$secretKey || !preg_match('/^sk_/', $secretKey)) {
            throw new PushRadarException("Please provide your PushRadar secret key. You can find it on the API page of your dashboard.");
        }

        $this->secretKey = trim($secretKey);
    }

    private function doCompatibilityCheck()
    {
        if (!extension_loaded('curl')) {
            throw new PushRadarException("PushRadar's PHP server library requires the cURL module to be installed.");
        }

        if (!extension_loaded('json')) {
            throw new PushRadarException("PushRadar's PHP server library requires the json module to be installed.");
        }
    }

    private function validateDataSize($channelName, $data)
    {
        $dataSize = (strlen(serialize($data)) + strlen(serialize($channelName))) / 1024;
        if ($dataSize > 10) {
            throw new PushRadarException('Data size is greater than 10KiB. PushRadar only allows you to broadcast data up to 10KiB in one go.');
        }
    }

    private function validateChannelName($channelName)
    {
        if (!preg_match('/\A[-a-zA-Z0-9_=@,.;]+\z/', $channelName)) {
            throw new PushRadarException('Invalid channel name: ' . $channelName . '. Channel names cannot contain spaces, and must consist of only upper and lowercase ' .
                'letters, numbers, underscores, equals characters, @ characters, commas, periods, semicolons, and hyphens (A-Za-z0-9_=@,.;-).');
        }
    }

    public function broadcast($channelName, $data)
    {
        if (trim($channelName) === '') {
            throw new PushRadarException('Channel name empty. Please provide a channel name.');
        }

        $this->validateChannelName($channelName);
        $this->validateDataSize($channelName, $data);

        $response = $this->doCURL('POST', $this->apiEndpoint . "/broadcasts", [
            "channel" => trim($channelName),
            "data" => $data
        ]);

        if ($response['status'] === 200) {
            return true;
        } else {
            throw new PushRadarException('An error occurred while calling the API. Server returned: ' . $response['body']);
        }
    }

    public function auth($channelName)
    {
        if (trim($channelName) === '') {
            throw new PushRadarException('Channel name empty. Please provide a channel name.');
        }

        if (substr($channelName, 0, strlen('private-')) !== 'private-') {
            throw new PushRadarException('Channel authentication can only be used with private channels.');
        }

        $response = $this->doCURL('GET', $this->apiEndpoint . "/channels/auth?channel=" . urlencode(trim($channelName)), []);
        if ($response['status'] === 200) {
            return json_decode($response['body'])->token;
        }

        throw new PushRadarException('There was a problem receiving a channel authentication token. Server returned: ' . $response['body']);
    }

    private function doCURL($method, $url, $data)
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new PushRadarException('Could not initialise cURL.');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-PushRadar-Library: pushradar-server-php ' . self::$version
        ));

        curl_setopt($ch, CURLOPT_USERPWD, $this->secretKey . ":");
        curl_setopt($ch, CURLOPT_URL, $url);

        if (strtolower($method) === 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POST, 0);
        }

        $certificateBundle = dirname(__FILE__) . '/../ca-bundle.pem';
        curl_setopt($ch, CURLOPT_CAINFO, $certificateBundle);
        curl_setopt($ch, CURLOPT_CAPATH, $certificateBundle);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = [];
        $response['body'] = curl_exec($ch);
        $response['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $response;
    }
}