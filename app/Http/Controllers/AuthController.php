<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use  App\User;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function register(Request $request)
    {

        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed',
        ]);

        try {

            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $plainPassword = $request->input('password');
            $user->password = app('hash')->make($plainPassword);

            $user->balance = 20000; //give new user 20k for testing

            $user->save();


            return response()->json(['user' => $user, 'message' => 'USER CREATED'], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'User Registration Failed!'], 409);
        }


   }

    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            "user" => $user
        ], 200);
    }


    public function login(Request $request)
    {
          //validate incoming request
        $this->validate($request, [
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['email', 'password']);

        try {
            if (! $token = Auth::setTTL(7200)->attempt($credentials)) {
                return response()->json(['message' => 'Invalid Credentials'], 401);
            }
            $user = User::where("email", $request->email)->first();

        return $this->respondWithToken($token, $user);
        } catch (\Throwable $th) {
            \Log::debug($th->getMessage());
             return response()->json(['message' => 'Server Error'], 500);
        }


    }
}
