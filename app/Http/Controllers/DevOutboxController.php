<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/** LOCAL ONLY (route is not registered in any other environment). */
class DevOutboxController extends Controller
{
    public function __invoke()
    {
        abort_unless(app()->environment('local') && config('family.messaging.driver') === 'fake', 404);
        $rows = DB::table('dev_outbox')->latest('id')->limit(30)->get();

        return response()->view('dev.outbox', ['rows' => $rows]);
    }
}
