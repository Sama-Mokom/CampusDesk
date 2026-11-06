<?php

namespace App\Services\Payments\Concerns;

trait ResolvesFrontendUrl
{
    /**
     * Resolve the frontend base URL dynamically from the current HTTP request,
     * falling back to the configured app.frontend_url if the origin is missing or untrusted.
     */
    protected function resolveFrontendUrl(): string
    {
        $defaultUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        $origin = request()?->header('Origin');
        if (! $origin && request()?->header('Referer')) {
            $parsed = parse_url((string) request()->header('Referer'));
            if (! empty($parsed['scheme']) && ! empty($parsed['host'])) {
                $origin = $parsed['scheme'] . '://' . $parsed['host'] . (! empty($parsed['port']) ? ':' . $parsed['port'] : '');
            }
        }

        if ($origin && $this->isAllowedFrontendOrigin($origin)) {
            return rtrim($origin, '/');
        }

        return $defaultUrl;
    }

    /**
     * Validate whether the detected origin belongs to an allowed frontend host.
     */
    protected function isAllowedFrontendOrigin(string $origin): bool
    {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if (! $originHost) {
            return false;
        }

        $defaultHost = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        $allowedHosts = array_filter([
            'localhost',
            '127.0.0.1',
            $defaultHost,
        ]);

        return in_array(strtolower($originHost), array_map('strtolower', $allowedHosts), true);
    }
}
