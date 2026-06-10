<div class="col-12 col-md-6 col-xl-4">
    <div class="small-box {{ $theme ?? 'bg-info' }}">
        <div class="inner">
            <h3>{{ $value ?? 'Ready' }}</h3>
            <p>{{ $label }}</p>
        </div>
        <div class="icon">
            <i class="{{ $icon }}"></i>
        </div>
        <a href="{{ $href ?? '#' }}" class="small-box-footer">
            Open <i class="fas fa-arrow-circle-right ml-1"></i>
        </a>
    </div>
</div>
