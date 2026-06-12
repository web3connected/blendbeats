@php($user = $user ?? null)

<div class="row">
    <div class="form-group col-md-6">
        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $user?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>

<div class="row">
    <div class="form-group col-md-6">
        <label for="first_name">First Name</label>
        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user?->first_name) }}" class="form-control @error('first_name') is-invalid @enderror">
        @error('first_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="last_name">Last Name</label>
        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user?->last_name) }}" class="form-control @error('last_name') is-invalid @enderror">
        @error('last_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>

<div class="row">
    <div class="form-group col-md-6">
        <label for="email_verified_at">Email Verified At</label>
        <input id="email_verified_at" type="datetime-local" name="email_verified_at" value="{{ old('email_verified_at', optional($user?->email_verified_at)->format('Y-m-d\\TH:i')) }}" class="form-control @error('email_verified_at') is-invalid @enderror">
        @error('email_verified_at') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-md-6">
        <label for="media_storage_tier">Membership Tier</label>
        <select id="media_storage_tier" name="media_storage_tier" class="form-control @error('media_storage_tier') is-invalid @enderror" required>
            @foreach (config('billing.subscription.tiers') as $tierKey => $tier)
                <option value="{{ $tierKey }}" @selected(old('media_storage_tier', config("media_storage.tier_aliases.{$user?->media_storage_tier}", $user?->media_storage_tier ?? 'free')) === $tierKey)>
                    {{ $tier['name'] }}
                </option>
            @endforeach
        </select>
        @error('media_storage_tier') <span class="invalid-feedback">{{ $message }}</span> @enderror
    </div>
</div>
