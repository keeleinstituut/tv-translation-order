<?php

return [
    /*
     * Keycloak realm role that Catto's service account must hold to call the
     * inbound Catto-authorization-delegation endpoint (/api/catto-authorization).
     * Deliberately separate from service_account_sync_role so Catto's service
     * account only gets access to this one endpoint, not the sync endpoints.
     */
    'catto_authorization_role' => env('KEYCLOAK_CATTO_AUTHORIZATION_ROLE', ''),
];
