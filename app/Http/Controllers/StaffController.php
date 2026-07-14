<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = User::where('is_system', false)
            ->withSum(['orders' => fn ($q) => $q->where('status', 'completed')], 'total')
            ->withCount(['orders' => fn ($q) => $q->where('status', 'completed')])
            ->orderByDesc('orders_sum_total')
            ->get();

        return view('staff.index', ['staff' => $staff]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $colors = ['#e8a838', '#34d399', '#60a5fa', '#f87171', '#a78bfa', '#fb923c', '#2dd4bf', '#f472b6'];
        $data['color'] = $colors[array_rand($colors)];
        $data['avatar_seed'] = 'staff'.time();
        $data['active'] = $request->boolean('active');
        $data['password'] = $data['password'] ?? 'password';
        User::create($data);

        return back()->with('success', 'Staff member added.');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $data = $this->validated($request, $staff->id);
        $data['active'] = $request->boolean('active');
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $staff->update($data);

        return back()->with('success', 'Staff member updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($ignoreId)],
            'role' => ['required', 'in:admin,manager,cashier,barista'],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);
    }
}
