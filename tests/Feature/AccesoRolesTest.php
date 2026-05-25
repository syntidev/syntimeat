<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests de acceso por rol — DB real, sin RefreshDatabase, cleanup manual.
 *
 * Supuestos:
 *  - User::find(1) existe y tiene role = 'super_admin'.
 *  - Branch con id=1 existe (requerido por validación exists:branches,id en caja store).
 *  - El business tiene onboarding_completed=true y subscription_active=true.
 *
 * Correr con: php artisan test tests/Feature/AccesoRolesTest.php
 */
class AccesoRolesTest extends TestCase
{
    private User $cajero;
    private User $admin;
    private User $supervisor;
    private User $superAdmin;
    private User $owner;
    private User $analyst;

    // ─── Setup ────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->withoutMiddleware([
            \App\Http\Middleware\EnforceUserSession::class,
            \App\Http\Middleware\CheckOnboarding::class,
            \App\Http\Middleware\CheckSubscription::class,
        ]);

        $business   = Business::firstOrFail();
        $businessId = $business->id;

        // Atributos base: evitan bloqueos de EnforceUserSession y verified middleware
        $base = [
            'business_id'       => $businessId,
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
            'session_token'     => null,
            'access_days'       => null,
            'access_start'      => null,
            'access_end'        => null,
        ];

        $this->cajero = User::create(array_merge($base, [
            'name'      => '[ST] Cajero AccesoTest',
            'email'     => '[ST]cajero.acceso@test.syntimeat',
            'role'      => 'cashier',
            'branch_id' => 1,
        ]));

        $this->admin = User::create(array_merge($base, [
            'name'      => '[ST] Admin AccesoTest',
            'email'     => '[ST]admin.acceso@test.syntimeat',
            'role'      => 'admin',
            'branch_id' => null,
        ]));

        $this->supervisor = User::create(array_merge($base, [
            'name'      => '[ST] Supervisor AccesoTest',
            'email'     => '[ST]supervisor.acceso@test.syntimeat',
            'role'      => 'supervisor',
            'branch_id' => 1,
        ]));

        $this->superAdmin = User::factory()->create([
            'business_id'   => $business->id,
            'role'          => 'super_admin',
            'session_token' => null,
            'is_active'     => true,
            'branch_id'     => null,
            'access_days'   => null,
            'access_start'  => null,
            'access_end'    => null,
            'name'          => '[ST] SuperAdmin Test',
            'email'         => 'st-superadmin-' . time() . '@syntimeat.test',
            'password'      => bcrypt('password'),
        ]);

        $this->owner = User::factory()->create([
            'business_id'       => $business->id,
            'role'              => 'owner',
            'session_token'     => null,
            'is_active'         => true,
            'is_hidden'         => false,
            'email_verified_at' => now(),
            'branch_id'         => null,
            'access_days'       => null,
            'access_start'      => null,
            'access_end'        => null,
            'name'              => '[ST] Owner Test',
            'email'             => 'st-owner-' . time() . '@syntimeat.test',
            'password'          => bcrypt('password'),
        ]);

        $this->analyst = User::factory()->create([
            'business_id'       => $business->id,
            'role'              => 'analyst',
            'session_token'     => null,
            'is_active'         => true,
            'is_hidden'         => false,
            'email_verified_at' => now(),
            'branch_id'         => null,
            'access_days'       => null,
            'access_start'      => null,
            'access_end'        => null,
            'name'              => '[ST] Analyst Test',
            'email'             => 'st-analyst-' . time() . '@syntimeat.test',
            'password'          => bcrypt('password'),
        ]);
    }

    // ─── Teardown ─────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        CashRegister::where('name', 'like', '[ST]%')->delete();

        $ids = array_filter([
            $this->cajero->id     ?? null,
            $this->admin->id      ?? null,
            $this->supervisor->id ?? null,
            $this->superAdmin->id ?? null,
            $this->owner->id      ?? null,
            $this->analyst->id    ?? null,
        ]);

        if (! empty($ids)) {
            User::whereIn('id', $ids)->delete();
        }

        parent::tearDown();
    }

    // ─── CAJERO ───────────────────────────────────────────────────────────────

    /** Test 1 — Cajero accede a POS */
    public function test_cajero_accede_pos(): void
    {
        $this->actingAs($this->cajero)
            ->get('/pos')
            ->assertStatus(200);
    }

    /** Test 2 — Cajero accede a Caja */
    public function test_cajero_accede_caja(): void
    {
        $this->actingAs($this->cajero)
            ->get('/caja')
            ->assertStatus(200);
    }

    /** Test 3 — Cajero bloqueado en Bóveda */
    public function test_cajero_bloqueado_boveda(): void
    {
        $this->actingAs($this->cajero)
            ->get('/boveda')
            ->assertStatus(403);
    }

    /** Test 4 — Cajero bloqueado en Fábrica */
    public function test_cajero_bloqueado_fabrica(): void
    {
        $this->actingAs($this->cajero)
            ->get('/fabrica')
            ->assertStatus(403);
    }

    /** Test 5 — Cajero bloqueado en Configuración General */
    public function test_cajero_bloqueado_configuracion_general(): void
    {
        $this->actingAs($this->cajero)
            ->get('/configuracion/general')
            ->assertStatus(403);
    }

    /** Test 6 — Cajero bloqueado en Configuración Usuarios */
    public function test_cajero_bloqueado_configuracion_usuarios(): void
    {
        $this->actingAs($this->cajero)
            ->get('/configuracion/usuarios')
            ->assertStatus(403);
    }

    /**
     * Test 7 — Cajero bloqueado en Reportes.
     * /reportes está bajo role:super_admin,admin,owner,branch_admin,supervisor,analyst.
     * cashier no está incluido → 403.
     */
    public function test_cajero_bloqueado_reportes(): void
    {
        $this->actingAs($this->cajero)
            ->get('/reportes')
            ->assertStatus(403);
    }

    // ─── ADMIN ────────────────────────────────────────────────────────────────

    /** Test 8 — Admin accede a Configuración General */
    public function test_admin_accede_configuracion_general(): void
    {
        $this->actingAs($this->admin)
            ->get('/configuracion/general')
            ->assertStatus(200);
    }

    /** Test 9 — Admin accede a Bóveda */
    public function test_admin_accede_boveda(): void
    {
        $this->actingAs($this->admin)
            ->get('/boveda')
            ->assertStatus(200);
    }

    /** Test 10 — Admin accede a POS */
    public function test_admin_accede_pos(): void
    {
        $this->actingAs($this->admin)
            ->get('/pos')
            ->assertStatus(200);
    }

    /** Test 11 — Admin bloqueado en Configuración Usuarios (solo super_admin) */
    public function test_admin_bloqueado_configuracion_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get('/configuracion/usuarios')
            ->assertStatus(403);
    }

    // ─── OWNER ───────────────────────────────────────────────────────────────

    /** Test 12 — Owner accede a Bóveda */
    public function test_owner_accede_boveda(): void
    {
        $this->withoutExceptionHandling()
            ->actingAs($this->owner, 'web')
            ->get('/boveda')
            ->assertStatus(200);
    }

    /** Test 13 — Owner accede a Configuración General */
    public function test_owner_accede_configuracion_general(): void
    {
        $this->actingAs($this->owner, 'web')
            ->get('/configuracion/general')
            ->assertStatus(200);
    }

    /** Test 14 — Owner bloqueado en Configuración Usuarios (solo super_admin) */
    public function test_owner_bloqueado_configuracion_usuarios(): void
    {
        $this->actingAs($this->owner)
            ->get('/configuracion/usuarios')
            ->assertStatus(403);
    }

    // ─── ANALYST ─────────────────────────────────────────────────────────────

    /** Test 15 — Analyst accede a Reportes */
    public function test_analyst_accede_reportes(): void
    {
        $this->actingAs($this->analyst, 'web')
            ->get('/reportes')
            ->assertStatus(200);
    }

    /** Test 16 — Analyst bloqueado en Bóveda */
    public function test_analyst_bloqueado_boveda(): void
    {
        $this->actingAs($this->analyst)
            ->get('/boveda')
            ->assertStatus(403);
    }

    /** Test 17 — Analyst bloqueado en Configuración General */
    public function test_analyst_bloqueado_configuracion_general(): void
    {
        $this->actingAs($this->analyst)
            ->get('/configuracion/general')
            ->assertStatus(403);
    }

    // ─── SUPER ADMIN ──────────────────────────────────────────────────────────

    /** Test 12 — Super admin accede a Configuración Usuarios */
    public function test_super_admin_accede_configuracion_usuarios(): void
    {
        $this->withoutExceptionHandling()
            ->actingAs($this->superAdmin)
            ->get('/configuracion/usuarios')
            ->assertStatus(200)->assertOk();
    }

    /** Test 13 — Super admin accede a Bóveda */
    public function test_super_admin_accede_boveda(): void
    {
        $this->withoutExceptionHandling()
            ->actingAs($this->superAdmin)
            ->get('/boveda')
            ->assertStatus(200)->assertOk();
    }

    // ─── BUG CAJA — branch_id ─────────────────────────────────────────────────

    /**
     * Test 14 — storeCashRegister guarda branch_id=1.
     * Requiere que exista un branch con id=1 en la DB (validación exists:branches,id).
     */
    public function test_store_caja_guarda_branch_id_1(): void
    {
        $this->actingAs($this->superAdmin)
            ->withHeaders(['X-CSRF-TOKEN' => 'test'])
            ->post('/configuracion/cajas', [
                'name'      => '[ST] Caja B1',
                'branch_id' => 1,
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('cash_registers', [
            'name'      => '[ST] Caja B1',
            'branch_id' => 1,
        ]);
    }

    /**
     * Test 15 — storeCashRegister sin branch_id guarda null.
     * superAdmin tiene branch_id=null; el controller usa $data['branch_id'] ?? $user->branch_id.
     */
    public function test_store_caja_sin_branch_guarda_null(): void
    {
        $this->actingAs($this->superAdmin)
            ->withHeaders(['X-CSRF-TOKEN' => 'test'])
            ->post('/configuracion/cajas', [
                'name' => '[ST] Caja Sin Branch',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('cash_registers', [
            'name'      => '[ST] Caja Sin Branch',
            'branch_id' => null,
        ]);
    }
}
