<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('dashboard.admin.settings.index', [
            'aiMemoryEnabled' => (bool) AppSetting::getValue('ai_memory_enabled', false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        AppSetting::putValue('ai_memory_enabled', $request->boolean('ai_memory_enabled'), 'boolean');

        return redirect()->route('admin.settings.index')
            ->with('success', 'AI settings updated successfully.');
    }
}