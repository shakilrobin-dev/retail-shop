<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The branch the logged-in user is working in.
 * Branch staff always work in their own branch. Admin can switch;
 * the choice is kept in the session.
 */
class CurrentBranch
{
    private const SESSION_KEY = 'current_branch_id';

    private ?Branch $branch = null;

    private ?int $resolvedForUserId = null;

    public function get(): ?Branch
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($this->branch && $this->resolvedForUserId === $user->id) {
            return $this->branch;
        }

        $this->resolvedForUserId = $user->id;

        if (! $user->canAccessAllBranches()) {
            return $this->branch = $user->branch;
        }

        $branchId = Session::get(self::SESSION_KEY, $user->branch_id);

        return $this->branch = Branch::query()->where('is_active', true)->find($branchId)
            ?? Branch::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function switchTo(Branch $branch): void
    {
        Session::put(self::SESSION_KEY, $branch->id);
        $this->branch = $branch;
    }

    /**
     * Branches the logged-in user may switch to.
     *
     * @return Collection<int, Branch>
     */
    public function available(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return new Collection;
        }

        if (! $user->canAccessAllBranches()) {
            return new Collection(array_filter([$user->branch]));
        }

        return Branch::query()->where('is_active', true)->orderBy('id')->get();
    }
}
