<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class XssSanitizer
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->except($request->files->keys()); // ← exclude file fields

        array_walk_recursive($input, function (&$input) {
            if (is_string($input)) { // ← only sanitize strings
                $input = strip_tags($input);
            }
        });

        $request->merge($input);

        return $next($request);
    }
}