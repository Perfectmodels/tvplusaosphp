@php $messagesAffiches = []; @endphp
@foreach(['status', 'success', 'info', 'warning', 'error'] as $typeMessage)
    @if(is_string(session($typeMessage)) && session($typeMessage) !== '' && !in_array(session($typeMessage), $messagesAffiches, true))
        @php $messagesAffiches[] = session($typeMessage); @endphp
        <div role="{{ in_array($typeMessage, ['warning', 'error'], true) ? 'alert' : 'status' }}" @class([
            'mb-4 rounded-lg border p-3 text-sm',
            'border-green-200 bg-green-50 text-green-800' => $typeMessage === 'success',
            'border-amber-200 bg-amber-50 text-amber-800' => $typeMessage === 'warning',
            'border-red-200 bg-red-50 text-red-800' => $typeMessage === 'error',
            'border-blue-200 bg-blue-50 text-primary' => in_array($typeMessage, ['status', 'info'], true),
        ])>{{ session($typeMessage) }}</div>
    @endif
@endforeach
@if(isset($errors) && $errors->any())
    <div data-form-errors tabindex="-1" role="alert" class="mb-4 p-3 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm">
        <p class="mb-2 font-semibold">Vérifiez les champs suivants :</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)
                    <li><button type="button" data-error-field="{{ $field }}" class="text-left underline underline-offset-2">{{ $message }}</button></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
@php
    $swal = session('swal');
@endphp
@if($swal)
    <script>
        (function() {
            const type = @json($swal['type'] ?? 'success');
            const title = @json($swal['title'] ?? 'Effectué ✅');
            const html  = @json($swal['html'] ?? ($swal['text'] ?? ''));
            const timer = parseInt(@json($swal['timer'] ?? '0'));
            const cfg = {...window.SwalConfig, icon: type, title, showConfirmButton: !timer};
            if (html) cfg.html = html;
            if (timer) { cfg.timer = timer; cfg.timerProgressBar = true; }
            document.addEventListener('DOMContentLoaded', () => Swal.fire(cfg));
        })();
    </script>
@endif
