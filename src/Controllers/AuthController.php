<?php

namespace App\Controllers;

use App\Auth\GoogleAuthService;
use App\Auth\JwtService;
use App\Auth\PasswordService;
use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use PDO;

class AuthController extends AbstractController
{
    private JwtService $jwt;
    private PasswordService $password;
    private GoogleAuthService $google;
    private UserRepository $users;

    public function __construct(PDO $pdo, JwtService $jwt, PasswordService $password, GoogleAuthService $google)
    {
        parent::__construct($pdo);
        $this->jwt = $jwt;
        $this->password = $password;
        $this->google = $google;
        $this->users = new UserRepository($pdo);
    }

    public function register(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('full_name')->string('full_name', 150)
            ->required('email')->email()
            ->required('password')->minLen('password', 8)
            ->optional('role')->in('role', ['resident', 'rescuer'])
            ->optional('phone_number')->string('phone_number', 20)
            ->optional('address')->string('address', 1000);

        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $email = strtolower(trim($req->body['email']));

        if ($this->users->findByEmail($email)) {
            Response::error('EMAIL_TAKEN', 'Email already registered', 409);
            return;
        }

        $role = $req->body['role'] ?? 'resident';
        $accountStatus = $role === 'rescuer' ? 'pending' : 'active';

        $id = $this->users->create([
            'id' => Database::uuidV4(),
            'full_name' => trim($req->body['full_name']),
            'email' => $email,
            'password_hash' => $this->password->hash($req->body['password']),
            'auth_provider' => 'native',
            'phone_number' => $req->body['phone_number'] ?? null,
            'address' => $req->body['address'] ?? null,
            'role' => $role,
            'account_status' => $accountStatus,
        ]);

        $user = $this->users->find($id);
        $userData = $user->toArray();

        $tokens = $this->issueTokens($userData);
        Response::success(['user' => $userData, 'tokens' => $tokens], 201);
    }

    public function login(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('email')->email()->required('password')->string('password', 255);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $user = $this->users->findByEmail(strtolower(trim($req->body['email'])));
        if (!$user || !$this->password->verify($req->body['password'], (string) $user->passwordHash())) {
            Response::error('INVALID_CREDENTIALS', 'Email or password is incorrect', 401);
            return;
        }
        if ($user->accountStatus() !== 'active') {
            error_log('[ACCOUNT_PENDING] account_status=' . $user->accountStatus());
            Response::error('ACCOUNT_PENDING', 'This account is not active.', 403);
            return;
        }

        $userData = $user->toArray();
        $tokens = $this->issueTokens($userData);
        Response::success(['user' => $userData, 'tokens' => $tokens]);
    }

    public function google(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('id_token')->string('id_token', 2000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $payload = $this->google->verifyIdToken($req->body['id_token']);
        if ($payload === null) {
            Response::error('GOOGLE_AUTH_FAILED', 'Google ID token verification failed', 401);
            return;
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            Response::error('GOOGLE_AUTH_FAILED', 'Google token missing email', 401);
            return;
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            $user = $this->users->findByGoogleId($payload['sub'] ?? '');
        }

        if (!$user) {
            $id = $this->users->create([
                'id' => Database::uuidV4(),
                'full_name' => $payload['name'] ?? 'Google User',
                'email' => $email,
                'password_hash' => null,
                'auth_provider' => 'google',
                'google_id' => $payload['sub'] ?? null,
                'profile_photo_url' => $payload['picture'] ?? null,
                'role' => 'resident',
                'account_status' => 'active',
            ]);
            $user = $this->users->find($id);
        }

        if ($user->accountStatus() !== 'active') {
            error_log('[ACCOUNT_PENDING] account_status=' . $user->accountStatus());
            Response::error('ACCOUNT_PENDING', 'This account is not active.', 403);
            return;
        }

        $userData = $user->toArray();
        $tokens = $this->issueTokens($userData);
        Response::success(['user' => $userData, 'tokens' => $tokens]);
    }

    public function refresh(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('refresh_token')->string('refresh_token', 1000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $payload = $this->jwt->verifyRefreshToken($req->body['refresh_token']);
        if ($payload === null) {
            Response::error('INVALID_REFRESH_TOKEN', 'Refresh token invalid or expired', 401);
            return;
        }

        $user = $this->users->find($payload['sub']);
        if (!$user || $user->accountStatus() !== 'active') {
            Response::error('INVALID_REFRESH_TOKEN', 'User not available', 401);
            return;
        }

        $userData = $user->toArray();
        Response::success([
            'access_token' => $this->jwt->issueAccessToken($userData),
            'user' => $userData,
        ]);
    }

    private function issueTokens(array $user): array
    {
        return [
            'access_token' => $this->jwt->issueAccessToken($user),
            'refresh_token' => $this->jwt->issueRefreshToken($user),
            'token_type' => 'Bearer',
        ];
    }
}
