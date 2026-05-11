<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PlanExpiredController extends Controller
{
    public function __invoke(): View
    {
        return view('app.plan-expired');
    }
}
