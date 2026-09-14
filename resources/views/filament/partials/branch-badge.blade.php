@php
    $user = auth()->user();
    $branch = $user && ! $user->canSwitchLocation() ? $user->homeLocation : null;
@endphp

@if ($branch)
    <div class="fi-sidebar-branch-badge px-4 py-3 text-xs">
        <span class="text-gray-500 dark:text-gray-400">Branch</span>
        <p class="font-medium text-gray-950 dark:text-white">{{ $branch->name }}</p>
    </div>
@endif
