@if ($customerClient)
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Business / Tax Details') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Update the billing, tax, and e-invoice details used on your invoices.') }}
            </p>
        </header>

        <form method="POST" action="{{ route('profile.business-details.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="business_name" :value="__('Business / Customer Name')" />
                    <x-text-input id="business_name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customerClient->name)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="contact_person" :value="__('Contact Person')" />
                    <x-text-input id="contact_person" name="contact_person" type="text" class="mt-1 block w-full" :value="old('contact_person', $customerClient->contact_person)" />
                    <x-input-error class="mt-2" :messages="$errors->get('contact_person')" />
                </div>

                <div>
                    <x-input-label for="billing_email" :value="__('Billing Email')" />
                    <x-text-input id="billing_email" name="billing_email" type="email" class="mt-1 block w-full" :value="old('billing_email', $customerClient->billing_email ?: $customerClient->email)" />
                    <x-input-error class="mt-2" :messages="$errors->get('billing_email')" />
                </div>

                <div>
                    <x-input-label for="phone" :value="__('Phone')" />
                    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $customerClient->phone)" />
                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                </div>

                <div>
                    <x-input-label for="website" :value="__('Website')" />
                    <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $customerClient->website)" placeholder="https://example.com" />
                    <x-input-error class="mt-2" :messages="$errors->get('website')" />
                </div>

                <div>
                    <x-input-label for="tin_number" :value="__('TIN Number')" />
                    <x-text-input id="tin_number" name="tin_number" type="text" class="mt-1 block w-full" :value="old('tin_number', $customerClient->tin_number)" />
                    <x-input-error class="mt-2" :messages="$errors->get('tin_number')" />
                </div>

                <div>
                    <x-input-label for="id_type" :value="__('ID Type')" />
                    <select id="id_type" name="id_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">{{ __('Select ID Type') }}</option>
                        @foreach(['BRN' => 'Business Registration Number', 'NRIC' => 'NRIC', 'PASSPORT' => 'Passport', 'ARMY' => 'Army', 'OTHER' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('id_type', $customerClient->id_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('id_type')" />
                </div>

                <div>
                    <x-input-label for="id_number" :value="__('ID / Registration Number')" />
                    <x-text-input id="id_number" name="id_number" type="text" class="mt-1 block w-full" :value="old('id_number', $customerClient->id_number)" />
                    <x-input-error class="mt-2" :messages="$errors->get('id_number')" />
                </div>

                <div>
                    <x-input-label for="sst_registration_number" :value="__('SST Registration Number')" />
                    <x-text-input id="sst_registration_number" name="sst_registration_number" type="text" class="mt-1 block w-full" :value="old('sst_registration_number', $customerClient->sst_registration_number)" />
                    <x-input-error class="mt-2" :messages="$errors->get('sst_registration_number')" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="address_line_1" :value="__('Address Line 1')" />
                    <x-text-input id="address_line_1" name="address_line_1" type="text" class="mt-1 block w-full" :value="old('address_line_1', $customerClient->address_line_1 ?: $customerClient->address)" />
                    <x-input-error class="mt-2" :messages="$errors->get('address_line_1')" />
                </div>

                <div>
                    <x-input-label for="address_line_2" :value="__('Address Line 2')" />
                    <x-text-input id="address_line_2" name="address_line_2" type="text" class="mt-1 block w-full" :value="old('address_line_2', $customerClient->address_line_2)" />
                    <x-input-error class="mt-2" :messages="$errors->get('address_line_2')" />
                </div>

                <div>
                    <x-input-label for="city" :value="__('City')" />
                    <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $customerClient->city)" />
                    <x-input-error class="mt-2" :messages="$errors->get('city')" />
                </div>

                <div>
                    <x-input-label for="state" :value="__('State')" />
                    <x-text-input id="state" name="state" type="text" class="mt-1 block w-full" :value="old('state', $customerClient->state)" />
                    <x-input-error class="mt-2" :messages="$errors->get('state')" />
                </div>

                <div>
                    <x-input-label for="postcode" :value="__('Postcode')" />
                    <x-text-input id="postcode" name="postcode" type="text" class="mt-1 block w-full" :value="old('postcode', $customerClient->postcode)" />
                    <x-input-error class="mt-2" :messages="$errors->get('postcode')" />
                </div>

                <div>
                    <x-input-label for="country_code" :value="__('Country Code')" />
                    <x-text-input id="country_code" name="country_code" type="text" class="mt-1 block w-full" :value="old('country_code', $customerClient->country_code ?: 'MY')" maxlength="10" />
                    <x-input-error class="mt-2" :messages="$errors->get('country_code')" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save Business Details') }}</x-primary-button>

                @if (session('status') === 'business-profile-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Saved.') }}
                    </p>
                @endif
            </div>
        </form>
    </section>
@else
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Business / Tax Details') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('No customer profile is linked to this login yet. Please contact the company admin.') }}
            </p>
        </header>
    </section>
@endif
