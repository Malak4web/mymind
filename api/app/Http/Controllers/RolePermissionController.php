<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function indexRoles(Request $request)
    {
        return response()->json(Role::with('permissions')->get());
    }

    public function indexPermissions(Request $request)
    {
        return response()->json(Permission::all());
    }
}
