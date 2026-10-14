<?php

namespace App\Http\Controllers;

use App\Filament\Resources\UserResource;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function stop(): RedirectResponse
    {
        $back = Impersonation::stop();

        return $back
            ? redirect()->to(UserResource::getUrl('index'))
            : redirect()->to('/admin');
    }
}
