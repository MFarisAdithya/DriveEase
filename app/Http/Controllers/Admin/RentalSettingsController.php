<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RentalSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.rental-settings', [
            'settings' => DB::table('rental_settings')->find(1),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'services' => ['required', 'string', 'max:3000'],
            'address' => ['required', 'string', 'max:3000'],
            'contact' => ['required', 'string', 'max:255'],
            'operating_hours' => ['required', 'string', 'max:255'],
            'terms' => ['required', 'string', 'max:6000'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'account_holder' => ['required', 'string', 'max:255'],
        ]);

        // upsert() menyisipkan data jika ID 1 belum ada atau memperbarui
        // baris dengan ID yang sama. Hasil validasi menjadi satu set pengaturan aktif.
        DB::table('rental_settings')->upsert(['id' => 1] + $settings, ['id']);

        return back()->with('success', 'Pengaturan rental berhasil disimpan.');
    }
}
