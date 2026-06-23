<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->whereIn('role', ['student', 'teacher']);

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where('name', 'like', '%' . $search . '%');
        }

        if ($request->filled('role')) {
            $role = $request->string('role')->toString();
            if (in_array($role, ['student', 'teacher'], true)) {
                $query->where('role', $role);
            }
        }

        $users = $query->withCount('assignedLessons')->orderBy('name')->paginate(20)->withQueryString();

        return view('dashboard.admin.users.index', [
            'users' => $users,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'role' => (string) $request->input('role', ''),
            ],
        ]);
    }

    public function create()
    {
        return view('dashboard.admin.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'role'                  => 'required|in:student,teacher',
            'password'              => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name'     => $validated['name'],
            'role'     => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        abort_if($user->role === 'admin', 403);

        $lessons     = Lesson::orderBy('title')->get();
        $assignedIds = $user->assignedLessons()->pluck('lessons.id')->all();

        return view('dashboard.admin.users.edit', compact('user', 'lessons', 'assignedIds'));
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:student,teacher',
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'lessons' => 'nullable|array',
            'lessons.*' => 'exists:lessons,id',
        ]);

        $user->name = $validated['name'];
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Only sync lesson assignments for teacher role
        if ($user->role === 'teacher') {
            $lessonIds = array_map('intval', $validated['lessons'] ?? []);
            $user->assignedLessons()->sync($lessonIds);
        } else {
            // Remove all assignments when demoted to student
            $user->assignedLessons()->sync([]);
        }

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        abort_if((int) $user->id === (int) Auth::id(), 403, 'You cannot delete your own account.');
        abort_if($user->role === 'admin', 403);

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
