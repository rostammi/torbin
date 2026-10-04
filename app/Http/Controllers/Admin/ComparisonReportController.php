<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComparisonReport;
use App\Models\Tour;
use App\Support\AdminTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComparisonReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ComparisonReport::STATUSES))],
            'type' => ['nullable', Rule::in(array_keys(ComparisonReport::TYPES))],
        ]);
        $status = $data['status'] ?? '';
        $type = $data['type'] ?? '';
        $term = AdminTable::term($request);
        $query = ComparisonReport::query()
            ->with('tour')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('details', 'like', "%{$term}%")
                ->orWhereHas('tour', fn ($tour) => $tour->where('title', 'like', "%{$term}%"))));
        AdminTable::sort($query, $request, [
            'tour' => fn ($query, $direction) => $query->orderBy(Tour::query()->select('title')->whereColumn('tours.id', 'comparison_reports.tour_id')->limit(1), $direction),
            'type' => 'type', 'created' => 'created_at', 'status' => 'status',
        ], [['status', 'asc'], ['created_at', 'desc']]);
        $reports = $query->paginate(25)->withQueryString();

        return view('admin.comparison-reports.index', compact('reports', 'status', 'type'));
    }

    public function update(Request $request, ComparisonReport $comparisonReport): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ComparisonReport::STATUSES))],
        ]);
        $comparisonReport->update([
            'status' => $data['status'],
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'وضعیت گزارش به‌روزرسانی شد.');
    }
}
