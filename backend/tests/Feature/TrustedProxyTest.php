<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_it_trusts_forwarded_https_from_the_internal_proxy(): void
    {
        Route::get('/proxy-scheme-test', fn (Request $request): array => [
            'secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/proxy-scheme-test')
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'scheme' => 'https',
            ]);
    }
}
