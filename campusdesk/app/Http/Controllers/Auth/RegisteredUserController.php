<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'matricule' => ['required', 'string', 'unique:student_profiles'],
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'programme_id' => ['required', 'exists:programmes,id'],
            'level' => ['required', 'in:L100,L200,L300,L400,L500,L600'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
            'role' => 'student',
        ]);
         
        // dd($request->all());
         $student = StudentProfile::create([
            'user_id' => $user->id,
            'faculty_id' => $request->faculty_id,
            'department_id' => $request->department_id,
            'programme_id' => $request->programme_id,
            'matricule' => $request->matricule,
            'level' => $request->level,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }
}
