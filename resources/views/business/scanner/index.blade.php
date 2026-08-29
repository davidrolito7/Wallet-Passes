@extends('business.layout')

@section('title', 'Escanear Tarjeta')
@section('heading', 'Escanear Tarjeta de Cliente')

@section('content')
<div class="max-w-lg mx-auto">

    {{-- Instrucciones --}}
    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-indigo-700">
            Apunta la cámara al <strong>código QR de la tarjeta de lealtad</strong> del cliente en su Apple Wallet o Google Wallet para registrar la visita.
        </p>
    </div>

    {{-- Escáner --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {{-- Viewfinder --}}
        <div class="relative bg-gray-900" style="min-height: 460px;">
            <div id="reader" class="w-full"></div>

            {{-- Overlay de esquinas --}}
            <div id="scan-overlay" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="relative w-52 h-52">
                    <span class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-white rounded-tl-lg"></span>
                    <span class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-white rounded-tr-lg"></span>
                    <span class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-white rounded-bl-lg"></span>
                    <span class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-white rounded-br-lg"></span>
                </div>
            </div>

            {{-- Estado / mensaje de cámara apagada --}}
            <div id="cam-off" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                <svg class="w-14 h-14 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="text-gray-400 text-sm">Cámara apagada</p>
            </div>
        </div>

    </div>

</div>

{{-- ── Overlay de resultado ───────────────────────────────────────────────── --}}
<div id="result-overlay"
     class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm hidden"
     onclick="closeResult(event)">

    <div id="result-card"
         class="w-full max-w-lg bg-white rounded-t-3xl shadow-2xl p-6 pb-8 transform translate-y-full transition-transform duration-300">

        {{-- Indicador de arrastre --}}
        <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mb-5"></div>

        {{-- Contenido dinámico --}}
        <div id="result-content"></div>

        <button onclick="resetScanner()"
                class="mt-5 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
            Escanear otro cliente
        </button>
    </div>
</div>
@endsection

@push('head')
<style>
    /* Ocultar el UI por defecto de html5-qrcode */
    #reader { overflow: hidden; }
    #reader__scan_region { border: none !important; }
    #reader__header_message,
    #reader__status_span,
    #reader__dashboard_section_csr,
    #reader__dashboard { display: none !important; }
    #reader video { width: 100% !important; height: 460px !important; object-fit: cover; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const SCAN_URL  = @json(route('business.scanner.scan'));
const CSRF      = @json(csrf_token());

let scanner     = null;
let isRunning   = false;
let isProcessing = false;

// ── Scanner ───────────────────────────────────────────────────────────────────

function startScanner() {
    if (scanner) {
        scanner.clear();
        scanner = null;
    }

    scanner = new Html5Qrcode('reader');

    const config = {
        fps: 12,
        qrbox: { width: 220, height: 220 },
        aspectRatio: 1.0,
        showTorchButtonIfSupported: true,
    };

    scanner.start(
        { facingMode: 'environment' },
        config,
        onScanSuccess,
        () => {}
    ).then(() => {
        isRunning = true;
        document.getElementById('cam-off').classList.add('hidden');
        document.getElementById('scan-overlay').classList.remove('hidden');
    }).catch(err => {
        console.error('Camera error:', err);
    });
}

function stopScanner() {
    if (scanner && isRunning) {
        scanner.stop().then(() => {
            isRunning = false;
            document.getElementById('cam-off').classList.remove('hidden');
        });
    }
}

document.addEventListener('DOMContentLoaded', startScanner);

// ── Procesamiento del QR ─────────────────────────────────────────────────────

async function onScanSuccess(decodedText) {
    if (isProcessing) return;
    isProcessing = true;

    stopScanner();

    try {
        const response = await fetch(SCAN_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ qr: decodedText }),
        });

        const data = await response.json();
        showResult(data);
    } catch (e) {
        showResult({ success: false, error: 'Error de red. Intenta de nuevo.' });
    }
}

// ── Resultado ────────────────────────────────────────────────────────────────

function showResult(data) {
    const content = document.getElementById('result-content');

    if (!data.success) {
        content.innerHTML = buildError(data.error ?? 'Error desconocido');
    } else if (data.redeemed) {
        content.innerHTML = buildRedeemed(data);
    } else {
        content.innerHTML = buildSuccess(data);
    }

    const overlay = document.getElementById('result-overlay');
    const card    = document.getElementById('result-card');
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => card.classList.remove('translate-y-full'));
    });
}

function buildError(message) {
    return `
        <div class="flex flex-col items-center text-center py-4">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="text-lg font-semibold text-gray-900 mb-1">QR no válido</p>
            <p class="text-sm text-gray-500">${message}</p>
        </div>`;
}

function buildSuccess(d) {
    const progress  = Math.round((d.stamps / d.total) * 100);
    const initials  = d.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    const isBd      = !!d.birthday;
    const isMilestone = !!d.milestone;
    const isComplete  = !!d.is_completed;

    // Color y título principal
    let headerBg    = 'bg-green-500';
    let headerIcon  = '✓';
    let headerTitle = 'Visita registrada';

    if (isBd && isComplete) {
        headerBg    = 'bg-purple-500';
        headerIcon  = '🎂🏆';
        headerTitle = '¡Cumpleaños y premio!';
    } else if (isBd) {
        headerBg    = 'bg-pink-500';
        headerIcon  = '🎂';
        headerTitle = '¡Feliz cumpleaños!';
    } else if (isComplete) {
        headerBg    = 'bg-yellow-500';
        headerIcon  = '🏆';
        headerTitle = '¡Tarjeta completa!';
    } else if (isMilestone) {
        headerBg    = 'bg-indigo-500';
        headerIcon  = '🎁';
        headerTitle = '¡Premio intermedio!';
    }

    // Badges adicionales
    let badges = '';
    if (isBd) {
        badges += `
            <div class="flex items-start gap-3 bg-pink-50 border border-pink-100 rounded-xl p-3">
                <span class="text-2xl leading-none">🎂</span>
                <div>
                    <p class="text-sm font-semibold text-pink-800">¡Hoy es su cumpleaños!</p>
                    ${d.birthday.title ? `<p class="text-sm text-pink-700 font-medium mt-0.5">Premio: ${d.birthday.title}</p>` : ''}
                    ${d.birthday.description ? `<p class="text-xs text-pink-600 mt-0.5">${d.birthday.description}</p>` : ''}
                </div>
            </div>`;
    }
    if (isMilestone) {
        badges += `
            <div class="flex items-start gap-3 bg-indigo-50 border border-indigo-100 rounded-xl p-3">
                <span class="text-2xl leading-none">🎁</span>
                <div>
                    <p class="text-sm font-semibold text-indigo-800">Premio intermedio ganado</p>
                    <p class="text-sm text-indigo-700 font-medium mt-0.5">${d.milestone.title}</p>
                </div>
            </div>`;
    }
    if (isComplete) {
        badges += `
            <div class="flex items-start gap-3 bg-yellow-50 border border-yellow-100 rounded-xl p-3">
                <span class="text-2xl leading-none">🏆</span>
                <div>
                    <p class="text-sm font-semibold text-yellow-800">¡Tarjeta completada!</p>
                    ${d.main_reward ? `<p class="text-sm text-yellow-700 font-medium mt-0.5">Canjear: ${d.main_reward}</p>` : ''}
                </div>
            </div>`;
    }

    return `
        <div class="flex flex-col items-center mb-5">
            <div class="w-16 h-16 ${headerBg} rounded-full flex items-center justify-center mb-3 text-2xl shadow-lg">
                ${typeof headerIcon === 'string' && headerIcon.length <= 2 && headerIcon === '✓'
                    ? '<svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>'
                    : `<span>${headerIcon}</span>`
                }
            </div>
            <p class="text-lg font-bold text-gray-900">${headerTitle}</p>
        </div>

        <div class="flex items-center gap-4 bg-gray-50 rounded-xl p-4 mb-4">
            <div class="w-11 h-11 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold text-indigo-700">${initials}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-base font-semibold text-gray-900 truncate">${d.name}</p>
                <p class="text-xs text-gray-500 mt-0.5">${d.stamps} / ${d.total} visitas</p>
                <div class="mt-1.5 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all" style="width: ${progress}%"></div>
                </div>
            </div>
        </div>

        ${badges ? `<div class="space-y-2">${badges}</div>` : ''}
    `;
}

function buildRedeemed(d) {
    const initials = d.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

    return `
        <div class="flex flex-col items-center mb-5">
            <div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center mb-3 text-2xl shadow-lg">
                <span>🎉</span>
            </div>
            <p class="text-lg font-bold text-gray-900">¡Premio canjeado!</p>
        </div>

        <div class="flex items-center gap-4 bg-gray-50 rounded-xl p-4 mb-4">
            <div class="w-11 h-11 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold text-indigo-700">${initials}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-base font-semibold text-gray-900 truncate">${d.name}</p>
                <p class="text-xs text-gray-500 mt-0.5">Nuevo ciclo · ${d.stamps} / ${d.total} visitas</p>
            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                <span class="text-2xl leading-none">🏆</span>
                <div>
                    <p class="text-sm font-semibold text-emerald-800">Premio entregado</p>
                    <p class="text-sm text-emerald-700 font-medium mt-0.5">${d.redeemed_reward}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 bg-indigo-50 border border-indigo-100 rounded-xl p-3">
                <span class="text-2xl leading-none">🎯</span>
                <div>
                    <p class="text-sm font-semibold text-indigo-800">Primer sello del nuevo ciclo</p>
                    <p class="text-sm text-indigo-700 font-medium mt-0.5">Ahora acumula para: ${d.next_reward}</p>
                </div>
            </div>
        </div>
    `;
}

// ── Cerrar / reset ────────────────────────────────────────────────────────────

function closeResult(e) {
    if (e.target === document.getElementById('result-overlay')) {
        resetScanner();
    }
}

function resetScanner() {
    const card    = document.getElementById('result-card');
    const overlay = document.getElementById('result-overlay');

    card.classList.add('translate-y-full');
    setTimeout(() => {
        overlay.classList.add('hidden');
        document.getElementById('result-content').innerHTML = '';
        isProcessing = false;
        startScanner();
    }, 300);
}
</script>
@endpush
