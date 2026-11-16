<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VerificationQueue
{
    /** @return array<string, Builder> */
    public function queries(): array
    {
        $base = User::whereHas('role', fn (Builder $query) => $query->where('name', 'customer'));
        $pending = (clone $base)->where('verification_status', 'pending');
        $readyIds = (clone $pending)->get()->filter(fn (User $user) => $user->hasSubmittedDocuments())->modelKeys();

        return [
            'all' => $base, 'pending' => $pending,
            'ready' => (clone $pending)->whereIn('id', $readyIds),
            'incomplete' => (clone $pending)->whereNotIn('id', $readyIds),
            'verified' => (clone $base)->where('verification_status', 'verified'),
            'rejected' => (clone $base)->where('verification_status', 'rejected'),
        ];
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return array_map(fn (Builder $query) => $query->count(), $this->queries());
    }
}
