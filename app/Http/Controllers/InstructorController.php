<?php

namespace App\Http\Controllers;

use App\Models\User;

class InstructorController extends Controller
{
    public function show(User $instructor)
    {
        abort_unless($instructor->role === 'instructor', 404);

        $courses = $instructor->courses()
            ->with(['category', 'instructor'])
            ->where('status', 'published')
            ->latest()
            ->get();

        return view('instructors.show', compact('instructor', 'courses'));
    }
}
