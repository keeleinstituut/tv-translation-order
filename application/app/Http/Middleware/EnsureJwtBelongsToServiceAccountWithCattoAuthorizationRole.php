<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use KeycloakAuthGuard\Services\Decoders\RequestBasedJwtTokenDecoder;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the inbound Catto-authorization-delegation endpoint. Deliberately checks a
 * dedicated realm role (not the existing sync role) so Catto's service account gets
 * access to only this one endpoint, not the sync endpoints too.
 */
readonly class EnsureJwtBelongsToServiceAccountWithCattoAuthorizationRole
{
    public function __construct(private RequestBasedJwtTokenDecoder $jwtDecoder)
    {
    }

    /**
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAuthorized()) {
            throw new AuthorizationException("You don't have the corresponding role to perform the action", 403);
        }

        return $next($request);
    }

    private function isAuthorized(): bool
    {
        $decodedJwt = $this->jwtDecoder->getDecodedJwtWithSpecifiedValidation(false, true);

        abort_if(empty($decodedJwt), 401);

        return $this->hasCattoAuthorizationRole($decodedJwt);
    }

    private function hasCattoAuthorizationRole(stdClass $decodedJwt): bool
    {
        $role = config('keycloak.catto_authorization_role');

        return isset($decodedJwt->realm_access->roles)
            && filled($decodedJwt->realm_access->roles)
            && is_array($decodedJwt->realm_access->roles)
            && filled($role)
            && in_array($role, $decodedJwt->realm_access->roles);
    }
}
