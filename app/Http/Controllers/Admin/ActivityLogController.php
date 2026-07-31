<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('actor')->when($request->filled('event'), fn ($query) => $query->where('event', $request->event))
            ->when($request->filled('q'), fn ($query) => $query->where('description', 'like', '%'.trim($request->q).'%'))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
