<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
            '^(.+\.)?staritawaterdistrictpamp\.gov\.ph$',
            'staritawaterdistrictpamp\.novulutions\.com',
            '127\.0\.0\.1',
            'localhost',
            '38\.226\.41\.3',
        ];
    }
}
