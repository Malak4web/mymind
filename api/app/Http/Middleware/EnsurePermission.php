<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Reject the request unless the authenticated user holds the permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'يجب تسجيل الدخول للمتابعة.');
        }

        if (! $user->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }

        return $next($request);
    }
}
