<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->get('q');
        $tierFilter = $request->get('tier');

        $customers = Customer::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('first_name')
            ->get();

        if ($tierFilter) {
            $customers = $customers->filter(fn ($c) => $c->tier === $tierFilter)->values();
        }

        $page = $request->get('page', 1);
        $perPage = 15;
        $paginated = new LengthAwarePaginator(
            $customers->forPage($page, $perPage),
            $customers->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $all = Customer::all();

        return view('customers.index', [
            'customers' => $paginated,
            'totalCustomers' => $all->count(),
            'goldCount' => $all->filter(fn ($c) => $c->tier === 'gold')->count(),
            'avgSpend' => $all->count() ? $all->avg(fn ($c) => $c->total_spent) : 0,
            'search' => $search,
            'tierFilter' => $tierFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $colors = ['#e8a838', '#34d399', '#60a5fa', '#f87171', '#a78bfa', '#fb923c', '#2dd4bf', '#f472b6'];
        $data = $this->validated($request);
        $data['color'] = $colors[array_rand($colors)];
        Customer::create($data);

        return back()->with('success', 'Customer added.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request, $customer->id);
        $customer->update($data);

        return back()->with('success', 'Customer updated.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
