<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class AutomationProxyController extends Controller
{
    private const UPSTREAM = 'http://127.0.0.1:5678';

    public function __invoke(Request $request, ?string $path = null): Response
    {
        $targetPath = trim($path ?? '', '/');
        $targetUrl = self::UPSTREAM.($targetPath ? '/'.$targetPath : '/');

        try {
            $upstream = Http::withOptions([
                'allow_redirects' => false,
                'http_errors' => false,
            ])
                ->withHeaders($this->forwardHeaders($request))
                ->send($request->method(), $targetUrl, [
                    'query' => $request->query(),
                    'body' => $request->getContent(),
                ]);
        } catch (ConnectionException) {
            return response('Automation service is not available.', 502);
        }

        $response = response($upstream->body(), $upstream->status());

        foreach ($upstream->headers() as $name => $values) {
            if ($this->shouldSkipResponseHeader($name)) {
                continue;
            }

            foreach ((array) $values as $value) {
                $response->headers->set($name, $this->rewriteHeader($name, $value), false);
            }
        }

        return $response;
    }

    private function forwardHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if ($this->shouldSkipRequestHeader($name)) {
                continue;
            }

            $headers[$name] = implode(', ', $values);
        }

        return array_merge($headers, [
            'Host' => $request->getHost(),
            'X-Forwarded-Host' => $request->getHost(),
            'X-Forwarded-Proto' => $request->getScheme(),
            'X-Forwarded-Prefix' => '/automation',
            'X-Real-IP' => $request->ip(),
        ]);
    }

    private function shouldSkipRequestHeader(string $name): bool
    {
        return in_array(strtolower($name), [
            'connection',
            'content-length',
            'host',
        ], true);
    }

    private function shouldSkipResponseHeader(string $name): bool
    {
        return in_array(strtolower($name), [
            'connection',
            'content-encoding',
            'content-length',
            'keep-alive',
            'transfer-encoding',
        ], true);
    }

    private function rewriteHeader(string $name, string $value): string
    {
        if (strtolower($name) === 'location' && str_starts_with($value, '/')) {
            return '/automation'.$value;
        }

        return $value;
    }
}
