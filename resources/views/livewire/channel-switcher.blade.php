<div class="flex items-center px-2">
    <select
        wire:model.live="activeChannelId"
        aria-label="Channel"
        class="rounded-lg border-none bg-gray-100 dark:bg-gray-800 py-1.5 pl-3 pr-8 text-sm font-medium text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-primary-500 cursor-pointer"
    >
        <option value="">All Channels</option>
        @foreach ($channels as $channel)
            <option value="{{ $channel->id }}">{{ $channel->name }}</option>
        @endforeach
    </select>
</div>
