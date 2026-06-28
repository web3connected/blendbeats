<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class WalletController extends Controller
{
    public function index(): View
    {
        return view('wallet.index');
    }

    public function transactions(): View
    {
        return view('wallet.transactions');
    }

    public function deposit(): View
    {
        return view('wallet.deposit');
    }

    public function withdraw(): View
    {
        return view('wallet.withdraw');
    }

    public function rewards(): View
    {
        return view('wallet.rewards');
    }

    public function purchases(): View
    {
        return view('wallet.purchases');
    }
}
