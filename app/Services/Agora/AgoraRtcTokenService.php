<?php

namespace App\Services\Agora;

use InvalidArgumentException;
use RuntimeException;

class AgoraRtcTokenService
{
    private const VERSION = '007';
    private const SERVICE_RTC = 1;
    private const PRIVILEGE_JOIN_CHANNEL = 1;
    private const PRIVILEGE_PUBLISH_AUDIO_STREAM = 2;
    private const PRIVILEGE_PUBLISH_VIDEO_STREAM = 3;
    private const PRIVILEGE_PUBLISH_DATA_STREAM = 4;

    public function buildRtcToken(
        string $appId,
        string $appCertificate,
        string $channelName,
        int $uid,
        bool $canPublish,
        int $expiresInSeconds,
    ): string {
        $this->assertUuid($appId, 'Agora App ID');
        $this->assertUuid($appCertificate, 'Agora App Certificate');

        if ($channelName === '') {
            throw new InvalidArgumentException('Agora channel name is required.');
        }

        if ($uid < 1) {
            throw new InvalidArgumentException('Agora uid must be a positive integer.');
        }

        if ($expiresInSeconds < 1) {
            throw new InvalidArgumentException('Agora token TTL must be a positive integer.');
        }

        $issuedAt = time();
        $salt = random_int(1, 99999999);
        $privileges = [
            self::PRIVILEGE_JOIN_CHANNEL => $expiresInSeconds,
        ];

        if ($canPublish) {
            $privileges[self::PRIVILEGE_PUBLISH_AUDIO_STREAM] = $expiresInSeconds;
            $privileges[self::PRIVILEGE_PUBLISH_VIDEO_STREAM] = $expiresInSeconds;
            $privileges[self::PRIVILEGE_PUBLISH_DATA_STREAM] = $expiresInSeconds;
        }

        $serviceRtc = self::packUint16(self::SERVICE_RTC)
            . self::packMapUint32($privileges)
            . self::packString($channelName)
            . self::packString((string) $uid);

        $payload = self::packString($appId)
            . self::packUint32($issuedAt)
            . self::packUint32($expiresInSeconds)
            . self::packUint32($salt)
            . self::packUint16(1)
            . $serviceRtc;

        $signature = hash_hmac('sha256', $payload, $this->signingKey($appCertificate, $issuedAt, $salt), true);
        $compressed = zlib_encode(self::packString($signature) . $payload, ZLIB_ENCODING_DEFLATE);

        if ($compressed === false) {
            throw new RuntimeException('Unable to compress Agora token payload.');
        }

        return self::VERSION . base64_encode($compressed);
    }

    private function signingKey(string $appCertificate, int $issuedAt, int $salt): string
    {
        $hash = hash_hmac('sha256', $appCertificate, self::packUint32($issuedAt), true);

        return hash_hmac('sha256', $hash, self::packUint32($salt), true);
    }

    private function assertUuid(string $value, string $label): void
    {
        if (strlen($value) !== 32 || ! ctype_xdigit($value)) {
            throw new InvalidArgumentException($label . ' must be a 32-character hexadecimal value.');
        }
    }

    private static function packString(string $value): string
    {
        return self::packUint16(strlen($value)) . $value;
    }

    private static function packMapUint32(array $values): string
    {
        ksort($values);

        $packed = self::packUint16(count($values));

        foreach ($values as $key => $value) {
            $packed .= self::packUint16((int) $key) . self::packUint32((int) $value);
        }

        return $packed;
    }

    private static function packUint16(int $value): string
    {
        return pack('v', $value);
    }

    private static function packUint32(int $value): string
    {
        return pack('V', $value);
    }
}
