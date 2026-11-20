@props(['roles' => [], 'activeRole' => ''])

@if(count($roles) > 1)
    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
        @foreach(['from', 'to', 'q'] as $filterName)
            @if(is_string(request($filterName)) && request($filterName) !== '')<input type="hidden" name="{{ $filterName }}" value="{{ request($filterName) }}">@endif
        @endforeach
        <label for="dashboard-role" class="sr-only">Fonction du tableau de bord</label>
        <span class="hidden sm:inline text-xs font-semibold text-on-surface-variant">Vue métier</span>
        <select id="dashboard-role" name="role" onchange="this.form.submit()"
                class="min-h-[44px] w-full max-w-[230px] rounded-xl border border-outline-variant bg-white px-3 py-2 text-sm font-semibold text-on-surface shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
            @foreach($roles as $role)
                <option value="{{ $role['value'] }}" @selected($activeRole === $role['value'])>{{ $role['label'] }}</option>
            @endforeach
        </select>
    </form>
@endif
