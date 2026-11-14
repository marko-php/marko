<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

use Marko\Core\Support\ErrorCapture;
use Marko\Webhook\Contracts\HostResolverInterface;

/**
 * Resolves hosts through the system resolver: IPv4 via gethostbynamel(), which reads
 * hostnames the way the HTTP client's resolver does, and IPv6 via AAAA records.
 */
class DnsHostResolver implements HostResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(
        string $host,
    ): array {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        // A failed lookup means "no addresses", which the policy rejects loudly, so lookup warnings are captured.
        $ipv4 = ErrorCapture::run($reason, fn (): array|false => gethostbynamel($host));

        if (is_array($ipv4)) {
            array_push($addresses, ...$ipv4);
        }

        $records = ErrorCapture::run($reason, fn (): array|false => dns_get_record($host, DNS_AAAA));

        if (is_array($records)) {
            foreach ($records as $record) {
                if (
                    isset($record['ipv6'])
                    && is_string($record['ipv6'])
                ) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
