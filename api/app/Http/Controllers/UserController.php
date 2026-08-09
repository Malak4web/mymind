<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function authorizeAdmin(Request $request)
    {
        if (!$request->user()->hasPermission('manage-users')) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        return response()->json(User::with('role')->get());
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id']
        ]);

        return response()->json($user->load('role'), 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role_id' => 'required|exists:roles,id'
        ]);

        $user->name = $validated['name'];
        if (!empty($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->role_id = $validated['role_id'];
        $user->save();

        return response()->json($user->load('role'));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeAdmin($request);

        $user = User::findOrFail($id);
        
        // Prevent deleting oneself
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'لا يمكنك حذف حسابك الشخصي أثناء تسجيل الدخول.'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }

    public function impersonate(Request $request, $id)
    {
        $this->authorizeAdmin($request);

        $targetUser = User::with('role')->findOrFail($id);

        // Create Sanctum token for target user
        $token = $targetUser->createToken('impersonated_by_' . $request->user()->id)->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح كـ ' . $targetUser->name,
            'token' => $token,
            'user' => $targetUser
        ]);
    }
}
