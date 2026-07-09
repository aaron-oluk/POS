<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $openSession = CashRegisterSession::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        $history = CashRegisterSession::with('user')
            ->when(! $request->user()->isManager(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', 'closed')
            ->latest('closed_at')
            ->paginate(15);

        return view('cash-register.index', [
            'openSession' => $openSession,
            'liveCashSales' => $openSession?->cashSales() ?? 0,
            'history' => $history,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        if (CashRegisterSession::where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
            return back()->with('error', 'You already have an open register session.');
        }

        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        CashRegisterSession::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_float' => $data['opening_float'],
            'status' => 'open',
        ]);

        return back()->with('success', 'Register session opened.');
    }

    public function close(Request $request, CashRegisterSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_unless($session->status === 'open', 422, 'This session is already closed.');

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $expected = (float) $session->opening_float + $session->cashSales();
        $counted = (float) $data['counted_cash'];

        $session->update([
            'closed_at' => now(),
            'expected_cash' => round($expected, 2),
            'counted_cash' => $counted,
            'discrepancy' => round($counted - $expected, 2),
            'status' => 'closed',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Register session closed.');
    }
}
