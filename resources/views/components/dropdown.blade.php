@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-background'])

@php
    $alignmentClasses = match ($align) {
        'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
        'top' => 'origin-top',
        default => 'ltr:origin-top-right rtl:origin-top-left end-0',
    };
    $widthClass = match ($width) {
        '48' => 'w-56 max-w-[calc(100vw-2rem)]',
        'full' => 'w-full',
        default => $width,
    };
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        triggerButton() { return this.$refs.trigger?.querySelector('button, [role=button]'); },
        items() {
            return Array.from(this.$refs.menu?.querySelectorAll('a[href],button:not([disabled]),[role=menuitem]') || [])
                .filter(el => el.getClientRects().length > 0);
        },
        syncTrigger() {
            const button = this.triggerButton();
            if (!button) return;
            button.setAttribute('aria-haspopup', 'menu');
            button.setAttribute('aria-expanded', String(this.open));
            if (this.$refs.menu?.id) button.setAttribute('aria-controls', this.$refs.menu.id);
        },
        closeMenu(focusTrigger = false) {
            this.open = false;
            if (focusTrigger) this.$nextTick(() => this.triggerButton()?.focus());
        },
        focusItem(direction) {
            const items = this.items();
            if (!items.length) return;
            const index = items.indexOf(document.activeElement);
            const next = index < 0 ? (direction > 0 ? 0 : items.length - 1)
                : (index + direction + items.length) % items.length;
            items[next].focus();
        },
    }"
    x-id="['tv-dropdown']"
    x-init="syncTrigger(); $watch('open', () => syncTrigger())"
    @click.outside="closeMenu()"
    @close.stop="closeMenu()"
    @keydown.escape.window="if (open) { $event.preventDefault(); closeMenu(true); }"
>
    <div x-ref="trigger"
         @click="open = !open"
         @keydown.arrow-down.prevent="open = true; $nextTick(() => focusItem(1))"
         @keydown.arrow-up.prevent="open = true; $nextTick(() => focusItem(-1))">
        {{ $trigger }}
    </div>

    <div
        x-ref="menu"
        :id="$id('tv-dropdown')"
        x-show="open"
        x-cloak
        role="menu"
        tabindex="-1"
        @keydown.arrow-down.prevent="focusItem(1)"
        @keydown.arrow-up.prevent="focusItem(-1)"
        @keydown.home.prevent="items()[0]?.focus()"
        @keydown.end.prevent="items().at(-1)?.focus()"
        @click="closeMenu()"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-[60] mt-2 max-h-[60vh] overflow-y-auto {{ $widthClass }} rounded-xl border border-outline-variant shadow-lg {{ $alignmentClasses }}"
    >
        <div class="rounded-xl {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
