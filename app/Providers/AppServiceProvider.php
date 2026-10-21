<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Vendor CrudBooster (form_detail.blade.php) & PHPExcel 1.8 memakai sintaks
        // lama "$var{...}" yang memicu E_DEPRECATED di PHP 7.4, dan Laravel
        // mengubahnya jadi ErrorException. Redam deprecation saja.
        //
        // Dipasang ulang tiap view dibuat, karena CBController.php men-set
        // error_reporting(E_ALL ^ E_NOTICE) saat dimuat (mengaktifkan lagi E_DEPRECATED).
        $suppressDeprecations = function () {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        };

        $suppressDeprecations();
        view()->creator('*', $suppressDeprecations);

        // config/lfm.php sengaja TIDAK berisi Closure (supaya php artisan config:cache
        // tidak error "Closure::__set_state"). Fitur folder per-user Laravel File
        // Manager tetap jalan dengan menyuntikkan closure-nya di sini saat runtime --
        // KECUALI saat perintah `config:cache` sendiri sedang berjalan, supaya closure
        // ini tidak ikut ter-tangkap & disimpan ke file cache (itu yang bikin error).
        $runningConfigCache = $this->app->runningInConsole()
            && isset($_SERVER['argv'])
            && in_array('config:cache', $_SERVER['argv'], true);

        if (!$runningConfigCache) {
            config(['lfm.user_field' => function () {
                return session('admin_id');
            }]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        require __DIR__.'/../helpers.php';
    }
}
