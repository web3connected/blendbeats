<?php

namespace App\Services\Live;

use App\Models\LiveStream;
use App\Services\Agora\AgoraRtcTokenService;
use InvalidArgumentException;

class AgoraService
{
    public function __construct(
        private readonly AgoraRtcTokenService $tokens,
    ) {}

    public function assertConfigured(): void
    {
        $this->assertCredential(config('services.agora.app_id'), 'Agora App ID');
        $this->assertCredential(config('services.agora.app_certificate'), 'Agora App Certificate');

        if ((int) config('services.agora.token_ttl', 3600) < 1) {
            throw new InvalidArgumentException('Agora token TTL must be a positive integer.');
        }
    }

    public function tokenForStream(LiveStream $stream, string $role): array
    {
        $this->assertConfigured();

        if (! in_array($role, ['host', 'audience'], true)) {
            throw new InvalidArgumentException('Unsupported Agora live role.');
        }

        $ttl = (int) config('services.agora.token_ttl', 3600);
        $uid = random_int(1, 2147483647);
        $appId = (string) config('services.agora.app_id', '');
        $appCertificate = (string) config('services.agora.app_certificate', '');

        return [
            'appId' => $appId,
            'channelName' => $stream->agora_channel_name,
            'expiresAt' => now()->addSeconds($ttl)->toIso8601String(),
            'role' => $role,
            'token' => $this->tokens->buildRtcToken(
                appId: $appId,
                appCertificate: $appCertificate,
                channelName: $stream->agora_channel_name,
                uid: $uid,
                canPublish: $role === 'host',
                expiresInSeconds: $ttl,
            ),
            'uid' => $uid,
        ];
    }

    private function assertCredential(mixed $value, string $label): void
    {
        if (! is_string($value) || strlen($value) !== 32 || ! ctype_xdigit($value)) {
            throw new InvalidArgumentException($label.' must be a 32-character hexadecimal value.');
        }
    }
}
