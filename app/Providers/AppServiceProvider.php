<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            (bool) config('app.force_https', false)
            || ($this->app->environment('production') && ! $this->app->runningUnitTests())
        ) {
            URL::forceScheme('https');
        }

        if (! $this->app->runningInConsole()) {
            $host = request()->getHost();
            if (
                request()->header('X-Forwarded-Proto') === 'https'
                || str_ends_with($host, '.lhr.life')
                || str_ends_with($host, '.trycloudflare.com')
            ) {
                URL::forceScheme('https');
            }
        }

        // Operator :: memanggil method static toMailUsing() pada class ResetPassword
        // milik Laravel. Closure menerima $notifiable (model User) dan $token reset,
        // lalu mengembalikan objek MailMessage berbahasa Indonesia sebagai isi email.
        // Pengaturan ini tetap memakai class ResetPassword bawaan agar broker dan
        // pengujian notifikasi berjalan seragam tanpa membuat class notifikasi terpisah.
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $brokerName = (string) config('auth.defaults.passwords', 'users');
            $expireMinutes = (int) config("auth.passwords.{$brokerName}.expire", 60);

            return (new MailMessage)
                ->subject('Tautan Pemulihan Kata Sandi - DriveEase')
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun DriveEase Anda.')
                ->action('Buat Kata Sandi Baru', $resetUrl)
                ->line("Tautan pemulihan ini berlaku selama {$expireMinutes} menit sejak dikirim.")
                ->line('Abaikan email ini apabila Anda tidak merasa mengajukan pemulihan kata sandi.');
        });
    }
}
