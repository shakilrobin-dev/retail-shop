<?php

namespace App\Livewire\Customers;

use App\Enums\SaleStatus;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    public function render(): View
    {
        return view('livewire.customers.customer-show', [
            'sales' => $this->customer->sales()->with('items')->where('status', '!=', SaleStatus::Held)->latest('sold_at')->limit(50)->get(),
            'repairs' => $this->customer->repairJobs()->latest()->limit(50)->get(),
            'totalSpent' => $this->customer->sales()->where('status', SaleStatus::Completed)->sum('total'),
        ])->title($this->customer->name);
    }
}
