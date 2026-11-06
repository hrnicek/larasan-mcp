<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckToken;

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP endpoint
    |--------------------------------------------------------------------------
    |
    | The streamable HTTP endpoint MCP clients (ChatGPT, claude.ai, Claude
    | Desktop, Cursor...) connect to. Every request needs an OAuth access token
    | issued by Laravel Passport, hence `auth:api`.
    |
    */

    'http' => [
        'path' => env('LARASAN_MCP_PATH', 'mcp/larasan'),
        // CheckToken: a token Passport issued for anything but MCP (a personal access token, say) is refused.
        'middleware' => ['auth:api', CheckToken::using(Registrar::OAUTH_SCOPE), 'throttle:larasan-mcp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth discovery
    |--------------------------------------------------------------------------
    |
    | laravel/mcp's OAuth 2.1 discovery and dynamic client registration routes,
    | backed by Laravel Passport. The registration route is public, so it is
    | throttled per IP.
    |
    */

    'oauth' => [
        // The consent screen Passport shows; it names the site the code is sent to. Null keeps the application's own.
        'authorization_view' => 'larasan-mcp::authorize',

        /*
         * Where a self-registered client may send the authorization code. Registration is public, so
         * without this list anyone could register "Claude" with their own callback and phish a token.
         * Applied unless config/mcp.php was published with its own `redirect_domains`.
         */
        'redirect_domains' => array_values(array_filter(explode(',', (string) env(
            'LARASAN_MCP_REDIRECT_DOMAINS',
            'https://claude.ai,https://claude.com,https://chatgpt.com,https://chat.openai.com,http://localhost,http://127.0.0.1',
        )))),

        // Custom URL schemes desktop editors call back on, e.g. cursor://anysphere.cursor-mcp/...
        'custom_schemes' => array_values(array_filter(explode(',', (string) env('LARASAN_MCP_CUSTOM_SCHEMES', 'cursor,vscode')))),

        'registrations_per_minute' => (int) env('LARASAN_MCP_OAUTH_REGISTRATIONS_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'requests_per_minute' => (int) env('LARASAN_MCP_REQUESTS_PER_MINUTE', 120),

    'page_size' => (int) env('LARASAN_MCP_PAGE_SIZE', 50),

    // Largest file `download_attachment` returns inline. Larger files return metadata only.
    'download_max_kilobytes' => (int) env('LARASAN_MCP_DOWNLOAD_MAX_KILOBYTES', 5_120),

];
