<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use Laravel\Mcp\Response;

trait ReturnsJson
{
    protected function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401, 'No autenticado.');

        return $user;
    }

    protected function json(array $data): Response
    {
        return Response::text(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
