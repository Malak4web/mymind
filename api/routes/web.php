<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/public-users', function (Request $request) {
    if ($request->query('key') !== 'admin@mymind') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $users = User::with('role:id,name')->get(['id', 'name', 'email', 'role_id', 'created_at']);

    $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Public Users</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem}
        h1{text-align:center;margin-bottom:2rem;color:#38bdf8}
        table{width:100%;border-collapse:collapse;background:#1e293b;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.3)}
        th{background:#334155;padding:14px 16px;text-align:right;color:#94a3b8;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em}
        td{padding:12px 16px;border-bottom:1px solid #334155}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#263348}
        .role{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600}
        .role-admin{background:#7c3aed22;color:#a78bfa;border:1px solid #7c3aed55}
        .role-member{background:#0891b222;color:#67e8f9;border:1px solid #0891b255}
        .role-viewer{background:#65a30d22;color:#a3e635;border:1px solid #65a30d55}
        .email{color:#38bdf8;font-family:monospace}
        .copy-btn{background:none;border:1px solid #475569;color:#94a3b8;padding:2px 8px;border-radius:4px;cursor:pointer;font-size:.75rem;margin-right:6px}
        .copy-btn:hover{background:#334155;color:#e2e8f0}
        .note{text-align:center;margin-top:1.5rem;color:#64748b;font-size:.85rem}
    </style></head><body>
    <h1>👥 المستخدمين</h1>
    <table><thead><tr><th>#</th><th>الاسم</th><th>الإيميل</th><th>الدور</th><th>تاريخ الإنشاء</th></tr></thead><tbody>';

    foreach ($users as $u) {
        $roleName = $u->role->name ?? '—';
        $roleClass = match($roleName) {
            'مدير' => 'role-admin',
            'عضو' => 'role-member',
            default => 'role-viewer',
        };
        $html .= "<tr>
            <td>{$u->id}</td>
            <td>{$u->name}</td>
            <td><span class=\"email\">{$u->email}</span></td>
            <td><span class=\"role {$roleClass}\">{$roleName}</span></td>
            <td>{$u->created_at->format('Y-m-d')}</td>
        </tr>";
    }

    $html .= '</tbody></table>
    <p class="note">⚠️ هذه الصفحة للتشخيص فقط — احذفها بعد الاستخدام</p>
    <script>
    document.querySelectorAll(".copy-btn").forEach(b=>b.onclick=()=>{navigator.clipboard.writeText(b.dataset.v);b.textContent="✓"});
    </script></body></html>';

    return response($html);
});
