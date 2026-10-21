<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ExtractionJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Right to erasure: deletes the account, its extractions, products and export files.
 * Invoices are kept for accounting with the link to the person removed (user_id set to null).
 */
class DeleteAccount
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.'])->errorBag('deleteAccount');
        }

        $disk = Storage::disk(config('storesiphon.exports.disk'));
        $jobIds = ExtractionJob::where('user_id', $user->id)->pluck('id');

        // Sign out BEFORE deleting: logout() cycles the remember-me token and saves the user model,
        // which would re-insert a just-deleted row.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($user, $jobIds) {
            AuditLog::record('user.deleted', $user, null, [
                'jobs' => $jobIds->count(),
                'invoices_kept_anonymised' => $user->invoices()->count(),
            ]);
            $user->delete(); // cascades: jobs → products, exports; subscriptions. Invoices: user_id → null.
        });

        foreach ($jobIds as $id) {
            $disk->deleteDirectory('exports/'.$id);
        }

        return redirect()->route('home')->with('status', 'Your account and its data have been deleted.');
    }
}
