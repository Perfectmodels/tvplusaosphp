<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-on-surface leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <header class="px-2">
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('Profile') }}</h1>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">
                    {{ __('Manage your account details, password and account deletion.') }}
                </p>
            </header>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-800">
                <div class="p-4 sm:p-8">
                    <div class="max-w-2xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-800">
                <div class="p-4 sm:p-8">
                    <div class="max-w-2xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-800">
                <div class="p-4 sm:p-8">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
