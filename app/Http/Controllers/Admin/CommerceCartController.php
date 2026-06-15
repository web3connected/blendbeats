<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShoppingCart;
use App\Services\ShoppingCartService;
use Illuminate\View\View;

class CommerceCartController extends Controller
{
    public function __construct(private readonly ShoppingCartService $carts) {}

    public function index(): View
    {
        return view('admin.carts.index', [
            'carts' => ShoppingCart::query()
                ->with(['user:id,name,email', 'items.product:id,title'])
                ->latest()
                ->paginate(15),
            'cartService' => $this->carts,
        ]);
    }
}
