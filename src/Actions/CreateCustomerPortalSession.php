<?php

declare(strict_types=1);

namespace Vatly\Fluent\Actions;

use Vatly\API\Types\PortalSession;

class CreateCustomerPortalSession extends BaseAction
{
    /**
     * Open a short-lived, single-use hosted customer portal session.
     *
     * Redirect the customer's browser to the returned {@see PortalSession::$url}.
     * The link is locked to this customer, expires after roughly 15 minutes, and
     * can be consumed once — it is credential-bearing, so do not cache or log it.
     * Pass `returnUrl` (an absolute HTTPS URL) in `$data` to render a return link
     * in the portal.
     *
     * @param array<string, mixed> $data  Optional body (`returnUrl`).
     */
    public function execute(string $customerId, array $data = []): PortalSession
    {
        return $this->guardApiCall(
            fn () => $this->vatlyApiClient->customers->createPortalSession($customerId, $data),
        );
    }
}
