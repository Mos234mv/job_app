<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Support\Facades\Auth;

class JobApllicationController extends Controller
{
    public function index()
    {
        $applications = JobApplication::where('user_id', Auth::id())->latest()->paginate(10);

        return view('job-applications.index', compact('applications'));
    }
}
