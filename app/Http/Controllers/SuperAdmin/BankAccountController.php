<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index()
    {
        $accounts = BankAccount::orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('SuperAdmin/BankAccounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name'           => ['required', 'string', 'max:100'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'account_number'      => ['required', 'string', 'max:50'],
            'account_type'        => ['required', 'in:savings,checking'],
            'currency'            => ['required', 'string', 'max:10'],
            'instructions'        => ['nullable', 'string', 'max:500'],
            'is_active'           => ['boolean'],
        ]);

        BankAccount::create($data);

        return back()->with('success', 'Cuenta bancaria creada correctamente.');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $data = $request->validate([
            'bank_name'           => ['required', 'string', 'max:100'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'account_number'      => ['required', 'string', 'max:50'],
            'account_type'        => ['required', 'in:savings,checking'],
            'currency'            => ['required', 'string', 'max:10'],
            'instructions'        => ['nullable', 'string', 'max:500'],
            'is_active'           => ['boolean'],
        ]);

        $bankAccount->update($data);

        return back()->with('success', 'Cuenta bancaria actualizada correctamente.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        if ($bankAccount->transferVerifications()->whereIn('status', ['pending', 'approved'])->exists()) {
            return back()->with('error', 'No se puede eliminar una cuenta con verificaciones activas.');
        }

        $bankAccount->delete();

        return back()->with('success', 'Cuenta bancaria eliminada.');
    }
}
