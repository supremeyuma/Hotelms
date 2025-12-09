<?php
// ========================================================
// Admin\SettingController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:manager|md']);
    }

    public function index()
    {
        $settings = Setting::all()->pluck('value','key')->toArray();
        return Inertia::render('Admin/Settings/Index', ['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'nullable|string|max:191',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'logo' => 'nullable|file|image|max:2048',
            'banner' => 'nullable|file|image|max:4096',
            'room_service_menu' => 'nullable|array'
        ]);

        // Save simple string settings
        foreach (['site_name','contact_email','contact_phone'] as $key) {
            if (isset($data[$key])) {
                Setting::updateOrCreate(['key'=>$key], ['value'=>$data[$key]]);
            }
        }

        // handle files
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings','public');
            Setting::updateOrCreate(['key'=>'logo'], ['value'=>$path]);
        }

        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('settings','public');
            Setting::updateOrCreate(['key'=>'banner'], ['value'=>$path]);
        }

        if (isset($data['room_service_menu'])) {
            Setting::updateOrCreate(['key'=>'room_service_menu'], ['value'=>json_encode($data['room_service_menu'])]);
        }

        AuditLogger::log('settings_updated', 'Setting', 0, ['data' => $data]);

        return back()->with('success','Settings updated.');
    }
}
