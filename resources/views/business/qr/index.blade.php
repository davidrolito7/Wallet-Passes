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
                <button onclick="downloadQR()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                    Descargar QR
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
<script>
@if($registerUrl)
const qrUrl = @json($registerUrl);

const qr = new QRCode(document.getElementById('qrcode'), {
    text: qrUrl,
    width: 220,
    height: 220,
    colorDark: '#1e1b4b',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
});

function downloadQR() {
    setTimeout(() => {
        const canvas = document.getElementById('qrcode').querySelector('canvas');
        if (!canvas) {
            alert('Error al generar el QR. Recarga la página e intenta de nuevo.');
            return;
        }
        const link = document.createElement('a');
        link.download = 'qr-registro.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }, 100);
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
