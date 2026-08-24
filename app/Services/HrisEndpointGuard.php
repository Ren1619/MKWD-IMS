<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Throwable;

class HrisEndpointGuard
{
    public function normalize(string $url): string
    {
        return Str::of($url)->trim()->rtrim('/')->toString();
    }

    public function validationError(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'The HRIS API base URL must be a valid HTTPS URL.';
        }

        $url = $this->normalize($value);

        try {
            $uri = Uri::of($url);
        } catch (Throwable) {
            return 'The HRIS API base URL must be a valid HTTPS URL.';
        }

        $host = Str::of((string) $uri->host())->trim('[]')->lower()->toString();

        if ($uri->scheme() !== 'https' || $host === '') {
            return 'The HRIS API base URL must use HTTPS.';
        }

        if ($uri->user() !== null || $uri->password() !== null) {
            return 'The HRIS API base URL cannot contain embedded credentials.';
        }

        if ((string) $uri->query() !== '' || filled($uri->fragment())) {
            return 'The HRIS API base URL cannot contain a query string or fragment.';
        }

        if ($this->isExplicitlyAllowed($host)) {
            return null;
        }

        if ($this->isLocalHostname($host)) {
            return 'Local and private HRIS hosts must be explicitly allowed by the server administrator.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return 'IP-address HRIS destinations must be explicitly allowed by the server administrator.';
        }

        return 'The HRIS API hostname is not in the server-approved host list.';
    }

    /** @return list<string> */
    public function allowedHosts(): array
    {
        $allowedHosts = [];

        foreach ($this->configuredAllowedHosts() as $allowedHost) {
            $normalizedHost = Str::lower(trim($allowedHost));

            if ($normalizedHost !== '') {
                $allowedHosts[] = $normalizedHost;
            }
        }

        $environmentBaseUrl = config('services.hris.base_url');

        if (is_string($environmentBaseUrl) && $environmentBaseUrl !== '') {
            try {
                $environmentHost = Str::lower((string) Uri::of($environmentBaseUrl)->host());

                if ($environmentHost !== '') {
                    $allowedHosts[] = $environmentHost;
                }
            } catch (Throwable) {
                // The configured URL will be rejected by the remaining validation checks.
            }
        }

        $allowedHosts = array_values(array_unique($allowedHosts));
        sort($allowedHosts);

        return $allowedHosts;
    }

    private function isExplicitlyAllowed(string $host): bool
    {
        return in_array($host, $this->allowedHosts(), true);
    }

    /** @return list<string> */
    private function configuredAllowedHosts(): array
    {
        $allowedHosts = config('services.hris.allowed_hosts', []);

        if (! is_array($allowedHosts)) {
            return [];
        }

        return array_values(array_filter($allowedHosts, is_string(...)));
    }

    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || Str::endsWith($host, ['.localhost', '.local', '.internal', '.test']);
    }
}
