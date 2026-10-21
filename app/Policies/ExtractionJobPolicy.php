<?php

namespace App\Policies;

use App\Models\ExtractionJob;
use App\Models\User;

/**
 * Jobs, their products and their exports belong to exactly one account.
 * Guest (anonymous preview) jobs are never reachable through these signed-in routes.
 */
class ExtractionJobPolicy
{
    public function view(User $user, ExtractionJob $job): bool
    {
        return $job->user_id !== null && $job->user_id === $user->id;
    }

    public function cancel(User $user, ExtractionJob $job): bool
    {
        return $this->view($user, $job) && $job->isActive();
    }

    public function export(User $user, ExtractionJob $job): bool
    {
        return $this->view($user, $job) && $job->hasResults();
    }
}
