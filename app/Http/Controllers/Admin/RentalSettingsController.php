<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalSettingsController extends Controller
{
    /**
     * Show the rental business information and bank account settings form.
     */
    public function edit(): View
    {
        // RentalSetting::current() membaca baris ID 1 dari tabel rental_settings
        // atau mengembalikan null jika admin belum pernah menyimpan pengaturan.
        return view('admin.rental-settings', [
            'settings' => RentalSetting::current(),
        ]);
    }

    /**
     * Validate and save the singleton rental settings record.
     */
    public function update(Request $request): RedirectResponse
    {
        // trim() menghapus spasi di awal/akhir nomor rekening sebelum aturan regex
        // memastikan seluruh karakter nomor rekening hanya berupa angka 0-9.
        if (is_string($request->input('account_number'))) {
            $request->merge([
                'account_number' => trim($request->input('account_number')),
            ]);
        }

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
        ], [
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka tanpa spasi atau tanda baca.',
        ]);

        // updateOrCreate() mencari baris dengan id = 1 lalu memperbaruinya, atau menyisipkan
        // baris baru bila belum ada, menjaga tabel rental_settings tetap memiliki satu konfigurasi aktif.
        RentalSetting::updateOrCreate(['id' => 1], $settings);

        return back()->with('success', 'Pengaturan rental berhasil disimpan.');
    }
}
