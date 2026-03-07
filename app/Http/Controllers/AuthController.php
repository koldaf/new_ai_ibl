<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    public function ShowLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Implement login logic here
        $credentials = $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
        ]);
         if(Auth::attempt($credentials, $request->filled('remember'))){
            // regenerate session to prevent fixation attacks
            $request->session()->regenerate();

            // redirect based on role; the route middleware will also enforce this
            $user = Auth::user();
            if ($user->hasRole('admin')) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('student.lessons.index'));
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function showRegister()
    {
        return view('auth.register');
        // Implement registration logic here
    }

    public function register(Request $request)
    {
        // dump & die removed; confirmation field renamed in form so validator works
        // Validate the request data
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        // Create a new user (you can use Eloquent or any other method)
        // User::create([
        //     'name' => $request->name,
        //     'email' => $request->email,
        //     'password' => Hash::make($request->password),
        // ]);

        // Redirect to login page after successful registration
        return redirect()->route('login')->with('success', 'Registration successful. Please log in.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
   
}
