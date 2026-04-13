<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * GET /api/staff
     *
     * Returns all ACTIVE users whose role links to a role-specific profile table
     * (cl, subcl, etc.). "Active" is determined by users.status = 'active'.
     *
     * Assumed schema
     * ──────────────
     * users  : id, firstName, lastName, profilePhoto, role (FK→roles.roleId), status
     * roles  : roleId, name, slug, table_name
     * cl     : clId, userId, state, lga, ...
     * subcl  : subclId, userId, state, lga, ...
     */

    private const USER_FK_COLUMN    = 'userId';
    private const ACTIVE_STATUS      = 'active';   // adjust if your status values differ
    // private const INCLUDED_ROLE_SLUGS = ['cls', 'sub_cls'];

    public function index(): JsonResponse
    {
        $roles = DB::table('roles')
            ->whereNotNull('table_name')
            ->where('table_name', '!=', '')
            // ->whereIn('slug', self::INCLUDED_ROLE_SLUGS)
            ->get();

        if ($roles->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $staff = collect();

        foreach ($roles as $role) {
            $tableName = $role->table_name;

            if (!$this->tableExists($tableName)) {
                continue;
            }

            $rows = DB::table("{$tableName} as rp")
                ->join('users as u', 'u.id', '=', 'rp.' . self::USER_FK_COLUMN)
                // ── Only active users ──────────────────────────────────────────
                ->where('u.status', self::ACTIVE_STATUS)
                ->select(
                    'u.id',
                    DB::raw("TRIM(CONCAT(COALESCE(u.firstName,''), ' ', COALESCE(u.lastName,''))) as name"),
                    DB::raw("'{$role->roleName}' as role"),
                    // DB::raw("'{$role->slug}' as roleSlug"),
                    'u.photo as image',
                    'rp.state',
                    'rp.lga',
                )
                ->get();

            $staff = $staff->merge($rows);
        }

        $staff = $staff->sortBy('name')->values()->map(fn ($s) => [
            'id'       => $s->id,
            'name'     => $s->name,
            'role'     => $s->role,
            // 'roleSlug' => $s->roleSlug,
            'image'    => $s->image,
            'state'    => $s->state ?? null,
            'lga'      => $s->lga   ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $staff]);
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}