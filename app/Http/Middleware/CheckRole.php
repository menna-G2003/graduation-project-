<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user() || !$request->user()->role) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $userRole = $request->user()->role->name;
        
        foreach ($roles as $role) {
            if ($userRole === $role) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden: Insufficient permissions'], 403);
    }
} 