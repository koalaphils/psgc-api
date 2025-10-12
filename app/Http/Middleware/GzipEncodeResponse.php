<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GzipEncodeResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (in_array('gzip', $request->getEncodings())
            && function_exists('gzencode')
            && ! ini_get('zlib.output_compression')
            && ini_get('output_handler') != 'ob_gzhandler'
        ) {
            $compressed = gzencode($response->getContent(), 9);
            if ($compressed !== false) {
                $response->setContent($compressed);
                $response->headers->add([
                    'Content-Encoding' => 'gzip',
                ]);
            }
        }

        return $response;
    }
}
