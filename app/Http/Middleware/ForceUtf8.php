<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get("Content-Type");
        if ($contentType && str_contains($contentType, "application/json")) {
            if (!str_contains($contentType, "charset")) {
                $response->headers->set("Content-Type", "application/json; charset=utf-8");
            }
        } elseif (!$contentType) {
            $response->headers->set("Content-Type", "text/html; charset=utf-8");
        } else {
            if (!str_contains($contentType, "charset")) {
                $response->headers->set("Content-Type", $contentType . "; charset=utf-8");
            }
        }

        return $response;
    }
}

