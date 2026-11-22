<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Enums\ResidentialComplexStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bootstrap del sistema (spec S91).
 *
 * Sin este comando no hay camino al primer conjunto: la policy de alta exige
 * una membresia de plataforma y una membresia exige un conjunto. El comando
 * crea, en una sola transaccion, el conjunto en borrador, el usuario
 * administrador, la membresia activa y la asignacion vigente del rol de
 * sistema `platform_admin` (el unico con `complex.create`), y siembra los
 * roles del sistema si faltan.
 *
 * Es idempotente por `code`: si el conjunto ya existe, falla con un mensaje
 * claro, no duplica nada y devuelve un codigo de salida distinto de cero. Si
 * el correo del administrador ya tiene cuenta, la cuenta se reutiliza.
 */
class ComplexBootstrapCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'z360:create-complex
        {--name= : Nombre del conjunto}
        {--code= : Codigo unico del conjunto}
        {--admin-email= : Correo del administrador}
        {--admin-name= : Nombre del administrador}
        {--admin-password= : Contrasena del administrador (obligatoria si la cuenta no existe todavia)}';

    /**
     * @var string
     */
    protected $description = 'Crea un conjunto en borrador con su administrador de plataforma (bootstrap del sistema)';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $code = trim((string) $this->option('code'));
        $email = trim((string) $this->option('admin-email'));
        $adminName = trim((string) $this->option('admin-name'));
        $password = (string) $this->option('admin-password');

        if ($name === '' || $code === '' || $email === '') {
            $this->error('Faltan datos obligatorios: --name, --code y --admin-email.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error(sprintf('El correo del administrador «%s» no es valido.', $email));

            return self::FAILURE;
        }

        // Idempotencia por codigo: un conjunto con ese codigo ya existe.
        $existing = ResidentialComplex::query()->where('code', $code)->first();

        if ($existing instanceof ResidentialComplex) {
            $this->error(sprintf(
                'El conjunto con el codigo «%s» ya existe (id %d). No se creo nada.',
                $code,
                $existing->id
            ));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        // La contrasena solo es obligatoria si la cuenta no existe todavia.
        if (! $user instanceof User && $password === '') {
            $this->error('La cuenta del administrador no existe todavia: --admin-password es obligatoria.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($name, $code, $email, $adminName, $password, $user): array {
                $this->seedSystemRoles();

                $complex = ResidentialComplex::create([
                    'name' => $name,
                    'code' => $code,
                    'address' => '',
                    'city' => '',
                    'country_code' => 'CO',
                    'timezone' => 'America/Bogota',
                    'currency_code' => 'COP',
                    'status' => ResidentialComplexStatus::DRAFT,
                ]);

                $admin = $user instanceof User
                    ? $user
                    : User::create([
                        'name' => $adminName !== '' ? $adminName : 'Administrador',
                        'email' => $email,
                        'password' => $password,
                    ]);

                $membership = ResidentialComplexMembership::create([
                    'residential_complex_id' => $complex->id,
                    'user_id' => $admin->id,
                    'status' => MembershipStatus::ACTIVE,
                    'invited_at' => now(),
                    'joined_at' => now(),
                ]);

                $platformRole = Role::query()
                    ->whereNull('residential_complex_id')
                    ->where('key', SystemRole::PLATFORM_ADMIN->value)
                    ->firstOrFail();

                // Asignacion vigente: sin caducidad.
                $membership->roles()->attach($platformRole->id, [
                    'valid_from' => now(),
                    'valid_until' => null,
                ]);

                return ['complex' => $complex, 'admin' => $admin];
            });
        } catch (Throwable $exception) {
            $this->error('No se pudo crear el conjunto: '.$exception->getMessage());

            return self::FAILURE;
        }

        /** @var ResidentialComplex $complex */
        $complex = $result['complex'];
        /** @var User $admin */
        $admin = $result['admin'];

        $this->info('Conjunto creado en borrador.');
        $this->line('  id: '.$complex->id);
        $this->line('  uuid: '.$complex->uuid);
        $this->line('  code: '.$complex->code);
        $this->line('  administrador: '.$admin->email);

        return self::SUCCESS;
    }

    /**
     * Siembra los roles del sistema si faltan.
     *
     * Los modulos del conjunto no se siembran aqui: eso lo hace el observer
     * del conjunto.
     */
    private function seedSystemRoles(): void
    {
        foreach (SystemRole::cases() as $role) {
            $exists = Role::query()
                ->whereNull('residential_complex_id')
                ->where('key', $role->value)
                ->exists();

            if (! $exists) {
                app(SystemRoleSeeder::class)->run();

                return;
            }
        }
    }
}
