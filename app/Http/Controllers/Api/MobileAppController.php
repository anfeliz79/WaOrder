<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileAppController extends Controller
{
    private function tenant()
    {
        return app('tenant');
    }

    private function expoToken(): string
    {
        return $this->tenant()->settings['eas']['token'] ?? '';
    }

    private function mobilePath(): string
    {
        $stored = $this->tenant()->settings['eas']['mobile_path'] ?? '';
        return $stored ?: base_path('mobile');
    }

    /**
     * Run an EAS CLI command and return the raw output.
     * Uses the local node_modules/.bin/eas binary directly to avoid PATH issues in PHP.
     */
    private function eas(string $subcommand, array $flags = [], int $timeout = 120): array
    {
        $token      = $this->expoToken();
        $mobilePath = $this->mobilePath();
        $easBin     = $mobilePath . '/node_modules/.bin/eas';

        // Build a PATH that includes node/npx locations + local node_modules/.bin
        // PHP exec() has a minimal PATH, but EAS CLI internally calls `npx expo config`
        $extraPaths = implode(':', array_filter([
            $mobilePath . '/node_modules/.bin',
            '/usr/local/bin',      // common node location
            '/opt/homebrew/bin',   // macOS Homebrew ARM
            '/usr/bin',
        ]));

        $cmd = sprintf(
            'cd %s && export PATH=%s:$PATH && EXPO_TOKEN=%s CI=1 NO_COLOR=1 %s %s %s 2>&1',
            escapeshellarg($mobilePath),
            escapeshellarg($extraPaths),
            escapeshellarg($token),
            escapeshellarg($easBin),
            $subcommand,  // hardcoded values only — not user input
            implode(' ', array_map('escapeshellarg', $flags))
        );

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [
            'output'   => implode("\n", $output),
            'exitCode' => $exitCode,
        ];
    }

    /**
     * List recent builds using EAS CLI.
     * GET /api/mobile-app/builds?platform=android|ios|all
     */
    public function builds(Request $request)
    {
        $platform = $request->get('platform', 'all');
        if (! in_array($platform, ['android', 'ios', 'all'])) {
            $platform = 'all';
        }

        $token = $this->expoToken();
        if (! $token) {
            return response()->json([
                'error' => 'EXPO_TOKEN no configurado. Guarda tu token de Expo en la configuración.',
            ], 422);
        }

        if (! is_dir($this->mobilePath())) {
            return response()->json([
                'error' => 'Directorio del proyecto móvil no encontrado.',
            ], 422);
        }

        $flags = ['--json', '--non-interactive', '--limit', '10'];
        if ($platform !== 'all') {
            $flags[] = '--platform';
            $flags[] = strtoupper($platform);
        }

        $result = $this->eas('build:list', $flags, 45);

        // Extract JSON array from output (CLI may print warnings before it)
        if (preg_match('/\[[\s\S]*\]/m', $result['output'], $matches)) {
            $builds = json_decode($matches[0], true);
            return response()->json($builds ?? []);
        }

        // No JSON found — might be empty or error
        if ($result['exitCode'] !== 0) {
            return response()->json([
                'error' => Str::limit($result['output'], 300),
            ], 500);
        }

        return response()->json([]);
    }

    /**
     * Trigger a new EAS build.
     * POST /api/mobile-app/builds/trigger  { platform, profile }
     */
    public function trigger(Request $request)
    {
        $platform = $request->input('platform');
        $profile  = $request->input('profile', 'preview');

        if (! in_array($platform, ['android', 'ios'])) {
            return response()->json(['error' => 'Plataforma inválida.'], 422);
        }
        if (! in_array($profile, ['preview', 'production'])) {
            return response()->json(['error' => 'Perfil inválido.'], 422);
        }

        $token = $this->expoToken();
        if (! $token) {
            return response()->json(['error' => 'EXPO_TOKEN no configurado.'], 422);
        }
        if (! is_dir($this->mobilePath())) {
            return response()->json(['error' => 'Directorio del proyecto móvil no encontrado.'], 422);
        }

        $result = $this->eas('build', [
            '--platform', $platform,
            '--profile', $profile,
            '--non-interactive',
            '--no-wait',
        ], 180);

        $output = $result['output'];

        // Extract Expo build URL from CLI output
        preg_match('|https://expo\.dev/[^\s\n]+|', $output, $urlMatches);
        $buildUrl = $urlMatches[0] ?? null;

        if ($result['exitCode'] !== 0 && ! $buildUrl) {
            return response()->json([
                'error'  => 'Error al iniciar el build.',
                'detail' => Str::limit($output, 600),
            ], 500);
        }

        return response()->json([
            'status'   => 'triggered',
            'buildUrl' => $buildUrl,
        ]);
    }
}
