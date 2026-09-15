<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUtf8Encoding
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType === '') {
            return $response;
        }

        if (
            str_starts_with($contentType, 'text/') ||
            str_contains($contentType, 'application/json') ||
            str_contains($contentType, 'application/javascript') ||
            str_contains($contentType, 'application/xml')
        ) {
            if (! str_contains(strtolower($contentType), 'charset=')) {
                $response->headers->set('Content-Type', rtrim($contentType, '; ') . '; charset=UTF-8');
            }
        }

        return $response;
    }
}