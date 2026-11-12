<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ShareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'share {--port=8000 : Port lokal aplikasi Laravel yang dibuka}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bagikan website Gianyar Planner secara online melalui Ngrok tunnel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = (int) $this->option('port');

        $this->info('Memeriksa status Ngrok...');

        $publicUrl = $this->getActiveNgrokUrl();

        if (! $publicUrl) {
            $this->info("Menjalankan Ngrok tunnel pada port {$port}...");

            $ngrokPath = $this->findNgrokPath();
            if (! $ngrokPath) {
                $this->error('Executable ngrok tidak ditemukan di sistem atau folder C:\laragon\bin\ngrok\ngrok.exe');

                return Command::FAILURE;
            }

            // Jalankan process di background
            $process = new Process([$ngrokPath, 'http', (string) $port, '--log=stdout']);
            $process->start();

            // Tunggu hingga API local ngrok siap (maks 10 detik)
            $attempts = 0;
            while ($attempts < 10 && ! $publicUrl) {
                sleep(1);
                $publicUrl = $this->getActiveNgrokUrl();
                $attempts++;
            }

            if (! $publicUrl) {
                $this->error('Gagal mendapatkan URL publik dari Ngrok.');
                if ($process->getErrorOutput()) {
                    $this->line($process->getErrorOutput());
                }

                return Command::FAILURE;
            }
        }

        $this->newLine();
        $this->line('=======================================================================');
        $this->info('           GIANYAR PLANNER ONLINE TUNNEL (NGROK LIVE)                  ');
        $this->line('=======================================================================');
        $this->line(" URL Publik Online : \e[1;32m{$publicUrl}\e[0m");
        $this->line(" Server Lokal      : \e[36mhttp://localhost:{$port}\e[0m");
        $this->line(" Web Dashboard UI  : \e[33mhttp://localhost:4040\e[0m (Inspeksi Request & Header)");
        $this->line('=======================================================================');
        $this->newLine();
        $this->comment('CATATAN UNTUK TEMAN ANDA:');
        $this->line('1. Pada kunjungan pertama di browser, Ngrok Free menampilkan layar peringatan.');
        $this->line("   Cukup klik tombol \e[1m'Visit Site'\e[0m untuk langsung masuk ke Gianyar Planner.");
        $this->line('2. Teman Anda dapat langsung mencoba mengisi form preferensi, melihat');
        $this->line('   rekomendasi itinerary cerdas, menyewa gear, dan memesan tour guide.');
        $this->newLine();
        $this->comment('AKUN UJI COBA (LOGIN):');
        $this->line('• Wisatawan : user@example.com / password');
        $this->line('• Guide     : guide@example.com / password');
        $this->line('• Mitra Gear: mitra@example.com / password');
        $this->line('• SuperAdmin: admin@example.com / password');
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Dapatkan URL publik dari ngrok local API jika sudah aktif.
     */
    protected function getActiveNgrokUrl(): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 1.5,
                ],
            ]);

            $json = @file_get_contents('http://127.0.0.1:4040/api/tunnels', false, $context);
            if ($json) {
                $data = json_decode($json, true);
                if (! empty($data['tunnels'][0]['public_url'])) {
                    return $data['tunnels'][0]['public_url'];
                }
            }
        } catch (\Throwable) {
            // Silently ignore if not reachable
        }

        return null;
    }

    /**
     * Cari letak binary ngrok.exe.
     */
    protected function findNgrokPath(): ?string
    {
        $laragonPath = 'C:\\laragon\\bin\\ngrok\\ngrok.exe';
        if (file_exists($laragonPath)) {
            return $laragonPath;
        }

        // Cek PATH environment
        $output = shell_exec('where ngrok 2>nul');
        if ($output && trim($output)) {
            $lines = explode("\n", trim($output));

            return trim($lines[0]);
        }

        return null;
    }
}
