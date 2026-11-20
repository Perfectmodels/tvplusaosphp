<div {{ $attributes->merge(['class' => 'tvos-regard', 'role' => 'img', 'aria-label' => 'Œil TV+A OS']) }} data-regard>
    <div class="tvos-regard-stage" aria-hidden="true">
        <div class="tvos-regard-eye" data-regard-eye>
            <div class="tvos-regard-layer"></div>
            <div class="tvos-regard-iris-wrap">
                <div class="tvos-regard-layer tvos-regard-iris"></div>
                <div class="tvos-regard-pupil"></div>
            </div>
            <div class="tvos-regard-glow"></div>
            <svg class="tvos-regard-hud" viewBox="0 0 1200 1200" fill="none" stroke="#5cc8ff" focusable="false">
                <g class="tvos-regard-ring-one" stroke-width="2" opacity=".8"><circle cx="600" cy="600" r="545" stroke-dasharray="2 14" opacity=".6"/><path d="M600 55A545 545 0 0 1 1060 310" stroke-width="3"/><path d="M140 890A545 545 0 0 1 100 700" stroke-width="3"/></g>
                <g class="tvos-regard-ring-two" stroke-width="2" opacity=".7"><circle cx="600" cy="600" r="575" stroke-dasharray="40 8 4 8" opacity=".35"/><path d="M290 1075A560 560 0 0 0 700 1150" stroke-width="3"/><path d="M1090 330A560 560 0 0 1 1150 520" stroke-width="3"/></g>
                <g class="tvos-regard-ring-three" stroke-width="1" opacity=".5"><circle cx="600" cy="600" r="505" stroke-dasharray="1 6"/></g>
            </svg>
            <div class="tvos-regard-scan"></div>
            <canvas class="tvos-regard-canvas"></canvas>
        </div>
    </div>
</div>
