@extends('admin.layouts.app', [
    'title' => 'Featured Slots',
    'heading' => 'Featured Slots',
    'subtitle' => 'Manage the 24 paid DJ spotlight slots used by DJ Hub and DJLounge.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $slotCount }}</h3>
                    <p>Total Slots</p>
                </div>
                <div class="icon">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $configuredSlotCount }}</h3>
                    <p>Configured Slots</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>4</h3>
                    <p>Visible Per Rotation</p>
                </div>
                <div class="icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Visibility Pricing</h3>
            <div class="card-tools text-muted">
                Daily price decays by group and again by slot position, so lower exposure costs less.
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Exposure Range</th>
                        <th>Daily Rate Range</th>
                        <th>Slot Range</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pricingGroups as $pricingGroup)
                        <tr>
                            <td>Group {{ $pricingGroup['group'] }}</td>
                            <td>{{ $pricingGroup['max_exposure_percent'] }}%-{{ $pricingGroup['min_exposure_percent'] }}%</td>
                            <td>{{ $pricingGroup['daily_price_range'] }} / day</td>
                            <td>
                                Slots {{ (($pricingGroup['group'] - 1) * 4) + 1 }}-{{ $pricingGroup['group'] * 4 }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Campaign Options</h3>
            <div class="card-tools text-muted">
                These are the choices DJs will see when claiming a featured slot.
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.admincenter.featuredslots.options.store') }}" class="mb-4">
                @csrf
                <div class="row">
                    <div class="form-group col-lg-3">
                        <label for="new_option_name">Option Name</label>
                        <input id="new_option_name" type="text" name="name" class="form-control" placeholder="1 Day Spotlight" required>
                    </div>
                    <div class="form-group col-lg-2">
                        <label for="new_option_duration_days">Days</label>
                        <input id="new_option_duration_days" type="number" name="duration_days" class="form-control" min="1" max="365" value="1" required>
                    </div>
                    <div class="form-group col-lg-2">
                        <label for="new_option_sort_order">Sort</label>
                        <input id="new_option_sort_order" type="number" name="sort_order" class="form-control" min="0" max="999" value="0">
                    </div>
                    <div class="form-group col-lg-5">
                        <label for="new_option_description">Description</label>
                        <input id="new_option_description" type="text" name="description" class="form-control" placeholder="Runs for 24 hours after approval">
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="custom-control custom-checkbox">
                        <input id="new_option_is_active" type="checkbox" name="is_active" value="1" class="custom-control-input" checked>
                        <label for="new_option_is_active" class="custom-control-label">Active option</label>
                    </div>
                    @can('featuredslots.update')
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Add Option
                        </button>
                    @endcan
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Sort</th>
                            <th>Description</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaignOptions as $option)
                            <tr>
                                <form method="POST" action="{{ route('admin.admincenter.featuredslots.options.update', $option) }}">
                                    @csrf
                                    @method('PUT')
                                    <td>
                                        <input type="text" name="name" value="{{ $option->name }}" class="form-control form-control-sm" required>
                                    </td>
                                    <td style="width: 110px;">
                                        <input type="number" name="duration_days" value="{{ $option->duration_days }}" class="form-control form-control-sm" min="1" max="365" required>
                                    </td>
                                    <td style="width: 120px;">
                                        <div class="custom-control custom-checkbox">
                                            <input id="option_{{ $option->id }}_is_active" type="checkbox" name="is_active" value="1" class="custom-control-input" @checked($option->is_active)>
                                            <label for="option_{{ $option->id }}_is_active" class="custom-control-label">Active</label>
                                        </div>
                                    </td>
                                    <td style="width: 100px;">
                                        <input type="number" name="sort_order" value="{{ $option->sort_order }}" class="form-control form-control-sm" min="0" max="999">
                                    </td>
                                    <td>
                                        <input type="text" name="description" value="{{ $option->description }}" class="form-control form-control-sm">
                                    </td>
                                    <td class="text-right" style="width: 170px;">
                                        @can('featuredslots.update')
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        @endcan
                                </form>
                                        @can('featuredslots.update')
                                            <form method="POST" action="{{ route('admin.admincenter.featuredslots.options.destroy', $option) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this campaign option? Existing slots will keep running without an option attached.')">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No campaign options yet. Add options like 1 day, 7 days, or 30 days.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rotation Slots</h3>
            <div class="card-tools text-muted">
                Groups rotate on the public DJ Hub. DJLounge can request a single slot.
            </div>
        </div>
        <div class="card-body">
            @forelse ($groups as $groupIndex => $group)
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom border-secondary pb-2 mb-3">
                        <h4 class="mb-0">Group {{ $groupIndex + 1 }}</h4>
                        <span class="badge badge-secondary">Slots {{ $group->first()['number'] }}-{{ $group->last()['number'] }}</span>
                    </div>

                    <div class="row">
                        @foreach ($group as $slot)
                            @php($slotSelectedOptionIds = collect($slotCampaignOptionIds->get($slot['number'], []))->map(fn ($id) => (int) $id))
                            <div class="col-xl-3 col-md-6">
                                <div class="card border border-secondary h-100">
                                    <div class="card-header">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong>Slot {{ $slot['number'] }}</strong>
                                            <span class="badge badge-{{ $slotSelectedOptionIds->isNotEmpty() ? 'success' : 'dark' }}">
                                                {{ $slotSelectedOptionIds->isNotEmpty() ? 'Configured' : 'No Options' }}
                                            </span>
                                        </div>
                                        <div class="small text-muted mt-2">
                                            {{ $slot['daily_price'] }} / day · {{ $slot['exposure_percent'] }}% exposure · weight {{ $slot['rotation_weight'] }}
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.admincenter.featuredslots.update', $slot['number']) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Claim Options Available For This Slot</label>
                                                <div class="border border-secondary rounded p-2">
                                                    @forelse ($activeCampaignOptions as $option)
                                                        @php($selectedOptionIds = collect(old('campaign_option_ids', $slotCampaignOptionIds->get($slot['number'], [])))->map(fn ($id) => (int) $id))
                                                        <div class="custom-control custom-checkbox mb-1">
                                                            <input
                                                                id="slot_{{ $slot['number'] }}_option_{{ $option->id }}"
                                                                type="checkbox"
                                                                name="campaign_option_ids[]"
                                                                value="{{ $option->id }}"
                                                                class="custom-control-input"
                                                                @checked($selectedOptionIds->contains($option->id))
                                                            >
                                                            <label for="slot_{{ $slot['number'] }}_option_{{ $option->id }}" class="custom-control-label">
                                                                {{ $option->name }}
                                                                <span class="text-muted">
                                                                    - {{ $option->duration_days }} {{ str('day')->plural($option->duration_days) }}
                                                                    - ${{ number_format(($slot['daily_price_cents'] * $option->duration_days) / 100, 2) }}
                                                                </span>
                                                            </label>
                                                        </div>
                                                    @empty
                                                        <p class="text-muted mb-0">Add campaign options above before enabling claims for this slot.</p>
                                                    @endforelse
                                                </div>
                                                <small class="text-muted">DJs will choose one of these when claiming this slot.</small>
                                            </div>
                                        </div>
                                        <div class="card-footer d-flex justify-content-between">
                                            @can('featuredslots.update')
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-save mr-1"></i> Save
                                                </button>
                                                @if ($slotSelectedOptionIds->isNotEmpty())
                                                    <button
                                                        type="submit"
                                                        name="clear_slot"
                                                        value="1"
                                                        class="btn btn-outline-danger btn-sm"
                                                        onclick="return confirm('Clear featured slot {{ $slot['number'] }}?')"
                                                    >
                                                        Clear
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No featured slots are available.</p>
            @endforelse
        </div>
    </div>
@endsection
