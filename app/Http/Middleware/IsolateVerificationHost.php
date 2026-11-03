<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Runs before sessions and routing, including framework file-serving routes. */
class IsolateVerificationHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $base=rtrim((string)config('services.verification_url'),'/');
        if($base==='')return $next($request);
        $host=strtolower((string)parse_url($base,PHP_URL_HOST));
        $main=strtolower((string)parse_url(config('app.url'),PHP_URL_HOST));
        if(!$host||$host===$main||parse_url($base,PHP_URL_SCHEME)!=='https')abort(503);
        $path=$request->getPathInfo();
        $certificate=preg_match('#^/certificates/(k3|g9|g12)/[A-Za-z0-9]{40}$#D',$path);
        $card=preg_match('#^/student-card/[A-Za-z0-9_-]{1,100}(?:/qr\.svg)?$#D',$path);
        $verification=($certificate||$card)&&in_array($request->method(),['GET','HEAD'],true);
        if(strtolower($request->getHost())===$host){
            abort_unless($verification,404);
            if(!$request->isSecure())return redirect()->away($base.$path,302)->header('Cache-Control','no-store');
            $response=$next($request);
            $response->headers->set('Cache-Control','no-store, private');
            $response->headers->set('X-Robots-Tag','noindex, nofollow');
            $response->headers->set('Referrer-Policy','no-referrer');
            $response->headers->set('X-Content-Type-Options','nosniff');
            return $response;
        }
        if($certificate||$card){
            abort_unless($verification&&strtolower($request->getHost())===$main,404);
            return redirect()->away($base.$path,302)->header('Cache-Control','no-store');
        }
        return $next($request);
    }
}
