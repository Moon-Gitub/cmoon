<?php

use App\Http\Middleware\EnsureMcpToken;
use App\Mcp\Servers\PosmoonServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', PosmoonServer::class)
    ->middleware(['auth:sanctum', EnsureMcpToken::class, 'throttle:mcp']);
