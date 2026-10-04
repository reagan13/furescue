<?php

namespace App\Middleware;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;

class PermissionMiddleware
{
    private string $permission;

    public function __construct(string $permission)
    {
        $this->permission = $permission;
    }

    public function __invoke(Request $request): ?Response
    {
        if ($request->user === null) {
            return Response::error('UNAUTHENTICATED', 'Authentication required', 401);
        }
        if (!Permissions::has($request->permissions, $this->permission)) {
            error_log('[FORBIDDEN] missing permission ' . $this->permission);
            return Response::error('FORBIDDEN', 'You do not have permission to do that.', 403);
        }
        return null;
    }
}
