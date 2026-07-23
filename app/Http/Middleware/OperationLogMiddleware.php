<?php

namespace App\Http\Middleware;

use App\Models\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationLogMiddleware
{
    /**
     * Log every API operation (method, path, user, request summary).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $path = $request->path();
            $action = $request->route() ? $request->route()->getName() : null;
            if (!$action) {
                $action = str_replace('api/', '', $path);
                $action = preg_replace('/[^a-z0-9\-_]/', '-', $action) ?: $path;
            }
            $requestSummary = $this->summarizeRequest($request);
            OperationLog::create([
                'user_id' => $request->get('auth_user_id'),
                'action' => $action ? substr($action, 0, 100) : substr($path, 0, 100),
                'method' => $request->method(),
                'path' => substr($path, 0, 500),
                'request_summary' => $requestSummary,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            // Don't break the request if logging fails
        }

        return $response;
    }

    private function summarizeRequest(Request $request): ?string
    {
        $input = $request->except(['password', 'password_confirmation', 'token']);
        $encoded = json_encode($input);
        if ($encoded === false || strlen($encoded) > 2000) {
            $encoded = substr($encoded ?: '{}', 0, 2000);
        }
        return $encoded ?: null;
    }
}
