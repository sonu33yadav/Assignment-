<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\Roleuser;

class RoleManagementMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
         $user = auth()->user();
         if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
          $userRole = Roleuser:: join('roles', 'role_user.role_id', '=', 'roles.id') ->where('role_user.user_id', $user->id)
        ->pluck('roles.name')->first();
        if(!empty($userRole)){
            $request->merge(['user_roles' => $userRole]);
            $request->merge(['userId' => $user->id]);
            return $next($request);
        }else{
             return response()->json(['message' => 'Forbidden'], 403);
        }

        
    }
}
