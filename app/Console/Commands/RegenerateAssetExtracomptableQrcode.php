<?php

namespace App\Console\Commands;

use App\Models\AssetExtracomptable;
use Illuminate\Console\Command;

/**
 * Backfill satu kali: paksa generate ulang QR Code SEMUA asset extracomptable
 * yang sudah ada. Dipakai supaya file QR lama (yang masih pakai logo lama)
 * ikut ter-update tanpa harus buka-edit satu-satu lewat web.
 *
 * Edit asset ke depannya otomatis regenerate sendiri — lihat
 * AssetExtracomptable::regenerateQrcode() & AssetExtracomptableController::saveAsset().
 */
class RegenerateAssetExtracomptableQrcode extends Command
{
    protected $signature = 'assets:regenerate-qrcode-extracomptable {--chunk=200}';

    protected $description = 'Generate ulang file QR Code semua asset extracomptable (mis. setelah ganti logo)';

    public function handle()
    {
        $total = AssetExtracomptable::count();
        if (!$total) {
            $this->info('Tidak ada asset extracomptable.');
            return 0;
        }

        $this->info("Generate ulang QR Code untuk {$total} asset extracomptable...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = [];

        AssetExtracomptable::with(['gedung', 'ruang'])
            ->orderBy('id')
            ->chunk((int) $this->option('chunk'), function ($assets) use ($bar, &$failed) {
                foreach ($assets as $asset) {
                    try {
                        $asset->regenerateQrcode();
                    } catch (\Throwable $e) {
                        $failed[] = "{$asset->kd_asset}: {$e->getMessage()}";
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->line('');

        if ($failed) {
            $this->warn(count($failed).' asset gagal di-generate ulang:');
            foreach ($failed as $f) {
                $this->line("  - {$f}");
            }
        } else {
            $this->info('Selesai, semua QR Code berhasil di-generate ulang.');
        }

        return 0;
    }
}
