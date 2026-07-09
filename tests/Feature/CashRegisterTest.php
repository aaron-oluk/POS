<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_and_close_a_balanced_register_session(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);

        $this->actingAs($cashier)->post('/cash-register/open', ['opening_float' => 50])
            ->assertRedirect();

        $session = CashRegisterSession::first();
        $this->assertSame('open', $session->status);

        $order = Order::create([
            'user_id' => $cashier->id,
            'subtotal' => 20, 'tax' => 0, 'tip' => 0, 'total' => 20,
            'payment_method' => 'cash', 'status' => 'completed',
        ]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => 20]);

        $this->actingAs($cashier)->put("/cash-register/{$session->id}/close", ['counted_cash' => 70])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertEquals(70, (float) $session->expected_cash);
        $this->assertEquals(70, (float) $session->counted_cash);
        $this->assertEquals(0, (float) $session->discrepancy);
    }

    public function test_cannot_open_a_second_session_while_one_is_already_open(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);
        CashRegisterSession::create([
            'user_id' => $cashier->id, 'opened_at' => now(), 'opening_float' => 50, 'status' => 'open',
        ]);

        $this->actingAs($cashier)->post('/cash-register/open', ['opening_float' => 30])
            ->assertRedirect();

        $this->assertDatabaseCount('cash_register_sessions', 1);
    }

    public function test_a_cashier_cannot_close_another_cashiers_session(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);
        $otherCashier = User::factory()->create(['role' => 'cashier', 'active' => true]);
        $session = CashRegisterSession::create([
            'user_id' => $otherCashier->id, 'opened_at' => now(), 'opening_float' => 50, 'status' => 'open',
        ]);

        $this->actingAs($cashier)->put("/cash-register/{$session->id}/close", ['counted_cash' => 50])
            ->assertForbidden();
    }
}
