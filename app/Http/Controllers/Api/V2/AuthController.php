<?php

namespace App\Http\Controllers\Api\V2;

use App\Helper\ResponseHelper;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Concerns\Authenticator;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{

    use Authenticator;

    // public function login(Request $request)
    // {
    //     $user = User::where('email', $request->get('email'))->first();

    //     if (!$user) {
    //         return $this->apiError('invalid_credentials', 401);
    //     }

    //     if (!\Hash::check($request->get('password'), $user->password)) {
    //         return $this->apiError('invalid_credentials', 401);
    //     }
    //     try {
    //         $token = JWTAuth::fromUser($user);
    //     } catch (JWTException $e) {
    //         // something went wrong whilst attempting to encode the token
    //         return $this->apiError('could_not_create_token', 500);
    //     }

    //     // all good so return the token
    //     return $this->apiSuccess(compact('token'));
    // }
    
    public function login(Request $request) {
        
        try {
            $user = User::where('email', $request->email)->first();
            if(!$user){
                return ResponseHelper::response(
                    'User tidak ditemukan!', // messages
                    null, // errors
                    null, // data
                    404 // status_code
                );
            }
            if( Hash::check($request->password,$user->password) ){

                // Hitung timestamp 100 tahun dari sekarang
                $exp = Carbon::now()->addYears(100)->timestamp;

                $token = JWTAuth::fromUser($user, ['exp' => $exp]);

                $data = [
                    'login_type' => 'login',
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $exp,
                ];

                return ResponseHelper::response(
                    'Login berhasil!', // messages
                    null, // errors
                    $data, // data
                    200 // status_code
                );
    
            }else{
                
                return ResponseHelper::response(
                    'Email atau kata sandi salah!', // messages
                    null, // errors
                    null, // data
                    404 // status_code
                );
    
            }
        }
         catch (\Throwable $th) {
            return ResponseHelper::response(
                'Error Internal Server', // messages
                $th->getMessage(), // errors
                null, // data
                500 // status_code
            );
        }
    }


    public function logout(Request $request) {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return ResponseHelper::response(
                'Logout berhasil!', // messages
                null, // errors
                null, // data
                200 // status_code
            );
        } catch (\Throwable $th) {
            return ResponseHelper::response(
                'Error Internal Server', // messages
                $th->getMessage(), // errors
                null, // data
                500 // status_code
            );
        }
    }
    
    public function me(Request $request) {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return ResponseHelper::response(
                'Data user berhasil diambil!', // messages
                null, // errors
                $user, // data
                200 // status_code
            );
        } catch (\Throwable $th) {
            return ResponseHelper::response(
                'Error Internal Server', // messages
                $th->getMessage(), // errors
                null, // data
                500 // status_code
            );
        }
    }

}
