<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse les URL internes (protection SSRF).
 *
 * Seules les URL publiques en http/https sont acceptées. Les hôtes locaux,
 * domaines internes, adresses IP privées/réservées et noms qui se résolvent vers
 * de telles adresses sont rejetés.
 */
class UrlPublique implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $fail('L\'URL est invalide.');

            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Seules les URL http et https sont acceptées.');

            return;
        }

        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if ($host === '' || $this->estHoteInterdit($host)) {
            $fail('Les URL internes ne sont pas autorisées.');
        }
    }

    public function estHoteInterdit(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        $reserves = [
            'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
            '0.0.0.0', '::1', 'broadcasthost', 'metadata.google.internal',
        ];

        if (in_array($host, $reserves, true)) {
            return true;
        }

        foreach (['.local', '.localdomain', '.internal', '.intranet', '.corp', '.home', '.lan', '.localhost'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        // Adresses IP littérales : on refuse toute plage privée ou réservée.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->estIpInterdite($host);
        }

        // Noms d'hôtes de la forme « 0x7f000001 », « 127.1 », « 2130706433 »…
        if (preg_match('/^(0x[0-9a-f]+|\d+)(\.(\d+|0x[0-9a-f]+))*$/i', $host)) {
            return true;
        }

        // Durcissement rebinding : on refuse un nom qui se résout vers une adresse
        // privée/réservée. Ce contrôle réduit le risque mais ne supprime pas le
        // TOCTOU entre validation DNS et connexion HTTP.
        $resolved = $this->resoudreAdresses($host);
        if ($resolved === []) {
            return true;
        }

        foreach ($resolved as $ip) {
            if ($this->estIpInterdite($ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resoudreAdresses(string $host): array
    {
        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        if ($ips === []) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) {
                $ips = array_merge($ips, $ipv4);
            }
        }

        return array_values(array_unique($ips));
    }

    private function estIpInterdite(string $ip): bool
    {
        $publique = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $publique === false;
    }
}
