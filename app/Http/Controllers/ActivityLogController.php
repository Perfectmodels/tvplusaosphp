<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActivityLog::class);

        $search = trim((string) $request->query('q', ''));
        $query = ActivityLog::query()
            ->with('actor')
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('actor_name', 'like', '%'.$search.'%')
                        ->orWhere('route_name', 'like', '%'.$search.'%')
                        ->orWhere('event', 'like', '%'.$search.'%')
                        ->orWhere('subject_type', 'like', '%'.$search.'%')
                        ->orWhere('subject_id', 'like', '%'.$search.'%');
                });
            })
            ->latest('created_at');

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.activity-logs.index', compact('logs', 'search'));
    }
}
