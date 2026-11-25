<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    /**
     * Display the paginated list of staff accounts and creation form.
     */
    public function index(): View
    {
        // firstOrCreate() mengambil baris role 'staff' atau membuatnya bila belum ada.
        // users() mengakses relasi HasMany pada model Role, lalu paginate(15) mengambil
        // 15 akun staf per halaman dalam keadaan terurut menurut kolom name.
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff'],
            ['display_name' => 'Staf Operasional']
        );
        $staff = $staffRole->users()->orderBy('name')->paginate(15);

        return view('admin.staff', compact('staff'));
    }

    /**
     * Show the edit profile form for a specific staff member.
     */
    public function edit(User $member): View
    {
        // abort_unless() menghentikan eksekusi dengan HTTP 404 jika $member bukan staf,
        // sehingga admin tidak dapat mengedit akun admin atau pelanggan lewat endpoint staf.
        abort_unless($member->isStaff(), 404);

        return view('admin.staff-form', compact('member'));
    }

    /**
     * Create a new staff user bound strictly to the staff role.
     */
    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validateStaff($request);
        $role = Role::firstOrCreate(
            ['name' => 'staff'],
            ['display_name' => 'Staf Operasional']
        );

        // Memanggil create() lewat relasi $role->users() mengisi kolom role_id otomatis
        // dari server, sehingga formulir tidak pernah bisa menentukan peran pengguna baru.
        $role->users()->create($attributes);

        return back()->with('success', 'Akun staf berhasil dibuat.');
    }

    /**
     * Update a staff member's profile details.
     */
    public function update(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);
        $member->update($this->validateStaff($request, $member));

        return redirect()->route('admin.staff.index')->with('success', 'Profil staf berhasil diperbarui.');
    }

    /**
     * Deactivate a staff account and terminate any active database sessions.
     */
    public function deactivate(User $member): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);

        // Operator ??= (null coalescing assignment) hanya mengisi deactivated_at jika
        // nilainya masih null, menjaga waktu penonaktifan awal tetap tercatat.
        // Baris pengguna tetap dipertahankan agar relasi inspeksi/riwayat tidak rusak.
        $member->deactivated_at ??= now();
        $member->setRememberToken(Str::random(60));
        $member->save();

        // Jika driver sesi menggunakan database, hapus seluruh baris sesi milik staf ini
        // agar sesi lama langsung terhapus di penyimpanan server selain diblokir middleware.
        $sessionTable = (string) config('session.table', 'sessions');
        if (config('session.driver') === 'database' && Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $member->id)->delete();
        }

        return redirect()->route('admin.staff.index')->with('success', 'Akun staf dinonaktifkan.');
    }

    /**
     * Validate incoming staff profile or creation payload.
     *
     * @return array{name: string, email: string, phone: ?string, password?: string}
     */
    private function validateStaff(Request $request, ?User $member = null): array
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        // Rule::unique('users')->ignore($member?->id) mengecualikan ID staf saat edit,
        // dan bernilai null saat tambah baru sehingga seluruh baris email tetap diperiksa.
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($member?->id)],
            'phone' => ['nullable', 'string', 'max:25'],
        ];
        if ($member === null) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $request->validate($rules);
    }
}
