<?php

namespace App\Middleware;

use App\Auth\JwtService;
use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use PDO;

class AuthMiddleware
{
    private JwtService $jwt;
    private UserRepository $users;

    public function __construct(PDO $pdo, JwtService $jwt)
    {
        $this->jwt = $jwt;
        $this->users = new UserRepository($pdo);
    }

    public function __invoke(Request $request): ?Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return Response::error('UNAUTHENTICATED', 'Missing bearer token', 401);
        }

        $payload = $this->jwt->verifyAccessToken($token);
        if ($payload === null) {
            return Response::error('UNAUTHENTICATED', 'Invalid or expired token', 401);
        }

        $user = $this->users->find($payload['sub']);
        if (!$user) {
            return Response::error('UNAUTHENTICATED', 'User not found', 401);
        }

        if ($user->accountStatus() !== 'active') {
            error_log('[ACCOUNT_PENDING] account_status=' . $user->accountStatus());
            return Response::error('ACCOUNT_PENDING', 'This account is not active.', 403);
        }

        $request->user = $user->toArray();
        $request->permissions = Permissions::resolve($user->role());
        return null;
    }
}
