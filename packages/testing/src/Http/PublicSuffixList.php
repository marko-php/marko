<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

/**
 * A small built-in subset of the Public Suffix List (https://publicsuffix.org), enough for
 * the TestClient cookie jar to reject `Domain=co.uk`-style cookies and to tell same-site
 * from cross-site requests.
 *
 * Every single-label domain (`com`, `uk`, `test`, `localhost`) is a public suffix, as the
 * list's default `*` rule says. Multi-label suffixes are limited to the common ones below;
 * a suffix missing from this list is treated as a registrable domain.
 */
class PublicSuffixList
{
    /** @var list<string> */
    private const array MULTI_LABEL_SUFFIXES = [
        // United Kingdom
        'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'ac.uk', 'gov.uk', 'nhs.uk', 'police.uk', 'sch.uk',
        // Australia and New Zealand
        'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au', 'id.au', 'asn.au',
        'co.nz', 'net.nz', 'org.nz', 'govt.nz', 'ac.nz',
        // Asia
        'co.jp', 'ne.jp', 'or.jp', 'ac.jp', 'go.jp', 'gr.jp',
        'co.kr', 'or.kr', 'ne.kr', 'go.kr',
        'com.cn', 'net.cn', 'org.cn', 'gov.cn', 'edu.cn',
        'com.hk', 'org.hk', 'net.hk', 'com.tw', 'org.tw', 'net.tw', 'com.sg', 'org.sg', 'net.sg', 'edu.sg',
        'co.in', 'net.in', 'org.in', 'firm.in', 'gen.in', 'ind.in', 'ac.in', 'gov.in',
        'co.id', 'or.id', 'web.id', 'com.my', 'net.my', 'org.my', 'com.ph', 'co.th', 'in.th', 'com.vn',
        // Americas
        'com.br', 'net.br', 'org.br', 'gov.br', 'com.mx', 'org.mx', 'gob.mx', 'com.ar', 'org.ar', 'gob.ar',
        'com.co', 'net.co', 'org.co', 'gov.co', 'com.pe', 'cl.cl', 'qc.ca', 'on.ca', 'bc.ca', 'ab.ca',
        // Europe, Middle East and Africa
        'com.tr', 'org.tr', 'gen.tr', 'co.il', 'org.il', 'ac.il', 'com.ua', 'org.ua', 'com.pl', 'net.pl', 'org.pl',
        'co.za', 'org.za', 'gov.za', 'ac.za', 'com.eg', 'com.ng', 'co.ke', 'com.sa', 'co.at', 'or.at', 'com.es',
        'com.gr', 'com.pt', 'co.hu', 'com.ru', 'org.ru',
        // Shared hosting, where each customer gets a subdomain
        'github.io', 'gitlab.io', 'herokuapp.com', 'vercel.app', 'netlify.app', 'pages.dev', 'workers.dev',
        'web.app', 'firebaseapp.com', 'appspot.com', 'azurewebsites.net', 'cloudfront.net', 'fly.dev',
        'onrender.com', 'blogspot.com', 'ngrok.io', 'ngrok-free.app',
    ];

    /**
     * Whether $domain (lowercase, no leading dot) is a public suffix: a domain under which
     * anyone can register names, so a cookie must not be scoped to it. IP addresses never are.
     */
    public static function isPublicSuffix(
        string $domain,
    ): bool {
        if (self::isIpAddress($domain)) {
            return false;
        }

        return !str_contains($domain, '.')
            || in_array($domain, self::MULTI_LABEL_SUFFIXES, true);
    }

    /**
     * The site of $host for same-site checks: its registrable domain (the public suffix plus
     * one more label), so `shop.example.co.uk` is `example.co.uk`. A host that is itself a
     * public suffix (`localhost`) or an IP address is its own site.
     */
    public static function site(
        string $host,
    ): string {
        if (
            self::isIpAddress($host)
            || self::isPublicSuffix($host)
        ) {
            return $host;
        }

        $labels = explode('.', $host);

        // Walk from the longest candidate suffix down; the first public suffix found is the longest.
        for ($i = 1; $i < count($labels); $i++) {
            if (self::isPublicSuffix(implode('.', array_slice($labels, $i)))) {
                return implode('.', array_slice($labels, $i - 1));
            }
        }

        return $host;
    }

    private static function isIpAddress(
        string $host,
    ): bool {
        return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
    }
}
