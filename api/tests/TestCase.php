<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function authenticateUser($user = null)
    {
        if (!$user) {
            $role = Role::firstOrCreate(['name' => 'مدير'], ['description' => 'صلاحيات كاملة للنظام']);
            $user = User::firstOrCreate(
                ['email' => 'test_admin@mymind.com'],
                ['name' => 'Test Admin', 'password' => bcrypt('password123'), 'role_id' => $role->id]
            );
        }
        Sanctum::actingAs($user);
        return $user;
    }
}
