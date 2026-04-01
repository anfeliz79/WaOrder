<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/System/Index', [
            'status' => $this->getStatus(),
        ]);
    }

    public function status()
    {
        return response()->json($this->getStatus());
    }

    public function migrate()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => true,
                'output' => $output ?: 'Nada que migrar.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('event:clear');

            return response()->json(['success' => true, 'output' => 'Todos los caches limpiados.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function rebuildCache()
    {
        try {
            Artisan::call('config:cache');
            $out = trim(Artisan::output());
            Artisan::call('route:cache');
            $out .= "\n" . trim(Artisan::output());
            Artisan::call('view:cache');
            $out .= "\n" . trim(Artisan::output());
            Artisan::call('event:cache');
            $out .= "\n" . trim(Artisan::output());

            return response()->json([
                'success' => true,
                'output' => trim($out) ?: 'Caches reconstruidos correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function restartWorkers()
    {
        try {
            Artisan::call('queue:restart');

            return response()->json([
                'success' => true,
                'output' => 'Señal de restart enviada a los workers. Se reiniciarán al terminar el job actual.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function clearFailedJobs()
    {
        try {
            Artisan::call('queue:flush');

            return response()->json(['success' => true, 'output' => 'Jobs fallidos eliminados.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function storageLink()
    {
        try {
            if (is_link(public_path('storage'))) {
                return response()->json(['success' => true, 'output' => 'El symlink de storage ya existe.']);
            }

            Artisan::call('storage:link');

            return response()->json([
                'success' => true,
                'output' => trim(Artisan::output()) ?: 'Symlink creado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'output' => $e->getMessage()], 500);
        }
    }

    public function logs()
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            return response()->json(['lines' => []]);
        }

        // Read last 150 lines
        $lines = $this->tailFile($logFile, 150);

        return response()->json(['lines' => $lines]);
    }

    // -------------------------------------------------------------------------

    private function getStatus(): array
    {
        $env        = app()->environment();
        $debug      = config('app.debug');
        $dbCheck    = $this->checkDatabase();
        $pending    = $this->countPendingMigrations();
        $storageOk  = is_link(public_path('storage'));
        $queueConn  = config('queue.default');
        $cacheStore = config('cache.default');
        $appUrl     = config('app.url', '');
        $https      = str_starts_with($appUrl, 'https://');

        // Build production readiness checklist
        $checklist = [
            [
                'key'   => 'env',
                'label' => 'Modo producción',
                'help'  => 'APP_ENV=production en tu archivo .env del servidor. Esto activa optimizaciones y desactiva mensajes de error detallados.',
                'ok'    => $env === 'production',
                'value' => $env,
                'fix'   => 'Cambia APP_ENV=production en el archivo .env',
            ],
            [
                'key'   => 'debug',
                'label' => 'Debug desactivado',
                'help'  => 'APP_DEBUG=false en .env. Si está en true, los usuarios verán errores técnicos con información sensible de tu servidor.',
                'ok'    => ! $debug,
                'value' => $debug ? 'Activado (peligroso)' : 'Desactivado',
                'fix'   => 'Cambia APP_DEBUG=false en el archivo .env',
            ],
            [
                'key'   => 'database',
                'label' => 'Base de datos conectada',
                'help'  => 'Verifica que MySQL/PostgreSQL esté corriendo y las credenciales DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD en .env sean correctas.',
                'ok'    => $dbCheck['ok'],
                'value' => $dbCheck['ok'] ? 'Conectada (' . $dbCheck['driver'] . ')' : 'Error: ' . ($dbCheck['error'] ?? 'desconocido'),
                'fix'   => 'Revisa las credenciales DB_* en tu .env',
            ],
            [
                'key'   => 'migrations',
                'label' => 'Migraciones al día',
                'help'  => 'Las migraciones crean las tablas en la base de datos. Si hay pendientes, la app puede fallar. Usa el botón "Ejecutar migrate" más abajo.',
                'ok'    => $pending === 0,
                'value' => $pending > 0 ? "{$pending} pendiente(s)" : 'Todas ejecutadas',
                'fix'   => 'Presiona "Ejecutar migrate" en la sección Base de datos',
            ],
            [
                'key'   => 'storage',
                'label' => 'Storage vinculado',
                'help'  => 'El symlink conecta la carpeta donde se guardan archivos (logos, fotos) con la carpeta pública del servidor para que sean visibles en el navegador.',
                'ok'    => $storageOk,
                'value' => $storageOk ? 'Vinculado' : 'No creado',
                'fix'   => 'Presiona "Crear symlink" en la sección Storage',
            ],
            [
                'key'   => 'https',
                'label' => 'HTTPS configurado',
                'help'  => 'APP_URL debe empezar con https:// en producción. Sin SSL, los datos de los usuarios viajan sin encriptar. Usa un certificado gratuito de Let\'s Encrypt.',
                'ok'    => $https,
                'value' => $https ? 'Sí' : 'No (APP_URL: ' . $appUrl . ')',
                'fix'   => 'Cambia APP_URL=https://tudominio.com en .env y configura SSL en tu servidor',
            ],
            [
                'key'   => 'cache',
                'label' => 'Caché optimizado',
                'help'  => 'En producción, reconstruir el caché hace que la app cargue más rápido porque no tiene que leer archivos de configuración cada vez. Usa el botón "Reconstruir caché" más abajo.',
                'ok'    => null, // Info only — always show as actionable
                'value' => "Driver: {$cacheStore}",
                'fix'   => 'Presiona "Reconstruir caché" después de cualquier cambio en .env',
            ],
            [
                'key'   => 'queue',
                'label' => 'Worker de colas corriendo',
                'help'  => 'El worker procesa trabajos en segundo plano: enviar mensajes WhatsApp, notificaciones, etc. Sin worker, los mensajes no se envían. En producción usa Supervisor para que el worker se reinicie solo si falla.',
                'ok'    => null,
                'value' => "Driver: {$queueConn}",
                'fix'   => 'Ejecuta: php artisan queue:work --daemon (o configura Supervisor)',
            ],
            [
                'key'   => 'whatsapp',
                'label' => 'WhatsApp configurado',
                'help'  => 'El chatbot necesita credenciales de Meta Cloud API. Configúralas en Settings → WhatsApp. Necesitas: Phone Number ID, Business Account ID y Access Token de Meta.',
                'ok'    => $this->checkWhatsApp(),
                'value' => $this->checkWhatsApp() ? 'Configurado' : 'Faltan credenciales',
                'fix'   => 'Ve a Configuración → WhatsApp y completa las credenciales de Meta',
            ],
        ];

        return [
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'environment'       => $env,
            'debug_mode'        => $debug,
            'database'          => $dbCheck,
            'redis'             => $this->checkRedis(),
            'pending_migrations'=> $pending,
            'storage_link'      => $storageOk,
            'failed_jobs'       => $this->countFailedJobs(),
            'queue_connection'  => $queueConn,
            'cache_driver'      => $cacheStore,
            'app_url'           => $appUrl,
            'checklist'         => $checklist,
        ];
    }

    private function checkWhatsApp(): bool
    {
        try {
            $tenant = app('tenant');

            return $tenant
                && $tenant->whatsapp_phone_number_id
                && $tenant->whatsapp_access_token;
        } catch (\Exception) {
            return false;
        }
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'driver' => config('database.default')];
        } catch (\Exception $e) {
            return ['ok' => false, 'driver' => config('database.default'), 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['ok' => null, 'note' => 'No configurado'];
        }

        try {
            Redis::ping();

            return ['ok' => true];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function countPendingMigrations(): int
    {
        try {
            $migrator = app('migrator');
            $files    = $migrator->getMigrationFiles(database_path('migrations'));
            $ran      = array_flip($migrator->getRepository()->getRan());

            return count(array_filter(array_keys($files), fn ($key) => ! isset($ran[$key])));
        } catch (\Exception) {
            return 0;
        }
    }

    private function countFailedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception) {
            return 0;
        }
    }

    private function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $start      = max(0, $totalLines - $lines);

        $result = [];
        $file->seek($start);
        while (! $file->eof()) {
            $line = rtrim($file->fgets());
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }
}
