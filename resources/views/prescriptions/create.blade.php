<x-layout title="Add Prescription">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('prescriptions.index') }}" class="hover:text-ink">{{ __('Prescriptions') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ __('New') }}</span>
    </nav>

    <div class="mb-8">
        <p class="eyebrow">{{ __('Your account') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Add a prescription') }}</h1>
    </div>

    <form method="POST" action="{{ route('prescriptions.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-8">
        @csrf

        <section class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="doctor_name">{{ __('Doctor name') }}</label>
                <input type="text" id="doctor_name" name="doctor_name" value="{{ old('doctor_name') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="clinic_name">{{ __('Clinic') }}</label>
                <input type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="issued_at">{{ __('Issued on') }}</label>
                <input type="date" id="issued_at" name="issued_at" value="{{ old('issued_at') }}" class="input">
            </div>
            <div>
                <label class="field-label" for="expires_at">{{ __('Expires on') }}</label>
                <input type="date" id="expires_at" name="expires_at" value="{{ old('expires_at') }}" class="input">
            </div>
        </section>

        <section class="hairline-top pt-6">
            <p class="eyebrow mb-4">{{ __('Your numbers') }}</p>
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="field-label">{{ __('Left eye (OS)') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.01" name="left_sphere" placeholder="{{ __('Sphere') }}" value="{{ old('left_sphere') }}" class="input">
                        <input type="number" step="0.01" name="left_cylinder" placeholder="{{ __('Cylinder') }}" value="{{ old('left_cylinder') }}" class="input">
                        <input type="number" step="1" min="0" max="180" name="left_axis" placeholder="{{ __('Axis') }}" value="{{ old('left_axis') }}" class="input">
                        <input type="number" step="0.01" name="left_add" placeholder="{{ __('Add') }}" value="{{ old('left_add') }}" class="input">
                    </div>
                </div>
                <div>
                    <p class="field-label">{{ __('Right eye (OD)') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.01" name="right_sphere" placeholder="{{ __('Sphere') }}" value="{{ old('right_sphere') }}" class="input">
                        <input type="number" step="0.01" name="right_cylinder" placeholder="{{ __('Cylinder') }}" value="{{ old('right_cylinder') }}" class="input">
                        <input type="number" step="1" min="0" max="180" name="right_axis" placeholder="{{ __('Axis') }}" value="{{ old('right_axis') }}" class="input">
                        <input type="number" step="0.01" name="right_add" placeholder="{{ __('Add') }}" value="{{ old('right_add') }}" class="input">
                    </div>
                </div>
            </div>
            <div class="mt-4 max-w-xs">
                <label class="field-label" for="pd">{{ __('Pupillary distance (PD)') }}</label>
                <input type="number" step="0.1" id="pd" name="pd" value="{{ old('pd') }}" class="input">
            </div>
        </section>

        <section class="hairline-top pt-6">
            <label class="field-label" for="file">{{ __('Upload a photo or PDF (optional)') }}</label>
            <input type="file" id="file" name="file" accept="image/*,.pdf" class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:text-white">
            <p class="mt-1.5 text-xs text-ink-faint">{{ __('JPG, PNG or PDF, up to 10MB.') }}</p>
        </section>

        <section>
            <label class="field-label" for="notes">{{ __('Notes (optional)') }}</label>
            <textarea id="notes" name="notes" rows="3" class="textarea" maxlength="1000">{{ old('notes') }}</textarea>
        </section>

        <button type="submit" class="btn-primary w-full">{{ __('Save prescription') }}</button>
    </form>
</x-layout>
