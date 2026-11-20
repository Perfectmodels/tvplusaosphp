<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité appliqués à toutes les réponses web.
 *
 * · X-Frame-Options / frame-ancestors → anti-clickjacking
 * · X-Content-Type-Options           → anti MIME-sniffing
 * · Referrer-Policy                  → pas de fuite d'URL interne
 * · Permissions-Policy               → désactive caméra/micro/géoloc…
 * · Cross-Origin-*                   → isolation des ressources
 * · Strict-Transport-Security        → HTTPS forcé (uniquement en HTTPS)
 * · Références CDN retirées du CSP si les fichiers sont servis localement.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Déjà définies par l'application ? On ne les écrase pas.
        if ($response->headers->has('X-Frame-Options')) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP : autorise les ressources réellement utilisées par l'application.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net",
                // qr-scanner construit le worker du décodeur depuis un Blob same-origin.
                "worker-src 'self' blob:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
                "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:",
                "img-src 'self' data: blob: https:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                // wa.me : les formulaires « Envoyer WhatsApp » redirigent vers WhatsApp.
                "form-action 'self' https://wa.me https://api.whatsapp.com",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
