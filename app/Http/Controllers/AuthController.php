<?php

namespace App\Http\Controllers;

use DB;
use App\Models\User;
use App\Models\Role;
use App\Models\Roleuser;
use App\Jobs\StoreUsersJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\StoreUserRequest;

class AuthController extends Controller
{
    public function CreateUser(Request $request)
    {
        db::beginTransaction();
        try{

            Log::info('CreateUser called');

            $request->validate([
            'name'      => 'required',
            'email'     => 'required|email|unique:users',
            'password'  => 'required',
            'role'      => 'required'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $userId = $user->id;
            Log::info('CreateUser -> ' . 'UserId = ' . $userId);
            $role = Roleuser::create([ 
                'user_id' => $userId,
                'role_id' => $request->role,       
                ]);
         db::commit();
            return response()->json(
            [
                "statusCode" => 200,
                "message" => 'User Created SucessFully!'
            ]);        
    } catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => 'User registration failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function Login(Request $request)
    {
        try{
             Log::info('Login called');

            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
            log::info('Login'.'Email => '. $request->email);
           $user = User::select(
                'users.name',
                'users.email',
                'users.password',
                'role_user.user_id',
                'role_user.role_id',
                'roles.id as role_id',
                'roles.name as role_name'
            )
            ->leftjoin('role_user', 'role_user.user_id', '=', 'users.id')
            ->leftjoin('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('users.email', $request->email)
            ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
            $payload = User::where('email', $request->email)->first();
            $token = $payload->createToken('API Token')->accessToken;

            return response()->json([
                 "statusCode" => 200,
                 "message" => 'Login SucessFull',
                 "token" => $token,
                 "UserInfo" =>$user
                ]);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => 'Login Failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
    public function getUser(Request $request)
    {
       log::info("getUser Called");
       $userRole = $request->get('user_roles');
       $userId = $request->get('userId');
       log::info("getUser" . "RoleName =>" . $userRole . "UserId =>" . $userId);
         $cacheKey = "users_list_{$userRole}_{$userId}";
        log::info("CACHE_KEY".$cacheKey);
        $users = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userRole, $userId) {
        $query = User::select(
        'users.name',
        'users.email',
        'role_user.user_id',
        'role_user.role_id',
        'roles.id as role_id',
        'roles.name as role_name'
        )->join('role_user', 'role_user.user_id', '=', 'users.id')->join('roles', 'roles.id', '=', 'role_user.role_id');
        if ($userRole == env('ADMIN')){
            $query->where('roles.name', env('USERS'));
        } elseif ($userRole !== env('SUPER_ADMIN')) {
            $query->where('users.id', $userId);
        }
        return $query->get(); 
            });
        return response()->json([
                 "statusCode" => 200,
                 "message" => 'Users Fetched sucessFully',
                 "data" =>$users 
                ]);

    }

    public function addBulkusers(Request $request)
    {
        try{
            log::info("addBulkusers Called");
            $userRole = $request->get('user_roles');
            $userId = $request->get('userId');
            log::info("addBulkusers" . "RoleName =>  " . $userRole." " . "UserId =>  " . $userId);
             $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            ]);
             $path = $request->file('file')->store('bulk_users');
             StoreUsersJob::dispatch($path, $userRole);

             return response()->json([
                 "statusCode" => 200,
                 "message" => 'Bulk user import started.',
                ]);
        }catch(\Exception $e){
                return response()->json([
                    'message' => 'insersion not done',
                    'error' => $e->getMessage()
                ], 400);
            }

    }
    public function updateUser(Request $request)
    {
        try{
            log::info("updateUser Called");
            $userRole = $request->get('user_roles');
            $targetUserId = $request->get('update_user_id');;
            $name = $request->get('name');
            if (!$targetUserId || !$name) {
            return response()->json(['message' => 'Missing required parameters'], 422);
            }
            $targetUser = User::find($targetUserId);

            if (!$targetUser) {
                return response()->json(['message' => 'User not found'], 404);
            }
             $targetUserRole = Roleuser::join('roles','roles.id','=','role_user.role_id')->where('user_id',$targetUserId)->first();
            

        // SuperAdmin can update anyone
        if ($userRole === env('SUPER_ADMIN')) {
            $targetUser->name = $name;
            $targetUser->save();
            return response()->json(['message' => 'User updated successfully (by superadmin)'], 200);
        }

        // Admin logic
        if ($userRole === env('ADMIN')) {
            if ($targetUserRole === env('SUPER_ADMIN')) {
                return response()->json(['message' => 'Admins cannot update superadmin data'], 403);
            }

            if ($authUserId != $targetUserId && $targetUserRole === 'admin') {
                return response()->json(['message' => 'Admins cannot update other admins'], 403);
            }

            $targetUser->name = $name;
            $targetUser->save();
            return response()->json(['message' => 'User updated successfully (by admin)'], 200);
        }

        // Regular user can update only self
        if ($userRole === enc('USERS')) {
            if ($authUserId != $targetUserId) {
                return response()->json(['message' => 'Users can only update their own profile'], 403);
            }

            $targetUser->name = $name;
            $targetUser->save();
            return response()->json(['message' => 'Profile updated successfully'], 200);
        }


         }catch(\Exception $e){
                return response()->json([
                    'message' => 'User Didnot Updated',
                    'error' => $e->getMessage()
                ], 400);
            }
    }
    public function deleteUser(Request $request)
    {
        try {
            Log::info("deleteUser Called");

            $userRole = $request->get('user_roles'); 
            $authUserId = $request->get('userId');               
            $targetUserId = $request->get('delete_user_id');     

            if (!$targetUserId) {
                return response()->json(['message' => 'Missing user ID to delete'], 422);
            }

            $targetUser = User::withTrashed()->find($targetUserId);

            if ($targetUser->trashed()) {
                return response()->json(['message' => 'User already deleted'], 422);
            }

            if ($userRole !== env('SUPER_ADMIN')) {
                return response()->json([
                    'message' => 'Unauthorized. Only SuperAdmin can delete users.'
                ], 403);
            }

            $targetUser->delete();

            Log::info("User deleted -> ID: " . $targetUserId);

            return response()->json([
                'message' => 'User deleted successfully (by SuperAdmin)'
            ], 200);

        } catch (\Exception $e) {
            Log::error("User deletion failed: " . $e->getMessage());

            return response()->json([
                'message' => 'User deletion failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    
}
