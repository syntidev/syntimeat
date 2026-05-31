<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QzController extends Controller
{
    public function certificate(): \Illuminate\Http\Response
    {
        if (! Storage::disk('local')->exists('qz/digital-certificate')) {
            abort(404, 'Certificado QZ no configurado. Coloca el archivo en storage/app/qz/digital-certificate.');
        }

        $cert = Storage::disk('local')->get('qz/digital-certificate');

        return response($cert, 200, ['Content-Type' => 'text/plain']);
    }

    public function sign(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'toSign' => ['required', 'string'],
        ]);

        if (! Storage::disk('local')->exists('qz/private-key.pem')) {
            abort(404, 'Clave privada QZ no configurada. Coloca el archivo en storage/app/qz/private-key.pem.');
        }

        $privateKey = Storage::disk('local')->get('qz/private-key.pem');
        $pkeyId     = openssl_pkey_get_private($privateKey);

        if ($pkeyId === false) {
            abort(500, 'Clave privada QZ inválida.');
        }

        $signature = '';
        openssl_sign($data['toSign'], $signature, $pkeyId, OPENSSL_ALGO_SHA512);

        return response(base64_encode($signature), 200, ['Content-Type' => 'text/plain']);
    }
}
