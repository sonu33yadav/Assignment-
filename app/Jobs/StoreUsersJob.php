<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use App\Models\Roleuser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;


class StoreUsersJob implements ShouldQueue
{
     use Dispatchable,InteractsWithQueue, Queueable, SerializesModels;
     protected $filePath;
      protected $userRole;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, $userRole)
    {
        $this->filePath = $filePath;
        $this->userRole = $userRole;
        
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // db::beginTransaction();
        try{
            $csvContent = Storage::get($this->filePath);
            // Parse CSV
             $lines = preg_split("/\r\n|\n|\r/", trim($csvContent));
            $lines[0] = rtrim($lines[0], ", \r\n"); // Clean header row (remove trailing commas/spaces)
            $cleanCsvContent = implode("\n", $lines);
            $csv = Reader::createFromString($cleanCsvContent);
            $csv->setHeaderOffset(0);

            $rawHeaders = $csv->getHeader();
            $cleanHeaders = array_filter($rawHeaders, fn($header) => trim($header) !== '');
            Log::info("CSV header line: " . json_encode($cleanHeaders));
            $records = $csv->getRecords();

        foreach ($records as $record) {
                $email = trim($record['email']);

                // Skip duplicate emails
                if (User::where('email', $email)->exists()) {
                    Log::info("Skipping duplicate email: $email");
                    continue;
                }

                // Create user
                $user = User::create([
                    'name' => trim($record['name']),
                    'email' => $email,
                    'password' => Hash::make($record['password'] ?? 'defaultPassword123'),
                ]);
                $userId = $user->id;
                Log::info('CreateUser -> ' . 'UserId = ' . $userId);
                Log::info('CreateUser -> ' . 'Emial = ' . $email);
                $roleId = 3;
                Log::info('UserROle -> ' . 'UserId = ' . strtolower($this->userRole));
                if (strtolower($this->userRole) !== 'admin') {
                    $roleId = intval($record['role'] ?? 3);
                }
                Log::info('User Role -> ' . 'UserId = ' . $userId . "ROLEID-> " .$roleId );    
                Roleuser::create([ 
                    'user_id' => $userId,
                    'role_id' => $roleId,       
                ]);
            }

            Storage::delete($this->filePath);
            Log::info("Bulk user import completed and file deleted.");
            // db::commit();
        }catch (\Exception $e) {
            // DB::rollBack();
            Log::error("Bulk user import failed: " . $e->getMessage());
        }
    }
}
