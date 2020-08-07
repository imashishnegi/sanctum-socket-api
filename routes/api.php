<?php

use App\Events\MessageSent;
use App\Events\UserLoggedIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Message;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', function (Request $request) {
    $request->validate([
        'name' => ['required'],
        'email' => ['required', 'email', 'unique:users'],
        'password' => ['required', 'min:8', 'confirmed']
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password)
    ]);

    return response()->json($user);
});

Route::post('/login', function (Request $request) {


    $request->validate([
        'email' => ['required'],
        'password' => ['required']
    ]);

    if (Auth::attempt($request->only('email', 'password'))) {
        $user = Auth::user();
        UserLoggedIn::dispatch($user);
        return response()->json(Auth::user(), 200);
    }

    throw ValidationException::withMessages([
        'email' => ['The provided credentials are incorrect.']
    ]);
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json([
        "token" => $user->createToken($request->device_name)->plainTextToken
    ]);
});

Route::middleware('auth:sanctum')->post('/payment', function (Request $request) {

    $request->validate([
        'source' => 'required',
        'amount' => 'required'
    ]);

    // convert amount to cents
    $amount = $request->amount * 100;
    $stripeKey = 'sk_test_4eC39HqLyjWDarjtT1zdp7dc';

    $stripe = new \Stripe\StripeClient($stripeKey);
    try {
        //code...
        $charge = $stripe->charges->create([
            'amount' => $amount,
            'currency' => 'usd',
            'description' => 'Example charge',
            'source' => $request->source
        ]);
        $request->user()->credit += $request->amount;
        $request->user()->save();
    } catch (\Throwable $th) {
        abort($th->getHttpStatus(), $th->getMessage());
    }
});

Route::middleware('auth:sanctum')->post('/update', function (Request $request) {

    $request->validate([
        'name' => 'required'
    ]);

    $request->user()->name = $request->name;
    $request->user()->save();
});

Route::middleware('auth:sanctum')->get('messages', function(Request $request) {
    return Message::with('user')->get();
});
Route::middleware('auth:sanctum')->post('messages', function(Request $request) {
    $user = $request->user();

    $message = $user->messages()->create([
      'message' => $request->input('message')
    ]);

    MessageSent::broadcast($user, $message)->toOthers();

    return ['status' => 'Message Sent!'];
});
