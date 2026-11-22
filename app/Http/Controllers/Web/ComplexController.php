<?php

namespace App\Http\Controllers\Web;

use App\Actions\Complex\SelectActiveComplex;
use App\Enums\ComplexModule;
use App\Enums\ResidentialComplexStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidentialComplexRequest;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Services\ComplexCatalog;
use App\Services\ComplexContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La UI del tenant root (spec S94): el conjunto tiene nombre propio en la
 * web (`/complexes`) y el actor elige de forma explicita con que conjunto
 * trabaja.
 *
 * El conjunto activo se resuelve en el servidor, contra las membresias
 * activas del actor; nunca se toma del cliente. Un conjunto ajeno no se
 * resuelve (404) y no se filtra su existencia (ACE-C01).
 *
 * La eleccion del conjunto activo no se reimplementa aqui: la hace la
 * accion `SelectActiveComplex`, la misma que usa la API.
 */
class ComplexController extends Controller
{
    public function __construct(private readonly SelectActiveComplex $selectActiveComplex) {}

    /**
     * Listado de los conjuntos del actor.
     *
     * Marca el conjunto activo y ofrece el selector cuando el actor tiene
     * mas de uno. Un actor sin conjuntos ve el estado "todavia no tenes
     * conjuntos" (y el alta, si tiene permiso), no una tabla vacia sin
     * explicacion.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $complexes = ComplexCatalog::catalog($user);

        return Inertia::render('Complexes/Index', [
            'complexes' => $complexes,
            'activeComplexUuid' => ComplexContext::getCurrentComplex()?->uuid,
            'selectionRequired' => (bool) $request->session()->get('complex_selection_required', false),
            'canSelect' => count($complexes) > 1,
            'canCreate' => Gate::allows('create', ResidentialComplex::class),
        ]);
    }

    /**
     * Formulario de alta.
     *
     * El alta es un permiso de plataforma (`complex.create`): el resto de
     * los miembros recibe 403.
     */
    public function create(): Response
    {
        abort_unless(
            Gate::allows('create', ResidentialComplex::class),
            403,
            'No tienes permiso para crear conjuntos.'
        );

        return Inertia::render('Complexes/Create', [
            'defaults' => [
                'country_code' => 'CO',
                'timezone' => 'America/Bogota',
                'currency_code' => 'COP',
            ],
        ]);
    }

    /**
     * Alta de un conjunto: nace siempre en borrador.
     *
     * La puerta es la del permiso de plataforma (`complex.create`), la misma
     * que exige la API.
     */
    public function store(StoreResidentialComplexRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['country_code'] = $validated['country_code'] ?? 'CO';
        $validated['timezone'] = $validated['timezone'] ?? 'America/Bogota';
        $validated['currency_code'] = $validated['currency_code'] ?? 'COP';
        $validated['status'] = ResidentialComplexStatus::DRAFT;

        $complex = ResidentialComplex::create($validated);

        return redirect()
            ->route('complexes.show', $complex->uuid)
            ->with('success', 'Conjunto creado exitosamente.');
    }

    /**
     * Ficha del conjunto: identidad, estado, modulos habilitados, roles del
     * catalogo y miembros del conjunto.
     */
    public function show(Request $request, string $complex): Response
    {
        $model = $this->resolve($complex);

        // Un conjunto fuera del alcance del actor no se resuelve: 404, sin
        // filtrar su existencia.
        abort_unless(Gate::allows('view', $model), 404, 'Conjunto no encontrado.');

        return Inertia::render('Complexes/Show', [
            'complex' => $this->identity($model),
            'modules' => $this->modules($model),
            'roles' => $this->roles($model),
            'members' => $this->members($model),
        ]);
    }

    /**
     * Elige el conjunto activo del actor (spec S93).
     *
     * La eleccion la hace `SelectActiveComplex`, la misma accion que usa la
     * API: solo se puede elegir un conjunto del que el actor es miembro
     * activo y un conjunto ajeno o sin membresia activa responde 404.
     *
     * Al terminar, el actor vuelve a la pantalla desde la que eligio.
     */
    public function select(Request $request, string $complex): RedirectResponse
    {
        $user = $request->user();

        $model = $this->selectActiveComplex->select($user, $complex);

        $target = $this->redirectTarget($request, $model);

        // La eleccion ya se hizo: el destino pendiente se consume.
        $request->session()->forget('url.intended');

        return redirect()
            ->to($target)
            ->with('success', 'Conjunto activo actualizado.');
    }

    /**
     * Conjunto resuelto por su uuid.
     *
     * Un identificador que no es un uuid no puede existir: se responde 404
     * en vez de dejar que PostgreSQL rechace la consulta con un error de
     * tipo (500).
     */
    private function resolve(string $uuid): ResidentialComplex
    {
        if (! Str::isUuid($uuid)) {
            abort(404, 'Conjunto no encontrado.');
        }

        $complex = ResidentialComplex::query()->where('uuid', $uuid)->first();

        abort_if(! $complex instanceof ResidentialComplex, 404, 'Conjunto no encontrado.');

        return $complex;
    }

    /**
     * Identidad del conjunto.
     *
     * Los datos de gobierno (`legal_identifier`) solo se exponen a quien
     * administra la configuracion del conjunto o a la plataforma.
     *
     * @return array<string, mixed>
     */
    private function identity(ResidentialComplex $complex): array
    {
        $identity = [
            'uuid' => $complex->uuid,
            'name' => $complex->name,
            'code' => $complex->code,
            'address' => $complex->address,
            'city' => $complex->city,
            'state' => $complex->state,
            'country_code' => $complex->country_code,
            'timezone' => $complex->timezone,
            'currency_code' => $complex->currency_code,
            'status' => $complex->status->value,
            'status_label' => $complex->status->label(),
            'created_at' => $complex->created_at?->toIso8601String(),
            'updated_at' => $complex->updated_at?->toIso8601String(),
        ];

        if (Gate::allows('viewGovernance', $complex)) {
            $identity['legal_identifier'] = $complex->legal_identifier;
        }

        return $identity;
    }

    /**
     * Modulos habilitados del conjunto.
     *
     * El orden es el del catalogo de modulos: primero los nucleos y
     * despues los opcionales.
     *
     * @return list<array<string, mixed>>
     */
    private function modules(ResidentialComplex $complex): array
    {
        $order = array_flip(array_map(
            fn (ComplexModule $module): string => $module->value,
            ComplexModule::cases()
        ));

        return $complex->modules()
            ->enabled()
            ->get()
            ->sortBy(fn ($module): int => $order[$module->module_key->value] ?? 0)
            ->map(fn ($module): array => [
                'key' => $module->module_key->value,
                'label' => $module->module_key->label(),
                'description' => $module->module_key->description(),
                'enabled' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Roles del catalogo y roles propios del conjunto.
     *
     * @return list<array<string, mixed>>
     */
    private function roles(ResidentialComplex $complex): array
    {
        return Role::query()
            ->where(function ($query) use ($complex): void {
                $query->whereNull('residential_complex_id')
                    ->orWhere('residential_complex_id', $complex->id);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => [
                'key' => $role->key,
                'name' => $role->name,
                'is_system' => (bool) $role->is_system,
                'is_active' => (bool) $role->is_active,
                'scope' => $role->residential_complex_id === null ? 'system' : 'complex',
            ])
            ->values()
            ->all();
    }

    /**
     * Miembros del conjunto con sus roles vigentes.
     *
     * @return list<array<string, mixed>>
     */
    private function members(ResidentialComplex $complex): array
    {
        return $complex->memberships()
            ->with(['user', 'roles'])
            ->get()
            ->map(fn (ResidentialComplexMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'status' => $membership->status->value,
                'status_label' => $membership->status->label(),
                'roles' => $membership->roles
                    ->map(fn (Role $role): string => $role->name)
                    ->values()
                    ->all(),
                'joined_at' => $membership->joined_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * La pantalla a la que vuelve el actor tras elegir el conjunto.
     *
     * Prioridad: el destino explicito de la peticion (el selector de la
     * cabecera), la pantalla anterior y, en ultimo termino, la ficha del
     * conjunto. Nunca se vuelve a la propia peticion (eso seria un bucle).
     */
    private function redirectTarget(Request $request, ResidentialComplex $complex): string
    {
        $candidates = [];

        $explicit = $request->query('redirect');
        if (is_string($explicit) && $explicit !== '') {
            $candidates[] = $explicit;
        }

        $previous = url()->previous();
        if (is_string($previous) && $previous !== '') {
            $candidates[] = $previous;
        }

        $intended = $request->session()->get('url.intended');
        if (is_string($intended) && $intended !== '') {
            $candidates[] = $intended;
        }

        $current = $request->fullUrl();

        foreach ($candidates as $candidate) {
            if ($candidate === $current) {
                continue;
            }

            if ($this->isLocalUrl($candidate, $request)) {
                return $candidate;
            }
        }

        return route('complexes.show', $complex->uuid);
    }

    /**
     * El destino es una URL de la propia aplicacion.
     *
     * Un destino externo (o un protocolo peligroso) no se sigue: se cae en
     * la ficha del conjunto.
     */
    private function isLocalUrl(string $url, Request $request): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        return $host === $request->getHost()
            || $host === parse_url((string) config('app.url'), PHP_URL_HOST);
    }
}
