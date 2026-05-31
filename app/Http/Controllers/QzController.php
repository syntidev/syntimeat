<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QzController extends Controller
{
    public function certificate(): \Illuminate\Http\Response
    {
        $path = storage_path('app/qz/digital-certificate.txt');
        if (! file_exists($path)) {
            $path = storage_path('app/qz/digital-certificate');
        }
        if (! file_exists($path)) {
            abort(404, 'Certificado QZ no configurado. Coloca el archivo en storage/app/qz/digital-certificate.txt.');
        }

        $cert = file_get_contents($path);

        return response($cert, 200, ['Content-Type' => 'text/plain']);
    }

    public function sign(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'toSign' => ['required', 'string'],
        ]);

        $keyPath = storage_path('app/qz/private-key.pem');
        if (! file_exists($keyPath)) {
            abort(404, 'Clave privada QZ no configurada. Coloca el archivo en storage/app/qz/private-key.pem.');
        }

        $privateKey = file_get_contents($keyPath);
        $pkeyId     = openssl_pkey_get_private($privateKey);

        if ($pkeyId === false) {
            abort(500, 'Clave privada QZ inválida.');
        }

        $signature = '';
        openssl_sign($data['toSign'], $signature, $pkeyId, OPENSSL_ALGO_SHA512);

        return response(base64_encode($signature), 200, ['Content-Type' => 'text/plain']);
    }
}
