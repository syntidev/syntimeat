<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests de roles y acceso — corre contra DB real, sin RefreshDatabase.
 * Usuarios de prueba se marcan con prefijo [ST] para cleanup seguro.
 *
 * Correr con: php artisan test tests/Feature/RolesYAccesoTest.php
 */
class RolesYAccesoTest extends TestCase
{
    private User $cajero;
    private User $admin;
    private User $superAdmin;
    private int  $branchId;

    // ─── Setup: crear usuarios de prueba apuntando al negocio real ───────────

    protected function setUp(): void
    {
        parent::setUp();

        $business       = Business::firstOrFail();
        $businessId     = $business->id;
        $this->branchId = Branch::where('business_id', $businessId)->value('id') ?? 1;

        $base = [
            'business_id'       => $businessId,
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
            'session_token'     => null,  // evita bloqueo de EnforceUserSession
            'access_days'       => null,  // sin restricción de días
            'access_start'      => null,  // sin restricción horaria
            'access_end'        => null,
        ];

        $this->cajero = User::create(array_merge($base, [
            'name'      => '[ST] Cajero Test',
            'email'     => '[ST]cajero@test.syntimeat',
            'role'      => 'cashier',
            'branch_id' => $this->branchId,
        ]));

        $this->admin = User::create(array_merge($base, [
            'name'      => '[ST] Admin Test',
            'email'     => '[ST]admin@test.syntimeat',
            'role'      => 'admin',
            'branch_id' => $this->branchId,
        ]));

        $this->superAdmin = User::create(array_merge($base, [
            'name'      => '[ST] SuperAdmin Test',
            'email'     => '[ST]superadmin@test.syntimeat',
            'role'      => 'super_admin',
            'branch_id' => null,
        ]));
    }

    // ─── Teardown: eliminar cajas y usuarios de prueba ────────────────────────

    protected function tearDown(): void
    {
        CashRegister::where('name', 'like', '[ST]%')->delete();

        $ids = array_filter([
            $this->cajero->id     ?? null,
            $this->admin->id      ?? null,
            $this->superAdmin->id ?? null,
        ]);

        if (! empty($ids)) {
            User::whereIn('id', $ids)->delete();
        }

        parent::tearDown();
    }

    // ─── TEST 1 — Cajero accede a POS y Caja ─────────────────────────────────

    public function test_cajero_accede_a_pos(): void
    {
        $this->actingAs($this->cajero)
            ->get('/pos')
            ->assertStatus(200);
    }

    public function test_cajero_accede_a_caja(): void
    {
        $this->actingAs($this->cajero)
            ->get('/caja')
            ->assertStatus(200);
    }

    // ─── TEST 2 — Cajero bloqueado de módulos restringidos ───────────────────
    // EnsureRole hace abort(403) — no redirect — cuando el rol no está permitido.

    public function test_cajero_bloqueado_en_boveda(): void
    {
        $this->actingAs($this->cajero)
            ->get('/boveda')
            ->assertStatus(403);
    }

    public function test_cajero_bloqueado_en_configuracion_general(): void
    {
        $this->actingAs($this->cajero)
            ->get('/configuracion/general')
            ->assertStatus(403);
    }

    public function test_cajero_bloqueado_en_configuracion_usuarios(): void
    {
        $this->actingAs($this->cajero)
            ->get('/configuracion/usuarios')
            ->assertStatus(403);
    }

    public function test_cajero_bloqueado_en_reportes(): void
    {
        // /reportes está bajo role:super_admin,admin,owner,branch_admin,supervisor,analyst
        $this->actingAs($this->cajero)
            ->get('/reportes')
            ->assertStatus(403);
    }

    // ─── TEST 3 — Admin accede a configuración, bóveda y POS ─────────────────

    public function test_admin_accede_a_configuracion_general(): void
    {
        $this->actingAs($this->admin)
            ->get('/configuracion/general')
            ->assertStatus(200);
    }

    public function test_admin_accede_a_boveda(): void
    {
        $this->actingAs($this->admin)
            ->get('/boveda')
            ->assertStatus(200);
    }

    public function test_admin_accede_a_pos(): void
    {
        $this->actingAs($this->admin)
            ->get('/pos')
            ->assertStatus(200);
    }

    // ─── TEST 4 — Admin bloqueado de /configuracion/usuarios (solo super_admin)

    public function test_admin_bloqueado_en_configuracion_usuarios(): void
    {
        $this->actingAs($this->admin)
            ->get('/configuracion/usuarios')
            ->assertStatus(403);
    }

    // ─── TEST 5 — storeCashRegister guarda branch_id correctamente ───────────

    public function test_store_cash_register_guarda_branch_id(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/configuracion/cajas', [
                'name'      => '[ST] Caja Test',
                'branch_id' => $this->branchId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_registers', [
            'name'      => '[ST] Caja Test',
            'branch_id' => $this->branchId,
        ]);
    }

    public function test_store_cash_register_sin_branch_guarda_null(): void
    {
        // superAdmin tiene branch_id=null → el controller asigna null al registro
        $this->actingAs($this->superAdmin)
            ->post('/configuracion/cajas', [
                'name' => '[ST] Caja Sin Branch',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_registers', [
            'name'      => '[ST] Caja Sin Branch',
            'branch_id' => null,
        ]);
    }

    // ─── TEST 6 — Cajero no puede crear entradas en bóveda ───────────────────
    // Nota: la ruta es POST /boveda (no /boveda/entradas — esa no existe).

    public function test_cajero_no_puede_crear_entrada_boveda(): void
    {
        $this->actingAs($this->cajero)
            ->post('/boveda', [
                'product_type' => 'RES - Medio Canal',
                'kg_entrada'   => 100,
                'supplier'     => 'Test',
                'entered_at'   => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    // ─── TEST 7 — Super admin ve todo sin restricción ─────────────────────────

    public function test_super_admin_accede_a_configuracion_usuarios(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/configuracion/usuarios')
            ->assertStatus(200);
    }

    public function test_super_admin_accede_a_boveda(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/boveda')
            ->assertStatus(200);
    }
}
