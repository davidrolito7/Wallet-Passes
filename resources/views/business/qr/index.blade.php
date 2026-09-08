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
            <p class="text-amber-800 font-medium">No tienes un programa de lealtad activo</p>
            <p class="text-amber-600 text-sm mt-1">Crea tu programa primero para poder compartir el código QR.</p>
            <a href="{{ route('business.loyalty-program') }}"
               class="mt-4 inline-block bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                Crear programa
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ $program->name }}</h2>
            <p class="text-sm text-gray-500 mb-6">Escanea este QR para registrarte al programa de lealtad</p>

            <div class="flex justify-center mb-6">
                <div id="qrcode" class="p-4 bg-white border border-gray-200 rounded-xl inline-block shadow-sm"></div>
            </div>

            <div class="bg-gray-50 rounded-lg px-4 py-3 mb-6 text-left">
                <p class="text-xs text-gray-500 font-medium mb-1">URL del formulario</p>
                <p class="text-sm text-gray-800 break-all font-mono">{{ $registerUrl }}</p>
            </div>

            <div class="flex gap-3 justify-center">
                <button id="download-qr-btn" onclick="downloadQR()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    Descargar QR (PDF)
                </button>
                <button onclick="copyUrl()"
                        class="border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                    Copiar enlace
                </button>
            </div>
            <p id="copy-confirm" class="text-xs text-green-600 mt-2 hidden">¡Enlace copiado!</p>
        </div>

        <div class="mt-6 bg-indigo-50 border border-indigo-100 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-indigo-900 mb-2">¿Cómo funciona?</h3>
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
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
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
        img.onerror = () => reject(new Error('No se pudo cargar la imagen: ' + url));
        img.src = url;
    });
}

// Genera un QR aparte, en alta resolución, para que no se vea pixelado al imprimir.
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
                reject(new Error('No se pudo generar el QR en alta resolución.'));
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

        const doc = new jsPDF({ unit: 'mm', format: 'a6', orientation: 'portrait' });
        const pageWidth = 105;
        const pageHeight = 148;
        const centerX = pageWidth / 2;

        // Fondo con el color de marca del negocio.
        doc.setFillColor(primaryColor[0], primaryColor[1], primaryColor[2]);
        doc.rect(0, 0, pageWidth, pageHeight, 'F');

        let cursorY = 12;

        // Logo del negocio, sobre una tarjeta blanca para que siempre se lea bien.
        if (cardData.logoUrl) {
            try {
                const logo = await loadImageData(cardData.logoUrl);
                const maxW = 55, maxH = 22;
                const ratio = Math.min(maxW / logo.width, maxH / logo.height);
                const w = logo.width * ratio;
                const h = logo.height * ratio;
                const padding = 5;
                const boxW = w + padding * 2;
                const boxH = h + padding * 2;
                const boxX = centerX - boxW / 2;

                doc.setFillColor(255, 255, 255);
                doc.roundedRect(boxX, cursorY, boxW, boxH, 3, 3, 'F');
                doc.addImage(logo.dataUrl, 'PNG', centerX - w / 2, cursorY + padding, w, h);
                cursorY += boxH + 8;
            } catch (e) {
                cursorY += 4;
            }
        } else {
            cursorY += 4;
        }

        // Nombre del negocio.
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(15);
        doc.setTextColor(secondaryColor[0], secondaryColor[1], secondaryColor[2]);
        doc.text(cardData.businessName, centerX, cursorY, { align: 'center' });
        cursorY += 8;

        // Texto instructivo.
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10.5);
        doc.setTextColor(labelColor[0], labelColor[1], labelColor[2]);
        const message = 'Escanea el QR y agrega nuestra tarjeta de lealtad';
        const lines = doc.splitTextToSize(message, 78);
        doc.text(lines, centerX, cursorY, { align: 'center' });
        cursorY += lines.length * 5 + 6;

        // Tarjeta blanca con el código QR.
        const qrBoxSize = 62;
        const qrBoxX = centerX - qrBoxSize / 2;
        doc.setFillColor(255, 255, 255);
        doc.roundedRect(qrBoxX, cursorY, qrBoxSize, qrBoxSize, 4, 4, 'F');
        const qrSize = 52;
        doc.addImage(qrDataUrl, 'PNG', centerX - qrSize / 2, cursorY + (qrBoxSize - qrSize) / 2, qrSize, qrSize);
        cursorY += qrBoxSize + 10;

        // Logos de Apple Wallet / Google Wallet, al pie de la hoja.
        const badgeHeight = 9;
        const gap = 4;
        const badges = [];
        for (const url of [cardData.appleWalletUrl, cardData.googleWalletUrl]) {
            const img = await loadImageData(url);
            badges.push({
                dataUrl: img.dataUrl,
                width: badgeHeight * (img.width / img.height),
                height: badgeHeight,
            });
        }
        const totalWidth = badges.reduce((sum, b) => sum + b.width, 0) + gap * (badges.length - 1);
        let badgeX = centerX - totalWidth / 2;
        const badgeY = pageHeight - badgeHeight - 10;
        for (const badge of badges) {
            doc.addImage(badge.dataUrl, 'PNG', badgeX, badgeY, badge.width, badge.height);
            badgeX += badge.width + gap;
        }

        doc.save(`qr-lealtad-${cardData.slug || 'negocio'}.pdf`);
    } catch (e) {
        console.error(e);
        alert('Ocurrió un error al generar el PDF. Intenta de nuevo.');
    } finally {
        downloadBtn.disabled = false;
        downloadBtn.textContent = originalLabel;
    }
}

function copyUrl() {
    navigator.clipboard.writeText(qrUrl).then(() => {
        const el = document.getElementById('copy-confirm');
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 2000);
    });
}
@endif
</script>
@endpush
