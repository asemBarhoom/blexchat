<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Blexchat API',
    version: '1.0.0',
    description: 'REST API for Blexchat — social commerce DM and comment automation platform supporting Instagram, WhatsApp, TikTok, and Messenger.',
    contact: new OA\Contact(email: 'api@blexchat.com'),
    license: new OA\License(name: 'Proprietary'),
)]
// Relative, so "Try it out" calls whichever server is serving these docs (production, staging or local).
#[OA\Server(url: '/', description: 'This server')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum personal access token',
    description: 'Obtain a token from POST /api/v1/auth/login and send it in the Authorization header.',
)]
abstract class Controller
{
}
