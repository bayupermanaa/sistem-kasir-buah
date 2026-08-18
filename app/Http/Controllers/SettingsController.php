<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $cashiers = User::query()
            ->where('role', 'cashier')
            ->with('outlet')
            ->orderBy('name')
            ->get();

        return view('pengaturan', [
            'cashiers' => $cashiers,
        ]);
    }

    public function updateCashierPassword(Request $request, User $cashier): RedirectResponse
    {
        abort_unless($request->user()?->role === 'owner', 403);

        abort_unless($cashier->role === 'cashier', 403);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $cashier->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()->route('pengaturan')->with('success', 'Password kasir berhasil diperbarui.');
    }
}
