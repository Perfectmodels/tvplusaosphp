<section class="ed-card ed-role-list-card" @if(isset($listId)) id="{{ $listId }}" @endif>
    <div class="ed-card-heading">
        <h2>{{ $listTitle }}</h2>
        @if(!empty($listItems))
            <span class="ed-card-count">{{ count($listItems) }}</span>
        @endif
    </div>
    <div class="ed-role-list">
        @forelse($listItems as $item)
            <a href="{{ $item['href'] }}" class="ed-role-row">
                <div class="ed-role-row-top">
                    <strong class="ed-role-row-title">{{ $item['title'] }}</strong>
                    @if(!empty($item['status']))
                        <span class="ed-role-row-status">{{ $item['status'] }}</span>
                    @endif
                </div>
                @if(!empty($item['detail']))
                    <p class="ed-role-row-detail">{{ $item['detail'] }}</p>
                @endif
                @if(!empty($item['when']))
                    <time class="ed-role-row-time">Mis à jour le {{ $item['when'] }}</time>
                @endif
            </a>
        @empty
            <div class="ed-role-empty ed-role-empty--soft">{{ $listEmpty }}</div>
        @endforelse
    </div>
</section>
