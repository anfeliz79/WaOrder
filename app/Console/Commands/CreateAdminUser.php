<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'waorder:create-admin
                            {--email=argenis1989@gmail.com : Email del admin}
                            {--password=WaOrder2026! : Contraseña}
                            {--name=Aneurys Feliz : Nombre}';

    protected $description = 'Crear tenant inicial y usuario admin';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $tenant = Tenant::create([
                'name'       => 'Mi Restaurante',
                'slug'       => 'default',
                'timezone'   => 'America/Santo_Domingo',
                'currency'   => 'DOP',
                'is_active'  => true,
                'settings'   => [],
            ]);
            $this->info("Tenant creado: {$tenant->name} (ID: {$tenant->id})");
        } else {
            $this->info("Tenant existente: {$tenant->name} (ID: {$tenant->id})");
        }

        $email    = $this->option('email');
        $password = $this->option('password');
        $name     = $this->option('name');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'password'  => Hash::make($password),
                'role'      => 'admin',
                'tenant_id' => $tenant->id,
            ]);
            $this->warn("Usuario actualizado (ya existía).");
        } else {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'password'  => Hash::make($password),
                'role'      => 'admin',
                'tenant_id' => $tenant->id,
            ]);
            $this->info("Usuario creado.");
        }

        $this->newLine();
        $this->table(['Campo', 'Valor'], [
            ['Email', $email],
            ['Password', $password],
            ['Rol', 'admin'],
            ['Tenant', $tenant->name],
        ]);

        return self::SUCCESS;
    }
}
