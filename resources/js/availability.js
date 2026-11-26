// Setiap perubahan membatalkan permintaan sebelumnya. Pemeriksaan identitas
// controller membuat balasan lama tidak dapat menimpa periode yang lebih baru.
function latestRequest() {
    let timer;
    let active;

    return (request) => {
        clearTimeout(timer);
        active?.abort();
        if (!request) return;

        const current = new AbortController();
        active = current;
        timer = setTimeout(async () => {
            const stillCurrent = () => active === current && !current.signal.aborted;
            await request(current.signal, stillCurrent);
        }, 300);
    };
}

const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const scheduleDate = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta',
});

document.querySelectorAll('[data-availability-form]').forEach((form) => {
    const car = form.elements.namedItem('car_id');
    const pickup = form.elements.namedItem('pickup_at');
    const days = form.elements.namedItem('total_days');
    const result = form.nextElementSibling?.matches('[data-availability-result]') ? form.nextElementSibling : form;
    const status = result.querySelector('[data-availability-status]');
    const action = result.querySelector('[data-availability-action]');
    const booking = result.querySelector('[data-availability-booking]');
    const submit = form.querySelector('[data-availability-submit]');
    const initiallyDisabled = submit?.disabled ?? false;
    const queue = latestRequest();

    const setState = (message, state) => {
        status.textContent = message;
        if (action) action.hidden = state !== 'available';
        // Jika jaringan gagal, form POST masih dapat meminta pemeriksaan atomik server.
        if (submit) submit.disabled = initiallyDisabled || !['available', 'network-error'].includes(state);
    };

    const update = () => {
        const carId = car?.value || form.dataset.carId;
        if (!carId || !pickup.value || !days.value || !pickup.validity.valid || !days.validity.valid) {
            queue(null);
            setState('Isi kendaraan, waktu pengambilan, dan jumlah hari yang valid.', 'incomplete');
            return;
        }

        const period = new URLSearchParams({ pickup_at: pickup.value, total_days: days.value });
        const url = new URL(form.dataset.availabilityUrl, location.href);
        url.search = new URLSearchParams({ car_id: carId, ...Object.fromEntries(period) });
        setState('Memeriksa ketersediaan…', 'loading');

        queue(async (signal, stillCurrent) => {
            try {
                const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
                if (!response.ok && response.status !== 422) throw new Error('Pemeriksaan gagal');
                const data = await response.json();
                if (!stillCurrent()) return;

                if (response.status === 422) {
                    const [message] = Object.values(data.errors ?? {}).flat();
                    setState(message ?? 'Periksa kembali periode sewa.', 'invalid');
                    return;
                }

                const [vehicle] = data.data;
                if (!vehicle) {
                    setState('Tidak tersedia pada periode yang dipilih.', 'unavailable');
                    return;
                }

                if (booking) {
                    const target = new URL(booking.href);
                    for (const [key, value] of period) target.searchParams.set(key, value);
                    booking.href = target;
                }
                const returnAt = scheduleDate.format(new Date(data.period.return_at));
                setState(
                    `Tersedia. Kembali ${returnAt} WIB · Tarif Rp ${currency.format(vehicle.price_per_day)}/hari · Total Rp ${currency.format(vehicle.estimated_total)}.`,
                    'available',
                );
            } catch {
                if (!stillCurrent()) return;
                setState(
                    submit ? 'Pratinjau gagal dimuat. Server akan memeriksa jadwal saat pemesanan dikirim.'
                        : 'Ketersediaan gagal dimuat. Ubah periode untuk mencoba lagi.',
                    'network-error',
                );
            }
        });
    };

    for (const input of [car, pickup, days]) input?.addEventListener('input', update);
    if (!submit) form.addEventListener('submit', (event) => {
        event.preventDefault();
        update();
    });
    if (pickup.value || submit) update();
});

const catalog = document.querySelector('[data-catalog-filter]');
if (catalog) {
    const pickup = catalog.elements.namedItem('pickup_at');
    const days = catalog.elements.namedItem('total_days');
    const status = document.querySelector('[data-catalog-status]');
    const results = document.querySelector('[data-catalog-results]');
    const queue = latestRequest();

    const update = () => {
        if ((pickup.value || days.value) && (!pickup.value || !days.value || !days.validity.valid)) {
            queue(null);
            results.hidden = true;
            status.textContent = 'Lengkapi waktu pengambilan dan jumlah hari.';
            return;
        }

        // GET menggunakan route katalog biasa agar filter dan pagination sama
        // seperti tombol Cari mobil. Ganti hasil tanpa memindahkan fokus input.
        const url = new URL(catalog.action);
        for (const [key, value] of new FormData(catalog)) {
            if (value) url.searchParams.set(key, value);
        }
        results.hidden = true;
        status.textContent = 'Memeriksa ketersediaan…';

        queue(async (signal, stillCurrent) => {
            try {
                const response = await fetch(url, { signal });
                if (!response.ok) throw new Error('Katalog gagal dimuat');
                const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                const updated = parsed.querySelector('[data-catalog-results]');
                if (!updated) throw new Error('Hasil katalog tidak ditemukan');
                if (!stillCurrent()) return;

                results.replaceChildren(...updated.childNodes);
                results.hidden = false;
                const count = results.querySelector('[data-catalog-count]');
                status.textContent = count ? count.textContent.trim() : 'Hasil katalog diperbarui.';
                history.replaceState(history.state, '', url);
            } catch {
                if (stillCurrent()) status.textContent = 'Hasil gagal dimuat. Pilih Cari mobil untuk mencoba lagi.';
            }
        });
    };

    pickup.addEventListener('input', update);
    days.addEventListener('input', update);
}
