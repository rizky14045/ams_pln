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
