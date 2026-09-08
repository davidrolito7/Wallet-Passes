@extends('business.layout')

@section('title', 'Código QR')
@section('heading', 'Código QR de Registro')

@section('content')
<div class="max-w-2xl mx-auto">
    @if(! $program)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
            <svg class="w-12 h-12 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>

            <p class="text-amber-800 font-medium">
                No tienes un programa de lealtad activo
            </p>

            <p class="text-amber-600 text-sm mt-1">
                Crea tu programa primero para poder compartir el código QR.
            </p>

            <a href="{{ route('business.loyalty-program') }}"
               class="mt-4 inline-block bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                Crear programa
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">
                {{ $program->name }}
            </h2>

            <p class="text-sm text-gray-500 mb-6">
                Escanea este QR para registrarte al programa de lealtad
            </p>

            <div class="flex justify-center mb-6">
                <div id="qrcode" class="p-4 bg-white border border-gray-200 rounded-xl inline-block shadow-sm"></div>
            </div>

            <div class="bg-gray-50 rounded-lg px-4 py-3 mb-6 text-left">
                <p class="text-xs text-gray-500 font-medium mb-1">
                    URL del formulario
                </p>

                <p class="text-sm text-gray-800 break-all font-mono">
                    {{ $registerUrl }}
                </p>
            </div>

            <div class="flex gap-3 justify-center">
                <button id="download-qr-btn"
                        onclick="downloadQR()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    Descargar QR (PDF)
                </button>

                <button onclick="copyUrl()"
                        class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                    Copiar enlace
                </button>
            </div>

            <p id="copy-confirm" class="text-xs text-green-600 mt-2 hidden">
                ¡Enlace copiado!
            </p>
        </div>

        <div class="mt-6 bg-indigo-50 border border-indigo-100 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-indigo-900 mb-2">
                ¿Cómo funciona?
            </h3>

            <ol class="text-sm text-indigo-700 space-y-1 list-decimal list-inside">
                <li>El cliente escanea el QR con su teléfono.</li>
                <li>Llena su nombre, apellido y fecha de nacimiento.</li>
                <li>Recibe automáticamente su tarjeta de lealtad digital en Apple Wallet o Google Wallet.</li>
            </ol>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
@if($registerUrl)

const qrUrl = @json($registerUrl);

const cardData = {
    businessName: @json($business->name),
    slug: @json($business->slug),
    logoUrl: @json($business->logoPublicUrl()),
    primaryColor: @json($business->primary_color ?? '#1a1a2e'),
    secondaryColor: @json($business->secondary_color ?? '#ffffff'),
    labelColor: @json($business->label_color ?? '#cccccc'),
    appleWalletUrl: @json(asset('wallet/AddtoAppleWallet.webp')),
    googleWalletUrl: @json(asset('wallet/AddtoGoogleWallet.webp')),
    fontUrl: @json(asset('fonts/Poppins-SemiBold.ttf')),
    accentFontUrl: @json(asset('fonts/PTSerif-BoldItalic.ttf')),
};

const qr = new QRCode(document.getElementById('qrcode'), {
    text: qrUrl,
    width: 220,
    height: 220,
    colorDark: '#1e1b4b',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
});

function hexToRgb(hex) {
    hex = (hex || '').replace('#', '').trim();

    if (hex.length === 3) {
        hex = hex.split('').map((c) => c + c).join('');
    }

    const num = parseInt(hex, 16);

    if (isNaN(num) || hex.length !== 6) {
        return [26, 26, 46];
    }

    return [
        (num >> 16) & 255,
        (num >> 8) & 255,
        num & 255
    ];
}

async function loadFontBase64(url) {
    const res = await fetch(url);
    const buffer = await res.arrayBuffer();
    const bytes = new Uint8Array(buffer);

    let binary = '';
    const chunkSize = 0x8000;

    for (let i = 0; i < bytes.length; i += chunkSize) {
        binary += String.fromCharCode.apply(
            null,
            bytes.subarray(i, i + chunkSize)
        );
    }

    return btoa(binary);
}

function loadImageData(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();

        img.crossOrigin = 'anonymous';

        img.onload = () => {
            const canvas = document.createElement('canvas');

            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;

            canvas.getContext('2d').drawImage(img, 0, 0);

            try {
                resolve({
                    dataUrl: canvas.toDataURL('image/png'),
                    width: img.naturalWidth,
                    height: img.naturalHeight,
                });
            } catch (e) {
                reject(e);
            }
        };

        img.onerror = () => reject(
            new Error('No se pudo cargar la imagen: ' + url)
        );

        img.src = url;
    });
}

function buildHighResQrDataUrl() {
    return new Promise((resolve, reject) => {
        const container = document.createElement('div');

        container.style.position = 'fixed';
        container.style.left = '-9999px';

        document.body.appendChild(container);

        new QRCode(container, {
            text: qrUrl,
            width: 600,
            height: 600,
            colorDark: '#1e1b4b',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H,
        });

        setTimeout(() => {
            const canvas = container.querySelector('canvas');

            if (!canvas) {
                document.body.removeChild(container);

                reject(
                    new Error('No se pudo generar el QR en alta resolución.')
                );

                return;
            }

            const dataUrl = canvas.toDataURL('image/png');

            document.body.removeChild(container);

            resolve(dataUrl);
        }, 150);
    });
}

async function downloadQR() {
    const downloadBtn = document.getElementById('download-qr-btn');
    const originalLabel = downloadBtn.textContent;

    downloadBtn.disabled = true;
    downloadBtn.textContent = 'Generando PDF...';

    try {
        const { jsPDF } = window.jspdf;

        const primaryColor = hexToRgb(cardData.primaryColor);
        const secondaryColor = hexToRgb(cardData.secondaryColor);
        const labelColor = hexToRgb(cardData.labelColor);

        const qrDataUrl = await buildHighResQrDataUrl();

        const doc = new jsPDF({
            unit: 'mm',
            format: 'a6',
            orientation: 'portrait'
        });

        const pageWidth = 105;
        const pageHeight = 148;
        const centerX = pageWidth / 2;

        let messageFont = {
            family: 'helvetica',
            style: 'bold'
        };

        try {
            const fontBase64 = await loadFontBase64(cardData.fontUrl);

            doc.addFileToVFS(
                'Poppins-SemiBold.ttf',
                fontBase64
            );

            doc.addFont(
                'Poppins-SemiBold.ttf',
                'Poppins',
                'normal'
            );

            messageFont = {
                family: 'Poppins',
                style: 'normal'
            };
        } catch (e) {
            // Si falla la fuente seguimos con Helvetica.
        }

        let accentFont = {
            family: 'helvetica',
            style: 'italic'
        };

        try {
            const accentBase64 = await loadFontBase64(cardData.accentFontUrl);

            doc.addFileToVFS(
                'PTSerif-BoldItalic.ttf',
                accentBase64
            );

            doc.addFont(
                'PTSerif-BoldItalic.ttf',
                'PTSerif',
                'bolditalic'
            );

            accentFont = {
                family: 'PTSerif',
                style: 'bolditalic'
            };
        } catch (e) {
            // Si falla seguimos con Helvetica itálica.
        }

        /*
        |--------------------------------------------------------------------------
        | Fondo
        |--------------------------------------------------------------------------
        */

        doc.setFillColor(
            primaryColor[0],
            primaryColor[1],
            primaryColor[2]
        );

        doc.rect(
            0,
            0,
            pageWidth,
            pageHeight,
            'F'
        );

        /*
        |--------------------------------------------------------------------------
        | Logo del negocio
        |--------------------------------------------------------------------------
        */

        const logoBandTop = 9;
        const logoBandHeight = 20;

        if (cardData.logoUrl) {
            try {
                const logo = await loadImageData(cardData.logoUrl);

                const maxW = 58;
                const maxH = 18;

                const ratio = Math.min(
                    maxW / logo.width,
                    maxH / logo.height
                );

                const w = logo.width * ratio;
                const h = logo.height * ratio;

                doc.addImage(
                    logo.dataUrl,
                    'PNG',
                    centerX - w / 2,
                    logoBandTop + (logoBandHeight - h) / 2,
                    w,
                    h
                );
            } catch (e) {
                // Si falla el logo seguimos.
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Separador
        |--------------------------------------------------------------------------
        */

        doc.setFillColor(
            labelColor[0],
            labelColor[1],
            labelColor[2]
        );

        doc.roundedRect(
            centerX - 5,
            34,
            10,
            0.6,
            0.3,
            0.3,
            'F'
        );

        /*
        |--------------------------------------------------------------------------
        | Título principal
        |--------------------------------------------------------------------------
        */

        doc.setFont(
            messageFont.family,
            messageFont.style
        );

        doc.setFontSize(14.5);

        doc.setTextColor(
            secondaryColor[0],
            secondaryColor[1],
            secondaryColor[2]
        );

        doc.text(
            'Tu lealtad tiene',
            centerX,
            43.5,
            {
                align: 'center'
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Recompensa
        |--------------------------------------------------------------------------
        */

        doc.setFont(
            accentFont.family,
            accentFont.style
        );

        doc.setFontSize(21);

        doc.setTextColor(
            secondaryColor[0],
            secondaryColor[1],
            secondaryColor[2]
        );

        doc.text(
            'recompensa',
            centerX,
            52,
            {
                align: 'center'
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Texto explicativo
        |--------------------------------------------------------------------------
        */

        const mutedTextColor = [
            Math.round(
                secondaryColor[0] * 0.82 +
                primaryColor[0] * 0.18
            ),
            Math.round(
                secondaryColor[1] * 0.82 +
                primaryColor[1] * 0.18
            ),
            Math.round(
                secondaryColor[2] * 0.82 +
                primaryColor[2] * 0.18
            ),
        ];

        doc.setFont(
            messageFont.family,
            messageFont.style
        );

        doc.setFontSize(8.4);

        doc.setTextColor(
            mutedTextColor[0],
            mutedTextColor[1],
            mutedTextColor[2]
        );

        const secondaryMessage =
            'Escanea el código y agrega nuestra tarjeta de lealtad.';

        const secondaryLines = doc.splitTextToSize(
            secondaryMessage,
            74
        );

        doc.text(
            secondaryLines,
            centerX,
            60.5,
            {
                align: 'center',
                lineHeightFactor: 1.25
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Acumula · Disfruta · Repite
        |--------------------------------------------------------------------------
        */

        doc.setFont(
            messageFont.family,
            messageFont.style
        );

        doc.setFontSize(6.2);

        doc.setTextColor(
            labelColor[0],
            labelColor[1],
            labelColor[2]
        );

        doc.text(
            'ACUMULA  ·  DISFRUTA  ·  REPITE',
            centerX,
            69.5,
            {
                align: 'center',
                charSpace: 0.28
            }
        );

        /*
        |--------------------------------------------------------------------------
        | QR
        |--------------------------------------------------------------------------
        */

        const badgeHeight = 9;
        const badgeGapBelowQr = 7;
        const bottomSafeLimit = 142;

        let qrBoxSize = 56;
        const qrBoxTop = 74;

        if (
            qrBoxTop +
            qrBoxSize +
            badgeGapBelowQr +
            badgeHeight >
            bottomSafeLimit
        ) {
            qrBoxSize = Math.max(
                44,
                bottomSafeLimit -
                badgeGapBelowQr -
                badgeHeight -
                qrBoxTop
            );
        }

        const qrBoxX =
            centerX -
            qrBoxSize / 2;

        /*
        |--------------------------------------------------------------------------
        | Sombra del QR
        |--------------------------------------------------------------------------
        */

        doc.setFillColor(
            Math.round(primaryColor[0] * 0.75),
            Math.round(primaryColor[1] * 0.75),
            Math.round(primaryColor[2] * 0.75)
        );

        doc.roundedRect(
            qrBoxX + 0.8,
            qrBoxTop + 0.8,
            qrBoxSize,
            qrBoxSize,
            5,
            5,
            'F'
        );

        /*
        |--------------------------------------------------------------------------
        | Caja blanca del QR
        |--------------------------------------------------------------------------
        */

        doc.setFillColor(
            255,
            255,
            255
        );

        doc.roundedRect(
            qrBoxX,
            qrBoxTop,
            qrBoxSize,
            qrBoxSize,
            5,
            5,
            'F'
        );

        /*
        |--------------------------------------------------------------------------
        | Imagen QR
        |--------------------------------------------------------------------------
        */

        const qrSize =
            qrBoxSize - 8;

        doc.addImage(
            qrDataUrl,
            'PNG',
            centerX - qrSize / 2,
            qrBoxTop + (qrBoxSize - qrSize) / 2,
            qrSize,
            qrSize
        );

        /*
        |--------------------------------------------------------------------------
        | Apple Wallet / Google Wallet
        |--------------------------------------------------------------------------
        */

        const badgeGap = 4;

        const badgeTop =
            qrBoxTop +
            qrBoxSize +
            badgeGapBelowQr;

        const badges = [];

        for (const url of [
            cardData.appleWalletUrl,
            cardData.googleWalletUrl
        ]) {
            const img = await loadImageData(url);

            badges.push({
                dataUrl: img.dataUrl,
                width: badgeHeight * (img.width / img.height),
                height: badgeHeight,
            });
        }

        const totalWidth =
            badges.reduce(
                (sum, badge) => sum + badge.width,
                0
            ) +
            badgeGap * (badges.length - 1);

        let badgeX =
            centerX -
            totalWidth / 2;

        for (const badge of badges) {
            doc.addImage(
                badge.dataUrl,
                'PNG',
                badgeX,
                badgeTop,
                badge.width,
                badge.height
            );

            badgeX +=
                badge.width +
                badgeGap;
        }

        doc.save(
            `qr-lealtad-${cardData.slug || 'negocio'}.pdf`
        );

    } catch (e) {
        console.error(e);

        alert(
            'Ocurrió un error al generar el PDF. Intenta de nuevo.'
        );
    } finally {
        downloadBtn.disabled = false;
        downloadBtn.textContent = originalLabel;
    }
}

function copyUrl() {
    navigator.clipboard.writeText(qrUrl).then(() => {
        const el = document.getElementById('copy-confirm');

        el.classList.remove('hidden');

        setTimeout(
            () => el.classList.add('hidden'),
            2000
        );
    });
}

@endif
</script>

@endpush