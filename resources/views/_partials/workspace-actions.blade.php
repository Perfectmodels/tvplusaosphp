@if($quickActions !== [])
<section class="ed-card ed-role-actions-card">
    <div class="ed-card-heading"><h2>Actions rapides</h2></div>
    <nav aria-label="Actions rapides" class="ed-role-actions">
        @foreach($quickActions as $action)
            <a href="{{ $action['href'] }}" class="ed-role-action-link">
                <span class="ed-role-action-icon material-symbols-outlined" aria-hidden="true">{{ $action['icon'] }}</span>
                <span class="ed-role-action-label">{{ $action['label'] }}</span>
                <span class="material-symbols-outlined ed-role-action-chevron" aria-hidden="true">chevron_right</span>
            </a>
        @endforeach
    </nav>
</section>
@endif
