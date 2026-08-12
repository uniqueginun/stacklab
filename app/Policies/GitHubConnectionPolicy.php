<?php

namespace App\Policies;

use App\Models\GitHubConnection;
use App\Models\User;

class GitHubConnectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $user->is($gitHubConnection->user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $user->is($gitHubConnection->user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $user->is($gitHubConnection->user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $user->is($gitHubConnection->user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $user->is($gitHubConnection->user);
    }
}
