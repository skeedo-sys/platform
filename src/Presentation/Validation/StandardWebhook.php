<?php

namespace Presentation\Validation;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

class StandardWebhook
{
    private const SECRET_PREFIX = "whsec_";
    private const TOLERANCE = 5 * 60;
    private $secret;

    public function __construct(string $secret)
    {
        if (substr($secret, 0, strlen(self::SECRET_PREFIX)) === self::SECRET_PREFIX) {
            $secret = substr($secret, strlen(self::SECRET_PREFIX));
        }

        $this->secret = base64_decode($secret);
    }

    public function verify(ServerRequestInterface $request): ?stdClass
    {
        $payload = $request->getBody()->getContents();

        $headers = [
            'webhook-id',
            'webhook-timestamp',
            'webhook-signature'
        ];

        foreach ($headers as $name) {
            if (!$request->hasHeader($name)) {
                throw new Exception("Missing required {$name} header");
            }
        }

        $msgId = $request->getHeaderLine('webhook-id');
        $msgSignature = $request->getHeaderLine('webhook-signature');

        $msgTimestamp = $request->getHeaderLine('webhook-timestamp');
        $this->verifyTimestamp($msgTimestamp);

        $signature = $this->sign($msgId, $msgTimestamp, $payload);
        $expectedSignature = explode(',', $signature, 2)[1];

        $passedSignatures = explode(' ', $msgSignature);
        foreach ($passedSignatures as $versionedSignature) {
            $sigParts = explode(',', $versionedSignature, 2);
            $version = $sigParts[0];
            $passedSignature = $sigParts[1];

            if (strcmp($version, "v1") !== 0) {
                continue;
            }

            if (hash_equals($expectedSignature, $passedSignature)) {
                return json_decode($payload);
            }
        }

        throw new Exception("No matching signature found");
    }

    private function sign(
        string $msgId,
        string $timestamp,
        string $payload
    ): string {
        $toSign = $msgId . "." . $timestamp . "." . $payload;
        $hex_hash = hash_hmac('sha256', $toSign, $this->secret);
        $signature = base64_encode(pack('H*', $hex_hash));
        return "v1,{$signature}";
    }

    private function verifyTimestamp(string $timestampHeader): void
    {
        $now = time();
        $timestamp = intval($timestampHeader, 10);

        if ($timestamp < ($now - self::TOLERANCE)) {
            throw new Exception("Message timestamp too old");
        }

        if ($timestamp > ($now + self::TOLERANCE)) {
            throw new Exception("Message timestamp too new");
        }
    }
}
