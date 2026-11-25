<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = Role::where('name', 'staff')->firstOrFail()->users()->orderBy('name')->paginate(15);

        return view('admin.staff', compact('staff'));
    }

    public function edit(User $member): View
    {
        abort_unless($member->isStaff(), 404);

        return view('admin.staff-form', compact('member'));
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validateStaff($request);
        $role = Role::where('name', 'staff')->firstOrFail();

        // users() mengembalikan relasi milik role staf. create() mengisi
        // role_id dari relasi itu, sehingga role tidak ditentukan formulir.
        $role->users()->create($attributes);

        return back()->with('success', 'Akun staf berhasil dibuat.');
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);
        $member->update($this->validateStaff($request, $member));

        return redirect()->route('admin.staff.index')->with('success', 'Profil staf berhasil diperbarui.');
    }

    public function deactivate(User $member): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);

        // Pertahankan baris pengguna agar histori tetap dapat merujuk staf ini.
        // Waktu nonaktif memblokir login dan dibaca middleware pada permintaan berikutnya.
        $member->deactivated_at ??= now();
        $member->setRememberToken(Str::random(60));
        $member->save();

        return redirect()->route('admin.staff.index')->with('success', 'Akun staf dinonaktifkan.');
    }

    /** @return array{name: string, email: string, phone: ?string, password?: string} */
    private function validateStaff(Request $request, ?User $member = null): array
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

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
