<label>Metode Konfirmasi Penyewa
    <select name="confirmation_method" @required($confirmationRequired ?? true) class="mt-1 block w-full rounded border border-field-line p-2">
        <option value="">Pilih metode</option>
        @foreach (['in_person' => 'Tatap muka', 'phone' => 'Telepon', 'message' => 'Pesan'] as $method => $label)
            <option value="{{ $method }}" @selected(old('confirmation_method') === $method)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Waktu Konfirmasi Penyewa (WIB)
    <input type="datetime-local" name="confirmed_at" value="{{ old('confirmed_at') }}" @required($confirmationRequired ?? true) class="mt-1 block w-full rounded border border-field-line p-2">
</label>
