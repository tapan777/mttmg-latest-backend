<?php

namespace App\Http\Controllers;

use App\Events\SendEmailNotification;
use App\Events\SendWhatsAppNotification;
use App\Mail\OfferLetterMail;
use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function addEmployee(Request $request)
    {
        try {
            $payloads = $request->all();
            // Validate the incoming data
            $validator = Validator::make($payloads, [
                'name' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
                'salary' => 'required|numeric',
                'designation' => 'required|string',
                'blood_group' => 'required|string',
                'joining_date' => 'required|date',
                'address' => 'required|string',
                'morning_slot' => 'nullable|string',
                'evening_slot' => 'nullable|string',
                'excuse_time'  => 'nullable|numeric',
            ]);

            $payloads['joining_date'] = date('Y-m-d', strtotime($request->joining_date . ' +1 day'));
            $payloads['designation_slug'] = Str::slug($payloads['designation']);
            if ($request->hasFile('image')) {
                $imageName = time() . '.jpg';
                $destPath  = public_path('images/employees/' . $imageName);
                if (!file_exists(public_path('images/employees'))) {
                    mkdir(public_path('images/employees'), 0755, true);
                }
                $this->compressAndSaveImage($request->file('image')->getRealPath(), $destPath);
                $payloads['image'] = url('images/employees/' . $imageName);
            }
            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code' => 500
                ], 200);
            }
            // Create a new employee record
            $employee = Employee::create($payloads)->fresh();

            if ($employee) {
                // Send offer letter WhatsApp — async via event
                event(new SendWhatsAppNotification(
                    'offerr_letter',
                    [$employee->name, $employee->designation],
                    [$employee->phone, '9937542268']
                ));

                // Send offer letter email with PDF attachment — async via event listener
                if (!empty($employee->email)) {
                    event(new SendEmailNotification(new OfferLetterMail($employee), $employee->email));
                }

                $sn       = config('zkteco.sn', 'HKQ8241900193');
                $uid      = $employee->id;
                $safeName = str_replace(' ', '_', $employee->name);
                $card     = $request->card_number ?? '0';
                $cmdId    = time();

                $params = [
                    "PIN={$uid}",
                    "Name={$safeName}",
                    "Card={$card}",
                    "Pri=0",
                ];
                if (!empty($request->password)) {
                    $params[] = "Pass={$request->password}";
                }

                try {
                    AdmsController::queueCommand(
                        $sn,
                        $cmdId,
                        "C:{$cmdId}:DATA UPDATE USERINFO\t" . implode("\t", $params)
                    );
                } catch (\Throwable $bioEx) {
                    Log::warning('Biometric sync failed', ['error' => $bioEx->getMessage()]);
                }

                return response()->json([
                    'message' => "Employee added successfully.",
                    'data'    => $employee,
                    'code'    => 200
                ], 200);
            } else {
                return response()->json([
                    'code' => 500,
                    'message' => 'Failed to add Employee',
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => "The biometric setup timed out or failed: {$e->getMessage()}",
                'data' => $employee ?? null,
                'code' => 408 // HTTP Status Code for Request Timeout
            ], 408);
        }
    }

    //retrive employees
    public function retriveEmployee(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $index = $request->index > 0 ? $request->index : 0;
        $search_text = $request->search_text;
        $employee_id = $request->id;
    
        if ($employee_id) {
            $data = Employee::where('id', $employee_id)->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'data' => $data,
                    'total_count' => 1, // Since fetching by ID
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Invalid ID",
                    'code' => 500
                ], 200);
            }
        } elseif ($search_text) {
            $query = Employee::where('name', 'like', "%$search_text%")
                ->orWhere('email', 'like', "%$search_text%")
                ->orWhere('phone', 'like', "%$search_text%")
                ->orWhere('designation', 'like', "%$search_text%");
    
            $total_count = $query->count(); // Total count without pagination
            $records = $query->limit($limit)
                ->offset($index)
                ->orderBy('id', 'desc')
                ->get();
    
            return response()->json([
                'data' => $records,
                'total_count' => $total_count,
                'code' => 200
            ], 200);
        } else {
            $total_count = Employee::count(); // Total count of all employees
            $records = Employee::limit($limit)
                ->offset($index)
                ->orderBy('id', 'desc')
                ->get();
    
            return response()->json([
                'data' => $records,
                'total_count' => $total_count,
                'code' => 200
            ], 200);
        }
    }
    
    //search employee
    public function searchEmployee(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $inedex = $request->index > 0 ? $request->limit : 0;
        $records = Employee::where('name', 'like', "%$request->search_text%")
            ->orWhere('email', 'like', "%$request->search_text%")
            ->orWhere('phone', 'like', "%$request->search_text%")
            ->orWhere('designation', 'like', "%$request->search_text%")
            ->limit($limit)
            ->offset($inedex)
            ->orderBy('id', 'desc')
            ->get();

        if ($records) {
            return response()->json([
                'data' => $records,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Something went wrong",
                'code' => 500
            ], 200);
        }
    }

    //search employee
    public function autoComplete(Request $request)
    {
        $search_text = $request->search_text;
        $records = Employee::where('name', 'like', "%$search_text%")
            ->orWhere('email', 'like', "%$search_text%")
            ->orWhere('phone', 'like', "%$search_text%")
            ->orWhere('designation', 'like', "%$search_text%")
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($search_text) {
                // If the name matches, return only the name
                if (strpos($item->name, $search_text) !== false) {
                    return  ['search_data' => $item->name];
                    // If the duration matches, return only the duration
                } elseif (stripos($item->email, $search_text) !== false) {
                    return ['search_data' => $item->email];
                } elseif (stripos($item->phone, $search_text) !== false) {
                    return ['search_data' => $item->phone];
                } elseif (stripos($item->designation, $search_text) !== false) {
                    return ['search_data' => $item->designation];
                }
            })->toArray();

        if ($records) {
            return response()->json([
                'data' => $records,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Something went wrong",
                'code' => 500
            ], 200);
        }
    }
    //update member
    public function updateEmployees(Request $request)
    {
        $employee = Employee::find($request->id);
        if (!$employee) {
            return response()->json(['message' => 'Invalid Id', 'code' => 500], 200);
        }

        $payload = [
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'salary'      => $request->salary,
            'designation' => $request->designation,
            'designation_slug' => $request->designation ? Str::slug($request->designation) : $employee->designation_slug,
            'blood_group' => $request->blood_group,
            'address'     => $request->address,
            'morning_slot' => $request->morning_slot,
            'evening_slot' => $request->evening_slot,
            'excuse_time'  => $request->excuse_time,
            'joining_date' => $request->joining_date ? date('Y-m-d', strtotime($request->joining_date . ' +1 day')) : $employee->joining_date,
        ];

        if ($request->has('card_number')) {
            $payload['card_number'] = $request->card_number;
        }

        if ($request->hasFile('image')) {
            $imageName = time() . '.jpg';
            $destPath  = public_path('images/employees/' . $imageName);
            if (!file_exists(public_path('images/employees'))) {
                mkdir(public_path('images/employees'), 0755, true);
            }
            $this->compressAndSaveImage($request->file('image')->getRealPath(), $destPath);
            $payload['image'] = url('images/employees/' . $imageName);
        }
        $oldCard = $employee->card_number;
        $result  = $employee->update($payload);

        if ($result) {
            if ($request->has('card_number') && (string) $request->card_number !== (string) $oldCard) {
                $sn       = config('zkteco.sn', 'HKQ8241900193');
                $uid      = $employee->id;
                $safeName = str_replace(' ', '_', $employee->fresh()->name);
                $card     = $employee->fresh()->card_number ?? '0';
                $cmdId    = time();
                AdmsController::queueCommand(
                    $sn,
                    $cmdId,
                    "C:{$cmdId}:DATA UPDATE USERINFO\tPIN={$uid}\tName={$safeName}\tCard={$card}\tPri=0"
                );
            }
            return response()->json([
                'message' => 'Updated Successfully',
                'data'    => $employee->fresh(),
                'code'    => 200
            ], 200);
        } else {
            return response()->json(['message' => 'Update Failed', 'code' => 500], 200);
        }
    }

    public function deleteEmployee(Request $request)
    {
        $employee = Employee::find($request->id);
        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found',
                'code' => 500
            ], 200);
        }

        // Delete the employee record
        $deleted = $employee->delete();


        if ($deleted) {
            $sn    = config('zkteco.sn', 'HKQ8241900193');
            $cmdId = time();
            AdmsController::queueCommand($sn, $cmdId, "C:{$cmdId}:DATA DELETE USERINFO\tPIN={$request->id}");

            return response()->json([
                'message' => 'Employee deleted. Device will sync within 10 seconds.',
                'code' => 200
            ], 200);
        } else {
            // Deletion failed
            return response()->json([
                'message' => 'Failed to delete employee',
                'code' => 500
            ], 500);
        }
    }

    public function trainer_slot_times(Request $request)
    {
        $trainerId = $request->input('trainer_id', $request->input('id'));
        if ($trainerId === null || $trainerId === '') {
            return response()->json(['message' => 'trainer_id is required', 'code' => 422], 200);
        }
        if (!is_numeric($trainerId)) {
            return response()->json(['message' => 'trainer_id must be a valid integer', 'code' => 422], 200);
        }

        $trainerId = (int) $trainerId;
        $employee  = Employee::where('designation_slug', 'trainer')
            ->where('id', $trainerId)
            ->select('id', 'name', 'morning_slot', 'evening_slot')
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Trainer not found', 'code' => 404], 200);
        }

        return response()->json([
            'data' => [
                'trainer_id'   => $employee->id,
                'name'         => $employee->name,
                'morning_slot' => $employee->morning_slot,
                'evening_slot' => $employee->evening_slot,
            ],
            'code' => 200,
        ], 200);
    }

    //dropdown for trainer
    public function trainer_dropdown()
    {
        $data = Employee::where('designation_slug', 'trainer')
            ->select('id', 'name')
            ->get();
        return response()->json([
            'data' =>  $data,
            'code' => 200
        ], 200);
    }

    private function compressAndSaveImage(string $sourcePath, string $destPath, int $maxWidth = 800, int $maxHeight = 800, int $quality = 75): void
    {
        $info = getimagesize($sourcePath);
        $mime = $info['mime'] ?? '';

        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($sourcePath);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($sourcePath);
                break;
            default:
                $src = imagecreatefromstring(file_get_contents($sourcePath));
        }

        if (!$src) {
            // fallback: just copy as-is if GD can't handle it
            copy($sourcePath, $destPath);
            return;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // calculate new dimensions maintaining aspect ratio
        $ratio   = min($maxWidth / $origW, $maxHeight / $origH, 1.0);
        $newW    = (int) round($origW * $ratio);
        $newH    = (int) round($origH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);

        // preserve transparency for PNG
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagejpeg($dst, $destPath, $quality);

        imagedestroy($src);
        imagedestroy($dst);
    }
}
