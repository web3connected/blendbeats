<ul class="list-group list-group-flush">
    @foreach ($items as $item)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ $item['label'] }}</span>
            <span class="badge badge-light border">{{ $item['value'] }}</span>
        </li>
    @endforeach
</ul>
