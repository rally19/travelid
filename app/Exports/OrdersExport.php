<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromQuery, WithHeadings, WithMapping
{
    protected $search;
    protected $filters;
    protected $sortBy;
    protected $sortDirection;

    public function __construct($search, $filters, $sortBy, $sortDirection)
    {
        $this->search = $search;
        $this->filters = $filters;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
    }

    public function query()
    {
        return Order::query()
            ->with(['user', 'seats'])
            ->when($this->search['departure_id'], function ($query) {
                if (str_starts_with($this->search['departure_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['departure_id']);
                    $query->where('departure_location', 'like', "%{$regencity}%");
                } else {
                    $query->where('departure_terminal', 'like', "%{$this->search['departure_id']}%");
                }
            })
            ->when($this->search['arrival_id'], function ($query) {
                if (str_starts_with($this->search['arrival_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['arrival_id']);
                    $query->where('arrival_location', 'like', "%{$regencity}%");
                } else {
                    $query->where('arrival_terminal', 'like', "%{$this->search['arrival_id']}%");
                }
            })
            ->when($this->search['user'], function ($query) {
                $query->whereHas('user', function($q) {
                    $q->Where('email', 'like', '%'.$this->search['user'].'%');
                });
            })
            ->when($this->search['code'], function ($query) {
                $query->where('code', 'like', '%'.$this->search['code'].'%');
            })
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->filters['payment_method'], function ($query) {
                $query->where('payment_method', $this->filters['payment_method']);
            })
            ->when($this->filters['after_date'], function ($query) {
                $query->whereDate('created_at', '>=', $this->filters['after_date']);
            })
            ->when($this->filters['before_date'], function ($query) {
                $query->whereDate('created_at', '<=', $this->filters['before_date']);
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            });
    }

    public function headings(): array
    {
        return [
            'Order Code',
            'Customer Email',
            'Customer Name',
            'Status',
            'Departure Terminal',
            'Arrival Terminal',
            'Seats Count',
            'Total Cost',
            'Payment Method',
            'Comments',
            'Order Date',
        ];
    }

    public function map($order): array
    {
        return [
            $order->code,
            $order->user->email ?? 'N/A',
            $order->user->name ?? 'N/A',
            $order->status,
            $order->departure_terminal,
            $order->arrival_terminal,
            $order->seats->count(),
            $order->total_cost,
            $order->payment_method,
            $order->comments ?? '',
            $order->created_at->format('Y-m-d H:i'),
        ];
    }
}