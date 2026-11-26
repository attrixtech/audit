<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FirmSetting;
use App\Services\ActivityLogger;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly WhatsAppProviderInterface $whatsApp
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $settings = FirmSetting::allAsMap();
        $logQuery = ActivityLog::with('user')->latest();

        if ($module = $request->query('module')) {
            $logQuery->where('module', $module);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $logQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $logQuery->paginate(20)->withQueryString();
        $logModules = ActivityLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $whatsAppConfigured = $this->whatsApp->isConfigured();

        return view('settings.index', compact('settings', 'logs', 'logModules', 'whatsAppConfigured'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $validated = $request->validate([
            'firm_name' => ['required', 'string', 'max:255'],
            'firm_tagline' => ['nullable', 'string', 'max:255'],
            'firm_email' => ['nullable', 'email', 'max:255'],
            'firm_phone' => ['nullable', 'string', 'max:40'],
            'firm_address' => ['nullable', 'string', 'max:1000'],
            'firm_pan' => ['nullable', 'string', 'max:20'],
            'firm_gstin' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'default_cgst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'default_sgst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'default_igst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
        ]);

        foreach ($validated as $key => $value) {
            FirmSetting::setValue($key, $value, 'firm');
        }

        $this->logger->log('settings', 'updated', 'Updated CA firm profile & billing settings');

        return back()->with('success', 'Firm settings saved.');
    }
}
