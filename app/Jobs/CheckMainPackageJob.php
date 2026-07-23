<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMainPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $records= Payment::where('end_date', date('Y-m-d', strtotime(Carbon::today())))->get();
        // $records= Payment::where('end_date', '2024-10-11')->get();
        if($records->toArray()){
            Member::find($records[0]->member_id)->update(['status' => 2]);
        }
    }
}
