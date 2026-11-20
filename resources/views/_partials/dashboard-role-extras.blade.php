@php
    $extrasTableauxDeBord = collect($extrasTableauxDeBord ?? []);
@endphp
@foreach($extrasTableauxDeBord as $extraTableauDeBord)
    @include('_partials.workspace-list', ['listTitle' => $extraTableauDeBord['title'], 'listItems' => $extraTableauDeBord['items'], 'listEmpty' => $extraTableauDeBord['empty'], 'listId' => null])
@endforeach
