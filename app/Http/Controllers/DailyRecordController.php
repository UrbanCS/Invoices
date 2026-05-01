<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\BusinessSetting;
use App\Models\DailyRecord;
use App\Models\UploadedDocument;
use App\Services\AuditLogService;
use App\Services\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DailyRecordController extends Controller
{
    public function index(): View
    {
        $records = DailyRecord::with('client')
            ->when(request('client_id'), fn ($q) => $q->where('client_id', request('client_id')))
            ->when(request('status'), fn ($q) => $q->where('status', request('status')))
            ->latest('service_date')
            ->paginate(20);

        return view('daily-records.index', ['records' => $records, 'clients' => Client::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('daily-records.form', [
            'record' => new DailyRecord(['service_date' => now(), 'status' => 'draft']),
            'clients' => Client::with('activeCategories')->where('is_active', true)->orderBy('name')->get(),
            'items' => collect(),
            'settings' => BusinessSetting::first(),
        ]);
    }

    public function store(Request $request, MoneyFormatter $money, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $record = DailyRecord::create([...$data, 'created_by' => Auth::id(), 'status' => $request->input('action') === 'review' ? 'reviewed' : 'draft']);
        $this->syncItems($record, $request, $money);
        $audit->record('daily_record.created', $record);

        return redirect()->route('daily-records.show', $record)->with('status', 'Daily record saved.');
    }

    public function show(DailyRecord $dailyRecord, MoneyFormatter $money): View
    {
        $this->authorizeRecord($dailyRecord);
        return view('daily-records.show', ['record' => $dailyRecord->load('client', 'items.category', 'documents'), 'money' => $money]);
    }

    public function edit(DailyRecord $dailyRecord): View
    {
        $this->authorizeRecord($dailyRecord);
        return view('daily-records.form', [
            'record' => $dailyRecord->load('items'),
            'clients' => Client::with('activeCategories')->where('is_active', true)->orderBy('name')->get(),
            'items' => $dailyRecord->items,
            'settings' => BusinessSetting::first(),
        ]);
    }

    public function update(Request $request, DailyRecord $dailyRecord, MoneyFormatter $money, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeRecord($dailyRecord);
        $before = $dailyRecord->load('items')->toArray();
        $dailyRecord->update($this->validated($request));
        $this->syncItems($dailyRecord, $request, $money);
        $audit->record('daily_record.updated', $dailyRecord, $before);

        return redirect()->route('daily-records.show', $dailyRecord)->with('status', 'Daily record updated.');
    }

    public function review(DailyRecord $dailyRecord, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeRecord($dailyRecord);
        $dailyRecord->update(['status' => 'reviewed']);
        $audit->record('daily_record.reviewed', $dailyRecord);

        return back()->with('status', 'Daily record reviewed.');
    }

    public function attachments(Request $request, DailyRecord $dailyRecord): RedirectResponse
    {
        $this->authorizeRecord($dailyRecord);
        $file = $request->validate(['attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']])['attachment'];
        $path = $file->store('daily-records/'.$dailyRecord->id, 'public');

        UploadedDocument::create([
            'client_id' => $dailyRecord->client_id,
            'daily_record_id' => $dailyRecord->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('status', 'Attachment uploaded.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function syncItems(DailyRecord $record, Request $request, MoneyFormatter $money): void
    {
        $record->items()->delete();
        foreach ($request->input('items', []) as $row) {
            if (blank($row['client_category_id'] ?? null) && blank($row['amount'] ?? null)) {
                continue;
            }
            $record->items()->create([
                'client_category_id' => $row['client_category_id'],
                'customer_name' => $row['customer_name'] ?? null,
                'department_or_room' => $row['department_or_room'] ?? null,
                'description' => $row['description'] ?? null,
                'amount_cents' => max(0, $money->parse($row['amount'] ?? '0')),
            ]);
        }
    }

    private function authorizeRecord(DailyRecord $record): void
    {
        abort_unless(Auth::user()->canManage(), 403);
    }
}
