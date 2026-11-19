<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * People can only see their own data. A 404 (not a 403) is returned for
     * somebody else's record, so its existence is not revealed.
     */
    protected function ensureOwner($request, $ownerId): void
    {
        abort_unless((int) $ownerId === (int) $request->user()->id, 404);
    }
}
