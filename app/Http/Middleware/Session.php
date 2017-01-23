<?php

namespace App\Http\Middleware;

use Closure;

class Session
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!isset($request->route()[2]['user'])) {
            return response()
                    ->json(['errors'=>'miss user'], 404);
        }
        $user = $request->route()[2]['user'];
        $key = sprintf('%s:Session', $user);
        $session = apcu_fetch($key);

        if ($session == false) {
            return response()
                    ->json(['errors'=>'unauthorized'], 401);
        }
        apcu_store($key, $session, 300);

        $request->merge([
            'session' => $session
        ]);

        return $next($request);
    }
}
