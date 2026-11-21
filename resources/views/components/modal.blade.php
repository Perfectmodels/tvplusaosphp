@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'label' => null,
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
    ];
    $widthClass = $maxWidthClasses[$maxWidth] ?? $maxWidthClasses['2xl'];
@endphp

<div
    x-data="{
        show: @js($show),
        previousFocus: null,
        previousOverflow: null,
        focusables() {
            const panel = this.$refs.panel;
            if (!panel) return [];
            return Array.from(panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter((item) => item.getClientRects().length && item.getAttribute('aria-hidden') !== 'true');
        },
        activate() {
            this.previousFocus = document.activeElement;
            if (this.previousOverflow === null) this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => (this.focusables()[0] || this.$refs.panel)?.focus());
        },
        deactivate() {
            document.body.style.overflow = this.previousOverflow ?? '';
            this.previousOverflow = null;
            const focus = this.previousFocus;
            this.previousFocus = null;
            this.$nextTick(() => { if (focus?.isConnected) focus.focus(); });
        },
        closeDialog() { this.show = false; },
        trapTab(event) {
            if (!this.show) return;
            const items = this.focusables();
            if (!items.length) { event.preventDefault(); this.$refs.panel?.focus(); return; }
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || !this.$refs.panel.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !this.$refs.panel.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        },
    }"
    x-init="if (show) activate(); $watch('show', (value) => value ? activate() : deactivate())"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? closeDialog() : null"
    x-on:close.stop="closeDialog()"
    x-on:keydown.escape.window="if (show) { $event.preventDefault(); closeDialog(); }"
    x-on:keydown.tab="trapTab($event)"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[80] overflow-y-auto px-3 py-4 sm:px-6 sm:py-8"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 bg-primary/65 backdrop-blur-[2px]"
        x-on:click="closeDialog()"
        aria-hidden="true"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    <div
        x-ref="panel"
        x-show="show"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $label ?: str_replace('-', ' ', $name) }}"
        tabindex="-1"
        class="relative mx-auto mb-6 w-full {{ $widthClass }} max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-outline-variant bg-white text-on-surface shadow-2xl focus:outline-none sm:mt-2"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
