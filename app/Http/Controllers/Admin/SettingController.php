<?php
// ========================================================
// Admin\SettingController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    protected AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->auditLogger = $auditLogger;
    }

    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        $settings['site_name'] = $settings['site_name'] ?? $settings['hotel_name'] ?? '';
        $settings['contact_email'] = $settings['contact_email'] ?? $settings['hotel_email'] ?? '';
        $settings['hotel_phone'] = $settings['hotel_phone'] ?? $settings['contact_phone'] ?? '';
        $settings['contact_phone'] = $settings['contact_phone'] ?? $settings['hotel_phone'] ?? '';
        $settings['hotel_address'] = $settings['hotel_address'] ?? '';
        $settings['map_embed_url'] = $settings['map_embed_url'] ?? '';
        $settings['site_whatsapp'] = $settings['site_whatsapp'] ?? '';
        $settings['payment_provider_flutterwave_enabled'] = filter_var(
            $settings['payment_provider_flutterwave_enabled'] ?? config('payment.providers.flutterwave', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $settings['payment_provider_paystack_enabled'] = filter_var(
            $settings['payment_provider_paystack_enabled'] ?? config('payment.providers.paystack', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $settings['payment_default_provider'] = $settings['payment_default_provider'] ?? config('payment.default', 'flutterwave');

        return Inertia::render('Admin/Settings/Index', ['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'nullable|string|max:191',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'hotel_phone' => 'nullable|string',
            'hotel_address' => 'nullable|string|max:500',
            'map_embed_url' => 'nullable|url|max:2000',
            'logo' => 'nullable|file|image|max:2048',
            'banner' => 'nullable|file|image|max:4096',
            'site_whatsapp' => 'nullable|string',
            'payment_provider_flutterwave_enabled' => 'required|boolean',
            'payment_provider_paystack_enabled' => 'required|boolean',
            'payment_default_provider' => 'required|string|in:flutterwave,paystack',
        ]);

        if (!$data['payment_provider_flutterwave_enabled'] && !$data['payment_provider_paystack_enabled']) {
            return back()
                ->withErrors([
                    'payment_providers' => 'At least one payment gateway must remain enabled.',
                ])
                ->withInput();
        }

        if (!$data['payment_provider_'.$data['payment_default_provider'].'_enabled']) {
            return back()
                ->withErrors([
                    'payment_default_provider' => 'Default payment gateway must be enabled.',
                ])
                ->withInput();
        }

        $phone = $data['hotel_phone'] ?? $data['contact_phone'] ?? null;

        $simpleSettings = [
            'site_name' => $data['site_name'] ?? null,
            'hotel_name' => $data['site_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'hotel_email' => $data['contact_email'] ?? null,
            'contact_phone' => $phone,
            'hotel_phone' => $phone,
            'hotel_address' => $data['hotel_address'] ?? null,
            'map_embed_url' => $data['map_embed_url'] ?? null,
            'site_whatsapp' => $data['site_whatsapp'] ?? null,
            'payment_provider_flutterwave_enabled' => $data['payment_provider_flutterwave_enabled'],
            'payment_provider_paystack_enabled' => $data['payment_provider_paystack_enabled'],
            'payment_default_provider' => $data['payment_default_provider'],
        ];

        foreach ($simpleSettings as $key => $value) {
            if (array_key_exists($key, $simpleSettings)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => $path]);
        }

        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'banner'], ['value' => $path]);
        }

        $this->auditLogger->log('settings_updated', 'Setting', 0, ['data' => $data]);

        return back()->with('success', 'Settings updated.');
    }
}
