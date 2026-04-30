<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(): View
    {
        return view('welcome', ['settings' => BusinessSetting::first()]);
    }
}
