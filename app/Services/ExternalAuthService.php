<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExternalAuthService
{
    /**
     * Attempt login against the external testagent User table.
     * Returns the synced local User on success, null on failure.
     */
    public function attempt(string $email, string $password): ?User
    {
        $external = DB::connection('mysql_external')
            ->table('User')
            ->where('email', $email)
            ->first();

        if (! $external) {
            return null;
        }

        // External passwords are bcrypt ($2b$ from Node — PHP password_verify handles both $2b$ and $2y$)
        if (! password_verify($password, $external->passwordHash)) {
            return null;
        }

        // Sync (find or create) the matching local user.
        // testagent is credential-only — OrbitRTMF owns the role assignment.
        $local = User::firstOrNew(['email' => $email]);

        $local->name     = $external->name;
        $local->password = Hash::make($password); // keep local password in sync

        if (! $local->exists) {
            // First-ever login: provision as User. Admin assigns to a project to grant access.
            $userRole        = DB::table('roles')->whereRaw("lower(name) = 'user'")->first();
            $local->role     = 'User';
            $local->role_id  = $userRole?->id;
            $local->is_active = true;
        }
        // Existing users: role/role_id are NOT touched — managed in OrbitRTMF only.

        $local->save();

        return $local;
    }
}
