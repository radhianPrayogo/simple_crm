<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends BaseController
{
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            // Get the authenticated user.
            $user = auth()->user();
            if (!$user->hasRole('super-admin') && !$user->employee->company) {
                return $this->sendError('Invalid credentials', $user->employee->company, 401);
            }

            //Attach the role to the token.
            $user_role = $user->getRoleNames();
            $token = JWTAuth::claims(['role' => $user_role[0]])->fromUser($user);

            $result = [
                'name' => $user->employee ? $user->employee->name : ucfirst(str_replace('-', ' ', $user_role[0])),
                'token' => $token
            ];
            return $this->sendResponse('User login successfully', $result);
        } catch (JWTException $e) {
            return $this->sendError('Could not create token', [], 500);
        }
    }

    /**
     * Logout api
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->sendResponse('Successfully logged out');
    }
}
